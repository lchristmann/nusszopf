import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * The public demo and its guided tour (docs/handbuch/demo.md). Needs a stack started with NUSSZOPF_DEMO=true,
 * so it is skipped elsewhere (CI runs it in its own step after the main run, docs/testing/README.md).
 */
test.skip(!process.env.E2E_DEMO, 'Needs a stack with NUSSZOPF_DEMO=true and E2E_DEMO=1 (docs/handbuch/demo.md).');

const STEPS = 8;

test('Home leads with the demo, not with the search', async ({ page }) => {
    await page.goto('/');

    const demo = page.getByTestId('home-demo');
    await expect(demo).toBeVisible();
    await expect(demo.getByTestId('btn_demo-login_home')).toBeVisible();

    // The demo card comes before the how-to section that holds the search CTA.
    const order = await page.evaluate(() => {
        const position = (selector: string) => document.querySelector(selector)!.getBoundingClientRect().top;
        return position('[data-test="home-demo"]') < position('[data-test="home-how-to"]');
    });
    expect(order).toBe(true);

    // The newsletter form is replaced by the explanation on the demo.
    await expect(page.getByTestId('form_newsletter-subscribe')).toHaveCount(0);
    await expect(page.getByTestId('home-newsletter-demo')).toContainText('betreibt keinen Newsletter');
});

test('"Demo ausprobieren" signs in as the demo account with sample projects and no tour', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('btn_demo-login_home').click();

    await expect(page).toHaveURL((url) => url.pathname === '/user/projects');
    await expect(page.getByTestId('text_title_project-edit-card').first()).toBeVisible();
    await expect(page.getByTestId('tour-card')).toHaveCount(0);
    await expect(page.getByTestId('btn_tour-start')).toBeVisible();
});

test('the demo offers no way to leave a real address: registration explains, and offers the demo instead', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByTestId('btn_login-google')).toHaveCount(0);

    await page.getByTestId('tab_register').click();
    await expect(page.getByTestId('demo-register-note')).toContainText('In der Demo ist die Registrierung abgeschaltet');
    await expect(page.getByTestId('btn_register')).toHaveCount(0);
    await expect(page.getByTestId('input_username')).toHaveCount(0);

    await page.getByTestId('btn_demo-login_register').click();
    await expect(page).toHaveURL((url) => url.pathname === '/user/projects');
});

test('the demo account cannot be deleted', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('btn_demo-login_home').click();
    await page.goto('/user/profile');

    await expect(page.getByTestId('demo-account-note')).toBeVisible();
    await expect(page.getByTestId('btn_delete-account_settings-page')).toHaveCount(0);
});

test('the guided tour walks the real pages from start to end', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('btn_demo-tour_home').click();

    const card = page.getByTestId('tour-card');
    await expect(card).toContainText(`Schritt 1 von ${STEPS}`);
    await expect(card).toHaveAttribute('role', 'dialog');
    await expect(page).toHaveURL((url) => url.pathname === '/user/projects' && !url.search);

    // Pages: my projects (2 steps), search (2), a project (2), settings (2).
    const expectedPaths = ['/user/projects', '/user/projects', '/search', '/search', /^\/projects\//, /^\/projects\//, '/user/profile', '/user/profile'];
    for (let step = 0; step < STEPS; step++) {
        await expect(card).toContainText(`Schritt ${step + 1} von ${STEPS}`);
        await expect(page).toHaveURL((url) => {
            const expected = expectedPaths[step];
            return typeof expected === 'string' ? url.pathname === expected : expected.test(url.pathname);
        });
        if (step < STEPS - 1) await page.getByTestId('tour-next').click();
    }

    await expect(card).toContainText('Das war die Tour');
    await expect(card.getByRole('link', { name: 'Selbst betreiben' })).toHaveAttribute('href', /docs\/handbuch\/installation\.md$/);
    await page.getByTestId('tour-next').click();
    await expect(card).toHaveCount(0);
});

test('the tour can be left at any step, by button and by Escape', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('btn_demo-tour_home').click();
    await page.getByTestId('tour-next').click();
    await page.getByTestId('tour-end').click();
    await expect(page.getByTestId('tour-card')).toHaveCount(0);

    // It stays ended across a reload, and can be started again from the launcher.
    await page.reload();
    await expect(page.getByTestId('tour-card')).toHaveCount(0);
    await page.getByTestId('btn_tour-start').click();
    await expect(page.getByTestId('tour-card')).toContainText('Schritt 1 von');

    await page.keyboard.press('Escape');
    await expect(page.getByTestId('tour-card')).toHaveCount(0);
});

test('the tour card is usable by keyboard and fits the viewport', async ({ page }) => {
    await page.goto('/');
    await page.getByTestId('btn_demo-tour_home').click();

    const card = page.getByTestId('tour-card');
    await expect(card).toBeVisible();
    await expect(page.getByTestId('tour-next')).toBeFocused();

    const box = (await card.boundingBox())!;
    const viewport = page.viewportSize()!;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);

    await page.keyboard.press('Enter');
    await expect(card).toContainText('Schritt 2 von');
});

test.describe('accessibility (axe, WCAG 2 A/AA)', () => {
    const scan = async (page: import('@playwright/test').Page) =>
        (await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze()).violations;

    test('Home with the demo card and its rainbow button', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByTestId('btn_demo-login_home')).toBeVisible();
        expect(await scan(page)).toEqual([]);
    });

    test('the register tab note and the open tour card', async ({ page }) => {
        await page.goto('/login');
        await page.getByTestId('tab_register').click();
        await expect(page.getByTestId('demo-register-note')).toBeVisible();
        expect(await scan(page)).toEqual([]);

        await page.goto('/');
        await page.getByTestId('btn_demo-tour_home').click();
        await expect(page.getByTestId('tour-card')).toBeVisible();
        await page.waitForTimeout(800);
        expect(await scan(page)).toEqual([]);
    });
});
