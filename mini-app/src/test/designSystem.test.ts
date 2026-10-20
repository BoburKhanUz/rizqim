import { describe, expect, it } from 'vitest';
import { NOT_YET_MIGRATED } from './designSystemAllowlist';

// The design system's guard (docs/DESIGN.md, "Qilmang"). Every component file of the app must use the design tokens
// and shared components; the files listed in designSystemAllowlist.ts are the ones not migrated yet and are skipped.
// When you migrate a file, delete it from the list: this test then keeps it clean. A listed file that is already
// clean fails the test too, so the list only ever shrinks.
//
// A line that really needs an exception (e.g. white text on a photo) ends with the comment `design-ok: <why>`.

const sources = import.meta.glob<string>(['../**/*.tsx', '!../**/*.test.tsx'], { query: '?raw', import: 'default', eager: true });

// '../pages/Home.tsx' -> 'src/pages/Home.tsx'
const files = Object.entries(sources).map(([path, source]) => ({ path: path.replace(/^\.\.\//, 'src/'), source }));

const PALETTE =
  'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
const UTILITY = 'bg|text|border|border-[trblxy]|from|via|to|ring|ring-offset|shadow|fill|stroke|divide|outline|placeholder|decoration|caret|accent';

interface Rule {
  name: string;
  pattern: RegExp;
  fix: string;
}

const RULES: Rule[] = [
  {
    name: 'raw Tailwind palette colour',
    pattern: new RegExp(`(?<![\\w-])(?:[\\w-]+:)*(?:${UTILITY})-(?:${PALETTE})-\\d{2,3}(?![\\w-])`, 'g'),
    fix: 'use a token: bg-surface, text-fg, text-fg-muted, bg-brand, bg-success-soft ... (docs/DESIGN.md)',
  },
  {
    name: 'raw white/black',
    pattern: new RegExp(`(?<![\\w-])(?:[\\w-]+:)*(?:${UTILITY})-(?:white|black)(?![\\w-])`, 'g'),
    fix: 'bg-surface / text-on-brand / text-fg; a scrim is bg-scrim',
  },
  {
    name: 'legacy staff alias',
    pattern: /(?<![\w-])(?:[\w-]+:)*(?:bg-bg|bg-secondary|text-text|text-hint|border-hint|bg-hint|(?:bg|text|border|from|via|to|ring|shadow)-primary)(?![\w-])/g,
    fix: 'bg-bg -> bg-surface, bg-secondary -> bg-page, text-text -> text-fg, text-hint -> text-fg-muted, *-primary -> *-brand',
  },
  {
    name: 'arbitrary colour',
    pattern: /(?<![\w-])(?:[\w-]+:)*(?:bg|text|border|from|via|to|ring|shadow|fill|stroke)-\[(?:#|rgb|hsl)[^\]]*\]/g,
    fix: 'add a token instead of a hex value',
  },
  {
    name: 'text smaller than 12px',
    pattern: /(?<![\w-])text-\[(?:[0-9]|1[01])(?:\.\d+)?px\]|(?<![\w-])text-\[0?\.[0-6]\d*rem\]/g,
    fix: 'text-caption (12px) is the smallest size',
  },
  {
    name: 'native alert/confirm',
    pattern: /(?<![\w.])(?:window\.)?(?:alert|confirm)\(/g,
    fix: 'await showAlert(...) / await showConfirm(...) from lib/dialogs.ts',
  },
];

interface Violation {
  line: number;
  rule: string;
  match: string;
}

function violationsOf(source: string): Violation[] {
  const found: Violation[] = [];
  source.split('\n').forEach((text, index) => {
    if (text.includes('design-ok:')) return;
    for (const rule of RULES) {
      for (const match of text.matchAll(rule.pattern)) found.push({ line: index + 1, rule: rule.name, match: match[0] });
    }
  });
  return found;
}

const allowlisted = new Set<string>(NOT_YET_MIGRATED);

describe('design system guard', () => {
  it('finds the component files', () => {
    expect(files.length).toBeGreaterThan(20);
    expect(files.some((file) => file.path === 'src/App.tsx')).toBe(true);
  });

  it.each(files.filter((file) => !allowlisted.has(file.path)).map((file) => [file.path, file.source] as const))(
    '%s uses design tokens only',
    (_path, source) => {
      const report = violationsOf(source).map(
        (v) => `  line ${v.line}: ${v.rule} "${v.match}" -> ${RULES.find((rule) => rule.name === v.rule)?.fix}`,
      );
      expect(report, `\n${report.join('\n')}\n`).toEqual([]);
    },
  );

  it('lists only files that exist and still need migrating (delete a file from the list once it is clean)', () => {
    const paths = new Set(files.map((file) => file.path));
    const stale = NOT_YET_MIGRATED.filter((path: string) => !paths.has(path) || violationsOf(sources[path.replace(/^src\//, '../')]).length === 0);
    expect(stale).toEqual([]);
  });

  it('catches what it is meant to catch', () => {
    const rules = (code: string) => violationsOf(code).map((v) => v.rule);
    expect(rules('<p className="text-slate-400 hover:bg-amber-500/10">')).toEqual(['raw Tailwind palette colour', 'raw Tailwind palette colour']);
    expect(rules('<p className="bg-white text-white/80">')).toEqual(['raw white/black', 'raw white/black']);
    expect(rules('<p className="bg-bg text-hint bg-primary/10 shadow-primary/20">')).toEqual(Array(4).fill('legacy staff alias'));
    expect(rules('<p className="text-[10px] text-[11px]">')).toEqual(['text smaller than 12px', 'text smaller than 12px']);
    expect(rules("alert('x'); window.confirm('y')")).toEqual(['native alert/confirm', 'native alert/confirm']);
    expect(rules('<p className="bg-[#0e1621]">')).toEqual(['arbitrary colour']);
    // tokens, bigger arbitrary sizes, showAlert and the escape hatch are fine
    expect(rules('<p className="bg-surface text-fg-muted bg-brand-soft text-on-brand text-[13px]">')).toEqual([]);
    expect(rules("await showAlert('x'); showConfirm('y')")).toEqual([]);
    expect(rules('<p className="text-white"> {/* design-ok: on a photo */}')).toEqual([]);
  });
});
