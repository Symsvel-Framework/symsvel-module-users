import { expect, test } from '@playwright/test';

test('profile uses the single GDPR runtime and supports withdrawal without floating controls', async ({ page }) => {
  const credential = 'a'.repeat(64) + '.' + 'b'.repeat(64);
  let reads = 0;
  let writes = 0;
  let granted = true;
  const tokens = {
    access_token: 'a'.repeat(43), expires_at: '2099-01-01T00:00:00+00:00', token_type: 'Bearer',
    refresh_token: 'b'.repeat(43), refresh_expires_at: '2099-02-01T00:00:00+00:00'
  };
  const state = () => ({ enabled: true, complete: true, version: (writes + 1).toString(16).padStart(32, '0'), expires_at: '2099-01-01T00:00:00Z', purposes: [{ subject: 'tracker-example', revision: 1, name: 'Example', purpose: 'Audience', description: 'Description', privacy_url: 'https://example.invalid/privacy', decided: true, granted }] });
  await page.addInitScript(token => localStorage.setItem('symsvel.gdpr.app', token), credential);
  await page.route('**/apiopen/project', route => route.fulfill({ json: { data: { name: 'GDPR CT' } } }));
  await page.route('**/apiopen/auth/login?*', route => route.fulfill({ json: tokens }));
  await page.route('**/api/auth/me?*', route => route.fulfill({ json: { id: 7, context: 'app' } }));
  await page.route('**/api/users/profile?*', route => route.fulfill({ json: { data: { id: 7, email: 'profile@example.test', language: 'en', email_verified: true } } }));
  await page.route('**/apiopen/languages?*', route => route.fulfill({ json: { data: [{ iso_code: 'en', name: 'English', primary: true }] } }));
  await page.route('**/apiopen/gdpr/app/consent/state', route => {
    reads++;
    expect(route.request().headers()['authorization']).toBe('Bearer ' + credential);
    return route.fulfill({ json: { data: state() } });
  });
  await page.route('**/apiopen/gdpr/app/consent', route => {
    writes++;
    granted = route.request().postDataJSON().choices['tracker-example'].granted;
    return route.fulfill({ json: { data: state() } });
  });
  await page.goto('/login');
  await expect(page.getByRole('button', { name: /Cookie preferences|Préférences cookies/ })).toBeVisible();
  await page.getByLabel('Email address').fill('profile@example.test');
  await page.getByLabel('Password').fill('correct-password-123');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await page.getByRole('link', { name: 'My profile' }).click();
  await page.getByRole('link', { name: 'Consents' }).click();
  await expect(page.locator('[data-profile-consents]')).toBeVisible();
  await expect(page.getByRole('checkbox')).toBeChecked();
  await expect(page.locator('.privacy-footer')).toHaveCount(0);
  await expect(page.locator('.consent-panel.floating')).toHaveCount(0);
  await page.getByRole('button', { name: /Refuse all|Tout refuser/ }).click();
  await expect(page.getByRole('checkbox')).not.toBeChecked();
  await expect(page.getByRole('button', { name: /Refuse all|Tout refuser/ })).toBeEnabled();
  expect(writes).toBe(1);
  expect(granted).toBe(false);
  expect(reads).toBe(1);
  await page.getByRole('link', { name: 'Security' }).click();
  await expect(page.locator('[data-profile-password]')).toBeVisible();
  await expect(page.locator('.consent-panel')).toHaveCount(0);
  await page.getByRole('link', { name: 'Consents' }).click();
  await expect(page.getByRole('checkbox')).not.toBeChecked();
  expect(reads).toBe(1);
});
