import { test, expect } from '@playwright/test';
import { LoginPage } from '../../pages/LoginPage';
import { registerFreshUser } from '../../support/session';
import { mailpitUrl, waitForMail } from '../../support/mailpit';

/**
 * "Passwort vergessen" → "Neues Passwort erstellen"
 * (docs/authentication/README.md §4; docs/rewrite/seventh-slice.md) end to
 * end through the real mail catcher, replacing Auth0's hosted reset flow.
 */
test('resets a forgotten password via the mailed link, then logs in with it', async ({ page, browser }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');

    // Registration logs the browser in immediately (docs/authentication/README.md
    // §2); "Passwort vergessen"/the reset link are guest-only routes, so the
    // account is created in a throwaway context to keep this test's own
    // `page` logged out throughout.
    const registrationContext = await browser.newContext();
    const user = await registerFreshUser(await registrationContext.newPage());
    await registrationContext.close();

    const login = new LoginPage(page);
    await login.goto();
    await login.goToForgotPassword();
    await expect(page).toHaveURL(/\/password\/forgot$/);

    await page.getByTestId('input_forgot-password-email').fill(user.email);
    await page.getByTestId('btn_send-reset-link').click();
    // ChangePasswordForm: a toast answers, and the form stays.
    await expect(page.getByText('E-Mail verschickt!')).toBeVisible();
    await expect(page.getByTestId('input_forgot-password-email')).toHaveValue(user.email);

    const html = await waitForMail(user.email, 'Nusszopf – Neues Passwort erstellen');
    const [, resetUrl] = html.match(/href="([^"]*\/password\/reset\/[^"]*)"/) ?? [];
    expect(resetUrl).toBeTruthy();

    await page.goto(resetUrl!.replace(/&amp;/g, '&'));
    await expect(page.getByTestId('input_new-password')).toBeVisible();

    const newPassword = 'Ne1u!Passw0rt';
    await page.getByTestId('input_new-password').fill(newPassword);
    await page.getByTestId('btn_save-new-password').click();
    await expect(page).toHaveURL(/\/login$/);

    await login.login(user.email, newPassword);
    await expect(page).toHaveURL(/\/user\/projects$/);
});
