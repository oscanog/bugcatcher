import { expect, test } from "@playwright/test";
import { cfg } from "../src/config";
import { loginViaUi } from "./helpers/auth";

test("invalid login shows the expected error", async ({ page }) => {
  await page.goto("rainier/login.php");
  await page.getByPlaceholder("Email Address").fill("invalid@example.com");
  await page.getByPlaceholder("Password").fill("incorrect-password");
  await page.getByRole("button", { name: "Login" }).click();
  await expect(page.getByText("Wrong email or password.")).toBeVisible();
});

test("valid login redirects into the authenticated app", async ({ page }) => {
  test.skip(!cfg.hasAuthCredentials, "Auth credentials are required for the valid login flow.");

  await loginViaUi(page, cfg.authEmail, cfg.authPassword);
  await expect(page.getByRole("heading", { name: "Organization", exact: true })).toBeVisible();
});
