// A section of texts owned by one area of the app (auth, team, roles, audit ...). Each area keeps its texts in its own file
// `src/i18n/sections/<area>.ts`, so parallel work never edits the same dictionary. `src/i18n/index.ts` merges every
// section into the core dictionary (uz.ts) and `t('key')` works for all of them.
//
// Uzbek (Latin) only for now. A language is added by giving `Section` a second field and the core dictionary a second
// file, as the partner web does (partner/web/src/i18n): the keys, their prefixes and the {{params}} stay the same.
// Prefix keys with the area (`team_`, `audit_` ...) so they never clash with the core or another section (a test checks it).
export type SectionTexts = Record<string, string>;

export interface Section<T extends SectionTexts> {
  uz: T;
}

export function defineSection<const T extends SectionTexts>(section: Section<T>): Section<T> {
  return section;
}

// The keys of a section, every value a string
export type SectionMessages<S> = S extends Section<infer T> ? { [K in keyof T]: string } : never;
