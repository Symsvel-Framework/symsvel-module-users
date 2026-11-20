import { describe, expect, it } from 'vitest';
import fr from '../src/lib/translations/fr';
import en from '../src/lib/translations/en';

describe('Users App dictionaries', () => {
  it('aligns registration and activation keys', () => {
    expect(Object.keys(fr.registration).sort()).toEqual(Object.keys(en.registration).sort());
    expect(Object.keys(fr.activation).sort()).toEqual(Object.keys(en.activation).sort());
  });
});
