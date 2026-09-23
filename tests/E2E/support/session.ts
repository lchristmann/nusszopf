import { type Page, expect } from '@playwright/test';
import { LoginPage } from '../pages/LoginPage';
import { TEST_PASSWORD, testEmail, testUsername, uniqueSuffix } from './env';

export interface TestUser {
    username: string;
    email: string;
    password: string;
}

/**
 * Registers a fresh account through the real registration screen (which logs
 * in immediately — no verification gate) and lands on My Projects.
 */
export async function registerFreshUser(page: Page, { newsletter = false } = {}): Promise<TestUser> {
    const suffix = uniqueSuffix();
    const user = { username: testUsername(suffix), email: testEmail(suffix), password: TEST_PASSWORD };

    const login = new LoginPage(page);
    await login.goto();
    await login.register(user.username, user.email, user.password, { newsletter });
    await expect(page).toHaveURL(/\/user\/projects$/);

    return user;
}
