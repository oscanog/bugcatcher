import { expect, Page } from "@playwright/test";

export async function loginViaUi(page: Page, email: string, password: string): Promise<void> {
  await page.goto("rainier/login.php");
  await page.getByPlaceholder("Email Address").fill(email);
  await page.getByPlaceholder("Password").fill(password);
  await page.getByRole("button", { name: "Login" }).click();
  await expect(page).toHaveURL(/\/zen\/organization\.php/);
}

export async function logoutViaUi(page: Page): Promise<void> {
  await page.goto("rainier/logout.php");
  await expect(page).toHaveURL(/\/rainier\/login\.php/);
}
