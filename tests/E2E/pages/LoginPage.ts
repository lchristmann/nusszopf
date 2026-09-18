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
        await this.page.getByTestId('checkbox_privacy').check();
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
