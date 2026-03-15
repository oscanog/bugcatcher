import { expect, test } from "@playwright/test";
import { cfg } from "../src/config";
import { waitForLatestOtp } from "../src/imap";
import { loginViaUi, logoutViaUi } from "./helpers/auth";
import { requestOtp, submitPasswordReset, verifyOtp } from "./helpers/forgot-password";

test.skip(!cfg.isProduction, "This suite is only for production auth verification.");

test.describe.serial("production forgot-password flow", () => {
  test("dummy email keeps the generic success response", async ({ page }) => {
    await requestOtp(page, "not-a-real-user@example.com");
    await expect(page.getByText(/If an account exists for that email/i)).toBeVisible();
    await expect(page.getByPlaceholder("000000")).toBeVisible();
  });

  test("real inbox reset completes and restores the original password", async ({ page }) => {
    test.skip(!cfg.hasResetCredentials, "Reset credentials are required for the production flow.");
    test.skip(!cfg.hasImapCredentials, "IMAP credentials are required for OTP retrieval.");

    const firstRequestAt = new Date();
    await requestOtp(page, cfg.resetEmail);
    await expect(page.getByText(/If an account exists for that email/i)).toBeVisible();

    const firstOtp = await waitForLatestOtp({
      recipient: cfg.resetEmail,
      since: firstRequestAt,
    });
    await verifyOtp(page, firstOtp);
    await submitPasswordReset(page, cfg.resetNewPassword);
    await expect(page).toHaveURL(/\/rainier\/login\.php\?reset=success/);

    await loginViaUi(page, cfg.resetEmail, cfg.resetNewPassword);
    await logoutViaUi(page);

    const secondRequestAt = new Date();
    await requestOtp(page, cfg.resetEmail);
    const secondOtp = await waitForLatestOtp({
      recipient: cfg.resetEmail,
      since: secondRequestAt,
    });
    await verifyOtp(page, secondOtp);
    await submitPasswordReset(page, cfg.resetOriginalPassword);
    await expect(page).toHaveURL(/\/rainier\/login\.php\?reset=success/);

    await loginViaUi(page, cfg.resetEmail, cfg.resetOriginalPassword);
  });
});
