import { describe, expect, it } from 'vitest';
import { boUsersCopy } from '../src/lib/modules/users/bo';
import { boNavigation } from '../src/lib/modules/users/navigation';

describe('BO Users invitation and navigation', () => {
  it('provides bilingual invitation labels without an initial password field', () => {
    expect(boUsersCopy('fr-FR').create).toBe('Inviter un utilisateur');
    expect(boUsersCopy('en-US').save).toBe('Save');
    expect(boUsersCopy('fr').inviteIntro).toContain('mot de passe');
  });

  it('keeps the BO ACL-protected navigation probe', () => {
    expect(boNavigation.path).toBe('/users');
    expect(boNavigation.probe).toBe('/apibo/users');
    expect(boNavigation.action).toBe('list');
    expect(boNavigation.resource).toBe('users');
  });
});
