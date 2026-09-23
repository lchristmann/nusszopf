import { test, expect, type Page } from '@playwright/test';
import { tick } from '../../pages/LoginPage';
import { registerFreshUser } from '../../support/session';
import { mailpitUrl, waitForMail } from '../../support/mailpit';

/**
 * docs/rewrite/master-roadmap.md, "Slice 9": subscribe → confirm via the
 * mailbox → unsubscribe, and an unknown link → 404. Double opt-in on every
 * path (decision A-1, BUG-011).
 *
 * The public forms share the historical 10-per-15-minutes-per-IP budget, and
 * every engine runs from the same address — these specs spend two of it per
 * engine (`docs/testing/README.md`).
 */

/** The link in a mailed button, as a path — `APP_URL` need not be the origin Playwright reaches. */
function mailedPath(html: string, prefix: string): string {
    const [, url] = html.match(new RegExp(`href="([^"]*${prefix}[^"]*)"`)) ?? [];
    expect(url).toBeTruthy();

    return new URL(url!.replace(/&amp;/g, '&')).pathname;
}

async function confirmSubscription(page: Page, email: string): Promise<void> {
    const html = await waitForMail(email, 'Nussiger Newsletter – Anmeldebestätigung');
    await page.goto(mailedPath(html, '/newsletter/subscribe/'));

    await expect(page.getByTestId('newsletter-subscribe-confirm')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Juhuu! Nussige News!' })).toBeVisible();
    await expect(page.getByText(email)).toBeVisible();
}

test('subscribes at registration, confirms via the mailbox, then unsubscribes by e-mail', async ({ page }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');

    const user = await registerFreshUser(page, { newsletter: true });

    // Still pending until the link is clicked — the Profile keeps offering the sign-up.
    await page.goto('/user/profile');
    await expect(page.getByTestId('btn_newsletter-subscribe_settings-page')).toBeVisible();

    await confirmSubscription(page, user.email);

    await page.goto('/user/profile');
    await expect(page.getByTestId('btn_newsletter-unsubscribe_settings-page')).toBeVisible();

    await page.goto('/newsletter/unsubscribe/lead');
    await expect(page.getByRole('heading', { name: 'Newsletterabmeldung' })).toBeVisible();
    await page.getByTestId('input_newsletter-unsubscribe-email').fill(user.email);
    await page.getByTestId('btn_newsletter-unsubscribe').click();
    await expect(page.getByText('E-Mail verschickt! Bitte bestätige deine Abmeldung.')).toBeVisible();

    const html = await waitForMail(user.email, 'Nussiger Newsletter – Abmeldebestätigung');
    await page.goto(mailedPath(html, '/newsletter/unsubscribe/'));
    await expect(page.getByTestId('newsletter-unsubscribe-confirm')).toBeVisible();
    await expect(page.getByText('Schade Marmelade')).toBeVisible();
    await expect(page.getByText(user.email)).toBeVisible();
    await expect(page.getByRole('link', { name: 'E-Mail an Nusszopf schreiben' })).toHaveAttribute('href', /^mailto:/);

    await page.goto('/user/profile');
    await expect(page.getByTestId('btn_newsletter-subscribe_settings-page')).toBeVisible();
});

test('subscribes from the Profile, confirms via the mailbox, then unsubscribes there', async ({ page }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');

    const user = await registerFreshUser(page);
    await page.goto('/user/profile');

    await tick(page.getByTestId('checkbox_newsletter_settings-page'));
    await page.getByTestId('btn_newsletter-subscribe_settings-page').click();
    await expect(page.getByText('E-Mail verschickt! Bitte bestätige deine Anmeldung.')).toBeVisible();

    await confirmSubscription(page, user.email);

    await page.goto('/user/profile');
    page.once('dialog', (dialog) => void dialog.accept());
    await page.getByTestId('btn_newsletter-unsubscribe_settings-page').click();
    await expect(page.getByText('Du bist jetzt abgemeldet!')).toBeVisible();

    await page.reload();
    await expect(page.getByTestId('btn_newsletter-subscribe_settings-page')).toBeVisible();
});

test('answers an unknown or tampered link with the 404 page', async ({ page }) => {
    for (const path of ['/newsletter/subscribe/unknown-token', '/newsletter/unsubscribe/abc.def']) {
        const response = await page.goto(path);
        expect(response?.status()).toBe(404);
        await expect(page.getByText('Juhuu! Nussige News!')).toHaveCount(0);
        await expect(page.getByText('Schade Marmelade')).toHaveCount(0);
        await expect(page.getByRole('heading', { name: '404 – Nusszopf verknetet...' })).toBeVisible();
    }
});
