'use client';

import { useMemo, useState } from 'react';
import { ChevronRight, Layers } from 'lucide-react';
import { Button, Card, Field, fieldClass, Pill } from '@rizqim/ui';
import { DEFAULT_SUBSCRIPTION_FILTERS, SUBSCRIPTION_STATUSES, useCatalog, useSubscriptions, type SubscriptionFilters, type SubscriptionRow } from '@/lib/api/pos';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { daysUntil, SUBSCRIPTION_LABEL, SUBSCRIPTION_TONE } from './posModel';
import { Chip, ChipRow, ListState } from './PosParts';
import { StoreBillingPanel } from './StoreBillingPanel';

// Obunalar (P4-24): every store's current subscription (trial, active, past due, locked), the trials ending in 3 days, and
// the store's subscription panel. The list is P4-21's GET /billing/subscriptions.

export const TRIAL_DAYS = 3;

export function SubscriptionsTab({ selected, onSelect, onOpenInvoice }: { selected: number | null; onSelect: (storeId: number | null) => void; onOpenInvoice: (uuid: string) => void }) {
  if (selected !== null) return <StoreBillingPanel storeId={selected} onBack={() => onSelect(null)} onOpenInvoice={onOpenInvoice} />;
  return <SubscriptionList onSelect={onSelect} />;
}

function SubscriptionList({ onSelect }: { onSelect: (storeId: number) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<SubscriptionFilters>(DEFAULT_SUBSCRIPTION_FILTERS);
  const list = useSubscriptions(filters);
  const catalog = useCatalog();
  const rows = useMemo(() => list.data?.pages.flatMap((page) => page.data) ?? [], [list.data]);
  const counts = list.data?.pages[0]?.counts;
  const trialsOn = filters.trial_ends_within === String(TRIAL_DAYS);
  const set = <K extends keyof SubscriptionFilters>(name: K, value: SubscriptionFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('pos_sub_statusFilter')}>
        <Chip on={filters.status === '' && !trialsOn} onClick={() => setFilters((previous) => ({ ...previous, status: '', trial_ends_within: '' }))}>
          {t('pos_sub_current')}
        </Chip>
        {SUBSCRIPTION_STATUSES.map((status) => (
          <Chip key={status} on={filters.status === status && !trialsOn} onClick={() => setFilters((previous) => ({ ...previous, status, trial_ends_within: '' }))}>
            {counts && status !== 'cancelled' ? t('pos_countLabel', { label: t(SUBSCRIPTION_LABEL[status]), count: counts[status] ?? 0 }) : t(SUBSCRIPTION_LABEL[status])}
          </Chip>
        ))}
        <Chip on={trialsOn} onClick={() => setFilters((previous) => ({ ...previous, status: '', trial_ends_within: trialsOn ? '' : String(TRIAL_DAYS) }))}>
          {t('pos_sub_trialEnding', { days: TRIAL_DAYS })}
        </Chip>
      </ChipRow>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={t('pos_plan')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.plan} onChange={(event) => set('plan', event.target.value)}>
              <option value="">{t('pos_all')}</option>
              {(catalog.data?.plans ?? []).map((plan) => (
                <option key={plan.code} value={plan.code}>
                  {plan.name}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('pos_period')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.period} onChange={(event) => set('period', event.target.value as SubscriptionFilters['period'])}>
              <option value="">{t('pos_all')}</option>
              <option value="monthly">{t('pos_period_monthly')}</option>
              <option value="yearly">{t('pos_period_yearly')}</option>
            </select>
          )}
        </Field>
      </div>

      <ListState
        loading={list.isLoading}
        error={list.isError ? list.error : null}
        fetching={list.isFetching}
        onRetry={() => void list.refetch()}
        empty={rows.length === 0}
        emptyTitle={t('pos_sub_empty')}
        emptyText={t('pos_sub_emptyText')}
        icon={Layers}
        errorTitle={t('pos_loadError')}
      >
        <ul aria-label={t('pos_sub_listLabel')} className="flex flex-col gap-2">
          {rows.map((row) => (
            <SubscriptionListRow key={row.subscription.id} row={row} onSelect={() => onSelect(row.store.id)} />
          ))}
        </ul>
        {list.hasNextPage && (
          <div className="flex justify-center">
            <Button variant="secondary" loading={list.isFetchingNextPage} onClick={() => void list.fetchNextPage()}>
              {t('pos_more')}
            </Button>
          </div>
        )}
      </ListState>
    </div>
  );
}

function SubscriptionListRow({ row, onSelect }: { row: SubscriptionRow; onSelect: () => void }) {
  const t = useT();
  const { subscription, store } = row;
  const trialDays = subscription.status === 'trialing' ? daysUntil(subscription.trial_ends_at) : null;
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onSelect}
          aria-label={t('pos_sub_open', { name: store.name })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">{store.name}</span>
              <Pill tone={SUBSCRIPTION_TONE[subscription.status]}>{t(SUBSCRIPTION_LABEL[subscription.status])}</Pill>
              {trialDays !== null && <Pill tone={trialDays <= TRIAL_DAYS ? 'warning' : 'info'}>{trialDays <= 0 ? t('pos_sub_trialToday') : t('pos_sub_trialDaysLeft', { days: trialDays })}</Pill>}
              {subscription.cancel_at_period_end && <Pill tone="neutral">{t('pos_sub_cancelling')}</Pill>}
            </span>
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{subscription.plan.name}</span>
              <span>{t(subscription.billing_period === 'yearly' ? 'pos_period_yearly' : 'pos_period_monthly')}</span>
              {subscription.next_plan && <span>{t('pos_sub_nextPlan', { plan: subscription.next_plan.name, date: when(subscription.changes_at) })}</span>}
              {subscription.locked_at && <span>{t('pos_sub_lockedSince', { date: when(subscription.locked_at) })}</span>}
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}
