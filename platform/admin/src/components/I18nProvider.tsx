'use client';

import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { createTranslator, type Language, type Translate } from '@/i18n';

interface I18nValue {
  language: Language;
  t: Translate;
}

const I18nContext = createContext<I18nValue | null>(null);

export function I18nProvider({ language, children }: { language: Language; children: ReactNode }) {
  const value = useMemo<I18nValue>(() => ({ language, t: createTranslator(language) }), [language]);
  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nValue {
  const value = useContext(I18nContext);
  if (!value) throw new Error('useI18n must be used inside <I18nProvider>');
  return value;
}

export const useT = (): Translate => useI18n().t;
