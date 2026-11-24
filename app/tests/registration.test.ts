import { describe, expect, it } from 'vitest';
import {
  registrationCopy,
  validRegistrationPassword
} from '../src/lib/modules/users/registration';

describe('App registration', () => {
  it('uses bilingual copy with an English fallback', () => {
    expect(registrationCopy('fr-FR').title).toBe('Créer un compte');
    expect(registrationCopy('en-US').title).toBe('Create account');
    expect(registrationCopy(null).title).toBe('Create account');
  });

  it('checks byte length without allowing embedded NUL', () => {
    expect(validRegistrationPassword('Long-password-123!')).toBe(true);
    expect(validRegistrationPassword('too-short')).toBe(false);
    expect(validRegistrationPassword('A'.repeat(1025))).toBe(false);
    expect(validRegistrationPassword('long-password\0')).toBe(false);
  });
});
