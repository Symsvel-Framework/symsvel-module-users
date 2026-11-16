import { describe, expect, it } from 'vitest';
import { activationCopy, validActivationPassword } from '../src/lib/modules/users/invitation';

describe('App invitation activation', () => {
  it('provides translated text and an English fallback', () => {
    expect(activationCopy('fr-FR').title).toBe('Activer mon compte');
    expect(activationCopy('en-US').title).toBe('Activate account');
    expect(activationCopy(null).title).toBe('Activate account');
  });
  it('validates password bytes before sending the one-time token', () => {
    expect(validActivationPassword('long-password-123')).toBe(true);
    expect(validActivationPassword('short')).toBe(false);
    expect(validActivationPassword('a'.repeat(1025))).toBe(false);
    expect(validActivationPassword('long-password\0')).toBe(false);
  });
});
