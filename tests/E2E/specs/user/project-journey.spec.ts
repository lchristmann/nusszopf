import { test, expect } from '@playwright/test';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { SearchPage } from '../../pages/SearchPage';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

/**
 * The complete historical Project journey (docs/rewrite/second-slice.md, §E2E):
 * authenticated user -> start creation -> four steps -> publish -> detail ->
 * edit -> modify -> save -> verify — against the real Compose stack
 * (PostgreSQL, Redis, Meilisearch, queue worker). Only LocationIQ is stood in
 * for, by the stack's own `locationiq-stub` service (docs/testing/README.md).
 */
test('creates, publishes, edits and re-verifies a project through the historical wizard', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const title = `Nachbarschaftsgarten ${suffix}`;
    await registerFreshUser(page);
    const wizard = new ProjectWizardPage(page);
    const edit = new ProjectEditPage(page);
    const myProjects = new MyProjectsPage(page);

    // --- Start creation from My Projects -----------------------------------
    await page.getByTestId('route_create-project_projects-page').first().click();
    await expect(page).toHaveURL(/\/user\/project\/create\?step=0$/);
    await expect(wizard.stepLabel('Beschreibung 1/2')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: 'Neues Projekt' })).toBeVisible();
    await expect(wizard.back).toHaveCount(0);

    // --- Step 1: an invalid step cannot be passed -----------------------------
    await wizard.clickNext();
    await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
    await expect(page.getByText('Gib ein Ziel ein')).toBeVisible();
    await expect(page.getByText('Gib eine Beschreibung ein')).toBeVisible();
    await expect(page.getByText('Gib einen Ort ein')).toBeVisible();
    await expect(page.getByText('Gib ein Startdatum ein')).toBeVisible();
    await expect(wizard.stepLabel('Beschreibung 1/2')).toBeVisible();
    expect(wizard.urlStep()).toBe('0');

    // --- Step 1: fill it in, including the rich-text toolbar -------------------
    await wizard.fillTitle(title);
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await wizard.fillGoal('Eine grüne Fläche für alle schaffen.');

    const description = wizard.editor('input_project-description');
    await description.click();
    await page.getByRole('button', { name: 'Schrift dick' }).click();
    await page.keyboard.type('Gemeinsam');
    await page.getByRole('button', { name: 'Schrift dick' }).click();
    await page.keyboard.type(' legen wir einen Garten an.');
    await page.keyboard.press('Enter');
    await page.getByRole('button', { name: 'Liste ungeordnet' }).click();
    await page.keyboard.type('Beete bauen');
    await expect(description.locator('strong')).toHaveText('Gemeinsam');
    await expect(description.locator('ul > li')).toHaveText('Beete bauen');

    await wizard.chooseLocation('Leip');
    await expect(page.getByTestId('combobox_project-location')).toHaveValue('Leipzig, Sachsen, Deutschland');
    await wizard.fillPeriod('1.3.2027', '31.5.2027');

    await wizard.clickNext();
    await expect(page).toHaveURL(/\/user\/project\/create\?step=1$/);
    await expect(wizard.stepLabel('Beschreibung 2/2')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    // --- Step 2: team and motto are optional; going back keeps the state --------
    await wizard.fillEditor('input_project-team', 'Anna und Ben');
    await wizard.fillMotto('Gemeinsam wächst mehr.');
    await wizard.back.click();
    await expect(page).toHaveURL(/\/user\/project\/create\?step=0$/);
    await expect(page.getByTestId('input_project-title')).toHaveValue(title);
    await expect(wizard.editor('input_project-description').locator('strong')).toHaveText('Gemeinsam');
    await expect(page.getByTestId('input_from_project-period')).toHaveValue('1.3.2027');
    await wizard.clickNext();
    await expect(page.getByTestId('input_project-motto')).toHaveValue('Gemeinsam wächst mehr.');
    await wizard.clickNext();

    // --- Step 3: Gesuche (zero requests is a valid path) -------------------------------
    await expect(page).toHaveURL(/\/user\/project\/create\?step=2$/);
    await expect(wizard.stepLabel('Gesuche')).toBeVisible();
    await expect(page.getByText('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')).toBeVisible();
    await wizard.clickNext();

    // --- Step 4: settings; the last step's button reads "Erstellen" ----------------------
    await expect(page).toHaveURL(/\/user\/project\/create\?step=3$/);
    await expect(wizard.stepLabel('Einstellungen')).toBeVisible();
    await expect(wizard.next).toHaveText('Erstellen');
    await expect(page.getByTestId('radio_public_project-visibility')).toBeChecked();
    // "Persönlich" now requires a verified e-mail address (decision A-3,
    // docs/rewrite/seventh-slice.md) — a freshly-registered account is not
    // verified, so this journey keeps the default "Über Nusszopf" contact;
    // the personal-contact path itself, and the new verification gate, are
    // covered by tests/Feature/Projects/ProjectWizardTest.php and
    // tests/Feature/Auth/EmailVerificationTest.php.
    await expect(page.getByTestId('radio_nusszopf_project-contact')).toBeChecked();
    await wizard.clickNext();

    // --- Created: My Projects, success toast, nothing left of the draft ------------------
    await expect(page).toHaveURL(/\/user\/projects$/);
    await expect(page.getByText('Projekt wurde erstellt.')).toBeVisible();
    await expect(page.getByText(title)).toBeVisible();

    // --- Project detail ---------------------------------------------------------------------
    await myProjects.openProject(title);
    const projectId = new URL(page.url()).pathname.split('/').at(-1)!;
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await expect(page.getByText('Eine grüne Fläche für alle schaffen.')).toBeVisible();
    const locationLink = page.getByTestId('location_project-detail').getByRole('link', { name: 'Zu OpenStreetMap' });
    await expect(locationLink).toHaveAttribute('href', 'https://www.openstreetmap.org/relation/62649');
    await expect(locationLink).toHaveText('Leipzig');
    await expect(page.getByTestId('period_project-detail')).toHaveText('1.3.2027 - 31.5.2027');
    await expect(page.getByTestId('description_project-detail').locator('span.font-medium')).toHaveText('Gemeinsam');
    await expect(page.getByTestId('description_project-detail').locator('ul li')).toHaveText('Beete bauen');
    await expect(page.getByTestId('team_project-detail')).toContainText('Anna und Ben');
    await expect(page.getByTestId('motto_project-detail')).toContainText('Gemeinsam wächst mehr.');
    // "Über Nusszopf" opens the in-app dialog, not a `mailto:` link (sixth
    // slice) — the personal-contact `mailto:` rendering itself is covered by
    // tests/Feature/Projects/ProjectDetailContentTest.php.
    await page.getByTestId('btn_contact_project-detail').click();
    await expect(page.getByTestId('contact-dialog')).toBeVisible();
    await page.getByRole('button', { name: 'Schließen' }).click();
    await expect(page.getByTestId('contact-dialog')).toBeHidden();
    await expect(page.getByText('So sieht das Projekt für andere Nusszopfer:innen aus.')).toBeVisible();

    // --- Edit: entered from the owner banner, loaded with the stored values -------------------
    await page.getByRole('link', { name: 'Projekt bearbeiten' }).click();
    await expect(page).toHaveURL(new RegExp(`/user/project/${projectId}/edit$`));
    await expect(edit.viewSelect).toHaveValue('Beschreibung');
    await expect(page.getByTestId('input_project-title')).toHaveValue(title);
    await expect(page.getByTestId('combobox_project-location')).toHaveValue('Leipzig, Sachsen, Deutschland');
    await expect(page.getByTestId('input_from_project-period')).toHaveValue('1.3.2027');
    await expect(page.getByTestId('input_to_project-period')).toHaveValue('31.5.2027');
    await expect(edit.fields.editor('input_project-team')).toContainText('Anna und Ben');
    await expect(page.getByTestId('input_project-motto')).toHaveValue('Gemeinsam wächst mehr.');

    // Whole-form validation on save
    await page.getByTestId('input_project-title').fill('');
    await edit.saveDescription();
    await expect(page.getByText('Gib einen Titel ein')).toBeVisible();

    // --- Edit: modify, save ---------------------------------------------------------------------
    const newTitle = `Gemeinschaftsgarten ${suffix}`;
    await page.getByTestId('input_project-title').fill(newTitle);
    await page.getByTestId('input_to_project-period').fill('30.6.2027');
    await wizard.fillMotto('Zusammen ernten.');
    await wizard.chooseRemote();
    await edit.saveDescription();
    await expect(page.getByText('Projekt wurde aktualisiert.')).toBeVisible();

    // Leaving with unsaved changes asks the historical native confirm(); dismissing stays.
    await wizard.fillMotto('Ungespeichert.');
    const dialogs: string[] = [];
    page.once('dialog', (dialog) => {
        dialogs.push(dialog.message());
        void dialog.dismiss();
    });
    await edit.viewSelect.selectOption('Einstellungen');
    await expect.poll(() => dialogs).toEqual(['Möchtest Du die Seite wirklich verlassen? Deine Änderungen gehen dann verloren.']);
    await expect(edit.viewSelect).toHaveValue('Beschreibung');
    await expect(page.getByTestId('input_project-motto')).toHaveValue('Ungespeichert.');

    // Accepting discards them.
    page.once('dialog', (dialog) => void dialog.accept());
    await edit.viewSelect.selectOption('Einstellungen');
    await edit.expectView('Einstellungen');

    // --- Settings: back to a private project -----------------------------------------------------
    await wizard.choosePrivate();
    await edit.saveSettings();
    await expect(page.getByText('Projekt wurde aktualisiert.').first()).toBeVisible();

    // A private project 404s for everyone but its owner...
    const stranger = await browser.newContext();
    const strangerPage = await stranger.newPage();
    expect((await strangerPage.goto(`/projects/${projectId}`))?.status()).toBe(404);

    // --- ...and public again it is visible and findable in search ----------------------------------
    await wizard.choosePublic();
    await edit.saveSettings();
    await expect(page.getByText('Projekt wurde aktualisiert.').first()).toBeVisible();
    await page.goto(`/projects/${projectId}`);

    await expect(page.getByRole('heading', { level: 1, name: newTitle })).toBeVisible();
    await expect(page.getByTestId('period_project-detail')).toHaveText('1.3.2027 - 30.6.2027');
    await expect(page.getByTestId('location_project-detail')).toHaveText('Ortsunabhängig');
    await expect(page.getByTestId('motto_project-detail')).toContainText('Zusammen ernten.');
    await expect(page.getByTestId('motto_project-detail')).not.toContainText('Ungespeichert.');

    expect((await strangerPage.goto(`/projects/${projectId}`))?.status()).toBe(200);
    await expect(strangerPage.getByRole('heading', { level: 1, name: newTitle })).toBeVisible();

    const search = new SearchPage(strangerPage);
    await search.goto();
    await expect(async () => {
        await search.search(newTitle);
        await expect(search.resultLink(newTitle)).toBeVisible();
    }).toPass({ timeout: 30_000 });

    // --- Delete from the settings view (native confirm) -------------------------------------------------
    await page.goto(`/user/project/${projectId}/edit`);
    await edit.selectView('Einstellungen');
    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe('Möchtest Du das Projekt wirklich löschen?');
        void dialog.accept();
    });
    await edit.deleteProject();
    await expect(page).toHaveURL(/\/user\/projects$/);
    await expect(page.getByText('Das Projekt wurde gelöscht.')).toBeVisible();
    expect((await strangerPage.goto(`/projects/${projectId}`))?.status()).toBe(404);

    await stranger.close();
});
