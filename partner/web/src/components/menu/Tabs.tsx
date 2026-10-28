'use client';

import Link from 'next/link';
import { cx, TAB_CLASS, tabTone } from '@rizqim/ui';

// Tabs that are pages (/stoplist and /stoplist/batches). Tabs that switch a panel of the same page: `Tabs` in @rizqim/ui.
export function LinkTabs({ tabs, current, label }: { tabs: ReadonlyArray<{ href: string; label: string }>; current: string; label: string }) {
  return (
    <nav aria-label={label} className="mb-4 flex flex-wrap gap-2">
      {tabs.map((tab) => (
        <Link key={tab.href} href={tab.href} aria-current={tab.href === current ? 'page' : undefined} className={cx(TAB_CLASS, tabTone(tab.href === current))}>
          {tab.label}
        </Link>
      ))}
    </nav>
  );
}
