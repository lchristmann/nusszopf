import { type Locator, type Page, test, expect } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { registerFreshUser } from '../../support/session';
import { createPublicProject } from '../../support/projects';
import { uniqueSuffix } from '../../support/env';

/**
 * The counter renders as four separate digit boxes
 * (`resources/views/livewire/projects/project-detail.blade.php`), not one
 * text node — `toHaveText` would otherwise compare against Blade's own
 * template whitespace between them.
 */
function counter(page: Page): Locator {
    return page.getByTestId('visitor-counter_project-detail');
}

async function digits(page: Page): Promise<string> {
    return (await counter(page).locator('p').allTextContents()).join('');
}

/**
 * `VisitorCounter` / `updateViews` (`pages/projects/[id].js`) — BUG-001's
 * fix: the counter is incremented server-side only, once per browser,
 * excluding the owner (docs/rewrite/master-roadmap.md, slice 5).
 */
test('counts a visitor once per browser, never the owner', async ({ page, browser }) => {
    const suffix = uniqueSuffix();
    const title = `Zähler-Projekt ${suffix}`;
    await registerFreshUser(page);
    await createPublicProject(page, { title });

    const myProjects = new MyProjectsPage(page);
    await myProjects.card(title).click();
    await page.waitForURL(/\/user\/project\/.+\/edit$/);
    const editUrl = new URL(page.url());
    const detailUrl = new URL(`/projects/${editUrl.pathname.split('/').at(-2)}`, editUrl.origin).toString();

    // The owner's own views are never counted.
    await page.goto(detailUrl);
    await expect(counter(page)).toBeVisible();
    expect(await digits(page)).toBe('0000');
    await page.goto(detailUrl);
    expect(await digits(page)).toBe('0000');

    // A guest's first visit counts once...
    const guestContext = await browser.newContext();
    const guestPage = await guestContext.newPage();
    await guestPage.goto(detailUrl);
    expect(await digits(guestPage)).toBe('0001');

    // ...and a second visit from the same browser does not count again.
    await guestPage.goto(detailUrl);
    expect(await digits(guestPage)).toBe('0001');
    await guestContext.close();
});
