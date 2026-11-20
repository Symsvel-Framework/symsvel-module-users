import { expect, test } from '@playwright/test';

test('public App registration uses apiopen, no token, and a uniform confirmation message', async ({ page }) => {
  let calls = 0;
  await page.route('**/apiopen/users/register*', async (route) => {
    ++calls;
    expect(route.request().headers()['authorization']).toBeUndefined();
    expect(route.request().postDataJSON()).toEqual({
      email: 'new@example.test', password: 'long-secure-password', language: 'en', locale: 'en'
    });
    await route.fulfill({ status: 202, contentType: 'application/json', body: JSON.stringify({
      status: 'accepted'
    }) });
  });

  await page.route('**/apiopen/languages/account-options*', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
      data: [{ iso_code: 'en', name: 'English', primary: true }]
    }) });
  });
  await page.goto('/register');
  await expect(page.getByRole('button', { name: 'Create my account' })).toBeEnabled();
  await page.getByLabel('Email address').fill('new@example.test');
  await page.getByLabel('Password', { exact: true }).fill('long-secure-password');
  await page.getByLabel('Confirm password').fill('long-secure-password');
  await page.getByRole('button', { name: 'Create my account' }).click();
  await expect(page.getByRole('status')).toContainText('confirmation email will be sent');
  expect(calls).toBe(1);
  await expect(page.getByRole('link', { name: 'Back to sign in' })).toBeVisible();
});

test('mismatched passwords never reach the public signup API', async ({ page }) => {
  let called = false;
  await page.route('**/apiopen/users/register*', async (route) => {
    called = true;
    await route.fulfill({ status: 202, contentType: 'application/json', body: '{}' });
  });
  await page.route('**/apiopen/languages/account-options*', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
      data: [{ iso_code: 'en', name: 'English', primary: true }]
    }) });
  });
  await page.goto('/register');
  await expect(page.getByRole('button', { name: 'Create my account' })).toBeEnabled();
  await page.getByLabel('Email address').fill('new@example.test');
  await page.getByLabel('Password', { exact: true }).fill('long-secure-password');
  await page.getByLabel('Confirm password').fill('different-password');
  await page.getByRole('button', { name: 'Create my account' }).click();
  await expect(page.getByRole('alert')).toContainText('Passwords do not match');
  expect(called).toBe(false);
});
