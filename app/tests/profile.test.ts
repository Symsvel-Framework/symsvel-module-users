import { describe, expect, it } from 'vitest';
import { appProfileCopy } from '../src/lib/modules/users/profile';

describe('App personal profile translations', () => {
  it('supplies the same field contract for both locales', () => {
    expect(appProfileCopy('fr').title).toBe('Mon profil');
    expect(appProfileCopy('en').title).toBe('My profile');
    expect(appProfileCopy('fr').email).toBeTruthy();
  });
});
