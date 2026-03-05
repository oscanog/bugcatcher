import fs from "node:fs";
import path from "node:path";
import { APIRequestContext, expect, request, test } from "@playwright/test";
import { cfg } from "../src/config";
import { loginAndSelectOrg } from "./helpers/auth";
import {
  apiDelete,
  apiGet,
  apiPatchJson,
  apiPostJson,
  parseJson,
} from "./helpers/client";

type Batch = { id: number; project_id: number; module_name: string; title: string };
type Item = { id: number; batch_id: number; status: string; issue_id?: number | null };
type Attachment = { id: number };

test.describe.configure({ mode: "serial" });

let authRequest: APIRequestContext;
let batchId = 0;
let itemId = 0;
let itemAttachmentId = 0;
let batchAttachmentId = 0;

test.beforeAll(async () => {
  authRequest = await request.newContext({
    baseURL: cfg.baseUrl,
  });
  await loginAndSelectOrg(authRequest);
});

test.afterAll(async () => {
  await authRequest.dispose();
});

test("create/list/get/update batch", async () => {
  const createPayload = {
    project_id: cfg.projectId,
    title: `E2E Batch ${Date.now()}`,
    module_name: "Checklist API",
    submodule_name: "CRUD",
    status: "open",
    assigned_qa_lead_id: cfg.assignedQaLeadId ?? 0,
    notes: "Created by Playwright E2E",
  };

  const created = await apiPostJson<{ batch: Batch }>(
    authRequest,
    "/api/checklist/v1/batches.php",
    createPayload
  );
  expect(created.res.status()).toBe(201);
  expect(created.body.ok).toBe(true);
  if (!created.body.ok) throw new Error("Expected successful create batch.");
  batchId = created.body.data.batch.id;
  expect(batchId).toBeGreaterThan(0);

  const listed = await apiGet<{ batches: Batch[] }>(
    authRequest,
    `/api/checklist/v1/batches.php?project_id=${cfg.projectId}`
  );
  expect(listed.res.status()).toBe(200);
  expect(listed.body.ok).toBe(true);
  if (!listed.body.ok) throw new Error("Expected successful list.");
  expect(listed.body.data.batches.some((b) => b.id === batchId)).toBeTruthy();

  const fetched = await apiGet<{ batch: Batch }>(
    authRequest,
    `/api/checklist/v1/batch.php?id=${batchId}`
  );
  expect(fetched.res.status()).toBe(200);
  expect(fetched.body.ok).toBe(true);

  const patched = await apiPatchJson<{ batch: Batch }>(
    authRequest,
    `/api/checklist/v1/batch.php?id=${batchId}`,
    {
      ...createPayload,
      title: `${createPayload.title} Updated`,
      notes: "Updated by Playwright E2E",
    }
  );
  expect(patched.res.status()).toBe(200);
  expect(patched.body.ok).toBe(true);
  if (!patched.body.ok) throw new Error("Expected successful patch.");
  expect(patched.body.data.batch.title).toContain("Updated");
});

test("create/get/update item and conflict check", async () => {
  expect(batchId).toBeGreaterThan(0);

  const created = await apiPostJson<{ item: Item }>(
    authRequest,
    "/api/checklist/v1/items.php",
    {
      batch_id: batchId,
      sequence_no: 1,
      title: "E2E Item",
      module_name: "Checklist API",
      submodule_name: "CRUD",
      description: "Item created by Playwright",
      required_role: "QA Tester",
      priority: "medium",
      assigned_to_user_id: cfg.assignedUserId ?? 0,
    }
  );
  expect(created.res.status()).toBe(201);
  expect(created.body.ok).toBe(true);
  if (!created.body.ok) throw new Error("Expected successful create item.");
  itemId = created.body.data.item.id;
  expect(itemId).toBeGreaterThan(0);

  const conflict = await apiPostJson<{ item: Item }>(
    authRequest,
    "/api/checklist/v1/items.php",
    {
      batch_id: batchId,
      sequence_no: 1,
      title: "E2E Item Duplicate Sequence",
      module_name: "Checklist API",
      required_role: "QA Tester",
      priority: "medium",
    }
  );
  expect(conflict.res.status()).toBe(409);
  expect(conflict.body.ok).toBe(false);

  const fetched = await apiGet<{ item: Item }>(
    authRequest,
    `/api/checklist/v1/item.php?id=${itemId}`
  );
  expect(fetched.res.status()).toBe(200);
  expect(fetched.body.ok).toBe(true);

  const patched = await apiPatchJson<{ item: Item }>(
    authRequest,
    `/api/checklist/v1/item.php?id=${itemId}`,
    {
      sequence_no: 2,
      title: "E2E Item Updated",
      module_name: "Checklist API Updated",
      submodule_name: "CRUD",
      description: "Updated item details",
      required_role: "QA Tester",
      priority: "high",
      assigned_to_user_id: cfg.assignedUserId ?? 0,
    }
  );
  expect(patched.res.status()).toBe(200);
  expect(patched.body.ok).toBe(true);
});

test("status transition flow with invalid transition check", async () => {
  expect(itemId).toBeGreaterThan(0);

  const inProgress = await apiPostJson<{ item: Item }>(
    authRequest,
    "/api/checklist/v1/item_status.php",
    { item_id: itemId, status: "in_progress" }
  );
  expect(inProgress.res.status()).toBe(200);
  expect(inProgress.body.ok).toBe(true);

  const blocked = await apiPostJson<{ item: Item }>(
    authRequest,
    "/api/checklist/v1/item_status.php",
    { item_id: itemId, status: "blocked" }
  );
  expect(blocked.res.status()).toBe(200);
  expect(blocked.body.ok).toBe(true);

  const invalid = await apiPostJson<{ item: Item }>(
    authRequest,
    "/api/checklist/v1/item_status.php",
    { item_id: itemId, status: "open" }
  );
  expect(invalid.res.status()).toBe(422);
  expect(invalid.body.ok).toBe(false);
});

test("upload and delete item attachment", async () => {
  expect(itemId).toBeGreaterThan(0);
  const fixturePath = path.resolve(__dirname, "fixtures", "sample.png");
  const buffer = fs.readFileSync(fixturePath);

  const uploadRes = await authRequest.post("/api/checklist/v1/item_attachments.php", {
    multipart: {
      item_id: String(itemId),
      "attachments[]": {
        name: "sample.png",
        mimeType: "image/png",
        buffer,
      },
    },
  });
  const uploadBody = await parseJson<{
    ok: boolean;
    data?: { uploaded_count: number; attachments: Attachment[] };
  }>(uploadRes);
  expect(uploadRes.status()).toBe(200);
  expect(uploadBody.ok).toBe(true);
  if (!uploadBody.ok || !uploadBody.data) throw new Error("Expected upload response body.");
  expect(uploadBody.data.uploaded_count).toBeGreaterThan(0);
  itemAttachmentId = uploadBody.data.attachments[0]?.id ?? 0;
  expect(itemAttachmentId).toBeGreaterThan(0);

  const deleted = await apiDelete<{ deleted: boolean; id: number }>(
    authRequest,
    `/api/checklist/v1/item_attachment.php?id=${itemAttachmentId}`
  );
  expect(deleted.res.status()).toBe(200);
  expect(deleted.body.ok).toBe(true);
});

test("upload and delete batch attachment", async () => {
  expect(batchId).toBeGreaterThan(0);
  const fixturePath = path.resolve(__dirname, "fixtures", "sample.png");
  const buffer = fs.readFileSync(fixturePath);

  const uploadRes = await authRequest.post("/api/checklist/v1/batch_attachments.php", {
    multipart: {
      batch_id: String(batchId),
      "attachments[]": {
        name: "sample.png",
        mimeType: "image/png",
        buffer,
      },
    },
  });
  const uploadBody = await parseJson<{
    ok: boolean;
    data?: { uploaded_count: number; attachments: Attachment[] };
  }>(uploadRes);
  expect(uploadRes.status()).toBe(200);
  expect(uploadBody.ok).toBe(true);
  if (!uploadBody.ok || !uploadBody.data) throw new Error("Expected batch upload response body.");
  expect(uploadBody.data.uploaded_count).toBeGreaterThan(0);
  batchAttachmentId = uploadBody.data.attachments[0]?.id ?? 0;
  expect(batchAttachmentId).toBeGreaterThan(0);

  const deleted = await apiDelete<{ deleted: boolean; id: number }>(
    authRequest,
    `/api/checklist/v1/batch_attachment.php?id=${batchAttachmentId}`
  );
  expect(deleted.res.status()).toBe(200);
  expect(deleted.body.ok).toBe(true);
});

test("unauthorized and method guard checks", async () => {
  const anon = await request.newContext({ baseURL: cfg.baseUrl });
  const unauthorized = await anon.get("/api/checklist/v1/batches.php");
  const unauthorizedBody = await parseJson<{ ok: boolean; error?: { code: string } }>(unauthorized);
  expect(unauthorized.status()).toBe(401);
  expect(unauthorizedBody.ok).toBe(false);
  await anon.dispose();

  const invalidMethod = await authRequest.get("/api/checklist/v1/items.php");
  const invalidMethodBody = await parseJson<{ ok: boolean; error?: { code: string } }>(invalidMethod);
  expect(invalidMethod.status()).toBe(405);
  expect(invalidMethodBody.ok).toBe(false);
});

test("delete item and batch then verify not found", async () => {
  expect(itemId).toBeGreaterThan(0);
  expect(batchId).toBeGreaterThan(0);

  const itemDeleted = await apiDelete<{ deleted: boolean; id: number }>(
    authRequest,
    `/api/checklist/v1/item.php?id=${itemId}`
  );
  expect(itemDeleted.res.status()).toBe(200);
  expect(itemDeleted.body.ok).toBe(true);

  const itemMissing = await apiGet<{ item: Item }>(
    authRequest,
    `/api/checklist/v1/item.php?id=${itemId}`
  );
  expect(itemMissing.res.status()).toBe(404);
  expect(itemMissing.body.ok).toBe(false);

  const batchDeleted = await apiDelete<{ deleted: boolean; id: number }>(
    authRequest,
    `/api/checklist/v1/batch.php?id=${batchId}`
  );
  expect(batchDeleted.res.status()).toBe(200);
  expect(batchDeleted.body.ok).toBe(true);

  const batchMissing = await apiGet<{ batch: Batch }>(
    authRequest,
    `/api/checklist/v1/batch.php?id=${batchId}`
  );
  expect(batchMissing.res.status()).toBe(404);
  expect(batchMissing.body.ok).toBe(false);
});

