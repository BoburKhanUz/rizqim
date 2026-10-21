import type { Language } from '@rizqim/ui';

export type { Language };

// A text in the languages of the staff screens. uz and ru are always written; en only where an app already shows one
// (the Mini App has an English interface; the staff screens read Russian there, so en falls back to ru).
export interface Localized {
  uz: string;
  ru: string;
  en?: string;
}

export const LANGUAGES: readonly Language[] = ['uz', 'ru', 'en'];

export const isLanguage = (value: unknown): value is Language => value === 'uz' || value === 'ru' || value === 'en';

// The text in `language`. A language the package does not know reads Uzbek, the default of both apps.
export function localize(text: Localized, language: Language | string): string {
  if (language === 'ru') return text.ru;
  if (language === 'en') return text.en ?? text.ru;
  return text.uz;
}

// {{name}} placeholders of a message, replaced by `params`.
export function fillParams(text: string, params?: Record<string, string | number>): string {
  return params ? text.replace(/\{\{(\w+)\}\}/g, (_, name: string) => String(params[name] ?? '')) : text;
}
