import { describe, expect, it } from 'vitest';
import { appProfileCopy } from '../src/lib/modules/users/profile';

describe('App personal profile translations', () => {
  it('supplies the same field contract for both locales', () => {
    expect(appProfileCopy('fr').title).toBe('Mon profil');
    expect(appProfileCopy('en').title).toBe('My profile');
    expect(appProfileCopy('fr').email).toBeTruthy();
  });
});

import { readFileSync } from 'node:fs';

it('offers email and password changes alongside language, never another account target', () => {
  const source = readFileSync(new URL('../src/routes/profile/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/api/users/profile/password'");
  expect(source).toContain("'/api/users/profile/email-change'");
  expect(source).toContain('current_password');
  expect(source).toContain('new_email');
  expect(source).toContain('new_password');
  expect(source).toContain('logoutApp()');
  expect(source).not.toContain('target_admin_id');
});

it('provides a public confirmation page in the correct session context', () => {
  const source = readFileSync(new URL('../src/routes/verify-email-change/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/apiopen/auth/profile/email-change/complete'");
  expect(source).toContain('public: true');
  expect(source).toContain('history.replaceState');
});
