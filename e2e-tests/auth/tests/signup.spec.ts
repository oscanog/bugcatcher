import { expect, test } from "@playwright/test";
import { cfg } from "../src/config";

function uniqueEmail(): string {
  return `auth-e2e-${Date.now()}@example.invalid`;
}

test("signup shows password mismatch validation", async ({ page }) => {
  await page.goto("rainier/signup.php");
  await page.getByPlaceholder("Username").fill("AuthE2E");
  await page.getByPlaceholder("Email Address").fill(uniqueEmail());
  await page.locator('input[name="password"]').fill("MismatchPass123!");
  await page.locator('input[name="cpass"]').fill("MismatchPass321!");
  await page.getByRole("button", { name: "Signup" }).click();
  await expect(page.getByText("Password does not match.")).toBeVisible();
});

test("signup shows duplicate email validation for an existing account", async ({ page }) => {
  test.skip(!cfg.hasAuthCredentials, "An existing auth account is required for duplicate-email coverage.");

  await page.goto("rainier/signup.php");
  await page.getByPlaceholder("Username").fill("ExistingUserCheck");
  await page.getByPlaceholder("Email Address").fill(cfg.authEmail);
  await page.locator('input[name="password"]').fill("DuplicatePass123!");
  await page.locator('input[name="cpass"]').fill("DuplicatePass123!");
  await page.getByRole("button", { name: "Signup" }).click();
  await expect(page.getByText("This email is already used. Try another one.")).toBeVisible();
});
