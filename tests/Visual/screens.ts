import { type Page, expect } from '@playwright/test';
import data from './reference-data.json' with { type: 'json' };

/**
 * Every screen of docs/design/screen-specs.md the visual suite captures, identical for both targets
 * (docs/testing/visual-regression.md). `prepare` brings the page into the captured state after navigation.
 */
export type Target = 'rewrite' | 'historical';

export interface Screen {
    name: string;
    path: string;
    auth?: boolean;
    /** The historical screen lived in another app (the Auth0-hosted login and password apps): its absolute URL. */
    historicalUrl?: string;
    prepare?: (page: Page, target: Target) => Promise<void>;
}

export const VIEWPORTS = [
    { name: 'phone', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1440, height: 900 },
] as const;

const [garden, repair] = data.projects;
const AUTH_LOGIN = process.env.VISUAL_HISTORICAL_LOGIN_URL ?? 'http://hist-auth-login:3001';
const AUTH_PASSWORD = process.env.VISUAL_HISTORICAL_PASSWORD_URL ?? 'http://hist-auth-password:3002';
const user = data.users[0];

/** Masked in every capture: grows with every visit, in both apps. */
export const MASKS: Record<Target, string> = {
    rewrite: '[data-test="visitor-counter_project-detail"]',
    historical: 'div.inline-flex.bg-lilac-150.rounded-md',
};

async function selectEditView(page: Page, view: string): Promise<void> {
    await page.locator('select').first().selectOption(view);
}

export const SCREENS: Screen[] = [
    { name: 'home', path: '/' },
    { name: 'search', path: '/search', prepare: async (page) => { await expect(page.getByText(garden.title).first()).toBeVisible(); } },
    {
        name: 'search-no-hits',
        path: '/search',
        prepare: async (page) => {
            const input = page.locator('[data-test="input_search-input"]');
            await input.fill('Zzyzx');
            await input.press('Enter');
            await expect(page.getByText(garden.title)).toHaveCount(0);
        },
    },
    { name: 'project-visitor', path: `/projects/${garden.id}` },
    { name: 'project-personal-contact', path: `/projects/${repair.id}` },
    { name: 'project-owner', path: `/projects/${garden.id}`, auth: true },
    {
        name: 'request-dialog',
        path: `/projects/${garden.id}`,
        prepare: async (page) => {
            await page.getByText(garden.requests[0].title).first().click();
            await expect(page.getByRole('dialog')).toBeVisible();
        },
    },
    {
        name: 'contact-dialog',
        path: `/projects/${garden.id}`,
        prepare: async (page) => {
            await page.getByRole('button', { name: 'Kontaktieren' }).first().click();
            await expect(page.getByRole('dialog')).toBeVisible();
        },
    },
    { name: 'my-projects', path: '/user/projects', auth: true, prepare: async (page) => { await expect(page.getByText(garden.title).first()).toBeVisible(); } },
    { name: 'project-create', path: '/user/project/create', auth: true },
    { name: 'project-edit-description', path: `/user/project/${garden.id}/edit`, auth: true, prepare: async (page) => { await expect(page.locator('select').first()).toHaveValue('Beschreibung'); await expect(page.getByText('Speichern').first()).toBeVisible(); } },
    { name: 'project-edit-requests', path: `/user/project/${garden.id}/edit`, auth: true, prepare: async (page) => { await expect(page.getByText('Speichern').first()).toBeVisible(); await selectEditView(page, 'Gesuche'); await expect(page.getByText('Aktuelle Gesuche')).toBeVisible(); } },
    { name: 'project-edit-settings', path: `/user/project/${garden.id}/edit`, auth: true, prepare: async (page) => { await expect(page.getByText('Speichern').first()).toBeVisible(); await selectEditView(page, 'Einstellungen'); await expect(page.getByText('Projekt löschen')).toBeVisible(); } },
    { name: 'profile', path: '/user/profile', auth: true },
    { name: 'legal-notice', path: '/legalNotice' },
    { name: 'legal-policy', path: '/legalPolicy' },
    { name: 'privacy', path: '/privacy' },
    { name: 'newsletter-unsubscribe-lead', path: '/newsletter/unsubscribe/lead' },
    { name: 'not-found', path: '/diese-seite-gibt-es-nicht' },
    { name: 'login', path: '/login', historicalUrl: `${AUTH_LOGIN}/` },
    {
        name: 'register',
        path: '/login',
        historicalUrl: `${AUTH_LOGIN}/`,
        prepare: async (page, target) => {
            await (target === 'historical' ? page.getByRole('tab').last() : page.getByTestId('tab_register')).click();
        },
    },
    {
        name: 'password-forgot',
        path: '/password/forgot',
        historicalUrl: `${AUTH_LOGIN}/`,
        // Historically a view of the login app, not a page of its own.
        prepare: async (page, target) => {
            if (target === 'historical') await page.getByRole('button', { name: 'Passwort vergessen' }).click();
        },
    },
    { name: 'password-reset', path: `/password/reset/visual-reference-token?email=${encodeURIComponent(user.email)}`, historicalUrl: `${AUTH_PASSWORD}/` },
];

export async function signIn(page: Page, target: Target, baseURL: string): Promise<void> {
    if (target === 'historical') {
        await page.context().addCookies([{ name: 'nzfake', value: user.historicalId, url: baseURL }]);
        return;
    }
    await page.goto('/login');
    await page.getByTestId('input_email-or-name').fill(user.name);
    await page.getByTestId('input_login-password').fill(user.password);
    await page.getByTestId('btn_login').click();
    await page.waitForURL(/\/user\/projects$/);
}
