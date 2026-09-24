import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, type Locator, type Page } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { createPublicProject } from '../../support/projects';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

const AVATAR_FIXTURE = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'fixtures', 'avatar.png');

/**
 * P-5 (docs/release/parity/P-05-browsers-devices.md): the journeys' interaction points driven by touch on the
 * phone and tablet projects (`playwright.config.ts`), where the desktop suite only ever clicks with a mouse.
 * `tap()` sends real touch events, so a control that only reacts to mouse events, or a popover that only closes
 * on a mouse click outside, fails here. The historical app was only ever tested at 1440×800 (Cypress).
 */
test.skip(({ isMobile }) => !isMobile, 'Touch devices only (the mobile-* and tablet projects).');

/** No screen or open state may scroll sideways: the historical layout never does at 360px and up. */
async function expectNoSidewaysScroll(page: Page, state: string): Promise<void> {
    const widths = await page.evaluate(() => ({ viewport: window.innerWidth, document: document.documentElement.scrollWidth }));
    expect(widths.document, `${state} scrolls sideways`).toBeLessThanOrEqual(widths.viewport);
}

async function expectWithinViewport(page: Page, element: Locator, state: string): Promise<void> {
    const box = (await element.boundingBox())!;
    const width = page.viewportSize()!.width;
    expect(box.x, `${state} starts left of the screen`).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width, `${state} ends right of the screen`).toBeLessThanOrEqual(width);
}

/**
 * A tap on blank page: the first spot, scanning the screen, with no control and no click handler of its own under it.
 * iOS Safari sends no `click` for such a tap unless something up the tree listens for one (DEV-01).
 */
async function tapOutside(page: Page): Promise<void> {
    const spot = await page.evaluate(() => {
        const interactive = 'a, button, label, input, select, textarea, summary, details, [role=menu], [role=dialog], [x-on\\:click], [wire\\:click]';
        for (let y = window.innerHeight - 10; y > 60; y -= 20) {
            for (let x = 10; x < window.innerWidth; x += 20) {
                const element = document.elementFromPoint(x, y);
                if (element && element !== document.body && !element.closest(interactive)) return { x, y };
            }
        }
        return null;
    });
    expect(spot, 'no blank spot to tap').not.toBeNull();
    await page.touchscreen.tap(spot!.x, spot!.y);
}

test('the nav menu and the search filter open, work and close by touch', async ({ page }) => {
    await page.goto('/search');
    await expectNoSidewaysScroll(page, 'search');

    const menu = page.locator('details:has([data-test=btn_burger_nav-header]) > div');
    await page.getByTestId('btn_burger_nav-header').tap();
    await expect(menu).toBeVisible();
    await expectWithinViewport(page, menu, 'the nav menu');
    await tapOutside(page);
    await expect(menu).toBeHidden();

    await page.getByTestId('btn_burger_nav-header').tap();
    await menu.getByTestId('btn_create-project_nav-header').tap();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto('/search');
    const popover = page.getByRole('dialog', { name: 'Gesuche filtern' });
    await page.getByTestId('btn_disclosure_filter-popover').tap();
    await expect(popover).toBeVisible();
    await expectWithinViewport(page, popover, 'the filter popover');
    await page.getByTestId('checkbox_materials_filter-popover').locator('xpath=ancestor::label').tap();
    await expect(page.getByTestId('checkbox_materials_filter-popover')).toBeChecked();
    await tapOutside(page);
    await expect(popover).toBeHidden();
});

test('the owner screens, card menu, wizard and dialogs work by touch without sideways scrolling', async ({ page }) => {
    test.setTimeout(180_000);
    const title = `Touch ${uniqueSuffix()}`;
    await registerFreshUser(page);
    await expectNoSidewaysScroll(page, 'My Projects, empty');

    // The wizard, driven by taps: step buttons, the request editor, the create button.
    const wizard = new ProjectWizardPage(page);
    await page.getByTestId('route_create-project_projects-page').filter({ visible: true }).tap();
    await expect(page).toHaveURL(/\/user\/project\/create\?step=0$/);
    await expectNoSidewaysScroll(page, 'wizard step 1');
    await wizard.fillStepOne(title, 'Ein Ziel.', 'Eine Beschreibung.');
    await wizard.next.tap();
    await expect(wizard.stepLabel('Beschreibung 2/2')).toBeVisible();
    await expectNoSidewaysScroll(page, 'wizard step 2');
    await wizard.next.tap();
    await expectNoSidewaysScroll(page, 'wizard step 3');
    await page.getByTestId('btn_create_requests-step').tap();
    const editor = page.getByTestId('edit-request-dialog').getByRole('dialog');
    await expect(editor).toBeVisible();
    await expectWithinViewport(page, editor, 'the request editor');
    await expectNoSidewaysScroll(page, 'the request editor');
    await page.getByTestId('edit-request-dialog').getByRole('button', { name: 'Abbrechen' }).tap();
    await expect(editor).toBeHidden();
    await wizard.next.tap();
    await expectNoSidewaysScroll(page, 'wizard step 4');
    await wizard.next.tap();
    await expect(page).toHaveURL(/\/user\/projects$/);

    // The card menu opens by tap and closes by a tap elsewhere.
    const myProjects = new MyProjectsPage(page);
    const card = myProjects.card(title);
    await expect(card).toBeVisible();
    await expectNoSidewaysScroll(page, 'My Projects, one project');
    await card.getByTestId('menu_edit-project-card').tap();
    await expect(card.getByTestId('menuitem-0')).toBeVisible();
    await expectWithinViewport(page, card.getByTestId('menuitem-0'), 'the card menu');
    await tapOutside(page);
    await expect(card.getByTestId('menuitem-0')).toBeHidden();

    // The edit screen's view select (a native <select>) and its three views.
    await card.tap();
    const edit = new ProjectEditPage(page);
    for (const view of ['Beschreibung', 'Gesuche', 'Einstellungen'] as const) {
        await edit.selectView(view);
        await expectNoSidewaysScroll(page, `edit: ${view}`);
    }

    // The project page and its dialogs.
    await myProjects.goto();
    await myProjects.openProject(title);
    await expectNoSidewaysScroll(page, 'project page');
    await page.getByTestId('btn_contact_project-detail').tap();
    const contact = page.getByTestId('contact-dialog').getByRole('dialog');
    await expect(contact).toBeVisible();
    await expectWithinViewport(page, contact, 'the contact dialog');
    await expectNoSidewaysScroll(page, 'the contact dialog');
    await contact.getByRole('button', { name: 'Abbrechen' }).tap();
    await expect(contact).toBeHidden();

    await page.goto('/user/profile');
    await expectNoSidewaysScroll(page, 'profile');
});

test('the public screens never scroll sideways', async ({ page }) => {
    for (const path of ['/', '/login', '/password/forgot', '/privacy', '/legalNotice', '/legalPolicy', '/diese-seite-gibt-es-nicht']) {
        await page.goto(path);
        await expectNoSidewaysScroll(page, path);
    }
});

test('the avatar cropper moves and zooms the picture by touch', async ({ page, browserName }) => {
    // Playwright can synthesise multi-touch gestures only through the Chrome DevTools Protocol; WebKit gets taps.
    test.skip(browserName !== 'chromium', 'Touch drag and pinch need CDP (Chromium).');
    await registerFreshUser(page);
    await page.goto('/user/profile');
    await page.getByTestId('btn_edit-avatar_settings-page').tap();
    await page.locator('[data-test="avatar-dialog"] input[type="file"]').setInputFiles(AVATAR_FIXTURE);
    const picture = page.locator('[data-test="avatar-dialog"] .cropper-canvas img');
    await expect(picture).toBeVisible();
    const dialog = page.getByTestId('avatar-dialog').getByRole('dialog');
    await expectWithinViewport(page, dialog, 'the avatar dialog');

    const cdp = await page.context().newCDPSession(page);
    const touch = (type: 'touchStart' | 'touchMove' | 'touchEnd', points: { x: number; y: number }[]) =>
        cdp.send('Input.dispatchTouchEvent', { type, touchPoints: points.map((point, id) => ({ ...point, id })) });
    const area = (await page.locator('[data-test="avatar-dialog"] .cropper-container').boundingBox())!;
    const cx = area.x + area.width / 2;
    const cy = area.y + area.height / 2;

    // Pinch out: the picture grows (react-easy-crop zoomed on pinch, up to 3×).
    const before = (await picture.boundingBox())!;
    await touch('touchStart', [{ x: cx - 20, y: cy }, { x: cx + 20, y: cy }]);
    for (let step = 1; step <= 10; step++) {
        await touch('touchMove', [{ x: cx - 20 - step * 6, y: cy }, { x: cx + 20 + step * 6, y: cy }]);
    }
    await touch('touchEnd', []);
    const zoomed = (await picture.boundingBox())!;
    expect(zoomed.width).toBeGreaterThan(before.width * 1.1);

    // One-finger drag: the picture moves under the fixed crop circle.
    await touch('touchStart', [{ x: cx, y: cy }]);
    for (let step = 1; step <= 10; step++) {
        await touch('touchMove', [{ x: cx + step * 4, y: cy + step * 3 }]);
    }
    await touch('touchEnd', []);
    const moved = (await picture.boundingBox())!;
    expect(Math.abs(moved.x - zoomed.x) + Math.abs(moved.y - zoomed.y)).toBeGreaterThan(10);

    // Chrome's gesture recogniser drops a tap that starts within a few dozen milliseconds of the synthetic gesture
    // above (no click follows the touchend; about one run in three in P-5). No finger is that fast.
    await page.waitForTimeout(500);
    await page.getByTestId('btn_save_avatar-dialog').tap();
    await expect(page.getByTestId('avatar-dialog')).toBeHidden();
    await expect(page.getByTestId('img_avatar')).toHaveAttribute('src', /\/storage\/avatars\/.+-v1\.jpg$/);
});
