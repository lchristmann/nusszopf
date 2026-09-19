import { test, expect } from '@playwright/test';
import { LoginPage } from '../../pages/LoginPage';
import { ProjectWizardPage } from '../../pages/ProjectWizardPage';
import { ProjectEditPage } from '../../pages/ProjectEditPage';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { SearchPage } from '../../pages/SearchPage';
import { uniqueSuffix, testUsername, testEmail, TEST_PASSWORD } from '../../support/env';

/**
 * The complete first vertical slice journey
 * (docs/rewrite/first-slice.md's acceptance criteria, all of them, in one
 * pass): register -> log in immediately -> create a private project ->
 * view it as the owner -> publish it -> view it as an anonymous visitor
 * -> find it in search. Does not mock any part of this path
 * (CLAUDE.md, §16: "Do not mock the core application path").
 */
test('register, create, publish, view, and find a project in search', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const username = testUsername(suffix);
    const email = testEmail(suffix);
    const projectTitle = `E2E Testprojekt ${suffix}`;

    const login = new LoginPage(page);
    const wizard = new ProjectWizardPage(page);
    const editPage = new ProjectEditPage(page);
    const myProjects = new MyProjectsPage(page);

    // 1. Register -> logged in immediately, no email-verification gate.
    await login.goto();
    await login.register(username, email, TEST_PASSWORD);
    await expect(page).toHaveURL(/\/user\/projects$/);

    // 2. Create exactly one project, through the historical wizard. The
    //    wizard defaults to public; this journey starts private, so it picks it.
    await wizard.goto();
    await wizard.fillStepOne(
        projectTitle,
        'Ein Ziel für das End-to-End-Testprojekt.',
        'Eine ausführliche Beschreibung des End-to-End-Testprojekts.',
    );
    await wizard.advanceTo(1);
    await wizard.advanceTo(2);
    await wizard.advanceTo(3);
    await wizard.choosePrivate();
    await wizard.clickNext();
    await expect(page).toHaveURL(/\/user\/projects$/);
    await expect(page.getByText(projectTitle)).toBeVisible();
    await expect(page.getByText('Privat').first()).toBeVisible();

    // 3. The owner can view their own private project.
    await myProjects.editFirstProject();
    const editUrl = new URL(page.url());
    const projectId = editUrl.pathname.split('/').at(-2);
    const projectUrl = new URL(`/projects/${projectId}`, editUrl.origin).toString();

    await page.goto(projectUrl);
    await expect(page.getByRole('heading', { name: projectTitle })).toBeVisible();

    // 4. A different, anonymous visitor gets a hard 404 — not the content.
    const anonymousContext = await browser.newContext();
    const anonymousPage = await anonymousContext.newPage();
    const anonymousResponse = await anonymousPage.goto(projectUrl);
    expect(anonymousResponse?.status()).toBe(404);
    await expect(anonymousPage.getByText(projectTitle)).toHaveCount(0);
    await anonymousContext.close();

    // 5. Publish: toggle visibility to public through the same edit form.
    await myProjects.goto();
    await myProjects.editFirstProject();
    await editPage.selectView('Einstellungen');
    await wizard.choosePublic();
    await editPage.saveSettings();
    await expect(page.getByText('Projekt wurde aktualisiert.')).toBeVisible();

    // 6. Once public, an anonymous visitor can view it.
    const publicContext = await browser.newContext();
    const publicPage = await publicContext.newPage();
    const publicResponse = await publicPage.goto(projectUrl);
    expect(publicResponse?.status()).toBe(200);
    await expect(publicPage.getByRole('heading', { name: projectTitle })).toBeVisible();

    // 7. The published project appears in search (real Meilisearch,
    //    indexed asynchronously via the queue worker).
    const search = new SearchPage(publicPage);
    await search.goto();
    await expect(async () => {
        await search.search(projectTitle);
        await expect(search.resultLink(projectTitle)).toBeVisible();
    }).toPass({ timeout: 15_000 });

    await publicContext.close();
});
