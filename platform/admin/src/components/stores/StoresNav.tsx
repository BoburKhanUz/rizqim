'use client';

import Link from 'next/link';
import { cx, TAB_CLASS, tabTone } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { useT } from '../I18nProvider';

// The pages of Ovqatxonalar, as link tabs: the list, the pipeline, tasks, announcements
const TABS: ReadonlyArray<{ key: 'list' | 'pipeline' | 'tasks' | 'broadcasts'; href: string; label: MessageKey }> = [
  { key: 'list', href: '/stores', label: 'stores_tab_list' },
  { key: 'pipeline', href: '/stores/applications', label: 'stores_tab_pipeline' },
  { key: 'tasks', href: '/stores/tasks', label: 'stores_tab_tasks' },
  { key: 'broadcasts', href: '/stores/broadcasts', label: 'stores_tab_broadcasts' },
];

export function StoresNav({ current }: { current: (typeof TABS)[number]['key'] }) {
  const t = useT();
  return (
    <nav aria-label={t('stores_tabs')} className="mb-4 flex flex-wrap gap-2">
      {TABS.map((tab) => (
        <Link key={tab.key} href={tab.href} aria-current={tab.key === current ? 'page' : undefined} className={cx(TAB_CLASS, tabTone(tab.key === current))}>
          {t(tab.label)}
        </Link>
      ))}
    </nav>
  );
}
