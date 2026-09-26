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
    await expect(page.getByTestId('avatar-dialog')).toBeVisible();
    await fileInput.setInputFiles(AVATAR_FIXTURE);
    await expect(saveButton).toBeEnabled();
    await saveButton.click();
    await expect(page.getByText('Frisches Bild gespeichert.')).toBeVisible();
    await expect(avatarImg).toHaveAttribute('src', /\/storage\/avatars\/.+-v2\.jpg$/);

    // Resolve against the page's own reachable origin, not `avatarSrc`'s
    // (built from `APP_URL`, which may not be the origin Playwright itself
    // can reach — dev/CI point them at different hosts).
    const oldFile = await page.request.get(new URL(new URL(avatarSrc!).pathname, page.url()).toString());
    expect(oldFile.status()).toBe(404);
});

/**
 * P-16, P16-07: "Speichern" was enabled as soon as the picture had been read, before cropperjs had been fetched and
 * built on it, and `save()` did nothing without a cropper. A click in that window (a slow connection, a fast test)
 * did nothing and showed nothing. Now the button waits for the cropper.
 */
test('offers "Speichern" only once the cropper is ready', async ({ page }) => {
    // Hold the cropper's chunk back, as a slow phone connection would.
    let release!: () => void;
    const held = new Promise<void>((resolve) => (release = resolve));
    await page.route('**/build/assets/cropper-*.js', async (route) => {
        await held;
        await route.continue();
    });

    await registerFreshUser(page);
    await page.goto('/user/profile');
    await page.getByTestId('btn_edit-avatar_settings-page').click();
    await expect(page.getByTestId('avatar-dialog')).toBeVisible();

    await page.locator('[data-test="avatar-dialog"] input[type="file"]').setInputFiles(AVATAR_FIXTURE);
    // The picture has been read (the chooser is gone), but there is no cropper yet: nothing to save.
    await expect(page.getByTestId('avatar-dialog').getByText('Bild auswählen')).toBeHidden();
    const saveButton = page.getByTestId('btn_save_avatar-dialog');
    await expect(saveButton).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Bild drehen' })).toBeDisabled();

    release();
    await expect(saveButton).toBeEnabled();
    await saveButton.click();
    await expect(page.getByText('Frisches Bild gespeichert.')).toBeVisible();
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
