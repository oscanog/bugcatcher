import { expect, test } from "@playwright/test";

const pageCases = [
  {
    route: "rainier/login.php",
    header: "Login",
  },
  {
    route: "rainier/signup.php",
    header: "Sign Up",
  },
  {
    route: "rainier/forgot_password.php",
    header: "Reset Password",
  },
] as const;

test.describe("auth page smoke", () => {
  for (const pageCase of pageCases) {
    test(`${pageCase.route} renders the page and auth styling`, async ({ page }) => {
      await page.goto(pageCase.route);
      await expect(page.locator(".form-box > header", { hasText: pageCase.header })).toBeVisible();
      await expect(page.locator("form")).toBeVisible();

      const backgroundImage = await page.locator("body").evaluate((element) => {
        return window.getComputedStyle(element).backgroundImage;
      });
      expect(backgroundImage).toContain("pinimg");
    });
  }
});
