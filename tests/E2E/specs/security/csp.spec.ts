import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect, type Page } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { createPublicProject } from '../../support/projects';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

const AVATAR_FIXTURE = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'fixtures', 'avatar.png');

/**
 * P-4, SEC-06 (docs/release/parity/P-04-security.md): the Content-Security-Policy blocks inline scripts and
 * inline event handlers. This drives the interactive parts of the journeys — registration, the wizard with
 * its rich-text editor and request dialog, the card menu, the owner banner, the request dialog, the avatar
 * cropper and upload, a toast flashed across a redirect, the Privacy back link, search and the contact
 * dialog — and fails on any report of the policy blocking something.
 */
async function recordViolations(page: Page): Promise<string[]> {
    const violations: string[] = [];
    await page.exposeFunction('nzReportCsp', (violation: string) => violations.push(violation));
    await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            (window as any).nzReportCsp(`${event.violatedDirective} blocked ${event.blockedURI} at ${event.sourceFile}:${event.lineNumber}`);
        });
    });
    page.on('console', (message) => {
        if (/content[- ]security[- ]policy/i.test(message.text())) violations.push(message.text());
    });

    return violations;
}

test('an owner journey runs under the policy without a single violation', async ({ page }) => {
    const violations = await recordViolations(page);

    const home = await page.goto('/');
    expect(home!.headers()['content-security-policy']).toContain("script-src 'self' 'unsafe-eval'");

    await registerFreshUser(page);
    const title = `CSP-Projekt ${uniqueSuffix()}`;
    await createPublicProject(page, { title, requests: [{ title: 'Leiter', category: 'materials', description: 'Eine lange Leiter.' }] });

    // The owner banner's close button (formerly an inline onclick).
    await new MyProjectsPage(page).openProject(title);
    const banner = page.locator('#nz-project-banner');
    await expect(banner).toBeVisible();
    // The button itself has no size; its absolutely positioned icon is what a visitor clicks.
    await page.locator('[data-hide="nz-project-banner"] svg').click();
    await expect(banner).toBeHidden();

    await page.getByTestId('card_request').first().click();
    await expect(page.getByTestId('request-dialog')).toBeVisible();
    await page.keyboard.press('Escape');

    // The avatar cropper reads the file as a data: URL and uploads through Livewire.
    await page.goto('/user/profile');
    await page.getByTestId('btn_edit-avatar_settings-page').click();
    await page.locator('[data-test="avatar-dialog"] input[type="file"]').setInputFiles(AVATAR_FIXTURE);
    await page.getByTestId('btn_save_avatar-dialog').click();
    await expect(page.getByText('Frisches Bild gespeichert.')).toBeVisible();

    // A toast flashed into the session before a redirect (formerly an inline script).
    await new MyProjectsPage(page).goto();
    await new MyProjectsPage(page).card(title).click();
    await page.waitForURL(/\/user\/project\/.+\/edit$/);
    const edit = new ProjectEditPage(page);
    await edit.selectView('Einstellungen');
    page.once('dialog', (dialog) => dialog.accept());
    await edit.deleteProject();
    await page.waitForURL(/\/user\/projects$/);
    await expect(page.getByText('Das Projekt wurde gelöscht.')).toBeVisible();

    expect(violations).toEqual([]);
});

test('a visitor journey runs under the policy without a single violation', async ({ page }) => {
    const violations = await recordViolations(page);

    // "Zurück" on Privacy opened with ?back (formerly an inline onclick).
    await page.goto('/');
    await page.goto('/privacy?back');
    await page.getByTestId('btn_go-back_nav-header').click();
    await expect(page).toHaveURL(/\/$/);

    await page.goto('/search');
    await page.getByTestId('btn_disclosure_filter-popover').click();

    await page.goto('/login');
    await page.getByTestId('btn_forgot-password').click();
    await page.waitForURL(/\/password\/forgot$/);

    expect(violations).toEqual([]);
});
