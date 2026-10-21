import { fillParams, localize, type Localized } from '@rizqim/staff';
import { useLanguageStore } from '../store/languageStore';

// The words of one staff screen: a table of { uz, ru, en? } entries (en reads Russian where it is not written, as everywhere
// in the staff screens) and a function that picks the one in the user's language, filling {{name}} placeholders.
//
//   const TEXT = { title: { uz: 'Stop-list', ru: 'Стоп-лист' } } satisfies Record<string, Localized>;
//   const tx = useStaffText(TEXT);   tx('title')
export function useStaffText<T extends Record<string, Localized>>(table: T) {
  const language = useLanguageStore((state) => state.language);
  return (key: keyof T, params?: Record<string, string | number>): string => fillParams(localize(table[key], language), params);
}
