import { describe, expect, it } from 'vitest';
import { boUsersCopy, validBoUserPassword } from '../src/lib/modules/users/bo';

describe('BO Users', () => {
  it('exposes translated labels', () => {
    expect(boUsersCopy('fr-FR').create).toBe('Créer un utilisateur');
    expect(boUsersCopy('en-US').save).toBe('Save');
  });
  it('enforces the same byte limits as the Users API', () => {
    expect(validBoUserPassword('a'.repeat(11))).toBe(false);
    expect(validBoUserPassword('a'.repeat(12))).toBe(true);
    expect(validBoUserPassword('a'.repeat(1025))).toBe(false);
    expect(validBoUserPassword('a'.repeat(11) + '\0')).toBe(false);
  });
});
