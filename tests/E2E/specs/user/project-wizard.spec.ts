import { test, expect } from '@playwright/test';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { registerFreshUser } from '../../support/session';

/**
 * The wizard's mechanics in the browser (docs/rewrite/second-slice.md):
 * URL-driven `?step=N`, history navigation, no draft persistence, the
 * authentication boundary, and the rich-text / place-search controls.
 */
test.describe('wizard mechanics', () => {
    test('is behind authentication: a guest is sent to the login screen', async ({ page }) => {
        await page.goto('/user/project/create?step=0');
        await expect(page).toHaveURL(/\/login$/);

        await page.goto('/user/project/00000000-0000-0000-0000-000000000000/edit');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('always starts at step 0, whatever ?step= says', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);

        for (const requested of ['3', '1', 'abc', '-1', '99']) {
            await page.goto(`/user/project/create?step=${requested}`);
            await expect(page).toHaveURL(/\/user\/project\/create\?step=0$/);
            await expect(wizard.stepLabel('Beschreibung 1/2')).toBeVisible();
        }
    });

    test('keeps no draft: a refresh discards everything and returns to step 0', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Entwurf', 'Ziel', 'Beschreibung');
        await wizard.clickNext();
        await expect(page).toHaveURL(/step=1$/);

        await page.reload();
        await expect(page).toHaveURL(/step=0$/);
        await expect(page.getByTestId('input_project-title')).toHaveValue('');
        await expect(page.getByRole('heading', { level: 1, name: 'Neues Projekt' })).toBeVisible();
    });

    test('follows browser back and forward through the steps, keeping the form state', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Zeitreise', 'Ziel', 'Beschreibung');
        await wizard.clickNext();
        await expect(page).toHaveURL(/step=1$/);
        await wizard.fillMotto('Motto bleibt');
        await wizard.clickNext();
        await expect(page).toHaveURL(/step=2$/);

        await page.goBack();
        await expect(page).toHaveURL(/step=1$/);
        await expect(wizard.stepLabel('Beschreibung 2/2')).toBeVisible();
        await expect(page.getByTestId('input_project-motto')).toHaveValue('Motto bleibt');

        await page.goBack();
        await expect(page).toHaveURL(/step=0$/);
        await expect(page.getByTestId('input_project-title')).toHaveValue('Zeitreise');

        await page.goForward();
        await expect(page).toHaveURL(/step=1$/);
        await expect(wizard.stepLabel('Beschreibung 2/2')).toBeVisible();
    });

    test('Enter in a field behaves like Weiter and is gated by the step\'s validation', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();

        await page.getByTestId('input_project-title').press('Enter');
        await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
        await expect(page).toHaveURL(/step=0$/);
    });

    test('shows a field error only after the field was left', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();

        await expect(page.getByText('Gib einen Titel ein')).toHaveCount(0);
        await page.getByTestId('input_project-title').focus();
        await page.getByTestId('input_project-goal').focus();
        await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
        await expect(page.getByText('Gib ein Ziel ein')).toHaveCount(0);
    });

    test('validates the period fields with the historical copy', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Zeitraum', 'Ziel', 'Beschreibung');
        await wizard.fillPeriod('morgen', '1.1.2020');
        await wizard.clickNext();
        await expect(page.getByText('Nicht im Format dd.mm.yyyy')).toBeVisible();

        await wizard.fillPeriod('1.3.2027', '1.1.2027');
        await wizard.clickNext();
        await expect(page.getByText('Enddatum vor Startdatum')).toBeVisible();
        await expect(page).toHaveURL(/step=0$/);

        // A flexible period disables the inputs and is never blocked by their stale values.
        await wizard.chooseFlexiblePeriod();
        await expect(page.getByTestId('input_from_project-period')).toBeDisabled();
        await wizard.clickNext();
        await expect(page).toHaveURL(/step=1$/);
    });

    test('offers exactly the six-tool toolbar and none of the package defaults', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();

        const toolbar = page.getByTestId('input_project-description').getByRole('toolbar');
        await expect(toolbar.getByRole('button')).toHaveCount(6);
        for (const name of ['Schrift dick', 'Schrift kursiv', 'Schrift unterstrich', 'Liste ungeordnet', 'Liste geordnet', 'Verlinkung']) {
            await expect(toolbar.getByRole('button', { name })).toBeVisible();
        }

        // No keyboard shortcuts and no markdown-style input rules, as in Slate.
        const editor = wizard.editor('input_project-description');
        await editor.click();
        await page.keyboard.press('ControlOrMeta+b');
        await page.keyboard.type('kein fett');
        await page.keyboard.press('Enter');
        await page.keyboard.type('- kein Listenpunkt');
        await page.keyboard.press('Enter');
        await page.keyboard.type('# keine Überschrift');
        await expect(editor.locator('strong, ul, ol, h1')).toHaveCount(0);
    });

    test('writes underline, italic, ordered lists and links', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        const editor = wizard.editor('input_project-description');
        await editor.click();

        await page.getByRole('button', { name: 'Schrift unterstrich' }).click();
        await page.keyboard.type('unter');
        await page.getByRole('button', { name: 'Schrift unterstrich' }).click();
        await page.getByRole('button', { name: 'Schrift kursiv' }).click();
        await page.keyboard.type('kursiv');
        await page.getByRole('button', { name: 'Schrift kursiv' }).click();
        await page.keyboard.press('Enter');
        await page.getByRole('button', { name: 'Liste geordnet' }).click();
        await page.keyboard.type('eins');
        await page.keyboard.press('Enter');
        await page.keyboard.type('zwei');

        await expect(editor.locator('u')).toHaveText('unter');
        await expect(editor.locator('em')).toHaveText('kursiv');
        await expect(editor.locator('ol > li')).toHaveText(['eins', 'zwei']);

        // Link: the historical native prompt, then the URL becomes the link text.
        page.once('dialog', (dialog) => {
            expect(dialog.message()).toBe('Gib die URL des Links ein.');
            void dialog.accept('example.org/seite');
        });
        await page.keyboard.press('Enter');
        await page.keyboard.press('Enter'); // leaves the list
        await page.getByRole('button', { name: 'Verlinkung' }).click();
        await expect(editor.locator('a')).toHaveText('example.org/seite');
    });

    test('place search: Enter never submits, arrow keys pick, editing discards the choice, X clears', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Ort', 'Ziel', 'Beschreibung');

        await page.getByTestId('radio_fixed_project-location').locator('xpath=ancestor::label[1]/span').click();
        const combobox = page.getByTestId('combobox_project-location');
        await combobox.fill('Leip');
        await expect(page.getByTestId('option_project-location')).toHaveCount(2);

        // Enter with nothing highlighted must not submit the form.
        await combobox.press('Enter');
        await expect(page).toHaveURL(/step=0$/);

        await combobox.press('ArrowDown');
        await combobox.press('ArrowDown');
        await combobox.press('Enter');
        await expect(combobox).toHaveValue('Leipzig-Land, Sachsen, Deutschland');
        await expect(page.getByTestId('option_project-location')).toHaveCount(0);

        // Editing the text throws the selection away: it must be picked again.
        await combobox.fill('Leipzig-Lan');
        await page.getByTestId('option_project-location').first().waitFor();
        await combobox.press('Escape');
        await wizard.clickNext();
        await expect(page.getByText('Wähle einen Ort aus der Liste aus')).toBeVisible();

        await page.getByRole('button', { name: 'Eingabe löschen' }).click();
        await expect(combobox).toHaveValue('');
        await expect(combobox).toBeFocused();
    });

    test('opens the field info popover', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();

        const info = page.getByRole('button', { name: 'Mehr Informationen' }).first();
        await info.click();
        await expect(page.getByText('Gib deinem Projekt einen Titel.')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByText('Gib deinem Projekt einen Titel.')).toBeHidden();
    });
});

test.describe('edit access', () => {
    test('a non-owner gets a 404, never a 403 or the form', async ({ page, browser }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Fremdes Projekt', 'Ziel', 'Beschreibung');
        await wizard.finishFromStepOne();
        await page.getByTestId('route_edit-project_projects-page').first().click();
        const editUrl = page.url();

        const other = await browser.newContext();
        const otherPage = await other.newPage();
        await registerFreshUser(otherPage);
        const response = await otherPage.goto(editUrl);
        expect(response?.status()).toBe(404);
        await expect(otherPage.getByTestId('input_project-title')).toHaveCount(0);
        await other.close();
    });
});

test.describe('responsive layout', () => {
    test.use({ viewport: { width: 375, height: 800 } });

    test('the wizard is a single column without horizontal scrolling on a phone', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        await wizard.goto();
        await wizard.fillStepOne('Mobil', 'Ziel', 'Beschreibung');

        const title = await page.getByTestId('input_project-title').boundingBox();
        const location = await page.getByTestId('radio_remote_project-location').locator('xpath=ancestor::label[1]').boundingBox();
        expect(location!.y).toBeGreaterThan(title!.y);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        await wizard.clickNext();
        await expect(wizard.stepLabel('Beschreibung 2/2')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
});
