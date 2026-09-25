// Search-recovery drill (scripts/search-recovery-test.sh, P-11): what a visitor sees on the search page, through a
// real browser: for each deep link (?q=, ?f[]=) the cards in order with their request hits, after clicking
// "Mehr laden" until it disappears, and whether the page shows the no-hits section instead.
//
//   node tests/SearchRecovery/browser.mjs <base-url> > browser.json
import { chromium, selectors } from '@playwright/test';

selectors.setTestIdAttribute('data-test');
const [base] = process.argv.slice(2);
const links = [
    '/search',
    '/search?q=Garten',
    '/search?q=Werkzeug',
    '/search?q=Gemeinschaftgarten',
    '/search?q=P9TOKEN001',
    '/search?f[0]=rooms',
    '/search?f[0]=none',
    '/search?q=Berlin&f[0]=companions&f[1]=none',
];
const browser = await chromium.launch();
const page = await (await browser.newContext({ baseURL: base })).newPage();
const out = {};

for (const link of links) {
    await page.goto(link);
    await page.getByTestId('search-results').or(page.getByTestId('no-hits')).first().waitFor({ timeout: 30_000 });
    let clicks = 0;
    const cardsAt = [];
    for (;;) {
        cardsAt.push(await page.getByTestId('route_hitcard').count());
        const more = page.getByTestId('btn_load-more');
        if (!(await more.isVisible())) break;
        const before = cardsAt.at(-1);
        await more.click();
        await page.waitForFunction((n) => document.querySelectorAll('[data-test="route_hitcard"]').length > n
            || !document.querySelector('[data-test="btn_load-more"]'), before, { timeout: 30_000 });
        await page.waitForLoadState('networkidle');
        clicks++;
    }
    const cards = await page.getByTestId('route_hitcard').evaluateAll((els) => els.map((el) => ({
        href: new URL(el.href).pathname,
        title: el.querySelector('[data-test="route_title_hitcard"]')?.textContent.trim(),
        requests: [...el.querySelectorAll('[data-test="card_request-hit"]')].map((r) => r.textContent.replace(/\s+/g, ' ').trim()),
    })));
    out[link] = { no_hits: await page.getByTestId('no-hits').isVisible(), load_more_clicks: clicks, cards_after_each_page: cardsAt, cards };
}

await browser.close();
console.log(JSON.stringify(out, null, 2));
