import { test, expect, type Page } from '@playwright/test';
import { tick } from '../../pages/LoginPage';
import { mailpitUrl, waitForMail } from '../../support/mailpit';

/**
 * docs/rewrite/master-roadmap.md, "Slice 10": Home, the legal pages, the
 * error page. Journey 1 (`_landingpage.spec.js`) is the landing CTA — its
 * `route_create-project-page` step is a stale historical assertion and stays
 * retired (docs/journeys/README.md).
 */

async function expectNoHorizontalScroll(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test('Journey 1: the landing page search CTA leads to the search page', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Netzwerk für gemeinsame Ideen und Projekte' })).toBeVisible();

    await page.getByTestId('route_search-page').last().click();
    await expect(page).toHaveURL((url) => url.pathname === '/search');
});

test('Home has its own header instead of the nav bar, and the classy footer', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByTestId('btn_burger_nav-header')).toHaveCount(0);
    const footer = page.getByTestId('footer');
    await expect(footer.getByRole('link', { name: 'Impressum' })).toBeVisible();
    await expect(footer.getByRole('link', { name: 'Datenschutz' })).toBeVisible();
    await expect(footer.getByRole('link', { name: 'Rechtliches' })).toBeVisible();
    await expect(footer.getByRole('link', { name: 'Zu Instagram' })).toHaveAttribute('href', 'https://www.instagram.com/nuss.zopf');
});

for (const width of [375, 768, 1440]) {
    test(`Home lays out without horizontal scrolling at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');

        for (const section of ['home-header', 'home-how-to', 'home-about', 'home-contest', 'home-fellows', 'home-newsletter']) {
            await expect(page.getByTestId(section)).toBeVisible();
        }
        await expectNoHorizontalScroll(page);

        // Hero: stacked below `lg` (1024px), side by side from it.
        const logo = await page.getByTestId('home-header').locator('svg').first().boundingBox();
        const title = await page.getByRole('heading', { level: 1 }).boundingBox();
        if (width >= 1024) {
            expect(title!.x).toBeGreaterThan(logo!.x + logo!.width - 1);
        } else {
            expect(title!.y).toBeGreaterThan(logo!.y + logo!.height - 1);
        }

        // Footer legal links: a column below `sm`, a row from it.
        const footer = page.getByTestId('footer');
        const impressum = await footer.getByRole('link', { name: 'Impressum' }).boundingBox();
        const privacy = await footer.getByRole('link', { name: 'Datenschutz' }).boundingBox();
        if (width >= 640) {
            expect(Math.abs(privacy!.y - impressum!.y)).toBeLessThan(2);
        } else {
            expect(privacy!.y).toBeGreaterThan(impressum!.y);
        }
    });
}

test('subscribes to the newsletter from Home and confirms via the mailbox', async ({ page }) => {
    test.skip(!mailpitUrl(), 'Needs E2E_MAILPIT_URL (docs/testing/README.md).');

    // One of the shared 10 / 15 min public-form budget per engine (docs/testing/README.md).
    const email = `home-${test.info().project.name}-${Date.now()}@example.test`;
    await page.goto('/#newsletter');

    await page.getByTestId('input_newsletter-name').fill('Nuss');
    await page.getByTestId('input_newsletter-email').fill(email);
    await tick(page.getByTestId('checkbox_newsletter-privacy'));
    await page.getByTestId('btn_newsletter-subscribe').click();
    await expect(page.getByText('E-Mail verschickt! Bitte bestätige deine Anmeldung.')).toBeVisible();

    const html = await waitForMail(email, 'Nussiger Newsletter – Anmeldebestätigung');
    const [, url] = html.match(/href="([^"]*\/newsletter\/subscribe\/[^"]*)"/) ?? [];
    await page.goto(new URL(url!.replace(/&amp;/g, '&')).pathname);
    await expect(page.getByRole('heading', { name: 'Juhuu! Nussige News!' })).toBeVisible();
    await expect(page.getByText(email)).toBeVisible();
});

test('reaches the legal pages from the Home footer and returns home', async ({ page }) => {
    for (const [name, path] of [['Impressum', '/legalNotice'], ['Datenschutz', '/privacy'], ['Rechtliches', '/legalPolicy']]) {
        await page.goto('/');
        await page.getByTestId('footer').getByRole('link', { name }).click();
        await expect(page).toHaveURL((url) => url.pathname === path);
        await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();

        await page.getByTestId('btn_go-back_nav-header').click();
        await expect(page).toHaveURL((url) => url.pathname === '/');
    }
});

test('Datenschutz with ?back returns to the previous page', async ({ page }) => {
    await page.goto('/search');
    await page.goto('/privacy?back=history');

    await page.getByTestId('btn_go-back_nav-header').click();
    await expect(page).toHaveURL((url) => url.pathname === '/search');
});

test('an unknown URL shows the error page with a way home', async ({ page }) => {
    const response = await page.goto('/gibt-es-nicht');
    expect(response?.status()).toBe(404);

    await expect(page.getByRole('heading', { name: '404 – Nusszopf verknetet...' })).toBeVisible();
    await expect(page.getByTitle('E-Mail an Nusszopf schreiben')).toHaveAttribute('href', /^mailto:.+\?subject=Nusszopf verknetet$/);
    await expect(page.getByTestId('btn_burger_nav-header')).toHaveCount(0);

    await page.getByTestId('btn_home_error-page').click();
    await expect(page).toHaveURL((url) => url.pathname === '/');
});
