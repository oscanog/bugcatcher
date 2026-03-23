import { expect, request, test, APIRequestContext } from "@playwright/test";
import { cfg } from "../src/config";
import { authHeaders, loginRole, RoleSession } from "./helpers/auth";
import { ApiEnvelope, apiDeleteJson, apiGet, apiPostJson, expectApiSuccess } from "./helpers/client";

test.describe.configure({ mode: "serial" });

type Issue = {
  id: number;
  title: string;
  status: string;
  assign_status: string;
};

type NotificationRecord = {
  id: number;
  event_key: string;
  title: string;
  body: string;
  link_path: string;
  read_at: string | null;
};

let api: APIRequestContext;
let superAdmin: RoleSession;
let pm: RoleSession;
let seniorDev: RoleSession;
let createdIssueId = 0;

async function createIssue(title: string): Promise<Issue> {
  const { res, body } = await apiPostJson<ApiEnvelope<{ issue: Issue }>>(
    api,
    `${cfg.apiBasePath}/issues`,
    {
      org_id: cfg.orgId,
      title,
      description: "Created by API v1 notification suite",
      labels: [cfg.labelId],
    },
    authHeaders(superAdmin)
  );
  expect(res.status()).toBe(201);
  expectApiSuccess(body);
  createdIssueId = body.data.issue.id;
  return body.data.issue;
}

test.beforeAll(async () => {
  api = await request.newContext({ baseURL: cfg.baseUrl });
  superAdmin = await loginRole(api, "superAdmin");
  pm = await loginRole(api, "pm");
  seniorDev = await loginRole(api, "seniorDev");
});

test.afterAll(async () => {
  if (createdIssueId > 0) {
    await apiDeleteJson<ApiEnvelope<{ deleted?: boolean }>>(
      api,
      `${cfg.apiBasePath}/issues/${createdIssueId}`,
      { org_id: cfg.orgId },
      authHeaders(superAdmin)
    );
  }
  await api.dispose();
});

test("notifications list, mark-read, mark-all-read, and issue deep links work", async () => {
  const issue = await createIssue(`API V1 notifications ${Date.now()}`);

  const pmInbox = await apiGet<ApiEnvelope<{ items: NotificationRecord[]; unread_count: number; total_count: number }>>(
    api,
    `${cfg.apiBasePath}/notifications?state=unread&limit=50`,
    authHeaders(pm)
  );
  expect(pmInbox.res.status()).toBe(200);
  expectApiSuccess(pmInbox.body);

  const createdNotification = pmInbox.body.data.items.find(
    (item) => item.event_key === "issue_created" && item.link_path === `/app/reports/${issue.id}`
  );
  expect(createdNotification).toBeTruthy();
  expect(createdNotification?.body).toContain(issue.title);
  expect(createdNotification?.read_at).toBeNull();

  const markRead = await apiPostJson<ApiEnvelope<{ notification: NotificationRecord }>>(
    api,
    `${cfg.apiBasePath}/notifications/${createdNotification!.id}/read`,
    {},
    authHeaders(pm)
  );
  expect(markRead.res.status()).toBe(200);
  expectApiSuccess(markRead.body);
  expect(markRead.body.data.notification.read_at).not.toBeNull();

  const assigned = await apiPostJson<ApiEnvelope<{ issue: Issue }>>(
    api,
    `${cfg.apiBasePath}/issues/${issue.id}/assign-dev`,
    {
      org_id: cfg.orgId,
      dev_id: cfg.accounts.seniorDev.userId,
    },
    authHeaders(pm)
  );
  expect(assigned.res.status()).toBe(200);
  expectApiSuccess(assigned.body);

  const seniorInbox = await apiGet<ApiEnvelope<{ items: NotificationRecord[]; unread_count: number; total_count: number }>>(
    api,
    `${cfg.apiBasePath}/notifications?state=unread&limit=50`,
    authHeaders(seniorDev)
  );
  expect(seniorInbox.res.status()).toBe(200);
  expectApiSuccess(seniorInbox.body);

  const seniorNotification = seniorInbox.body.data.items.find(
    (item) => item.event_key === "issue_assigned_dev" && item.link_path === `/app/reports/${issue.id}`
  );
  expect(seniorNotification).toBeTruthy();
  expect(seniorNotification?.title).toBe("Issue assigned to Senior Developer");

  const markAll = await apiPostJson<ApiEnvelope<{ updated: number }>>(
    api,
    `${cfg.apiBasePath}/notifications/read-all`,
    {},
    authHeaders(seniorDev)
  );
  expect(markAll.res.status()).toBe(200);
  expectApiSuccess(markAll.body);
  expect(markAll.body.data.updated).toBeGreaterThan(0);

  const seniorInboxAfter = await apiGet<ApiEnvelope<{ items: NotificationRecord[]; unread_count: number; total_count: number }>>(
    api,
    `${cfg.apiBasePath}/notifications?state=all&limit=50`,
    authHeaders(seniorDev)
  );
  expect(seniorInboxAfter.res.status()).toBe(200);
  expectApiSuccess(seniorInboxAfter.body);
  const seniorNotificationAfter = seniorInboxAfter.body.data.items.find((item) => item.id === seniorNotification!.id);
  expect(seniorNotificationAfter?.read_at).not.toBeNull();
});
