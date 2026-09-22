import { test, expect, type Page } from '@playwright/test';
import { SearchPage } from '../../pages/SearchPage';
import { registerFreshUser } from '../../support/session';
import { createPublicProject } from '../../support/projects';
import { execSync } from 'node:child_process';

/**
 * Journey 5 (docs/journeys/README.md), specified from the implementation because the historical E2E was
 * stubbed out (BUG-018): querying, filtering, and reaching a project's owner from a result — plus the
 * states around them (skeleton, no hits, load more, scroll to top, index recovery).
 * Against the real Compose stack: PostgreSQL, Redis, Meilisearch and the queue worker that syncs the index.
 */
test.describe.configure({ mode: 'serial' });

// Random letters, not a timestamp or a shared part: Meilisearch tolerates typos, so words that differ in a digit or two,
// or by a prefix, would find each other's projects.
function randomLetters(length: number): string {
    return Array.from({ length }, () => 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]).join('');
}

// Set by beforeAll, once per browser project: a project's data must not be shared with the next one's run in the same worker.
// Titles are limited to 40 characters, so the run's word stays short.
let suffix: string;
let word: string;
let rooms: string;
let materials: string;
let plain: string;
// Occurs only in one request's description.
let requestOnly: string;

test.beforeAll(async ({ browser }) => {
    test.setTimeout(240_000);
    suffix = randomLetters(8);
    word = `Sw${suffix}`;
    rooms = `Raumprojekt ${word}`;
    materials = `Materialprojekt ${word}`;
    plain = `Schlichtes Projekt ${word}`;
    requestOnly = `Hb${suffix}`;
    const context = await browser.newContext();
    const page = await context.newPage();
    await registerFreshUser(page);

    await createPublicProject(page, { title: rooms, requests: [
        { title: 'Ein trockener Raum', category: 'rooms', description: 'Zum Werkeln.' },
        { title: 'Mitstreiter gesucht', category: 'companions', description: 'Fürs Mähen.' },
    ] });
    await createPublicProject(page, { title: materials, requests: [{ title: 'Holz und Nägel', category: 'materials', description: `Für ein Hochbeet ${requestOnly}.` }] });
    await createPublicProject(page, { title: plain });

    // The index is synced by the queue worker; wait until all three are findable.
    const visitor = await browser.newContext();
    const search = new SearchPage(await visitor.newPage());
    await search.goto();
    await expect(async () => {
        await search.search(word);
        await expect(search.cards).toHaveCount(3);
        // Each project's own document has been replaced by its requests' documents.
        await expect(search.requestsOf(rooms)).toHaveCount(2);
    }).toPass({ timeout: 60_000 });
    await visitor.close();
    await context.close();
});

test('finds projects by their own text and by their requests, grouped, with the matches highlighted', async ({ page }) => {
    const search = new SearchPage(page);
    await search.goto();

    // Opens on the results of the empty query, not on an empty screen.
    await expect(search.cards.first()).toBeVisible();

    await search.search(word);
    await search.expectResults([rooms, materials, plain]);
    // The card nests the request that matched — the project's text matched, so both of its requests did.
    await expect(search.requestsOf(rooms)).toHaveCount(2);
    await expect(search.requestsOf(plain)).toHaveCount(0);
    await expect(search.resultLink(materials).getByTestId('route_title_hitcard').locator('em')).toHaveText(word);

    // A term that only occurs in a request finds the project and shows just that request.
    await search.search(requestOnly);
    await search.expectResults([materials]);
    await expect(search.requestsOf(materials)).toContainText('Holz und Nägel');

    // Nothing found: the no-hits section, with the way to start a project.
    await search.search(`Nichtvorhanden${suffix}`);
    await expect(search.noHits).toBeVisible();
    await expect(search.noHits).toContainText('Verzopft, wir konnten leider nichts zu deiner Suche finden!');
    await expect(search.noHits.getByRole('link', { name: 'Projekt starten' })).toHaveAttribute('href', /\/user\/project\/create$/);
    await expect(search.cards).toHaveCount(0);
});

test('a query runs only on submit, and the input takes at most 30 characters', async ({ page }) => {
    const search = new SearchPage(page);
    await search.goto();

    await expect(search.cards.first()).toBeVisible();
    await search.input.fill(`Nichtvorhanden${suffix}`);
    // Typing alone searches nothing: the results of the empty query stay.
    await page.waitForTimeout(1000);
    await expect(search.noHits).toBeHidden();
    await expect(search.cards.first()).toBeVisible();
    await search.searchButton.click();
    await expect(search.noHits).toBeVisible();

    await search.input.fill(word);
    await search.searchButton.click();
    await expect(search.resultLink(rooms)).toHaveCount(1);

    await search.input.fill('x'.repeat(40));
    await expect(search.input).toHaveValue('x'.repeat(30));

    // The clear button empties the field without searching.
    await page.getByRole('button', { name: 'Suchfeld leeren' }).click();
    await expect(search.input).toHaveValue('');
    await expect(search.resultLink(rooms)).toHaveCount(1);
    await expect(search.noHits).toBeHidden();
});

test('filters by request category, applied together with the query', async ({ page }) => {
    const search = new SearchPage(page);
    await search.goto();
    await search.search(word);
    await search.expectResults([rooms, materials, plain]);

    await search.openFilter();
    for (const [option, label] of [['companions', 'Mitstreiter:innen'], ['rooms', 'Räume'], ['materials', 'Materialien'], ['financials', 'Finanzielles'], ['others', 'Sonstiges'], ['none', 'Keine Gesuche']] as const) {
        await expect(search.option(option)).toBeAttached();
        await expect(page.getByLabel(label)).toBeAttached();
    }

    // Picking only marks the pending change: the icon asks for a refresh, the results stay.
    await search.toggle('rooms');
    await expect(page.getByTestId('icon_refresh_search-input')).toBeVisible();
    await search.expectResults([rooms, materials, plain]);

    await search.searchButton.click();
    await search.expectResults([rooms]);
    await expect(page.getByTestId('icon_refresh_search-input')).toBeHidden();
    // Only the request of the checked category is nested.
    await expect(search.requestsOf(rooms)).toHaveCount(1);
    await expect(search.requestsOf(rooms)).toContainText('Ein trockener Raum');
    await expect(page).toHaveURL(/f%5B0%5D=rooms|f\[0\]=rooms/);

    // "Keine Gesuche" adds the projects without requests.
    await search.toggle('none');
    await search.searchButton.click();
    await search.expectResults([rooms, plain]);

    // Unchecking everything filters nothing again.
    await search.toggle('rooms');
    await search.toggle('none');
    await search.searchButton.click();
    await search.expectResults([rooms, materials, plain]);

    // The filter survives a reload (deep link).
    await search.toggle('materials');
    await search.searchButton.click();
    await search.expectResults([materials]);
    await page.reload();
    await search.results.waitFor();
    await search.expectResults([materials]);
    await expect(search.option('materials')).toBeChecked();
});

test('the filter popover opens on click, closes on Escape and on an outside click', async ({ page }) => {
    const search = new SearchPage(page);
    await search.goto();

    await expect(search.option('rooms')).toBeHidden();
    await search.openFilter();
    await expect(search.option('rooms')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(search.option('rooms')).toBeHidden();

    await search.openFilter();
    await page.getByRole('heading', { name: 'Ideen und Projekte aus dem Nusswerk' }).click();
    await expect(search.option('rooms')).toBeHidden();
});

test('shows the skeleton until the first results have loaded', async ({ page }) => {
    await page.route(/livewire.*\/update/, async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 1200));
        await route.continue();
    });
    await page.goto('/search');

    const search = new SearchPage(page);
    await expect(search.skeleton).toBeVisible();
    await expect(search.skeleton).toBeHidden();
    await expect(search.cards.first()).toBeVisible();
});

test('opens a project from a result, where its owner can be contacted', async ({ page }) => {
    const search = new SearchPage(page);
    await search.goto();
    await search.search(word);
    // Settled on this query's results, so the click cannot land on a card that is about to be replaced.
    await search.expectResults([rooms, materials, plain]);

    await search.resultLink(rooms).click();
    await expect(page).toHaveURL(/\/projects\/[0-9a-f-]{36}$/);
    await expect(page.getByRole('heading', { name: rooms })).toBeVisible();
    // The contact path (BUG-018's third journey): the button opens the dialog; submitting it and
    // receiving the mail is covered end to end by `visitor/project-detail.spec.ts`'s own contact test.
    await page.getByTestId('btn_contact_project-detail').click();
    await expect(page.getByTestId('contact-dialog')).toBeVisible();
});

test('scrolls back to the top from the floating button', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 500 });
    const search = new SearchPage(page);
    await search.goto();
    await search.search(word);
    await search.expectResults([rooms, materials, plain]);

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);
    await expect(search.scrollTop).toBeVisible();
    await search.scrollTop.click();
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
});

test('loads more results a page at a time until there are no more', async ({ page, browser }) => {
    const pageSize = Number(process.env.E2E_SEARCH_PAGE_SIZE ?? 0);
    test.skip(pageSize < 1, 'Needs the stack to run with SEARCH_PAGE_SIZE=5 and E2E_SEARCH_PAGE_SIZE=5 (docs/testing/README.md).');
    test.setTimeout(240_000);

    // One project with one document more than fits on a page: a request is a document of its own.
    const many = `Vw${randomLetters(8)}`;
    const title = `Vielfaches ${randomLetters(8)}`;
    const owner = await browser.newContext();
    const ownerPage = await owner.newPage();
    await registerFreshUser(ownerPage);
    await createPublicProject(ownerPage, {
        title,
        description: many,
        requests: Array.from({ length: pageSize + 1 }, (_, index) => ({ title: `Gesuch Nr. ${index + 1}`, category: 'others' as const, description: 'Etwas.' })),
    });
    await owner.close();

    const search = new SearchPage(page);
    await search.goto();
    // The queue worker syncs the project's documents one by one, so start over until the sync has settled:
    // a full page and a button for the rest first, then every request once the button has been clicked.
    await expect(async () => {
        await search.search(many);
        await expect(search.requestsOf(title)).toHaveCount(pageSize, { timeout: 1500 });
        await expect(search.loadMore).toBeVisible({ timeout: 1500 });
        await search.loadMore.click();
        await expect(search.requestsOf(title)).toHaveCount(pageSize + 1, { timeout: 3000 });
    }).toPass({ timeout: 60_000 });

    // Still one card — the project is listed once however many pages its documents span.
    await expect(search.resultLink(title)).toHaveCount(1);
    await expect(search.loadMore).toBeHidden();
});

test('search recovery: after the index is wiped, the documented command brings every project back', async ({ page }) => {
    const command = process.env.E2E_REINDEX_COMMAND;
    const meilisearch = process.env.E2E_MEILISEARCH_URL;
    test.skip(!command || !meilisearch, 'Needs E2E_REINDEX_COMMAND and E2E_MEILISEARCH_URL/E2E_MEILISEARCH_KEY (docs/testing/README.md).');
    test.setTimeout(120_000);

    const search = new SearchPage(page);
    const wipe = await fetch(`${meilisearch}/indexes/items/documents`, { method: 'DELETE', headers: { Authorization: `Bearer ${process.env.E2E_MEILISEARCH_KEY ?? ''}` } });
    expect(wipe.ok).toBeTruthy();

    await search.goto();
    await expect(async () => {
        await search.search(word);
        await expect(search.noHits).toBeVisible();
    }).toPass({ timeout: 30_000 });

    execSync(command!, { stdio: 'inherit' });

    await expect(async () => {
        await search.search(word);
        await search.expectResults([rooms, materials, plain]);
    }).toPass({ timeout: 60_000 });
});
