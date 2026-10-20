'use client';

import { useId } from 'react';
import { fieldClass } from '@rizqim/ui';
import { isLanguage, LANGUAGE_NAMES, LANGUAGES } from '@/i18n';
import { useI18n } from './I18nProvider';

export function LanguageSwitcher({ className }: { className?: string }) {
  const { language, setLanguage, t } = useI18n();
  const id = useId();
  return (
    <div className={className}>
      <label htmlFor={id} className="sr-only">
        {t('language')}
      </label>
      <select
        id={id}
        className={`${fieldClass} text-body`}
        value={language}
        onChange={(event) => isLanguage(event.target.value) && setLanguage(event.target.value)}
      >
        {LANGUAGES.map((code) => (
          <option key={code} value={code}>
            {LANGUAGE_NAMES[code]}
          </option>
        ))}
      </select>
    </div>
  );
}
