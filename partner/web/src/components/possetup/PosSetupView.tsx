'use client';

import { useState } from 'react';
import { Printer } from 'lucide-react';
import { CardSkeletons, EmptyState, InlineError, PageHeader, Tabs } from '@rizqim/ui';
import { usePosFormat } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { BookingRulesPanel } from './BookingRulesPanel';
import { BranchSettingsPanel } from './BranchSettingsPanel';
import { FormatPanel } from './FormatPanel';
import { PrintersPanel } from './PrintersPanel';
import { RoutesPanel } from './RoutesPanel';
import { StationsPanel } from './StationsPanel';
import { WaitlistPanel } from './WaitlistPanel';

type Tab = 'format' | 'stations' | 'routes' | 'printers' | 'settings' | 'rules' | 'waitlist';
// P6-11: the branch's format comes first (it decides the till's mode and modules)
const TABS: Tab[] = ['format', 'stations', 'routes', 'printers', 'settings'];
// P6-24/P6-26: the restaurant floor's tabs appear with their modules (table_booking, hostess); a choyxona's page stays as it was
const MODULE_TABS: Array<{ id: Tab; module: string }> = [
  { id: 'rules', module: 'table_booking' },
  { id: 'waitlist', module: 'hostess' },
];

// /pos-setup (settings.manage, the owner): the branch's format (P6-11), kitchen stations, which dishes go to which station, printers and the branch's POS
// settings. Everything belongs to one branch: the top bar's, or the page's own picker when the top bar says "all".
export function PosSetupView() {
  const { t } = useI18n();
  const access = useCan();
  const choice = useBranchChoice();
  const [tab, setTab] = useState<Tab>('format');
  const format = usePosFormat(choice.branchId);
  const modules = new Set<string>(format.data?.modules ?? []);
  const tabs = [...TABS, ...MODULE_TABS.filter((entry) => modules.has(entry.module)).map((entry) => entry.id)];
  // The module a tab needs went off (in the Format tab): back to the first
  const shown: Tab = tabs.includes(tab) ? tab : 'format';

  if (access.ready && !access.can('settings.manage')) {
    return (
      <>
        <PageHeader title={t('nav_possetup')} />
        <EmptyState icon={Printer} title={t('mgmt_forbidden')} text={t('pos_ownerOnly')} />
      </>
    );
  }

  return (
    <>
      <PageHeader title={t('nav_possetup')} subtitle={t('pos_subtitle')} />
      {choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(null, t)} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.isPending || !access.ready ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : choice.branchId === null ? (
        <EmptyState icon={Printer} title={t('pos_noBranch')} text={t('pos_noBranchText')} />
      ) : (
        <div className="flex flex-col gap-4">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="sm:max-w-sm" />
          <Tabs idPrefix="pos" label={t('pos_tabs')} value={shown} onChange={setTab} tabs={tabs.map((id) => ({ id, label: id === 'rules' ? t('hst_tab_rules') : id === 'waitlist' ? t('hst_tab_waitlist') : t(`pos_tab_${id}`) }))} />
          <div role="tabpanel" id={`pos-panel-${shown}`} aria-labelledby={`pos-tab-${shown}`}>
            {shown === 'format' && <FormatPanel branchId={choice.branchId} />}
            {shown === 'stations' && <StationsPanel branchId={choice.branchId} />}
            {shown === 'routes' && <RoutesPanel branchId={choice.branchId} />}
            {shown === 'printers' && <PrintersPanel branchId={choice.branchId} />}
            {shown === 'settings' && <BranchSettingsPanel branchId={choice.branchId} />}
            {shown === 'rules' && <BookingRulesPanel branchId={choice.branchId} enabled={modules.has('table_booking')} />}
            {shown === 'waitlist' && <WaitlistPanel branchId={choice.branchId} enabled={modules.has('hostess')} />}
          </div>
        </div>
      )}
    </>
  );
}
