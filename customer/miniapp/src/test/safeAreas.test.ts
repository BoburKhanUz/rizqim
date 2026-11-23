/// <reference types="node" />
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import dialog from '../../../../packages/ts/ui/src/components/Dialog.tsx?raw';
import app from '../App.tsx?raw';
import storeDetail from '../pages/StoreDetail.tsx?raw';

// Read from disk: the test run turns CSS imports (even ?raw) into empty modules (vitest `css: false`)
const read = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8');
const css = read('../index.css');
const shared = read('../../../../packages/ts/ui/src/styles.css');

// jsdom computes neither env() nor max(), so the safe-area formulas (index.css) are checked as written: Telegram's
// device safe area plus its content safe area (Bot API 8.0 full-screen), with env(safe-area-inset-*) for clients
// before 8.0, on every side; and the layout pieces that must use them.

const declaration = (source: string, name: string) => new RegExp(`${name}:\\s*([^;]+);`).exec(source)?.[1].replace(/\s+/g, ' ');

describe('safe areas (index.css)', () => {
  it.each(['top', 'bottom', 'left', 'right'])('%s: the device safe area plus the band of Telegram\'s controls', (side) => {
    expect(declaration(css, `--rz-safe-${side}`)).toBe(
      `max(env(safe-area-inset-${side}, 0px), var(--tg-safe-area-inset-${side}, 0px), var(--rz-tg-safe-${side}, 0px))`,
    );
    expect(declaration(css, `--rz-content-${side}`)).toBe(
      `max(var(--tg-content-safe-area-inset-${side}, 0px), var(--rz-tg-content-${side}, 0px))`,
    );
    expect(declaration(css, `--rz-inset-${side}`)).toBe(`calc(var(--rz-safe-${side}) + var(--rz-content-${side}))`);
  });

  it('gives the shared package defaults for the hosts without Telegram (partner web)', () => {
    for (const side of ['top', 'left', 'right']) expect(declaration(shared, `--rz-inset-${side}`)).toBe(`env(safe-area-inset-${side}, 0px)`);
  });

  it('keeps the shell, sticky bars and dialogs clear of the top band', () => {
    expect(css).toMatch(/\.pt-inset \{\s*padding-top: var\(--rz-inset-top\);/);
    expect(css).toMatch(/\.sticky-top \{\s*position: sticky;\s*top: var\(--rz-inset-top\);/);
    expect(app).toMatch(/pb-dock pt-inset px-inset/);
    expect(app).toMatch(/top-inset-band[^>]*h-\[var\(--rz-inset-top\)\]/);
    expect(storeDetail).toMatch(/sticky-top z-10/);
    expect(storeDetail).not.toMatch(/sticky top-0/);
    expect(dialog).toMatch(/pt-\[calc\(var\(--rz-inset-top\)\+12px\)\]/);
    expect(dialog).toMatch(/max-h-\[min\(90vh,100%\)\]/);
  });
});
