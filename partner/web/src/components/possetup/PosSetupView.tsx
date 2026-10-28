'use client';

import { useState } from 'react';
import { Printer } from 'lucide-react';
import { CardSkeletons, EmptyState, InlineError, PageHeader, Tabs } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { BranchSettingsPanel } from './BranchSettingsPanel';
import { PrintersPanel } from './PrintersPanel';
import { RoutesPanel } from './RoutesPanel';
import { StationsPanel } from './StationsPanel';

type Tab = 'stations' | 'routes' | 'printers' | 'settings';
const TABS: Tab[] = ['stations', 'routes', 'printers', 'settings'];

// /pos-setup (settings.manage, the owner): kitchen stations, which dishes go to which station, printers and the branch's POS
// settings. Everything belongs to one branch: the top bar's, or the page's own picker when the top bar says "all".
export function PosSetupView() {
  const { t } = useI18n();
  const access = useCan();
  const choice = useBranchChoice();
  const [tab, setTab] = useState<Tab>('stations');

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
          <Tabs idPrefix="pos" label={t('pos_tabs')} value={tab} onChange={setTab} tabs={TABS.map((id) => ({ id, label: t(`pos_tab_${id}`) }))} />
          <div role="tabpanel" id={`pos-panel-${tab}`} aria-labelledby={`pos-tab-${tab}`}>
            {tab === 'stations' && <StationsPanel branchId={choice.branchId} />}
            {tab === 'routes' && <RoutesPanel branchId={choice.branchId} />}
            {tab === 'printers' && <PrintersPanel branchId={choice.branchId} />}
            {tab === 'settings' && <BranchSettingsPanel branchId={choice.branchId} />}
          </div>
        </div>
      )}
    </>
  );
}
