import { afterEach, describe, expect, it } from 'vitest';
import { translations, useLanguageStore } from './languageStore';

const keysOf = (language: keyof typeof translations) => Object.keys(translations[language]).sort();
const placeholders = (text: string) => (text.match(/\{\{\w+\}\}/g) ?? []).sort();

afterEach(() => {
  useLanguageStore.setState({ language: 'uz' });
});

describe('the translation table', () => {
  it('has the same keys in uz, ru and en', () => {
    expect(keysOf('ru')).toEqual(keysOf('uz'));
    expect(keysOf('en')).toEqual(keysOf('uz'));
  });

  it('has no empty phrase (an empty one would fall back to Uzbek)', () => {
    for (const language of ['uz', 'ru', 'en'] as const) {
      for (const [key, text] of Object.entries(translations[language])) expect(text, `${language}.${key}`).not.toBe('');
    }
  });

  it('uses the same {{placeholders}} in every language of a phrase', () => {
    for (const [key, text] of Object.entries(translations.uz)) {
      expect(placeholders(translations.ru[key as keyof typeof translations.uz]), key).toEqual(placeholders(text));
      expect(placeholders(translations.en[key as keyof typeof translations.uz]), key).toEqual(placeholders(text));
    }
  });

  // The words of the customer's order, profile, complaint and notification screens used to live in lib/customerCopy.ts
  it('holds the customer screens\' phrases too, in all three languages', () => {
    const keys = ['ordersSubtitle', 'rateTitle', 'profileTitle', 'addressDeleteConfirm', 'complaintType_WRONG_ITEM', 'complaintStatus_owner_replied', 'notificationsEmptyStaff'] as const;
    const texts = { uz: 'Mening profilim', ru: 'Мой профиль', en: 'My profile' };

    for (const language of ['uz', 'ru', 'en'] as const) {
      useLanguageStore.setState({ language });
      for (const key of keys) expect(useLanguageStore.getState().t(key), `${language}.${key}`).not.toBe(key);
      expect(useLanguageStore.getState().t('profileTitle')).toBe(texts[language]);
    }
  });
});

describe('t', () => {
  it('fills {{placeholders}}', () => {
    useLanguageStore.setState({ language: 'ru' });

    expect(useLanguageStore.getState().t('profileOrdersCount', { n: 3 })).toBe('Заказов: 3');
  });

  it('answers with the key itself for a phrase it does not have', () => {
    expect(useLanguageStore.getState().t('complaintType_NOT_A_TYPE' as never)).toBe('complaintType_NOT_A_TYPE');
  });
});
