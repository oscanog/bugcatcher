import { expect, request, test, APIRequestContext } from "@playwright/test";
import { cfg } from "../src/config";
import { authHeaders, loginRole, RoleSession } from "./helpers/auth";
import { ApiEnvelope, apiDeleteJson, apiGet, apiPostJson } from "./helpers/client";

test.describe.configure({ mode: "serial" });

let api: APIRequestContext;
let pm: RoleSession;

test.beforeAll(async () => {
  api = await request.newContext({ baseURL: cfg.baseUrl });
  pm = await loginRole(api, "pm");
});

test.afterAll(async () => {
  await api.dispose();
});

test("discord link APIs are retired", async () => {
  const anon = await apiGet<ApiEnvelope<unknown>>(api, `${cfg.apiBasePath}/discord/link`);
  expect(anon.res.status()).toBe(401);

  const before = await apiGet<ApiEnvelope<unknown>>(
    api,
    `${cfg.apiBasePath}/discord/link`,
    authHeaders(pm)
  );
  expect(before.res.status()).toBe(410);
  expect(before.body.ok).toBe(false);

  const generated = await apiPostJson<ApiEnvelope<unknown>>(
    api,
    `${cfg.apiBasePath}/discord/link-code`,
    {},
    authHeaders(pm)
  );
  expect(generated.res.status()).toBe(410);
  expect(generated.body.ok).toBe(false);

  const removed = await apiDeleteJson<ApiEnvelope<unknown>>(
    api,
    `${cfg.apiBasePath}/discord/link`,
    undefined,
    authHeaders(pm)
  );
  expect(removed.res.status()).toBe(410);
  expect(removed.body.ok).toBe(false);
});
