// Component files that do not follow the design system yet (raw slate/amber/white classes, the legacy staff aliases
// bg-bg/text-hint/bg-primary, text under 12px, native alert/confirm). designSystem.test.ts skips them and checks every
// other .tsx file.
//
// MIGRATING A FILE: switch it to the tokens and components of docs/DESIGN.md, delete its line here, run `npm test`.
// The test fails if a file listed here is already clean, so remove it in the same change. Never add a file.

export const NOT_YET_MIGRATED: readonly string[] = [
];
