import { type Locator, type Page, expect } from '@playwright/test';

/**
 * Users click the visible glyph/label; the native input is visually hidden.
 * Retried until it reads as checked: a still-in-flight Livewire round trip
 * for an earlier field can otherwise swallow the click.
 */
export async function tick(checkbox: Locator): Promise<void> {
    await expect(async () => {
        if (!(await checkbox.isChecked())) {
            await checkbox.locator('xpath=ancestor::label[1]/span').click({ position: { x: 10, y: 10 } });
        }
        await expect(checkbox).toBeChecked({ timeout: 1000 });
    }).toPass({ timeout: 10_000 });
}

/**
 * docs/authentication/README.md §2-3 — the combined, tab-switched
 * Login/Register screen.
 */
export class LoginPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/login');
    }

    async register(username: string, email: string, password: string, { newsletter = false } = {}): Promise<void> {
        await this.page.getByTestId('tab_register').click();
        await this.page.getByTestId('input_username').fill(username);
        await this.page.getByTestId('input_email').fill(email);
        await this.page.getByTestId('input_register-password').fill(password);
        await tick(this.page.getByTestId('checkbox_privacy'));
        if (newsletter) {
            await tick(this.page.getByTestId('checkbox_newsletter'));
        }
        await this.page.getByTestId('btn_register').click();
    }

    async login(emailOrName: string, password: string): Promise<void> {
        await this.page.getByTestId('input_email-or-name').fill(emailOrName);
        await this.page.getByTestId('input_login-password').fill(password);
        await this.page.getByTestId('btn_login').click();
    }

    /**
     * A wrong password answers with the generic error toast (`handleLogin`); a locked account or a throttled
     * address with its notice under the field. Either way the form stays.
     */
    async loginExpectingError(emailOrName: string, password: string): Promise<void> {
        await this.page.getByTestId('input_email-or-name').fill(emailOrName);
        await this.page.getByTestId('input_login-password').fill(password);
        await this.page.getByTestId('btn_login').click();
        await expect(this.page.getByText(/Sorry, da lief etwas schief\.|vorübergehend gesperrt|Zu viele Versuche/).last()).toBeVisible();
        await expect(this.page.getByTestId('input_login-password')).toBeVisible();
    }

    /**
     * A full page load: wait for it to finish, or a field filled before Livewire has bound `wire:model`
     * loses its value (seen on Firefox, docs/rewrite/ninth-slice.md "Remaining gaps").
     */
    async goToForgotPassword(): Promise<void> {
        await this.page.getByTestId('btn_forgot-password').click();
        await this.page.waitForURL(/\/password\/forgot$/);
        await this.page.waitForLoadState('load');
    }

    async expectFieldError(testId: string): Promise<void> {
        await expect(this.page.getByTestId(testId)).toBeVisible();
    }
}
