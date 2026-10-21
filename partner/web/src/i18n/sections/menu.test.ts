import { describe, expect, it } from 'vitest';
import { menu } from './menu';

describe('the menu section of texts', () => {
  it('has the same keys in uz, ru and en, all with the menu_ prefix and no empty text', () => {
    const keys = Object.keys(menu.uz).sort();
    expect(Object.keys(menu.ru).sort()).toEqual(keys);
    expect(Object.keys(menu.en).sort()).toEqual(keys);
    expect(keys.filter((key) => !key.startsWith('menu_'))).toEqual([]);
    for (const texts of [menu.uz, menu.ru, menu.en]) expect(Object.values(texts).filter((text) => text.trim() === '')).toEqual([]);
  });

  it('keeps the {{params}} of a text in its translations', () => {
    const params = (text: string) => (text.match(/\{\{\w+\}\}/g) ?? []).sort();
    for (const key of Object.keys(menu.uz) as Array<keyof typeof menu.uz>) {
      expect(params(menu.ru[key]), key).toEqual(params(menu.uz[key]));
      expect(params(menu.en[key]), key).toEqual(params(menu.uz[key]));
    }
  });
});
