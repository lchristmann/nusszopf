import { test, expect, type Locator, type Page } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { createPublicProject } from '../../support/projects';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

/**
 * Decision A-7's keyboard bar (docs/testing/accessibility.md): every dialog and popover takes focus, keeps it
 * while open, closes on Escape and gives focus back to what opened it; menus follow the ARIA menu pattern;
 * every control is reachable and shows a visible focus indicator; every validation message is tied to its field.
 */
const focusInside = (container: Locator) => container.evaluate((element) => element.contains(document.activeElement));

async function expectTrapped(page: Page, container: Locator): Promise<void> {
    await expect.poll(() => focusInside(container)).toBe(true);
    for (let i = 0; i < 12; i++) {
        await page.keyboard.press('Tab');
        expect(await focusInside(container)).toBe(true);
    }
}

async function expectEscapeRestores(page: Page, container: Locator, trigger: Locator): Promise<void> {
    await page.keyboard.press('Escape');
    await expect(container).toBeHidden();
    await expect(trigger).toBeFocused();
}

async function publicProjectPath(browser: import('@playwright/test').Browser, title: string): Promise<string> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await registerFreshUser(page);
    await createPublicProject(page, { title, requests: [{ title: 'Leiter', category: 'materials', description: 'Eine lange Leiter.' }] });
    await new MyProjectsPage(page).openProject(title);
    const path = new URL(page.url()).pathname;
    await context.close();

    return path;
}

test('the nav menu and the search filter take and keep focus, and Escape gives it back', async ({ page }) => {
    await page.goto('/search');
    const burger = page.getByTestId('btn_burger_nav-header');
    const menu = page.locator('details:has([data-test=btn_burger_nav-header]) > div');
    await burger.focus();
    await page.keyboard.press('Enter');
    await expectTrapped(page, menu);
    await expectEscapeRestores(page, menu, burger);

    const disclosure = page.getByTestId('btn_disclosure_filter-popover');
    const popover = page.getByRole('dialog', { name: 'Gesuche filtern' });
    await disclosure.focus();
    await page.keyboard.press('Enter');
    await expectTrapped(page, popover);
    // Space ticks the focused category, as with a mouse.
    await page.keyboard.press('Space');
    await expect(popover.locator('input:checked')).toHaveCount(1);
    await expectEscapeRestores(page, popover, disclosure);
});

test('the request and contact dialogs, with errors tied to their fields', async ({ page, browser }) => {
    test.setTimeout(120_000);
    await page.goto(await publicProjectPath(browser, `Tastatur ${uniqueSuffix()}`));

    const card = page.getByTestId('card_request').first();
    await card.focus();
    await page.keyboard.press('Enter');
    const requestDialog = page.getByTestId('request-dialog').getByRole('dialog');
    await expectTrapped(page, requestDialog);
    await expectEscapeRestores(page, requestDialog, card);

    const contact = page.getByTestId('btn_contact_project-detail');
    await contact.focus();
    await page.keyboard.press('Enter');
    const contactDialog = page.getByTestId('contact-dialog').getByRole('dialog');
    await expectTrapped(page, contactDialog);
    await page.getByTestId('btn_send_contact-dialog').click();
    const email = page.getByTestId('input_contact-email');
    await expect(email).toHaveAttribute('aria-invalid', 'true');
    await expect(email).toHaveAccessibleDescription('Gib eine E-Mail-Adresse ein');
    await expect(page.getByTestId('input_contact-msg')).toHaveAccessibleDescription('Bitte schreibe eine Nachricht');
    await expectEscapeRestores(page, contactDialog, contact);
});

test('the card menu, the request editor and the wizard, by keyboard', async ({ page }) => {
    test.setTimeout(120_000);
    await registerFreshUser(page);
    const wizard = new ProjectWizardPage(page);
    await wizard.goto();

    // Errors are tied to their fields, the rich-text editor included.
    await wizard.clickNext();
    await expect(page.getByTestId('input_project-title')).toHaveAccessibleDescription('Gib einen Titel ein');
    await expect(page.getByTestId('input_project-title')).toHaveAttribute('aria-invalid', 'true');
    const description = page.getByTestId('input_project-description').getByRole('textbox');
    await expect(description).toHaveAttribute('aria-invalid', 'true');
    await expect(description).toHaveAccessibleDescription(/Gib eine Beschreibung ein/);

    const title = `Tastaturmenü ${uniqueSuffix()}`;
    await wizard.fillStepOne(title, 'Ein Ziel.', 'Eine Beschreibung.');
    await wizard.advanceTo(1);
    await wizard.advanceTo(2);

    // The request editor: Escape is "Abbrechen" — a clean form just closes, a dirty one asks first.
    const create = page.getByTestId('btn_create_requests-step');
    await create.focus();
    await page.keyboard.press('Enter');
    const editor = page.getByTestId('edit-request-dialog').getByRole('dialog');
    await expectTrapped(page, editor);
    await page.keyboard.press('Escape');
    await expect(editor).toBeHidden();
    await expect(create).toBeFocused();
    await create.press('Enter');
    await page.getByTestId('input_request-title').fill('Halb fertig');
    const asked: string[] = [];
    page.once('dialog', (dialog) => {
        asked.push(dialog.message());
        void dialog.dismiss();
    });
    await page.getByTestId('input_request-title').press('Escape');
    await expect.poll(() => asked).toEqual(['Willst Du wirklich abbrechen? Dein Gesuch wird nicht gespeichert.']);
    await expect(editor).toBeVisible();
    page.once('dialog', (dialog) => void dialog.accept());
    await page.getByTestId('input_request-title').press('Escape');
    await expect(editor).toBeHidden();

    await wizard.advanceTo(3);
    await wizard.next.click();
    await expect(page).toHaveURL(/\/user\/projects$/);

    // My Projects' card menu: Enter opens on the first item, arrows move, Escape returns to the button, Tab closes.
    const myProjects = new MyProjectsPage(page);
    await expect(myProjects.card(title)).toBeVisible();
    const trigger = myProjects.card(title).getByTestId('menu_edit-project-card');
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(myProjects.card(title).getByTestId('menuitem-0')).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(myProjects.card(title).getByTestId('menuitem-1')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(myProjects.card(title).getByTestId('menuitem-0')).toBeHidden();
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await expect(myProjects.card(title).getByTestId('menuitem-0')).toBeHidden();
});

test('the avatar dialog, the password toggle and the focus indicator', async ({ page }) => {
    await registerFreshUser(page);
    await page.goto('/user/profile');
    const edit = page.getByTestId('btn_edit-avatar_settings-page');
    await edit.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByTestId('avatar-dialog').getByRole('dialog');
    await expectTrapped(page, dialog);
    await expect(dialog.getByLabel('Bild auswählen')).toBeAttached();
    for (const name of ['Bild drehen', 'Vergrößern', 'Verkleinern']) {
        await expect(dialog.getByRole('button', { name })).toBeAttached();
    }
    await expectEscapeRestores(page, dialog, edit);

    // A keyboard-focused link draws the A-7 outline; a mouse click does not.
    const vcard = page.getByTestId('link_vcard_settings-page');
    await vcard.focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    await expect(vcard).toBeFocused();
    expect(await vcard.evaluate((element) => getComputedStyle(element).outlineStyle)).toBe('solid');

    // The password field's eye toggle is reachable and works from the keyboard.
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByTestId('input_login-password').fill('geheim');
    await page.getByTestId('input_login-password').press('Tab');
    const toggle = page.getByRole('button', { name: 'Passwort anzeigen' });
    await expect(toggle).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('input_login-password')).toHaveAttribute('type', 'text');
});
