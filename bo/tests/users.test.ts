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

import { boNavigation } from '../src/lib/modules/users/navigation';

describe('users BO navigation declaration', () => {
  it('links to the ACL-protected read-only endpoint', () => {
    expect(boNavigation.path).toBe('/users');
    expect(boNavigation.probe).toBe('/apibo/users');
    expect(boNavigation.action).toBe('list');
    expect(boNavigation.resource).toBe('users');
  });
});
