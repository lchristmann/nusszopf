import { test, expect } from '@playwright/test';
import { LoginPage } from '../../pages/LoginPage';
import { TEST_PASSWORD, testEmail, testUsername, uniqueSuffix } from '../../support/env';

/**
 * Journey 2 (`_auth.spec.js`, docs/journeys/README.md) as the historical
 * Cypress spec drove it — through the nav header's hamburger menu:
 * register -> My Projects, logout -> Home, login with the *username* ->
 * My Projects. Then Journey 4 step 1: the Profile avatar shows the username.
 */
test('registers, logs out and logs back in with the username, all from the nav menu', async ({ page }) => {
    const suffix = uniqueSuffix();
    const username = testUsername(suffix);
    const login = new LoginPage(page);

    await page.goto('/search');
    await page.getByTestId('btn_burger_nav-header').click();
    await page.getByTestId('btn_login_nav-header').click();
    await expect(page).toHaveURL(/\/login$/);

    await login.register(username, testEmail(suffix), TEST_PASSWORD);
    await expect(page).toHaveURL(/\/user\/projects$/);

    await page.getByTestId('btn_burger_nav-header').click();
    await page.getByTestId('btn_logout_nav-header').click();
    await expect(page).toHaveURL((url) => url.pathname === '/');

    await page.goto('/search');
    await page.getByTestId('btn_burger_nav-header').click();
    await page.getByTestId('btn_login_nav-header').click();
    await login.login(username, TEST_PASSWORD);
    await expect(page).toHaveURL(/\/user\/projects$/);

    await page.goto('/user/profile');
    await expect(page.getByTestId('username_avatar')).toHaveText(username);
});
