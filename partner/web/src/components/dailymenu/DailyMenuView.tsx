'use client';

import { useState } from 'react';
import { PageHeader, Tabs } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { DailyMenuPanel } from './DailyMenuPanel';
import { MealSetsPanel } from './MealSetsPanel';

type TabId = 'menu' | 'sets';

// Bugungi menyu and kompleks: the dishes cooked today, and the fixed-price sets the kitchen prepares from several dishes.
export function DailyMenuView() {
  const { t } = useI18n();
  const [tab, setTab] = useState<TabId>('menu');

  return (
    <>
      <PageHeader title={t('dm_title')} subtitle={t('dm_subtitle')} />
      <Tabs
        idPrefix="dm"
        label={t('dm_tabs')}
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'menu', label: t('dm_tabMenu') },
          { id: 'sets', label: t('dm_tabSets') },
        ]}
      />
      <div role="tabpanel" id={`dm-panel-${tab}`} aria-labelledby={`dm-tab-${tab}`}>
        {tab === 'menu' ? <DailyMenuPanel /> : <MealSetsPanel />}
      </div>
    </>
  );
}
