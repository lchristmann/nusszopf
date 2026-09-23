import { test } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';
test('aria snapshots', async ({ page }) => {
  mkdirSync('test-results/aria', { recursive: true });
  const shots: [string, string][] = [['home','/'],['search','/search'],['detail','/projects/0199a000-0000-7000-8000-000000000001'],['login','/login'],['legal','/legalNotice'],['404','/nix']];
  for (const [n, p] of shots) { await page.goto(p); await page.waitForLoadState('networkidle'); writeFileSync(`test-results/aria/${n}.yaml`, await page.locator('body').ariaSnapshot()); }
  await page.goto('/login'); await page.getByTestId('input_email-or-name').fill('demo'); await page.getByTestId('input_login-password').fill('Str0ng!Passw0rd'); await page.getByTestId('btn_login').click(); await page.waitForURL(/projects$/);
  for (const [n, p] of [['myprojects','/user/projects'],['wizard','/user/project/create'],['profile','/user/profile'],['edit','/user/project/0199a000-0000-7000-8000-000000000001/edit']] as const) { await page.goto(p); await page.waitForLoadState('networkidle'); writeFileSync(`test-results/aria/${n}.yaml`, await page.locator('body').ariaSnapshot()); }
});
