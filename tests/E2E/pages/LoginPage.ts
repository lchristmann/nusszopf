import { type Page, expect } from '@playwright/test';

/**
 * docs/authentication/README.md §2-3 — the combined, tab-switched
 * Login/Register screen.
 */
export class LoginPage {
    constructor(private readonly page: Page) {}

    async goto(): Promise<void> {
        await this.page.goto('/login');
    }

    async register(username: string, email: string, password: string): Promise<void> {
        await this.page.getByTestId('tab_register').click();
        await this.page.getByTestId('input_username').fill(username);
        await this.page.getByTestId('input_email').fill(email);
        await this.page.getByTestId('input_register-password').fill(password);
        // Users click the visible glyph/label; the native input is visually hidden.
        // Retried until it reads as checked: a still-in-flight Livewire round trip
        // for an earlier field can otherwise swallow the click.
        const privacy = this.page.getByTestId('checkbox_privacy');
        await expect(async () => {
            if (!(await privacy.isChecked())) {
                await privacy.locator('xpath=ancestor::label[1]/span').click({ position: { x: 10, y: 10 } });
            }
            await expect(privacy).toBeChecked({ timeout: 1000 });
        }).toPass({ timeout: 10_000 });
        await this.page.getByTestId('btn_register').click();
    }

    async login(emailOrName: string, password: string): Promise<void> {
        await this.page.getByTestId('input_email-or-name').fill(emailOrName);
        await this.page.getByTestId('input_login-password').fill(password);
        await this.page.getByTestId('btn_login').click();
    }

    async expectFieldError(testId: string): Promise<void> {
        await expect(this.page.getByTestId(testId)).toBeVisible();
    }
}
