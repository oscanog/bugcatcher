import { APIRequestContext, expect } from "@playwright/test";
import { cfg } from "../../src/config";

export async function loginAndSelectOrg(request: APIRequestContext): Promise<void> {
  const loginRes = await request.post("/register-passed-by-maglaque/login.php", {
    form: {
      email: cfg.email,
      password: cfg.password,
      login: "Login",
    },
  });

  expect([200, 302]).toContain(loginRes.status());
  const loginBody = await loginRes.text();
  if (loginBody.includes("Wrong email or password")) {
    throw new Error("Login failed. Check E2E_EMAIL/E2E_PASSWORD.");
  }

  const orgRes = await request.get(`/organization.php?org_id=${cfg.orgId}`);
  expect(orgRes.status()).toBeLessThan(400);

  const verifyRes = await request.get("/dashboard.php");
  expect(verifyRes.status()).toBeLessThan(400);
}

