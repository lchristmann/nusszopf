import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { registerFreshUser } from '../../support/session';

const AVATAR_FIXTURE = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'fixtures', 'avatar.png');

/**
 * docs/rewrite/master-roadmap.md, "Slice 8": upload/crop/replace avatar, and
 * delete account end to end — no fixed sleeps (the historical E2E test's
 * unexplained `cy.wait(2000)` is deliberately not reproduced,
 * docs/rewrite/open-questions.md, "Unexplained 2-second wait in the
 * account-deletion E2E test").
 */
test('uploads, crops and replaces an avatar', async ({ page }) => {
    await registerFreshUser(page);
    await page.goto('/user/profile');

    await page.getByTestId('btn_edit-avatar_settings-page').click();
    await expect(page.getByTestId('avatar-dialog')).toBeVisible();

    const fileInput = page.locator('[data-test="avatar-dialog"] input[type="file"]');
    await fileInput.setInputFiles(AVATAR_FIXTURE);

    const saveButton = page.getByTestId('btn_save_avatar-dialog');
    await expect(saveButton).toBeEnabled();
    await saveButton.click();

    await expect(page.getByText('Frisches Bild gespeichert.')).toBeVisible();
    await expect(page.getByTestId('avatar-dialog')).toBeHidden();

    const avatarImg = page.getByTestId('img_avatar');
    await expect(avatarImg).toHaveAttribute('src', /\/storage\/avatars\/.+-v1\.jpg$/);
    const avatarSrc = await avatarImg.getAttribute('src');

    // Replace it — the version increments and the previous file is gone.
    await page.getByTestId('btn_edit-avatar_settings-page').click();
    await fileInput.setInputFiles(AVATAR_FIXTURE);
    await saveButton.click();
    await expect(page.getByText('Frisches Bild gespeichert.')).toBeVisible();
    await expect(avatarImg).toHaveAttribute('src', /\/storage\/avatars\/.+-v2\.jpg$/);

    // Resolve against the page's own reachable origin, not `avatarSrc`'s
    // (built from `APP_URL`, which may not be the origin Playwright itself
    // can reach — dev/CI point them at different hosts).
    const oldFile = await page.request.get(new URL(new URL(avatarSrc!).pathname, page.url()).toString());
    expect(oldFile.status()).toBe(404);
});

test('deletes the account', async ({ page }) => {
    await registerFreshUser(page);
    await page.goto('/user/profile');

    page.once('dialog', (dialog) => void dialog.accept());
    await page.getByTestId('btn_delete-account_settings-page').click();

    // Logout lands on Home, as historically (`/api/logout`).
    await expect(page).toHaveURL((url) => url.pathname === '/');
    await expect(page.getByText('Dein Account wurde gelöscht!')).toBeVisible();

    // Logged out for real — the profile page (and everything else that
    // needs auth) redirects to login again.
    await page.goto('/user/profile');
    await expect(page).toHaveURL(/\/login$/);
});
