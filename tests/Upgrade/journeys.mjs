// Upgrade test (scripts/upgrade-test.sh): journeys on the data the old release wrote, run after the upgrade.
//
//   node tests/Upgrade/journeys.mjs <base-url> <password-reset-url or ->
//
// The reset URL is the link seed.php had the old release mail out; "-" when that release had no password reset.
import { chromium, selectors } from '@playwright/test';

selectors.setTestIdAttribute('data-test');
const [base, resetUrl] = process.argv.slice(2);
const browser = await chromium.launch();
let failed = false;
const check = (ok, message) => {
    console.log(`${ok ? 'OK  ' : 'FAIL'} ${message}`);
    failed ||= !ok;
};

async function signIn(user, password) {
    const context = await browser.newContext({ baseURL: base });
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByTestId('input_email-or-name').fill(user);
    await page.getByTestId('input_login-password').fill(password);
    await page.getByTestId('btn_login').click();
    const ok = await page.waitForURL(/\/user\/projects$/, { timeout: 10000 }).then(() => true, () => false);
    return { context, page, ok };
}

// Search finds what the old release indexed, and never a private project.
{
    const page = await (await browser.newContext({ baseURL: base })).newPage();
    for (const [query, expected] of [
        ['P9TOKEN003', 'Projekt P9TOKEN003'],
        ['Gemeinschaftsgarten', 'Projekt P9TOKEN'],
        ['Suche P9TOKEN004-1', 'Suche P9TOKEN004-1'],
    ]) {
        await page.goto(`/search?q=${encodeURIComponent(query)}`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(800);
        check((await page.locator('main').innerText()).includes(expected), `search "${query}" shows "${expected}"`);
    }
    await page.goto('/search?q=P9TOKEN001');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    check(!(await page.locator('main').innerText()).includes('Projekt P9TOKEN001'), 'the private project P9TOKEN001 is not found');
}

// Passwords set under the old release still work, by name and by e-mail address.
for (const user of ['p9user21', 'p9user02@example.test']) {
    const { context, page, ok } = await signIn(user, 'P9-Upgrade-Passw0rd!');
    check(ok, `sign-in as ${user} with the password set before the upgrade`);
    if (ok && user === 'p9user21') {
        await page.goto('/user/profile');
        await page.waitForLoadState('networkidle');
        const src = await page.locator('img[src*="/storage/avatars/"]').first().getAttribute('src', { timeout: 5000 }).catch(() => null);
        if (src) {
            check((await page.request.get(src)).status() === 200, `the avatar uploaded before the upgrade is served (${src})`);
        } else {
            console.log('SKIP the old release had no avatar uploads');
        }
    }
    await context.close();
}

// A password-reset link mailed before the upgrade still works after it.
if (resetUrl && resetUrl !== '-') {
    const context = await browser.newContext({ baseURL: base });
    const page = await context.newPage();
    await page.goto(resetUrl);
    const fields = page.locator('input[type=password]');
    const count = await fields.count();
    check(count >= 1, 'the reset link from before the upgrade opens the reset form');
    for (let i = 0; i < count; i++) {
        await fields.nth(i).fill('P9-Nach-Upgrade-1!');
    }
    await page.locator('button[type=submit], [data-test^="btn_"]').last().click();
    await page.waitForTimeout(2500);
    await context.close();
    const after = await signIn('p9user10', 'P9-Nach-Upgrade-1!');
    check(after.ok, 'sign-in with the password set through that link');
    await after.context.close();
}

await browser.close();
process.exit(failed ? 1 : 0);
