import { expect, test } from "@playwright/test";
import { cfg } from "../src/config";
import { loginViaUi, logoutViaUi } from "./helpers/auth";
import { expectPreviewOtp, requestOtp, submitPasswordReset, verifyOtp } from "./helpers/forgot-password";

test.skip(cfg.isProduction, "This suite is only for the local development environment.");

test.describe.serial("local forgot-password flow", () => {
  test("dummy email keeps the generic success response", async ({ page }) => {
    await requestOtp(page, "not-a-real-user@example.com");
    await expect(page.getByText(/If an account exists for that email/i)).toBeVisible();
    await expect(page.getByPlaceholder("000000")).toBeVisible();
  });

  test("real local account completes reset via development OTP preview and restores the original password", async ({
    page,
  }) => {
    test.skip(!cfg.hasResetCredentials, "Local reset credentials are required for the full reset flow.");

    await requestOtp(page, cfg.resetEmail);
    await expect(page.getByText(/If an account exists for that email/i)).toBeVisible();
    const firstOtp = await expectPreviewOtp(page);
    await verifyOtp(page, firstOtp);
    await submitPasswordReset(page, cfg.resetNewPassword);

    await expect(page).toHaveURL(/\/rainier\/login\.php\?reset=success/);
    await expect(page.getByText(/Your password has been reset/i)).toBeVisible();

    await loginViaUi(page, cfg.resetEmail, cfg.resetNewPassword);
    await logoutViaUi(page);

    await requestOtp(page, cfg.resetEmail);
    const secondOtp = await expectPreviewOtp(page);
    await verifyOtp(page, secondOtp);
    await submitPasswordReset(page, cfg.resetOriginalPassword);

    await expect(page).toHaveURL(/\/rainier\/login\.php\?reset=success/);
    await loginViaUi(page, cfg.resetEmail, cfg.resetOriginalPassword);
  });
});
