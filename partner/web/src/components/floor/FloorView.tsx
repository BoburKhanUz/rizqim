'use client';

import { useState } from 'react';
import { Armchair } from 'lucide-react';
import { EmptyState, InlineError, CardSkeletons } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { BranchSelect, useBranchChoice } from '../menu/BranchChoice';
import { Tabs } from '../menu/Tabs';
import { GuestItemsPanel } from './GuestItemsPanel';
import { HallsPanel } from './HallsPanel';
import { LayoutEditor } from './LayoutEditor';
import { TariffsPanel } from './TariffsPanel';

type Tab = 'halls' | 'map' | 'tariffs' | 'guests';

// /floor: halls and places, the floor map editor, room tariffs with the weekend/holiday calendar and the per-guest items.
// Halls, places and the map are tables.manage (changing them also needs the owner or manager role); tariffs and guest items
// are settings.manage. The branch is the top bar's, or the page's own picker when the top bar says "all".
export function FloorView() {
  const { t } = useI18n();
  const access = useCan();
  const choice = useBranchChoice();
  const [chosen, setChosen] = useState<Tab | null>(null);

  const canEdit = access.can('tables.manage') && (access.role === 'owner' || access.role === 'manager');
  const canSettings = access.can('settings.manage');
  const available: Tab[] = [];
  if (access.can('tables.manage')) available.push('halls', 'map');
  if (canSettings) available.push('tariffs', 'guests');
  const tab = chosen && available.includes(chosen) ? chosen : available[0];

  return (
    <>
      <PageHeader title={t('nav_floor')} subtitle={t('floor_subtitle')} />
      {choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(null, t)} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.isPending || !access.ready ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : choice.branchId === null || !tab ? (
        <EmptyState icon={Armchair} title={t('floor_noBranch')} text={t('floor_noBranchText')} />
      ) : (
        <div className="flex flex-col gap-4">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="sm:max-w-sm" />
          <Tabs idPrefix="floor" label={t('floor_tabs')} value={tab} onChange={setChosen} tabs={available.map((id) => ({ id, label: t(`floor_tab_${id}`) }))} />
          <div role="tabpanel" id={`floor-panel-${tab}`} aria-labelledby={`floor-tab-${tab}`}>
            {tab === 'halls' && <HallsPanel branchId={choice.branchId} canEdit={canEdit} canSettings={canSettings} />}
            {tab === 'map' && <LayoutEditor branchId={choice.branchId} canEdit={canEdit} />}
            {tab === 'tariffs' && <TariffsPanel branchId={choice.branchId} />}
            {tab === 'guests' && <GuestItemsPanel branchId={choice.branchId} />}
          </div>
        </div>
      )}
    </>
  );
}
