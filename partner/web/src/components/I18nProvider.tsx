'use client';

import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { createTranslator, LANGUAGE_COOKIE, type Language, type Translate } from '@/i18n';

interface I18nValue {
  language: Language;
  t: Translate;
  setLanguage: (language: Language) => void;
}

const I18nContext = createContext<I18nValue | null>(null);

// The language comes from the server (the rz_lang cookie), so the first paint is already in the right language
export function I18nProvider({ language, children }: { language: Language; children: ReactNode }) {
  const router = useRouter();
  const setLanguage = useCallback(
    (next: Language) => {
      document.cookie = `${LANGUAGE_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;
      router.refresh();
    },
    [router],
  );
  const value = useMemo<I18nValue>(() => ({ language, t: createTranslator(language), setLanguage }), [language, setLanguage]);
  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18nValue {
  const value = useContext(I18nContext);
  if (!value) throw new Error('useI18n must be used inside <I18nProvider>');
  return value;
}

export const useT = (): Translate => useI18n().t;
