import { test, expect } from '@playwright/test';
import { LoginPage } from '../../pages/LoginPage';
import { registerFreshUser } from '../../support/session';
import { mailpitUrl, waitForMail } from '../../support/mailpit';

/**
 * Repeated failed logins against one account lock it out and mail the
 * owner a "Das bin ich!" unblock link (register B-7/B-12,
 * docs/rewrite/seventh-slice.md) — the Laravel-native replacement for
 * Auth0's IP-block Attack Protection.
 */
test('locks out after repeated failed attempts and unblocks via the mailed link', async ({ page, browser }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');

    // Registration logs the browser in immediately (docs/authentication/README.md
    // §2) — a throwaway, discarded context creates the account without leaving
    // this test's own `page` authenticated, since the login *form* is what's
    // under test here.
    const registrationContext = await browser.newContext();
    const user = await registerFreshUser(await registrationContext.newPage());
    await registrationContext.close();

    const login = new LoginPage(page);
    await login.goto();

    for (let attempt = 0; attempt < 5; attempt++) {
        await login.loginExpectingError(user.email, 'totally-wrong-password');
    }

    // A sixth attempt, even with the correct password, is still locked out.
    await login.loginExpectingError(user.email, user.password);

    const html = await waitForMail(user.email, 'Nusszopf – IP-Adresse blockiert');
    const [, unblockUrl] = html.match(/href="([^"]*\/auth\/unblock[^"]*)"/) ?? [];
    expect(unblockUrl).toBeTruthy();

    await page.goto(unblockUrl!.replace(/&amp;/g, '&'));
    await expect(page).toHaveURL(/\/login$/);

    await login.login(user.email, user.password);
    await expect(page).toHaveURL(/\/user\/projects$/);
});
