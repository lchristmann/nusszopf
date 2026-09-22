import { test, expect } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { registerFreshUser } from '../../support/session';
import { createPublicProject } from '../../support/projects';
import { uniqueSuffix } from '../../support/env';

/**
 * `pages/user/projects.js`'s per-card actions (`EditProjectCard`), deferred
 * by the second slice to this one (docs/rewrite/master-roadmap.md, slice 5):
 * publish/hide and delete straight from the grid, without going through the
 * edit screen.
 */
test('publishes and hides a project from the grid', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const title = `Gartenprojekt ${suffix}`;
    await registerFreshUser(page);
    await createPublicProject(page, { title });

    const myProjects = new MyProjectsPage(page);

    // Find the project's own detail URL by opening it once from the grid.
    await myProjects.card(title).click();
    await page.waitForURL(/\/user\/project\/.+\/edit$/);
    const editUrl = new URL(page.url());
    const detailUrl = new URL(`/projects/${editUrl.pathname.split('/').at(-2)}`, editUrl.origin).toString();
    await myProjects.goto();

    // Hide it through the grid's own menu — not the edit screen. `EditProjectCard`
    // marks visibility with the Eye/EyeOff icon only, no text label — `EyeOff`'s
    // is the only one of the two with a diagonal strike-through `<line>`.
    const eyeOffLine = myProjects.card(title).locator('svg line');
    await myProjects.toggleVisibility(title);
    await expect(eyeOffLine).toBeVisible();
    // The shared `updateProject` toast (`ProjectEdit::saveSettings()`'s own), not a distinct one.
    await expect(page.getByText('Projekt wurde aktualisiert.')).toBeVisible();

    const anonymousContext = await browser.newContext();
    const anonymousPage = await anonymousContext.newPage();
    expect((await anonymousPage.goto(detailUrl))?.status()).toBe(404);

    // Publish it again from the grid — outside the 1/second throttle window
    // (`MyProjects::toggleVisibility()` hard-drops a second call inside the
    // window, a documented deviation from lodash.throttle's actual default —
    // docs/rewrite/fifth-slice.md, decision 3), so this second toggle isn't
    // itself the one the throttle is meant to drop.
    await page.waitForTimeout(1100);
    await myProjects.toggleVisibility(title);
    await expect(eyeOffLine).toHaveCount(0);

    expect((await anonymousPage.goto(detailUrl))?.status()).toBe(200);
    await anonymousContext.close();
});

test('deletes a project from the grid', async ({ page }) => {
    const suffix = uniqueSuffix();
    const title = `Löschprojekt ${suffix}`;
    await registerFreshUser(page);
    await createPublicProject(page, { title });

    const myProjects = new MyProjectsPage(page);
    await expect(myProjects.card(title)).toBeVisible();

    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe('Möchtest Du das Projekt wirklich löschen?');
        void dialog.accept();
    });
    await myProjects.deleteFromGrid(title);

    await expect(page.getByText('Das Projekt wurde gelöscht.')).toBeVisible();
    await expect(myProjects.card(title)).toHaveCount(0);
});
