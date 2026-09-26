import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { SearchPage } from '../../pages/SearchPage';
import { createPublicProject } from '../../support/projects';
import { registerFreshUser } from '../../support/session';
import { mailpitUrl, waitForMail } from '../../support/mailpit';
import { uniqueSuffix } from '../../support/env';

/**
 * Decision A-7 (docs/rewrite/decisions-register.md): an axe scan of every reachable screen and state, with
 * zero critical or serious violations; colour contrast is gated except for its two documented exceptions
 * (docs/testing/accessibility.md). Every scan's full result goes to test-results/a11y/<state>.json.
 * The DOM is the same in every engine, so the scan runs in Chromium only.
 */
test.skip(({ browserName }) => browserName !== 'chromium', 'The axe scan runs once, in Chromium.');
test.describe.configure({ mode: 'serial' });

const OUT = 'test-results/a11y';

async function scan(page: Page, state: string): Promise<void> {
    await page.mouse.move(0, 0);
    // Let opening transitions finish: a popover caught mid fade-in measures a false contrast failure. Alpine starts
    // a transition a frame after the click, so let two frames pass before collecting the running ones.
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    await page.evaluate(() => Promise.all(document.getAnimations()
        .filter((animation) => animation.effect?.getTiming().iterations !== Infinity)
        .map((animation) => animation.finished.catch(() => null))));
    const results = await new AxeBuilder({ page }).analyze();
    mkdirSync(OUT, { recursive: true });
    writeFileSync(`${OUT}/${state}.json`, JSON.stringify({ state, url: page.url(), violations: results.violations }, null, 2));
    // WCAG 2.5.3 "Label in Name": an aria-label must contain the visible text (axe's own rule is experimental).
    const mismatches = await page.evaluate(() => {
        const norm = (text: string) => text.replace(/\s+/g, ' ').trim().toLowerCase();
        return [...document.querySelectorAll('a[aria-label], button[aria-label], input[aria-label], [role=button][aria-label]')]
            .filter((element) => (element as HTMLElement).offsetParent !== null)
            .map((element) => {
                const visible = element instanceof HTMLInputElement
                    ? norm(element.closest('label')?.innerText ?? '')
                    : norm((element as HTMLElement).innerText);
                return { name: element.getAttribute('aria-label') ?? '', visible };
            })
            .filter(({ name, visible }) => visible !== '' && !norm(name).includes(visible))
            .map(({ name, visible }) => `"${name}" does not contain "${visible}"`);
    });
    expect.soft(mismatches, `label in name on "${state}"`).toEqual([]);
    // Contrast: fixed where the maintainer approved it (BUG-042–044), so any failure now is a regression — except the
    // two documented ones: labels of disabled inputs (`opacity-50`, exempt under WCAG 1.4.3) and older toasts,
    // which are dimmed historically (BUG-045, waived).
    const contrast = results.violations
        .filter((violation) => violation.id === 'color-contrast')
        .flatMap((violation) => violation.nodes)
        .filter((node) => !node.html.includes('opacity-50') && !node.target.join(' ').startsWith('#nz-toasts'))
        .map((node) => `${node.target.join(' ')}: ${node.any[0]?.data?.contrastRatio}`);
    expect.soft(contrast, `colour contrast on "${state}"`).toEqual([]);
    const blocking = results.violations
        .filter((violation) => (violation.impact === 'critical' || violation.impact === 'serious') && violation.id !== 'color-contrast')
        .map((violation) => `${violation.id} (${violation.impact}): ${violation.nodes.map((node) => node.target.join(' ')).join(' | ')}`);
    expect.soft(blocking, `axe on "${state}"`).toEqual([]);
}

const suffix = uniqueSuffix();
const title = `Barrierefrei ${suffix}`;

test('visitor screens and states', async ({ page, browser }) => {
    test.setTimeout(180_000);
    // An owner with a public project with requests, created in its own context.
    const ownerContext = await browser.newContext();
    const ownerPage = await ownerContext.newPage();
    await registerFreshUser(ownerPage);
    await createPublicProject(ownerPage, {
        title,
        requests: [{ title: 'Werkzeug', category: 'materials', description: 'Hammer und Säge.' }],
    });
    await new MyProjectsPage(ownerPage).openProject(title);
    const projectPath = new URL(ownerPage.url()).pathname;
    await ownerContext.close();

    await page.goto('/');
    await scan(page, 'home');

    const search = new SearchPage(page);
    await search.goto();
    // The queue worker indexes the new project asynchronously, and the page does not refresh itself: look again
    // until the card is there, as search.spec.ts does (P-16, P16-02; on an empty database there is no other card).
    await expect(async () => {
        await page.reload();
        await expect(search.cards.first()).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 60_000 });
    await scan(page, 'search');
    await search.openFilter();
    await scan(page, 'search-filter-open');
    await page.keyboard.press('Escape');
    await search.search(`Zq${suffix}xy`);
    await expect(search.noHits).toBeVisible();
    await scan(page, 'search-no-hits');

    await page.goto(projectPath);
    await scan(page, 'project-visitor');
    await page.getByText('Werkzeug').first().click();
    await expect(page.getByTestId('request-dialog')).toBeVisible();
    await scan(page, 'request-dialog');
    await page.keyboard.press('Escape');
    await page.getByTestId('btn_contact_project-detail').click();
    await expect(page.getByTestId('contact-dialog')).toBeVisible();
    await scan(page, 'contact-dialog');
    await page.getByTestId('btn_send_contact-dialog').click();
    await expect(page.getByTestId('contact-dialog').getByText('Bitte schreibe eine Nachricht')).toBeVisible();
    await scan(page, 'contact-dialog-errors');
    await page.keyboard.press('Escape');

    await page.getByTestId('btn_burger_nav-header').click();
    await scan(page, 'nav-menu-guest');
    await page.keyboard.press('Escape');

    for (const [path, state] of [['/legalNotice', 'legal-notice'], ['/legalPolicy', 'legal-policy'], ['/privacy', 'privacy'], ['/diese-seite-gibt-es-nicht', 'not-found']] as const) {
        await page.goto(path);
        await scan(page, state);
    }

    await page.goto('/newsletter/unsubscribe/lead');
    await scan(page, 'newsletter-unsubscribe-lead');
    await page.getByTestId('btn_newsletter-unsubscribe').click();
    await expect(page.getByText('Gib eine E-Mail-Adresse ein.', { exact: true })).toBeVisible();
    await scan(page, 'newsletter-unsubscribe-lead-errors');
});

test('authentication screens and states', async ({ page }) => {
    await page.goto('/login');
    await scan(page, 'login');
    await page.getByTestId('btn_login').click();
    await expect(page.getByText('Bitte gib ein Passwort ein')).toBeVisible();
    await scan(page, 'login-errors');
    await page.getByTestId('tab_register').click();
    await scan(page, 'register');
    await page.getByTestId('btn_register').click();
    await expect(page.getByText('Stimme den Datenschutzbestimmungen zu')).toBeVisible();
    await scan(page, 'register-errors');

    await page.goto('/password/forgot');
    await scan(page, 'password-forgot');
    await page.getByTestId('btn_send-reset-link').click();
    await expect(page.getByText('Bitte gib eine E-Mail-Adresse ein')).toBeVisible();
    await scan(page, 'password-forgot-errors');

    await page.goto('/password/reset/axe-scan-token?email=someone%40example.test');
    await scan(page, 'password-reset');
    await page.getByTestId('btn_save-new-password').click();
    await expect(page.getByTestId('input_new-password').locator('xpath=ancestor::div[1]/following-sibling::*[1]')).toBeVisible();
    await scan(page, 'password-reset-errors');
});

test('signed-in screens and states', async ({ page }) => {
    test.setTimeout(180_000);
    await registerFreshUser(page, { newsletter: true });
    const myProjects = new MyProjectsPage(page);

    await myProjects.goto();
    await expect(page.getByTestId('welcome-card')).toBeVisible();
    await scan(page, 'my-projects-empty');
    await page.getByTestId('btn_burger_nav-header').click();
    await scan(page, 'nav-menu-user');
    await page.keyboard.press('Escape');

    const wizard = new ProjectWizardPage(page);
    await wizard.goto();
    await scan(page, 'wizard-step-1');
    await wizard.clickNext();
    await expect(page.getByText('Gib einen Titel ein', { exact: true })).toBeVisible();
    await scan(page, 'wizard-step-1-errors');
    await page.getByTestId('input_project-title').locator('xpath=ancestor::div[1]').getByRole('button').first().click();
    await scan(page, 'wizard-info-popover');
    await page.keyboard.press('Escape');
    await wizard.pick('radio_fixed_project-location');
    await page.getByTestId('combobox_project-location').fill('Leip');
    await expect(page.getByTestId('option_project-location').first()).toBeVisible({ timeout: 10_000 });
    await scan(page, 'wizard-place-suggestions');
    await wizard.fillStepOne(`Barrierefrei Assistent ${suffix}`, 'Ein Ziel.', 'Eine Beschreibung.');
    await wizard.advanceTo(1);
    await scan(page, 'wizard-step-2');
    await wizard.advanceTo(2);
    await scan(page, 'wizard-step-3');
    await page.getByTestId('btn_create_requests-step').click();
    await expect(page.getByTestId('edit-request-dialog')).toBeVisible();
    await page.getByTestId('btn_create-or-save_edit-request-dialog').click();
    await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
    await scan(page, 'request-edit-dialog-errors');
    page.once('dialog', (dialog) => void dialog.accept());
    await page.keyboard.press('Escape');
    await page.getByTestId('edit-request-dialog').getByRole('button', { name: 'Abbrechen' }).click();
    await wizard.advanceTo(3);
    await scan(page, 'wizard-step-4');
    await wizard.next.click();
    await expect(page).toHaveURL(/\/user\/projects$/);

    await expect(myProjects.card(`Barrierefrei Assistent ${suffix}`)).toBeVisible();
    await scan(page, 'my-projects');
    await myProjects.openCardMenu(`Barrierefrei Assistent ${suffix}`);
    await scan(page, 'my-projects-card-menu');
    await page.keyboard.press('Escape');

    await myProjects.openProject(`Barrierefrei Assistent ${suffix}`);
    await scan(page, 'project-owner');
    const projectId = new URL(page.url()).pathname.split('/').at(-1);

    const edit = new ProjectEditPage(page);
    await page.goto(`/user/project/${projectId}/edit`);
    await expect(page.getByTestId('skeleton_edit-project')).toHaveCount(0);
    await scan(page, 'edit-description');
    await edit.selectView('Gesuche');
    await scan(page, 'edit-requests');
    await page.getByTestId('btn_create_requests-view').click();
    await expect(page.getByTestId('edit-request-dialog')).toBeVisible();
    await scan(page, 'request-edit-dialog');
    page.once('dialog', (dialog) => void dialog.accept());
    await page.getByTestId('edit-request-dialog').getByRole('button', { name: 'Abbrechen' }).click();
    await edit.selectView('Einstellungen');
    await scan(page, 'edit-settings');

    await page.goto('/user/profile');
    await scan(page, 'profile');
    await page.getByTestId('btn_edit-avatar_settings-page').click();
    await expect(page.getByTestId('avatar-dialog')).toBeVisible();
    await scan(page, 'avatar-dialog');
    await page.keyboard.press('Escape');
    await page.getByTestId('btn_newsletter-subscribe_settings-page').click();
    await expect(page.getByText('Stimme den Datenschutzbestimmungen zu')).toBeVisible();
    await scan(page, 'profile-newsletter-errors');

    // The newsletter confirmation page, from the double opt-in mail of the registration checkbox.
    if (mailpitUrl()) {
        const email = (await page.getByTestId('username_avatar').locator('xpath=following-sibling::*[1]').textContent())!.trim();
        const html = await waitForMail(email, 'Nussiger Newsletter – Anmeldebestätigung');
        const [, url] = html.match(/href="([^"]*\/newsletter\/subscribe\/[^"]*)"/) ?? [];
        await page.goto(new URL(url!.replace(/&amp;/g, '&')).pathname);
        await expect(page.getByTestId('newsletter-subscribe-confirm')).toBeVisible();
        await scan(page, 'newsletter-subscribe-confirm');

        await page.goto('/newsletter/unsubscribe/lead');
        await page.getByTestId('input_newsletter-unsubscribe-email').fill(email);
        await page.getByTestId('btn_newsletter-unsubscribe').click();
        const unsubscribe = await waitForMail(email, 'Nussiger Newsletter – Abmeldebestätigung');
        const [, link] = unsubscribe.match(/href="([^"]*\/newsletter\/unsubscribe\/[^"]*)"/) ?? [];
        await page.goto(new URL(link!.replace(/&amp;/g, '&')).pathname);
        await expect(page.getByTestId('newsletter-unsubscribe-confirm')).toBeVisible();
        await scan(page, 'newsletter-unsubscribe-confirm');
    }
});
