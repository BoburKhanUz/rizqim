import { SECTIONS, type AllSectionMessages } from './sections';
import { uz, type Messages as CoreMessages } from './uz';

// The core texts (uz.ts: the shell, shared words) plus every area's section (src/i18n/sections/<area>.ts, see
// sections/define.ts)
export type Messages = CoreMessages & AllSectionMessages;
export type MessageKey = keyof Messages;
// Uzbek (Latin) only for now; ru follows the partner web's structure (a second dictionary per language)
export type Language = 'uz';

export const LANGUAGES: readonly Language[] = ['uz'];
export const DEFAULT_LANGUAGE: Language = 'uz';

function merge(): Messages {
  const merged: Record<string, string> = { ...uz };
  for (const section of Object.values(SECTIONS)) Object.assign(merged, section.uz);
  return merged as Messages;
}

export const DICTIONARIES: Record<Language, Messages> = { uz: merge() };

export type Translate = (key: MessageKey, params?: Record<string, string | number>) => string;

// A translator for `language`: t('auth_idleText', { time: '4:59' }) replaces {{time}}.
export function createTranslator(language: Language): Translate {
  const messages = DICTIONARIES[language];
  return (key, params) => {
    const text = messages[key];
    return params ? text.replace(/\{\{(\w+)\}\}/g, (_, name: string) => String(params[name] ?? '')) : text;
  };
}

export const isMessageKey = (key: string): key is MessageKey => Object.hasOwn(DICTIONARIES.uz, key);
