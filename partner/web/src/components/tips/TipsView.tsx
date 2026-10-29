'use client';

import { useState } from 'react';
import { HandCoins } from 'lucide-react';
import { CardSkeletons, EmptyState, InlineError, PageHeader, Tabs } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { PoolPanel } from './PoolPanel';
import { ReportPanel } from './ReportPanel';
import { ServiceChargePanel } from './ServiceChargePanel';
import { SettingsPanel } from './SettingsPanel';

type TabId = 'report' | 'pool' | 'settings' | 'service';

// "Choy puli va xizmat haqi" (P6-22): the tips of a branch (not revenue: never on the receipt, never in the sales), the
// pool's split per day or shift, the branch's tip settings (personal or pool, the split rule, the roles) and the
// service charge variants (by order type, large group, cover, the bill's notice). The server does every sum; reading
// needs reports.view, corrections and splits tips.manage, the settings settings.manage.
export function TipsView() {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const access = useCan();
  const canRead = access.can('reports.view');
  const canManage = access.can('tips.manage');
  const canSettings = access.can('settings.manage');
  const tabs = [
    ...(canRead ? [{ id: 'report' as const, label: t('tips_tabReport') }, { id: 'pool' as const, label: t('tips_tabPool') }] : []),
    ...(canSettings ? [{ id: 'settings' as const, label: t('tips_tabSettings') }, { id: 'service' as const, label: t('tips_tabService') }] : []),
  ];
  const [chosen, setTab] = useState<TabId | null>(null);
  const tab = chosen !== null && tabs.some((item) => item.id === chosen) ? chosen : (tabs[0]?.id ?? null);

  return (
    <>
      <PageHeader title={t('tips_title')} subtitle={t('tips_subtitle')} />
      {choice.isPending || !access.ready ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={HandCoins} title={t('menu_no_branches')} />
      ) : tab === null ? (
        <EmptyState icon={HandCoins} title={t('tips_noAccess')} />
      ) : (
        <div className="flex flex-col gap-4">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="min-w-52 max-w-xs" />
          <Tabs idPrefix="tips" label={t('tips_tabs')} value={tab} onChange={setTab} tabs={tabs} />
          <div role="tabpanel" id={`tips-panel-${tab}`} aria-labelledby={`tips-tab-${tab}`}>
            {tab === 'report' ? (
              <ReportPanel key={choice.branchId} branchId={choice.branchId} canManage={canManage} />
            ) : tab === 'pool' ? (
              <PoolPanel key={choice.branchId} branchId={choice.branchId} canManage={canManage} />
            ) : tab === 'settings' ? (
              <SettingsPanel key={choice.branchId} branchId={choice.branchId} />
            ) : (
              <ServiceChargePanel key={choice.branchId} branchId={choice.branchId} />
            )}
          </div>
        </div>
      )}
    </>
  );
}
