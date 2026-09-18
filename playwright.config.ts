import { defineConfig, devices } from '@playwright/test';

/**
 * docs/testing/README.md: Playwright specs under tests/E2E/specs/<actor>/,
 * Page Object Models under tests/E2E/pages/, one shared fixture/env-constants
 * file under tests/E2E/support/ — every browser engine as a separate
 * parallel project, matching LCxHolz's convention.
 */
export default defineConfig({
    testDir: './tests/E2E/specs',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8080',
        trace: 'on-first-retry',
        // Matches the historical `data-test="..."` convention
        // (docs/design/navigation.md) instead of Playwright's own default
        // `data-testid` attribute name.
        testIdAttribute: 'data-test',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
