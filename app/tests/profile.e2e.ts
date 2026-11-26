import { expect, test } from '@playwright/test';

const tokens = {
  access_token: 'a'.repeat(43), expires_at: '2099-01-01T00:00:00+00:00', token_type: 'Bearer', refresh_token: 'b'.repeat(43), refresh_expires_at: '2099-02-01T00:00:00+00:00'
};

for (const multiple of [false, true]) {
  test('profile shell and language selector: multiple=' + multiple, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.route('**/apiopen/auth/login?*', route => route.fulfill({ json: tokens }));
    await page.route('**/api/auth/me?*', route => route.fulfill({ json: { id: 7, context: 'app' } }));
    
    await page.route('**/api/users/profile?*', route => route.fulfill({ json: { data: { id: 7, email: 'profile@example.test', language: 'en', email_verified: true } } }));
    await page.route('**/apiopen/languages?*', route => route.fulfill({ json: { data: multiple ? [{ iso_code: 'fr', name: 'Français', primary: false}, { iso_code: 'en', name: 'English', primary: true}] : [{ iso_code: 'en', name: 'English', primary: true }] } }));
    await page.goto('/login');
    await page.getByLabel('Email address').fill('profile@example.test');
    await page.getByLabel('Password').fill('correct-password-123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('.bo-shell')).toBeVisible();
    await page.locator('[data-app-shell]').evaluate(el => el.setAttribute('data-shell-test', 'persistent'));
    await page.getByRole('link', { name: 'My profile' }).click();
    await expect(page).toHaveURL(/\/profile$/);
    await expect(page.locator('.bo-shell')).toBeVisible();
    await expect(page.locator('[data-app-shell]')).toHaveAttribute('data-shell-test', 'persistent');
    const details = page.locator('[data-profile-details]');
    const password = page.locator('[data-profile-password]');
    await expect(details).toContainText('profile@example.test');
    await expect(password).toBeVisible();
    await expect(page.locator('[data-profile-language]')).toHaveCount(multiple ? 1 : 0);
    const left = await details.boundingBox();
    const right = await password.boundingBox();
    expect(left && right && right.x >= left.x + left.width - 1 && Math.abs(right.y - left.y) < 3).toBeTruthy();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(async () => {
      const a = await details.boundingBox();
      const b = await password.boundingBox();
      return Boolean(a && b && b.y >= a.y + a.height - 1);
    }).toBe(true);
  });
}
