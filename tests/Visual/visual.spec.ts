import { test, expect } from '@playwright/test';
import { MASKS, SCREENS, VIEWPORTS, signIn, type Target } from './screens';

/**
 * docs/testing/visual-regression.md. Rewrite: `toHaveScreenshot` against tests/Visual/baselines.
 * Historical: full-page captures into tests/Visual/reference for the side-by-side review page.
 */
const target: Target = process.env.VISUAL_TARGET === 'historical' ? 'historical' : 'rewrite';

for (const screen of SCREENS) {
    for (const viewport of VIEWPORTS) {
        test(`${screen.name} @ ${viewport.name}`, async ({ page, baseURL }) => {
            await page.setViewportSize({ width: viewport.width, height: viewport.height });
            if (screen.auth) {
                await signIn(page, target, baseURL!);
            }
            await page.goto(target === 'historical' && screen.historicalUrl ? screen.historicalUrl : screen.path);
            await page.waitForLoadState('networkidle');
            await page.evaluate(() => document.fonts.ready);
            await screen.prepare?.(page, target);
            await page.waitForLoadState('networkidle');
            // Toasts and hover states are transient; the pointer rests outside the page.
            await page.mouse.move(0, 0);

            const file = `${screen.name}-${viewport.name}.png`;
            const mask = [page.locator(MASKS[target])];
            if (target === 'historical') {
                await page.screenshot({ path: `tests/Visual/reference/${file}`, fullPage: true, animations: 'disabled', caret: 'hide', mask });
                return;
            }
            await expect(page).toHaveScreenshot(file, { fullPage: true, mask });
        });
    }
}
