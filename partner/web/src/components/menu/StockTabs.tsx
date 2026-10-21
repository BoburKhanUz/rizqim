'use client';

import { useT } from '../I18nProvider';
import { LinkTabs } from './Tabs';

// The two screens of a branch's stock: what is stopped, and the pots ("qozon") being cooked
export function StockTabs({ current }: { current: 'stoplist' | 'batches' }) {
  const t = useT();
  return (
    <LinkTabs
      label={t('menu_stop_sections')}
      current={current === 'stoplist' ? '/stoplist' : '/stoplist/batches'}
      tabs={[
        { href: '/stoplist', label: t('nav_stoplist') },
        { href: '/stoplist/batches', label: t('menu_batch_title') },
      ]}
    />
  );
}
