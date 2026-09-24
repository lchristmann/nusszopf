import { test, expect, type Page } from '@playwright/test';
import { MyProjectsPage } from '../../pages/MyProjectsPage';
import { createPublicProject } from '../../support/projects';
import { registerFreshUser } from '../../support/session';
import { uniqueSuffix } from '../../support/env';

/**
 * P-6, PERF-01 (docs/release/parity/P-06-performance.md): the rich-text editor (TipTap) and the avatar cropper are
 * chunks of their own, fetched only where they are used. They are ~98 % of the JavaScript, and the historical
 * Next.js app split its code per page, so a page without an editor never loaded one.
 */
/** Marker strings each library's minified code keeps. */
const LIBRARY_MARKERS = { tiptap: 'ProseMirror', cropper: 'cropper-container' };

/**
 * Which of the two libraries the page's own scripts contained while `visit` ran, whether they came as their own chunk
 * or folded into another bundle.
 */
async function librariesDuring(page: Page, visit: () => Promise<void>): Promise<string[]> {
    const bodies: Promise<string>[] = [];
    const listener = (response: import('@playwright/test').Response) => {
        if (/\/build\/assets\/[^/]+\.js$/.test(response.url())) bodies.push(response.text().catch(() => ''));
    };
    page.on('response', listener);
    await visit();
    await page.waitForLoadState('load');
    page.off('response', listener);
    const code = (await Promise.all(bodies)).join('\n');

    return Object.entries(LIBRARY_MARKERS).filter(([, marker]) => code.includes(marker)).map(([library]) => library);
}

test('pages without an editor or a cropper never fetch them; the wizard fetches only the editor', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const title = `Leicht ${uniqueSuffix()}`;
    const context = await browser.newContext();
    const owner = await context.newPage();
    await registerFreshUser(owner);
    await createPublicProject(owner, { title });
    await new MyProjectsPage(owner).openProject(title);
    const projectPath = new URL(owner.url()).pathname;
    await context.close();

    for (const path of ['/', '/search', projectPath, '/login']) {
        expect(await librariesDuring(page, () => page.goto(path).then(() => undefined)), path).toEqual([]);
    }

    await registerFreshUser(page);
    expect(await librariesDuring(page, () => page.goto('/user/profile').then(() => undefined)), 'profile').toEqual([]);
    const wizard = await librariesDuring(page, async () => {
        await page.goto('/user/project/create?step=0');
        await expect(page.getByTestId('input_project-description').getByRole('textbox')).toBeVisible();
    });
    expect(wizard).toContain('tiptap');
    expect(wizard).not.toContain('cropper');
});
