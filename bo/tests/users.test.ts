import { describe, expect, it } from 'vitest';
import { boUsersCopy, isNeutralized, type BoUser } from '../src/lib/modules/users/bo';
import { boNavigation } from '../src/lib/modules/users/navigation';

describe('BO Users invitation and navigation', () => {
  it('provides bilingual invitation labels without an initial password field', () => {
    expect(boUsersCopy('fr-FR').create).toBe('Inviter un utilisateur');
    expect(boUsersCopy('en-US').save).toBe('Save');
    expect(boUsersCopy('fr').inviteIntro).toContain('mot de passe');
    expect(boUsersCopy('de-DE').create).toBe('Inviter un utilisateur');
  });

  it('distinguishes a disabled account from a permanently neutralized identity', () => {
    const row: BoUser = { id: 1, email: 'user@example.test', language: null, enabled: false, email_verified: false, created_at: '2026-01-01' };
    expect(isNeutralized(row)).toBe(false);
    expect(isNeutralized({ ...row, anonymized_at: null })).toBe(false);
    expect(isNeutralized({ ...row, anonymized_at: '2026-11-28' })).toBe(true);
    expect(boUsersCopy('fr').anonymized).toBe('Neutralisé');
    expect(boUsersCopy('en').anonymized).toBe('De-identified');
  });

  it('keeps the BO ACL-protected navigation probe', () => {
    expect(boNavigation.path).toBe('/users');
    expect(boNavigation.probe).toBe('/apibo/users');
    expect(boNavigation.action).toBe('list');
    expect(boNavigation.resource).toBe('users');
  });
});
