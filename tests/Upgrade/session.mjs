// Upgrade test (scripts/upgrade-test.sh): a browser session opened before the upgrade must still be signed in
// after it, and show that user's projects.
//
//   node tests/Upgrade/session.mjs save  <base-url> <user> <state-file>   # before: sign in, keep the cookies
//   node tests/Upgrade/session.mjs check <base-url> <user> <state-file>   # after: the same cookies, no sign-in
import { chromium, selectors } from '@playwright/test';

selectors.setTestIdAttribute('data-test');
const [mode, base, user, state] = process.argv.slice(2);
const browser = await chromium.launch();
let failed = false;

if (mode === 'save') {
    const context = await browser.newContext({ baseURL: base });
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByTestId('input_email-or-name').fill(user);
    await page.getByTestId('input_login-password').fill('P9-Upgrade-Passw0rd!');
    await page.getByTestId('btn_login').click();
    await page.waitForURL(/\/user\/projects$/);
    await context.storageState({ path: state });
    console.log(`OK   signed in as ${user}, session saved`);
} else {
    const context = await browser.newContext({ baseURL: base, storageState: state });
    const page = await context.newPage();
    await page.goto('/user/projects');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500); // My Projects renders its cards after a skeleton
    const signedIn = page.url().endsWith('/user/projects');
    const titles = [...new Set((await page.content()).match(/Projekt P9TOKEN\d+/g) ?? [])].sort();
    console.log(`${signedIn ? 'OK  ' : 'FAIL'} the session from before the upgrade is still signed in (${page.url()})`);
    console.log(`${titles.length > 0 ? 'OK  ' : 'FAIL'} My Projects lists ${titles.join(', ') || 'nothing'}`);
    failed = !signedIn || titles.length === 0;
}

await browser.close();
process.exit(failed ? 1 : 0);
