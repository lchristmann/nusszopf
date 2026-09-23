import { type Locator, type Page, test, expect } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { registerFreshUser } from '../../support/session';
import { createPublicProject } from '../../support/projects';
import { uniqueSuffix } from '../../support/env';
import { mailpitUrl, waitForMail } from '../../support/mailpit';

/**
 * The counter renders as four separate digit boxes
 * (`resources/views/livewire/projects/project-detail.blade.php`), not one
 * text node — `toHaveText` would otherwise compare against Blade's own
 * template whitespace between them.
 */
function counter(page: Page): Locator {
    return page.getByTestId('visitor-counter_project-detail');
}

async function digits(page: Page): Promise<string> {
    return (await counter(page).locator('p').allTextContents()).join('');
}

/**
 * `VisitorCounter` / `updateViews` (`pages/projects/[id].js`) — BUG-001's
 * fix: the counter is incremented server-side only, once per browser,
 * excluding the owner (docs/rewrite/master-roadmap.md, slice 5).
 */
test('counts a visitor once per browser, never the owner', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const title = `Zähler-Projekt ${suffix}`;
    await registerFreshUser(page);
    await createPublicProject(page, { title });

    const myProjects = new MyProjectsPage(page);
    await myProjects.card(title).click();
    await page.waitForURL(/\/user\/project\/.+\/edit$/);
    const editUrl = new URL(page.url());
    const detailUrl = new URL(`/projects/${editUrl.pathname.split('/').at(-2)}`, editUrl.origin).toString();

    // The owner's own views are never counted.
    await page.goto(detailUrl);
    await expect(counter(page)).toBeVisible();
    expect(await digits(page)).toBe('0000');
    await page.goto(detailUrl);
    expect(await digits(page)).toBe('0000');

    // A guest's first visit counts once...
    const guestContext = await browser.newContext();
    const guestPage = await guestContext.newPage();
    await guestPage.goto(detailUrl);
    expect(await digits(guestPage)).toBe('0001');

    // ...and a second visit from the same browser does not count again.
    await guestPage.goto(detailUrl);
    expect(await digits(guestPage)).toBe('0001');
    await guestContext.close();
});

/**
 * The "Über Nusszopf" contact path (`ContactDialog.js`, docs/rewrite/sixth-slice.md): the wizard's
 * default contact ("Über Nusszopf") opens the form instead of a `mailto:` link; a message a visitor
 * sends is queued, delivered to the owner's own address, and never reveals it to the visitor.
 */
test('a visitor contacts a project through the dialog, and the owner receives it', async ({ page, browser }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');
    const suffix = uniqueSuffix();
    const title = `Kontaktprojekt ${suffix}`;
    const ownerContext = await browser.newContext();
    const ownerPage = await ownerContext.newPage();
    const owner = await registerFreshUser(ownerPage);
    await createPublicProject(ownerPage, { title });
    const myProjects = new MyProjectsPage(ownerPage);
    await myProjects.card(title).click();
    await ownerPage.waitForURL(/\/user\/project\/.+\/edit$/);
    const editUrl = new URL(ownerPage.url());
    const detailUrl = new URL(`/projects/${editUrl.pathname.split('/').at(-2)}`, editUrl.origin).toString();
    await ownerContext.close();

    await page.goto(detailUrl);
    await page.getByTestId('btn_contact_project-detail').click();
    const dialog = page.getByTestId('contact-dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByTestId('input_contact-email').fill('visitor-e2e@example.test');
    await dialog.getByTestId('input_contact-msg').fill(`Eine Testnachricht ${suffix}.`);
    await dialog.getByTestId('btn_send_contact-dialog').click();
    await expect(page.getByText('Nachricht versendet!')).toBeVisible();
    await expect(dialog).toBeHidden();

    const html = await waitForMail(owner.email, 'Nusszopf – Kontaktanfrage');
    expect(html).toContain(title);
    expect(html).toContain(`Eine Testnachricht ${suffix}.`);
});

/**
 * "Teilen" (`handleShare` in `pages/projects/[id].js`): the native share sheet where the browser
 * has one, otherwise the URL is copied and a toast confirms it. Both browser APIs are stubbed —
 * headless engines differ in which they offer, and the real share sheet cannot be driven.
 */
test('shares a project through the share sheet, or copies its link where there is none', async ({ page, browser }) => {
    const title = `Teilprojekt ${uniqueSuffix()}`;
    await registerFreshUser(page);
    await createPublicProject(page, { title });
    await new MyProjectsPage(page).openProject(title);
    const detailUrl = page.url();

    const withoutShare = await browser.newContext();
    await withoutShare.addInitScript(() => {
        Object.defineProperty(navigator, 'share', { value: undefined, configurable: true });
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText: async (text: string) => { (window as any).__copied = text; } },
            configurable: true,
        });
    });
    const copyPage = await withoutShare.newPage();
    await copyPage.goto(detailUrl);
    await copyPage.getByTestId('btn_share_project-detail').click();
    await expect(copyPage.getByText('Link zum Teilen kopiert!')).toBeVisible();
    expect(await copyPage.evaluate(() => (window as any).__copied)).toBe(detailUrl);
    await withoutShare.close();

    const withShare = await browser.newContext();
    await withShare.addInitScript(() => {
        Object.defineProperty(navigator, 'share', {
            value: async (data: ShareData) => { (window as any).__shared = data; },
            configurable: true,
        });
    });
    const sharePage = await withShare.newPage();
    await sharePage.goto(detailUrl);
    await sharePage.getByTestId('btn_share_project-detail').click();
    await expect.poll(() => sharePage.evaluate(() => (window as any).__shared?.url)).toBe(detailUrl);
    await expect(sharePage.getByText('Link zum Teilen kopiert!')).toHaveCount(0);
    await withShare.close();
});
