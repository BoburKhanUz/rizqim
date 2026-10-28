import { describe, expect, it } from 'vitest';
import { createTranslator, DICTIONARIES, isMessageKey } from '.';
import { SECTIONS } from './sections';
import { uz } from './uz';

describe('i18n', () => {
  it('has no empty text', () => {
    expect(Object.entries(DICTIONARIES.uz).filter(([, text]) => text.trim() === '')).toEqual([]);
  });

  it('keeps every section key apart from the core and from the other sections', () => {
    const seen = new Map<string, string>();
    for (const key of Object.keys(uz)) seen.set(key, 'core');
    for (const [name, section] of Object.entries(SECTIONS)) {
      for (const key of Object.keys(section.uz)) {
        expect(seen.get(key), `${name}.${key} clashes with ${seen.get(key)}`).toBeUndefined();
        seen.set(key, name);
        // A section's keys start with its own name, so the owner of a text is obvious
        expect(key.startsWith(`${name}_`), `${name}.${key}`).toBe(true);
      }
    }
  });

  it('fills {{params}} and knows its keys', () => {
    const t = createTranslator('uz');
    expect(t('auth_idleText', { time: '4:59' })).toContain('4:59');
    expect(isMessageKey('nav_today')).toBe(true);
    expect(isMessageKey('nav_unknown')).toBe(false);
  });

  it('uses the straight apostrophe of the partner web for Uzbek words', () => {
    expect(Object.values(DICTIONARIES.uz).filter((text) => /[‘’ʻʼ`]/.test(text))).toEqual([]);
  });
});
