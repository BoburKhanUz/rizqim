'use client';

import { useT } from '../I18nProvider';
import { LinkTabs } from './Tabs';

const HREF = { stoplist: '/stoplist', batches: '/stoplist/batches', ingredients: '/stoplist/ingredients' } as const;

// The screens of a branch's stock: what is stopped, the pots ("qozon") being cooked, and (P6-55) the ingredients whose
// stop closes every dish that uses them
export function StockTabs({ current }: { current: 'stoplist' | 'batches' | 'ingredients' }) {
  const t = useT();
  return (
    <LinkTabs
      label={t('menu_stop_sections')}
      current={HREF[current]}
      tabs={[
        { href: HREF.stoplist, label: t('nav_stoplist') },
        { href: HREF.batches, label: t('menu_batch_title') },
        { href: HREF.ingredients, label: t('dk_ingTab') },
      ]}
    />
  );
}
