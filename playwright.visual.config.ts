import { defineConfig, devices } from '@playwright/test';

/**
 * The visual-regression suite (docs/testing/visual-regression.md), apart from the E2E suite because it needs
 * its own fixed dataset (`VisualReferenceSeeder`). `VISUAL_TARGET=rewrite` (default) compares every screen
 * against the committed baselines; `VISUAL_TARGET=historical` captures the same screens from the historical
 * app (tests/Visual/historical-harness) as the reference the baselines were reviewed against.
 */
export default defineConfig({
    testDir: './tests/Visual',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    reporter: [['list'], ['html', { outputFolder: 'playwright-report-visual', open: 'never' }]],
    snapshotPathTemplate: '{testDir}/baselines/{arg}{ext}',
    expect: { toHaveScreenshot: { maxDiffPixels: 0, animations: 'disabled', caret: 'hide' } },
    use: {
        baseURL: process.env.VISUAL_TARGET === 'historical'
            ? (process.env.VISUAL_HISTORICAL_URL ?? 'http://hist-web:3000')
            : (process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8080'),
        testIdAttribute: 'data-test',
        locale: 'de-DE',
        timezoneId: 'Europe/Berlin',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
