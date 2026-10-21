// A section of texts owned by one area of the panel (menu, management, reports, orders...). Each area keeps its texts in
// its own file `src/i18n/sections/<area>.ts`, so parallel work never edits the same dictionary. `src/i18n/index.ts`
// merges every section into the core dictionaries (uz.ts, ru.ts, en.ts) and `t('key')` works for all of them.
//
// Rules: Uzbek (Latin) first; ru and en must have exactly the uz keys (the type checks it); prefix keys with the area
// (`menu_`, `staff_`, `report_`...) so they never clash with the core or another section (a test checks it).
export type SectionTexts = Record<string, string>;

export interface Section<T extends SectionTexts> {
  uz: T;
  ru: { [K in keyof T]: string };
  en: { [K in keyof T]: string };
}

export function defineSection<const T extends SectionTexts>(section: Section<T>): Section<T> {
  return section;
}

// The keys of a section, every value a string
export type SectionMessages<S> = S extends Section<infer T> ? { [K in keyof T]: string } : never;
