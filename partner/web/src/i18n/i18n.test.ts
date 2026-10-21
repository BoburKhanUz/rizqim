import { describe, expect, it } from 'vitest';
import { createTranslator, DICTIONARIES, isMessageKey, LANGUAGES } from '.';
import { SECTIONS } from './sections';
import { defineSection } from './sections/define';
import { uz } from './uz';
import { ru } from './ru';
import { en } from './en';

describe('i18n', () => {
  it('has the same keys in every language and no empty text', () => {
    for (const messages of [ru, en]) expect(Object.keys(messages).sort()).toEqual(Object.keys(uz).sort());
    for (const messages of [uz, ru, en]) expect(Object.values(messages).filter((text) => text.trim() === '')).toEqual([]);
  });

  it('keeps the {{params}} of a text in its translations', () => {
    const params = (text: string) => (text.match(/\{\{\w+\}\}/g) ?? []).sort();
    for (const key of Object.keys(uz) as Array<keyof typeof uz>) {
      expect(params(ru[key]), key).toEqual(params(uz[key]));
      expect(params(en[key]), key).toEqual(params(uz[key]));
    }
  });

  it('fills params and knows its keys', () => {
    expect(createTranslator('uz')('minutes', { n: 12 })).toBe('12 daq');
    expect(createTranslator('ru')('minutes', { n: 12 })).toBe('12 мин');
    expect(LANGUAGES).toEqual(['uz', 'ru', 'en']);
    expect(isMessageKey('role_owner')).toBe(true);
    expect(isMessageKey('role_unknown')).toBe(false);
  });

  describe('sections', () => {
    const params = (text: string) => (text.match(/\{\{\w+\}\}/g) ?? []).sort();

    it('merges every section into each language', () => {
      for (const [name, section] of Object.entries(SECTIONS)) {
        for (const language of LANGUAGES) {
          for (const [key, text] of Object.entries(section[language])) expect(DICTIONARIES[language][key as keyof typeof uz], `${name}.${key}`).toBe(text);
        }
      }
      expect(createTranslator('ru')('board_title')).toBe('Доска заказов');
      expect(isMessageKey('board_title')).toBe(true);
    });

    it('has the same keys and {{params}} in uz, ru and en, and no empty text', () => {
      for (const [name, section] of Object.entries(SECTIONS)) {
        const keys = Object.keys(section.uz).sort();
        expect(Object.keys(section.ru).sort(), name).toEqual(keys);
        expect(Object.keys(section.en).sort(), name).toEqual(keys);
        for (const key of keys) {
          const uzText = (section.uz as Record<string, string>)[key] ?? '';
          for (const language of ['ru', 'en'] as const) {
            const text = (section[language] as Record<string, string>)[key] ?? '';
            expect(text.trim(), `${name}.${language}.${key}`).not.toBe('');
            expect(params(text), `${name}.${language}.${key}`).toEqual(params(uzText));
          }
          expect(uzText.trim(), `${name}.uz.${key}`).not.toBe('');
        }
      }
    });

    it('never reuses a key of the core or of another section', () => {
      const owner = new Map<string, string>(Object.keys(uz).map((key) => [key, 'core']));
      for (const [name, section] of Object.entries(SECTIONS)) {
        for (const key of Object.keys(section.uz)) {
          expect(owner.get(key), `${name}.${key} is already in ${owner.get(key)}`).toBeUndefined();
          owner.set(key, name);
        }
      }
    });

    it('defineSection keeps the texts as given', () => {
      const example = defineSection({ uz: { ex_hi: 'Salom {{name}}' }, ru: { ex_hi: 'Привет {{name}}' }, en: { ex_hi: 'Hi {{name}}' } });
      expect(example.ru.ex_hi).toBe('Привет {{name}}');
    });
  });
});
