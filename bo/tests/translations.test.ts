import { describe, expect, it } from 'vitest';
import fr from '../src/lib/translations/fr';
import en from '../src/lib/translations/en';

describe('Users BO dictionaries', () => {
  it('aligns FR/EN labels', () => { expect(Object.keys(fr).sort()).toEqual(Object.keys(en).sort()); });
});
