import { test, expect } from '@playwright/test';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { RequestDialogPage } from '../../pages/RequestDialogPage';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { SearchPage } from '../../pages/SearchPage';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

/**
 * Journey 3 completed (docs/journeys/README.md; docs/rewrite/third-slice.md):
 * create a project with requests in the wizard -> see them on the detail
 * page -> edit, add and delete requests on the edit screen -> the private
 * project's requests are never visible to a visitor -> delete the project.
 * Against the real Compose stack (PostgreSQL, Redis, Meilisearch, queue worker).
 */
test('creates requests in the wizard and shows, edits and deletes them through the project', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const title = `Streuobstwiese ${suffix}`;
    const searchWord = `Baumschnittleiter${suffix.replace(/\W/g, '')}`;
    await registerFreshUser(page);
    const wizard = new ProjectWizardPage(page);
    const requests = new RequestDialogPage(page);
    const edit = new ProjectEditPage(page);
    const myProjects = new MyProjectsPage(page);

    // --- Wizard step 3 -------------------------------------------------------------------
    await wizard.goto();
    await wizard.fillStepOne(title, 'Alte Obstsorten erhalten.', 'Wir pflegen eine Wiese.');
    await wizard.advanceTo(1);
    await wizard.advanceTo(2);
    await expect(page.getByText('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')).toBeVisible();
    await expect(page.getByTestId('btn_create_requests-step')).toBeEnabled();

    // The dialog validates with the historical copy and cannot be dismissed by clicking outside.
    await page.getByTestId('btn_create_requests-step').click();
    await expect(requests.dialog).toBeVisible();
    await requests.submit.click();
    await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
    await expect(page.getByText('Wähle eine Kategorie aus')).toBeVisible();
    await expect(page.getByText('Gib eine Beschreibung ein')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(requests.dialog).toBeVisible();

    // The title input caps at 30 characters, the category select takes its color.
    await requests.title.fill('x'.repeat(35));
    await expect(requests.title).toHaveValue('x'.repeat(30));
    await requests.category.selectOption('materials');
    await expect(requests.category.locator('xpath=ancestor::div[contains(@class,"nz-select-stone")][1]')).toHaveClass(/bg-turquoise-200/);

    // Cancelling a dirty dialog asks the historical native confirm().
    const messages: string[] = [];
    page.once('dialog', (dialog) => {
        messages.push(dialog.message());
        void dialog.dismiss();
    });
    await requests.cancel.click();
    await expect.poll(() => messages).toEqual(['Willst Du wirklich abbrechen? Dein Gesuch wird nicht gespeichert.']);
    await expect(requests.dialog).toBeVisible();
    page.once('dialog', (dialog) => void dialog.accept());
    await requests.cancel.click();
    await expect(requests.dialog).toBeHidden();
    await expect(page.getByText('Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen.')).toBeVisible();

    await page.getByTestId('btn_create_requests-step').click();
    await requests.create('Leitern', 'materials', `Wir brauchen ${searchWord}.`);
    await page.getByTestId('btn_create_requests-step').click();
    await requests.create('Mitstreiter:innen gesucht', 'companions', 'Wer hilft beim Mähen?');

    await expect(page.getByText('Erstellte Gesuche')).toBeVisible();
    await expect(requests.cards).toHaveCount(2);
    await expect(requests.card('Leitern')).toHaveClass(/bg-turquoise-200/);
    await expect(requests.card('Mitstreiter:innen gesucht')).toHaveClass(/bg-red-200/);
    await expect(requests.card('Leitern')).toContainText('Erstellt am');

    // Edit one from its card, delete the other through the menu — locally, no confirmation.
    await requests.card('Leitern').getByRole('button').first().click();
    await expect(requests.title).toHaveValue('Leitern');
    await expect(requests.submit).toHaveText('Speichern');
    await requests.title.fill('Baumleitern');
    await requests.submit.click();
    await expect(requests.dialog).toBeHidden();
    await expect(requests.card('Baumleitern')).toBeVisible();
    await requests.menuItem('Mitstreiter:innen gesucht', 1);
    await expect(requests.cards).toHaveCount(1);

    // The step keeps the request when going back and forth (one shared form state).
    await wizard.advanceTo(3);
    await wizard.back.click();
    await expect(requests.card('Baumleitern')).toBeVisible();
    await requests.menuItem('Baumleitern', 0);
    await requests.description.click();
    await page.keyboard.type(' Bitte bis Mai.');
    await requests.submit.click();
    await wizard.advanceTo(3);
    await wizard.next.click();
    await expect(page).toHaveURL(/\/user\/projects$/);
    await expect(page.getByText('Projekt wurde erstellt.')).toBeVisible();

    // --- Detail page: the request as a card and in its dialog ---------------------------------
    await myProjects.openProject(title);
    const projectId = new URL(page.url()).pathname.split('/').at(-1)!;
    await expect(page.getByText('Projektgesuche')).toBeVisible();
    await expect(requests.cards).toHaveCount(1);
    await expect(requests.card('Baumleitern')).toHaveClass(/bg-turquoise-200/);
    await requests.card('Baumleitern').click();
    const view = page.getByTestId('request-dialog');
    await expect(view).toBeVisible();
    await expect(view.getByTestId('title_request-dialog')).toHaveText('Baumleitern');
    await expect(view.getByTestId('description_request-dialog')).toContainText(`Wir brauchen ${searchWord}. Bitte bis Mai.`);
    // Default wizard contact is "Über Nusszopf" (no personal contact chosen): the button opens the
    // contact form instead of a mailto link — the mail slice's own journey covers submitting it.
    await view.getByTestId('btn_contact_request-dialog').click();
    await expect(page.getByTestId('contact-dialog')).toBeVisible();
    await expect(page.getByTestId('contact-dialog').getByTestId('input_contact-msg')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('contact-dialog')).toBeHidden();
    await requests.card('Baumleitern').click();
    await view.getByRole('button', { name: 'Schließen' }).last().click();
    await expect(view).toBeHidden();

    // --- Search finds the project through its request's text ----------------------------------------
    const visitor = await browser.newContext();
    const visitorPage = await visitor.newPage();
    const search = new SearchPage(visitorPage);
    await search.goto();
    await expect(async () => {
        await search.search(searchWord);
        await expect(search.resultLink(title)).toHaveCount(1);
    }).toPass({ timeout: 30_000 });

    // --- Edit screen: Gesuche view ------------------------------------------------------------------
    await page.goto(`/user/project/${projectId}/edit`);
    await edit.selectView('Gesuche');
    await expect(page.getByText('Aktuelle Gesuche')).toBeVisible();
    await expect(requests.card('Baumleitern')).toBeVisible();

    await page.getByTestId('btn_create_requests-view').click();
    await requests.create('Werkstatt', 'rooms', 'Ein trockener Raum.');
    await expect(page.getByText('Gesuch wurde erstellt.')).toBeVisible();
    await expect(requests.cards).toHaveCount(2);
    await expect(requests.cards.first()).toContainText('Werkstatt'); // newest first
    await expect(requests.card('Werkstatt')).toHaveClass(/bg-yellow-200/);

    await requests.menuItem('Werkstatt', 0);
    await expect(requests.title).toHaveValue('Werkstatt');
    await requests.title.fill('Lagerraum');
    await requests.submit.click();
    await expect(page.getByText('Gesuch wurde aktualisiert.')).toBeVisible();
    await expect(requests.card('Lagerraum')).toBeVisible();

    // Journey 3 "Update": the edited request title shows on the My Projects card's preview too.
    await myProjects.goto();
    await expect(myProjects.card(title).getByTestId('text_title_preview-request-card')).toContainText(['Lagerraum']);
    await page.goto(`/user/project/${projectId}/edit`);
    await edit.selectView('Gesuche');

    // Saving unchanged just closes; invalid input keeps the dialog open.
    await requests.menuItem('Lagerraum', 0);
    await requests.title.fill('');
    await requests.submit.click();
    await expect(page.getByText('Gib einen Titel ein')).toBeVisible();
    await expect(requests.dialog).toBeVisible();
    page.once('dialog', (dialog) => void dialog.accept());
    await requests.cancel.click();
    await expect(requests.dialog).toBeHidden();
    await expect(requests.card('Lagerraum')).toBeVisible();

    // Delete: the native confirm first, declining keeps it.
    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe('Möchtest Du das Gesuch wirklich löschen?');
        void dialog.dismiss();
    });
    await requests.menuItem('Lagerraum', 1);
    await expect(requests.card('Lagerraum')).toBeVisible();
    page.once('dialog', (dialog) => void dialog.accept());
    await requests.menuItem('Lagerraum', 1);
    await expect(page.getByText('Gesuch wurde gelöscht.')).toBeVisible();
    await expect(requests.card('Lagerraum')).toHaveCount(0);

    page.once('dialog', (dialog) => void dialog.accept());
    await requests.menuItem('Baumleitern', 1);
    await expect(page.getByText('Alles zopfig! Derzeit gibt es keine Gesuche.')).toBeVisible();

    // --- A private project's requests are never visible to a visitor -----------------------------------
    await page.getByTestId('btn_create_requests-view').click();
    await requests.create('Geheimes Gesuch', 'others', 'Nicht öffentlich.');
    await edit.selectView('Einstellungen');
    await wizard.choosePrivate();
    await edit.saveSettings();
    await expect(page.getByText('Projekt wurde aktualisiert.').first()).toBeVisible();

    const response = await visitorPage.goto(`/projects/${projectId}`);
    expect(response?.status()).toBe(404);
    await expect(visitorPage.getByText('Geheimes Gesuch')).toHaveCount(0);

    await page.goto(`/projects/${projectId}`);
    await expect(page.getByText('Das Projekt ist gerade nur für dich sichtbar!')).toBeVisible();
    await expect(requests.card('Geheimes Gesuch')).toBeVisible();

    // --- Delete the project; its requests go with it -----------------------------------------------------
    await page.goto(`/user/project/${projectId}/edit`);
    await edit.selectView('Einstellungen');
    page.once('dialog', (dialog) => void dialog.accept());
    await edit.deleteProject();
    await expect(page).toHaveURL(/\/user\/projects$/);
    expect((await page.goto(`/projects/${projectId}`))?.status()).toBe(404);

    await visitor.close();
});

test.describe('on a phone', () => {
    test.use({ viewport: { width: 375, height: 700 } });

    test('shows the request dialog full-screen and keeps every request screen inside the viewport', async ({ page }) => {
        await registerFreshUser(page);
        const wizard = new ProjectWizardPage(page);
        const requests = new RequestDialogPage(page);

        await wizard.goto();
        await wizard.fillStepOne(`Phone ${uniqueSuffix()}`, 'Ziel', 'Text');
        await wizard.advanceTo(1);
        await wizard.advanceTo(2);

        const overflows = () => page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(await overflows()).toBe(false);

        await page.getByTestId('btn_create_requests-step').click();
        await expect(requests.dialog).toBeVisible();
        const box = await requests.dialog.getByRole('dialog').locator('> div').boundingBox();
        expect(box?.width).toBeCloseTo(375, 0);
        expect(box?.height).toBeGreaterThanOrEqual(700);
        await requests.fill('Ein sehr langer Titel für das Gesuch', 'financials', 'Geld für Saatgut.');
        await requests.submit.click();
        await expect(requests.card('Ein sehr langer')).toBeVisible();
        expect(await overflows()).toBe(false);
    });
});
