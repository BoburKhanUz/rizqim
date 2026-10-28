'use client';

import { useMemo, useState } from 'react';
import { ShieldAlert } from 'lucide-react';
import { CardSkeletons, EmptyState, PageHeader, Tabs } from '@rizqim/ui';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { BannersTab } from './BannersTab';
import { CategoriesTab } from './CategoriesTab';
import { CodesTab } from './CodesTab';
import { isTab, TAB_LABEL, TABS, type MarketingTab } from './marketingModel';

// Marketing (P4-31, docs/PHASE4_PLAN.md 4.7): the banners and promotions of the Mini App's home screen, the promo codes with
// how they are used, and the categories with their order. Everything here needs marketing.view; changes marketing.manage.
// ?tab= opens a tab.

export function MarketingView({ initialTab }: { initialTab?: string }) {
  const t = useT();
  const { ready, can } = useCan();
  const [tab, setTab] = useState<MarketingTab>(isTab(initialTab) ? initialTab : 'banners');
  const tabs = useMemo(() => TABS.map((id) => ({ id, label: t(TAB_LABEL[id]) })), [t]);

  // Until the member is known no tab may start loading (it could be one they may not open)
  if (!ready) {
    return (
      <>
        <PageHeader title={t('marketing_title')} subtitle={t('marketing_subtitle')} />
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      </>
    );
  }

  if (!can('marketing.view')) {
    return (
      <>
        <PageHeader title={t('nav_marketing')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const choose = (next: MarketingTab) => {
    setTab(next);
    window.history.replaceState(null, '', `?tab=${next}`);
  };

  return (
    <>
      <PageHeader title={t('marketing_title')} subtitle={t('marketing_subtitle')} />
      <Tabs tabs={tabs} value={tab} onChange={choose} label={t('marketing_tabs')} idPrefix="marketing" />
      <div role="tabpanel" id={`marketing-panel-${tab}`} aria-labelledby={`marketing-tab-${tab}`}>
        {tab === 'banners' && <BannersTab />}
        {tab === 'codes' && <CodesTab />}
        {tab === 'categories' && <CategoriesTab />}
      </div>
    </>
  );
}
