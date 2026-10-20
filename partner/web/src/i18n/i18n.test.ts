import { describe, expect, it } from 'vitest';
import { createTranslator, isMessageKey, LANGUAGES } from '.';
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
    expect(isMessageKey('status_new')).toBe(true);
    expect(isMessageKey('status_unknown')).toBe(false);
  });
});
