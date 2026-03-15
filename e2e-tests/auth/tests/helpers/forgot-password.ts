import { expect, Page } from "@playwright/test";

export async function requestOtp(page: Page, email: string): Promise<void> {
  await page.goto("rainier/forgot_password.php?restart=1");
  await page.getByPlaceholder("Email Address").fill(email);
  await page.getByRole("button", { name: "Send OTP" }).click();
}

export async function verifyOtp(page: Page, otp: string): Promise<void> {
  await page.getByPlaceholder("000000").fill(otp);
  await page.getByRole("button", { name: "Verify OTP" }).click();
  await expect(page.locator('input[name="password"]')).toBeVisible();
}

export async function submitPasswordReset(page: Page, password: string): Promise<void> {
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="cpass"]').fill(password);
  await page.getByRole("button", { name: "Reset Password" }).click();
}

export async function expectPreviewOtp(page: Page): Promise<string> {
  const preview = page.locator("[data-test-otp]");
  await expect(preview).toBeVisible();
  const otp = await preview.getAttribute("data-test-otp");
  expect(otp).toMatch(/^\d{6}$/);
  return otp ?? "";
}
