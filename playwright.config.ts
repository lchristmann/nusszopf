import { defineConfig, devices } from '@playwright/test';

/** Touch-only specs. */
const DEVICE_ONLY = /specs\/devices\//;
/** Keyboard, axe, ARIA and CSP specs: engine- not device-dependent, and keyboard-driven. */
const DESKTOP_ONLY = /specs\/(a11y|security)\/|zz-aria/;

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
        { name: 'chromium', use: { ...devices['Desktop Chrome'] }, testIgnore: DEVICE_ONLY },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] }, testIgnore: DEVICE_ONLY },
        { name: 'webkit', use: { ...devices['Desktop Safari'] }, testIgnore: DEVICE_ONLY },
        // P-5 (docs/release/parity/P-05-browsers-devices.md): the journeys again on the narrowest current phones
        // of decision A-7's two mobile browsers, emulated (viewport, touch, mobile user agent), plus the touch
        // spec. Not a substitute for the manual real-device smoke A-7 requires on each release candidate.
        { name: 'mobile-safari', use: { ...devices['iPhone SE (3rd gen)'] }, testIgnore: DESKTOP_ONLY },
        { name: 'mobile-chrome', use: { ...devices['Galaxy S24'] }, testIgnore: DESKTOP_ONLY },
        { name: 'tablet-safari', use: { ...devices['iPad Mini'] }, testMatch: DEVICE_ONLY },
    ],
});
