'use client';

import { useCallback, useMemo, useState } from 'react';
import { ShieldAlert } from 'lucide-react';
import { CardSkeletons, EmptyState, PageHeader, Tabs } from '@rizqim/ui';
import { useOverview, type Overview } from '@/lib/api/pos';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { DevicesTab } from './DevicesTab';
import { FiscalTab } from './FiscalTab';
import { InvoicesTab } from './InvoicesTab';
import { PlansTab } from './PlansTab';
import { isTab, TAB_LABEL, TAB_PERMISSIONS, TABS, type PosTab } from './posModel';
import { ShiftsTab } from './ShiftsTab';
import { SubscriptionsTab } from './SubscriptionsTab';
import { SyncTab } from './SyncTab';

// POS va obunalar (P4-24, docs/PHASE4_PLAN.md 4.4): subscriptions, the tariff catalog, invoices across stores, devices, shifts,
// fiscal receipts and offline sync errors, one tab each. The links the Bugun page and the store card use open the right tab and
// row: /pos?invoice=<uuid>, ?device=<uuid>, ?fiscal=<uuid>, ?store=<id> (that store's subscription), plus ?tab=, ?shift=, ?sync=.

export interface PosInitial {
  tab?: string;
  store?: string;
  invoice?: string;
  device?: string;
  fiscal?: string;
  shift?: string;
  sync?: string;
}

// The tab a deep link points at (a row id wins over ?tab=)
export function initialTab(initial: PosInitial): PosTab {
  if (initial.invoice) return 'invoices';
  if (initial.device) return 'devices';
  if (initial.fiscal) return 'fiscal';
  if (initial.shift) return 'shifts';
  if (initial.sync) return 'sync';
  if (initial.store) return 'subscriptions';
  return isTab(initial.tab) ? initial.tab : 'subscriptions';
}

interface Selection {
  store: number | null;
  invoice: string | null;
  device: string | null;
  fiscal: string | null;
  shift: string | null;
  sync: string | null;
}

// The numbers that need a person, per tab (0: nothing to show)
function attention(overview: Overview | undefined): Partial<Record<PosTab, number>> {
  if (!overview) return {};
  return {
    subscriptions: overview.subscriptions ? overview.subscriptions.trial_ending + (overview.subscriptions.counts.past_due ?? 0) + (overview.subscriptions.counts.locked ?? 0) : 0,
    invoices: overview.invoices?.overdue ?? 0,
    devices: overview.devices ? overview.devices.offline + overview.devices.outdated : 0,
    shifts: overview.shifts ? overview.shifts.difference + overview.shifts.force_closed : 0,
    fiscal: overview.fiscal ? overview.fiscal.failed + overview.fiscal.pending : 0,
    sync: overview.sync_errors?.open ?? 0,
  };
}

export function PosView({ initial = {} }: { initial?: PosInitial }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowedTabs = useMemo(() => TABS.filter((tab) => can(...TAB_PERMISSIONS[tab])), [can]);
  const wanted = initialTab(initial);
  const [chosen, setTab] = useState<PosTab>(wanted);
  const [selection, setSelection] = useState<Selection>(() => ({
    store: initial.store && /^[1-9]\d{0,17}$/.test(initial.store) ? Number(initial.store) : null,
    invoice: initial.invoice ?? null,
    device: initial.device ?? null,
    fiscal: initial.fiscal ?? null,
    shift: initial.shift ?? null,
    sync: initial.sync ?? null,
  }));
  const overview = useOverview(ready && allowedTabs.length > 0);
  const badges = attention(overview.data);

  // A tab the member may not open falls back to the first they may
  const tab: PosTab = allowedTabs.includes(chosen) ? chosen : (allowedTabs[0] ?? chosen);

  // The address follows the tab and the open row, so a reload or a shared link lands in the same place
  const remember = useCallback((next: PosTab, selected: Selection) => {
    const params = new URLSearchParams();
    params.set('tab', next);
    const value: Record<PosTab, string | null> = {
      subscriptions: selected.store === null ? null : String(selected.store),
      plans: null,
      invoices: selected.invoice,
      devices: selected.device,
      shifts: selected.shift,
      fiscal: selected.fiscal,
      sync: selected.sync,
    };
    const key: Record<PosTab, string> = { subscriptions: 'store', plans: '', invoices: 'invoice', devices: 'device', shifts: 'shift', fiscal: 'fiscal', sync: 'sync' };
    if (value[next] && key[next]) params.set(key[next], value[next]);
    window.history.replaceState(null, '', `?${params.toString()}`);
  }, []);

  // Until the member is known no tab may start loading (it could be one they may not open)
  if (!ready) {
    return (
      <>
        <PageHeader title={t('pos_title')} subtitle={t('pos_subtitle')} />
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      </>
    );
  }

  if (allowedTabs.length === 0) {
    return (
      <>
        <PageHeader title={t('nav_pos')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const choose = (next: PosTab) => {
    // Another tab starts at its list
    const cleared: Selection = { store: null, invoice: null, device: null, fiscal: null, shift: null, sync: null };
    setSelection(cleared);
    setTab(next);
    remember(next, cleared);
  };
  const select = <K extends keyof Selection>(key: K, value: Selection[K]) => {
    const next = { ...selection, [key]: value };
    setSelection(next);
    remember(tab, next);
  };

  // From a store's subscription to one of its invoices
  const openInvoice = (uuid: string) => {
    const next: Selection = { store: null, invoice: uuid, device: null, fiscal: null, shift: null, sync: null };
    setSelection(next);
    setTab('invoices');
    remember('invoices', next);
  };

  const tabs = allowedTabs.map((id) => {
    const count = badges[id] ?? 0;
    return { id, label: count > 0 ? t('pos_tabWithCount', { label: t(TAB_LABEL[id]), count }) : t(TAB_LABEL[id]) };
  });

  return (
    <>
      <PageHeader title={t('pos_title')} subtitle={t('pos_subtitle')} />
      <Tabs tabs={tabs} value={tab} onChange={choose} label={t('pos_tabs')} idPrefix="pos" />
      <div role="tabpanel" id={`pos-panel-${tab}`} aria-labelledby={`pos-tab-${tab}`}>
        {tab === 'subscriptions' && <SubscriptionsTab selected={selection.store} onSelect={(store) => select('store', store)} onOpenInvoice={openInvoice} />}
        {tab === 'plans' && <PlansTab />}
        {tab === 'invoices' && <InvoicesTab selected={selection.invoice} onSelect={(uuid) => select('invoice', uuid)} />}
        {tab === 'devices' && <DevicesTab selected={selection.device} onSelect={(uuid) => select('device', uuid)} />}
        {tab === 'shifts' && <ShiftsTab selected={selection.shift} onSelect={(uuid) => select('shift', uuid)} />}
        {tab === 'fiscal' && <FiscalTab selected={selection.fiscal} onSelect={(uuid) => select('fiscal', uuid)} />}
        {tab === 'sync' && <SyncTab selected={selection.sync} onSelect={(opId) => select('sync', opId)} />}
      </div>
    </>
  );
}
