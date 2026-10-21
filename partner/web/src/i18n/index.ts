import { en } from './en';
import { ru } from './ru';
import { SECTIONS, type AllSectionMessages } from './sections';
import { uz, type Messages as CoreMessages } from './uz';

// The core texts (uz.ts, ru.ts, en.ts: the shell, sign-in, shared words) plus every area's section
// (src/i18n/sections/<area>.ts, see sections/define.ts)
export type Messages = CoreMessages & AllSectionMessages;
export type MessageKey = keyof Messages;
export type Language = 'uz' | 'ru' | 'en';

export const LANGUAGES: readonly Language[] = ['uz', 'ru', 'en'];
export const DEFAULT_LANGUAGE: Language = 'uz';
// Not a secret, so a plain cookie: the server reads it to render the right language at once (no flash)
export const LANGUAGE_COOKIE = 'rz_lang';

export const LANGUAGE_NAMES: Record<Language, string> = { uz: "O'zbekcha", ru: 'Русский', en: 'English' };

export const isLanguage = (value: unknown): value is Language => value === 'uz' || value === 'ru' || value === 'en';

const CORE: Record<Language, CoreMessages> = { uz, ru, en };

function merge(language: Language): Messages {
  const merged: Record<string, string> = { ...CORE[language] };
  for (const section of Object.values(SECTIONS)) Object.assign(merged, section[language]);
  return merged as Messages;
}

export const DICTIONARIES: Record<Language, Messages> = { uz: merge('uz'), ru: merge('ru'), en: merge('en') };

export type Translate = (key: MessageKey, params?: Record<string, string | number>) => string;

// A translator for `language`: t('minutes', { n: 5 }) replaces {{n}}.
export function createTranslator(language: Language): Translate {
  const messages = DICTIONARIES[language];
  return (key, params) => {
    const text = messages[key];
    return params ? text.replace(/\{\{(\w+)\}\}/g, (_, name: string) => String(params[name] ?? '')) : text;
  };
}

export const isMessageKey = (key: string): key is MessageKey => Object.hasOwn(DICTIONARIES.uz, key);
