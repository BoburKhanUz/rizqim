'use client';

import { useState } from 'react';
import { Wallet } from 'lucide-react';
import { CardSkeletons, EmptyState, InlineError, PageHeader, Tabs } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { RulesPanel } from './RulesPanel';
import { StatementsPanel } from './StatementsPanel';
import { WorkTimePanel } from './WorkTimePanel';

type TabId = 'rules' | 'worktime' | 'statements';

// "Ish haqi" (P6-23): the wage rules of a branch (each staff member or role gets parts that add up), its staff's work
// time (clock-in/out and corrections) and the pay statement of each period ("Hisob-kitob"). The server works out every
// amount; staff_pay.manage changes things, staff_pay.view only reads.
export function StaffPayView() {
  const { t } = useI18n();
  const choice = useBranchChoice();
  const access = useCan();
  const canManage = access.can('staff_pay.manage');
  const [tab, setTab] = useState<TabId>('rules');

  return (
    <>
      <PageHeader title={t('sp_title')} subtitle={t('sp_subtitle')} />
      {choice.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={Wallet} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-4">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="min-w-52 max-w-xs" />
          <Tabs
            idPrefix="sp"
            label={t('sp_tabs')}
            value={tab}
            onChange={setTab}
            tabs={[
              { id: 'rules', label: t('sp_tabRules') },
              { id: 'worktime', label: t('sp_tabWorkTime') },
              { id: 'statements', label: t('sp_tabStatements') },
            ]}
          />
          <div role="tabpanel" id={`sp-panel-${tab}`} aria-labelledby={`sp-tab-${tab}`}>
            {tab === 'rules' ? (
              <RulesPanel key={choice.branchId} branchId={choice.branchId} canManage={canManage} />
            ) : tab === 'worktime' ? (
              <WorkTimePanel key={choice.branchId} branchId={choice.branchId} canManage={canManage} />
            ) : (
              <StatementsPanel key={choice.branchId} branchId={choice.branchId} canManage={canManage} />
            )}
          </div>
        </div>
      )}
    </>
  );
}
