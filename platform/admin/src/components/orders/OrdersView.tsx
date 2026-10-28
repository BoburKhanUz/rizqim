'use client';

import Link from 'next/link';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ChevronRight, ClipboardList, Gauge, Radio, RefreshCw, Search, ShieldAlert, Timer, Undo2 } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, formatOrderCode, formatTime, IconButton, InlineError, Notice, PageHeader, Pill, Section, StatusBadge } from '@rizqim/ui';
import {
  DEFAULT_FILTERS,
  ORDER_CHANNELS,
  ORDER_KEYS,
  SLA_FILTERS,
  STATUS_GROUPS,
  useOrders,
  useRefundQueue,
  useSla,
  type GroupCounts,
  type OrderFilters,
  type OrderRow,
} from '@/lib/api/orders';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { OPS_EVENTS, usePlatformOps } from '@/lib/realtime';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { RegionSelect, StorePicker } from '../stores/StorePicker';
import { SlaSettingsDialog } from './OrderDialogs';
import { clockSkew, exactMoney, GROUP_LABEL, money } from './ordersModel';
import { SlaBadge } from './SlaBadge';
import { StoreQualityPanel } from './StoreQualityPanel';
import { useNow } from './useNow';

// Buyurtmalar (P4-14, docs/PHASE4_PLAN.md 4.2): the marketplace orders of every store, newest first. Live: an
// ops.order.* event on platform.ops refetches at once, and the list polls anyway (every 15 s while real-time is not up,
// every minute while it is). The SLA of each order is the server's; the words keep counting between refetches.

// Real-time refetches of the list: the first event at once, the rest of a burst once at the end of this window
export const ORDERS_EVENT_COALESCE_MS = 1_000;

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');

// P4-22: `initial` carries ?store_id= and ?region_id= (the store card's "Buyurtmalarini ochish" link)
export function OrdersView({ initial }: { initial?: Partial<Pick<OrderFilters, 'store_id' | 'region_id'>> } = {}) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('orders.view');
  const queryClient = useQueryClient();
  const now = useNow(15_000);
  const [filters, setFilters] = useState<OrderFilters>(() => ({ ...DEFAULT_FILTERS, ...initial }));
  const [search, setSearch] = useState('');
  const [more, setMore] = useState(Boolean(initial?.store_id || initial?.region_id));
  const [slaOpen, setSlaOpen] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  // An event says "something changed": refetch at once, then at most once per ORDERS_EVENT_COALESCE_MS. Events inside
  // that window are coalesced into one refetch at its end (trailing edge), so the last change of a burst is never lost.
  const lastRefetch = useRef(0);
  const trailing = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => {
    if (trailing.current) clearTimeout(trailing.current);
  }, []);
  const { live, status } = usePlatformOps(
    [OPS_EVENTS.orderCreated, OPS_EVENTS.orderUpdated],
    (payload) => {
      if (payload === null) return; // the list polls by itself (refetchInterval)
      const refetch = () => {
        lastRefetch.current = Date.now();
        void queryClient.invalidateQueries({ queryKey: ORDER_KEYS.all });
      };
      const wait = ORDERS_EVENT_COALESCE_MS - (Date.now() - lastRefetch.current);
      if (wait <= 0 && !trailing.current) {
        refetch();
        return;
      }
      if (trailing.current) return; // already coming
      trailing.current = setTimeout(() => {
        trailing.current = null;
        refetch();
      }, Math.max(wait, 0));
    },
    { enabled: allowed, pollMs: 0 },
  );

  const orders = useOrders(filters, { enabled: allowed, live });
  const sla = useSla(allowed);
  const queue = useRefundQueue(allowed && can('orders.refund_approve'));
  const rows = useMemo(() => orders.data?.pages.flatMap((page) => page.data) ?? [], [orders.data]);
  const first = orders.data?.pages[0];
  const skew = useMemo(() => (orders.dataUpdatedAt ? clockSkew(first?.server_time, orders.dataUpdatedAt) : 0), [first?.server_time, orders.dataUpdatedAt]);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_orders')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const set = <K extends keyof OrderFilters>(name: K, value: OrderFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value }));
  const clear = () => {
    setSearch('');
    setFilters(DEFAULT_FILTERS);
  };
  const limits = first?.sla_limits ?? sla.data?.limits;

  return (
    <>
      <PageHeader
        title={t('orders_title')}
        subtitle={t('orders_subtitle')}
        action={
          <div className="flex items-center gap-1">
            {orders.dataUpdatedAt > 0 && (
              <p className="text-caption text-fg-muted" aria-live="polite">
                {t('orders_updated', { time: formatTime(orders.dataUpdatedAt) })}
              </p>
            )}
            <IconButton
              icon={RefreshCw}
              label={t('orders_refresh')}
              onClick={() => void queryClient.invalidateQueries({ queryKey: ORDER_KEYS.all })}
              disabled={orders.isFetching}
              className={cx(orders.isFetching && 'animate-spin motion-reduce:animate-none')}
            />
          </div>
        }
      />
      <Notice message={notice} />

      <div className="mb-4 flex max-w-5xl flex-wrap items-center gap-x-3 gap-y-2 text-footnote text-fg-muted">
        <span className="inline-flex items-center gap-1.5" data-testid="live-status">
          <Radio aria-hidden="true" className={cx('size-4', live ? 'text-success' : 'text-fg-muted')} />
          {live ? t('orders_live') : status === 'connecting' ? t('orders_connecting') : t('orders_polling')}
        </span>
        {limits && (
          <span className="inline-flex items-center gap-1.5">
            <Timer aria-hidden="true" className="size-4" />
            {t('orders_limits', { accept: limits.accept, prepare: limits.prepare, deliver: limits.deliver })}
          </span>
        )}
        {can('system.manage') && sla.data && (
          <Button variant="ghost" icon={Gauge} onClick={() => setSlaOpen(true)}>
            {t('orders_slaSettings')}
          </Button>
        )}
      </div>

      {can('orders.refund_approve') && queue.data && queue.data.data.length > 0 && (
        <Section title={t('orders_refundQueue')} className="mb-4 max-w-5xl">
          <Card padded={false}>
            <ul className="divide-y divide-line">
              {queue.data.data.map((request) => (
                <li key={request.id}>
                  <Link href={`/orders/${request.order?.uuid ?? ''}`} className="flex min-h-11 items-center gap-3 px-4 py-3 hover:bg-surface-2 focus-visible:bg-surface-2">
                    <Undo2 aria-hidden="true" className="size-4 shrink-0 text-warning-soft-fg" />
                    <span className="min-w-0 flex-1 break-words text-footnote text-fg">
                      {t('orders_refundQueueItem', {
                        code: request.order ? formatOrderCode(request.order) : `#${request.id}`,
                        store: request.store?.name ?? '—',
                        amount: exactMoney(request.amount),
                        name: request.requested_by?.name ?? '—',
                      })}
                    </span>
                    <ChevronRight aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
                  </Link>
                </li>
              ))}
            </ul>
          </Card>
        </Section>
      )}

      <section aria-label={t('orders_filters')} className="mb-4 flex max-w-5xl flex-col gap-3">
        <div role="group" aria-label={t('orders_statusFilter')} className="flex flex-wrap gap-2">
          {STATUS_GROUPS.map((group) => (
            <button key={group} type="button" aria-pressed={filters.status === group} className={cx(CHIP, chipTone(filters.status === group))} onClick={() => set('status', group)}>
              {countLabel(t, group, first?.counts)}
            </button>
          ))}
        </div>
        <div role="group" aria-label={t('orders_slaFilter')} className="flex flex-wrap gap-2">
          <button type="button" aria-pressed={filters.sla === ''} className={cx(CHIP, chipTone(filters.sla === ''))} onClick={() => set('sla', '')}>
            {t('orders_slaAny')}
          </button>
          {SLA_FILTERS.map((value) => (
            <button key={value} type="button" aria-pressed={filters.sla === value} className={cx(CHIP, chipTone(filters.sla === value))} onClick={() => set('sla', value)}>
              {value === 'late' ? t('orders_slaLate') : t('orders_slaWarning')}
            </button>
          ))}
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={t('orders_search')}>
            {(control) => (
              <div className="relative">
                <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
                <input {...control} type="search" value={search} placeholder={t('orders_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
              </div>
            )}
          </Field>
          <Field label={t('orders_channel')}>
            {(control) => (
              <select {...control} className={fieldClass} value={filters.channel} onChange={(event) => set('channel', event.target.value)}>
                <option value="">{t('orders_allChannels')}</option>
                {ORDER_CHANNELS.map((channel) => (
                  <option key={channel} value={channel}>
                    {t(channel === 'telegram' ? 'orders_channel_telegram' : 'orders_channel_mobile')}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
        {more && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {/* P4-22: pickers by name instead of raw ids (GET /admin/stores/lookup) */}
            <StorePicker value={filters.store_id ? Number(filters.store_id) : null} onChange={(id) => set('store_id', id === null ? '' : String(id))} label={t('orders_store')} />
            <RegionSelect value={filters.region_id} onChange={(id) => set('region_id', id)} label={t('orders_region')} />
            <Field label={t('orders_from')}>{(control) => <input {...control} type="date" className={fieldClass} value={filters.from} onChange={(event) => set('from', event.target.value)} />}</Field>
            <Field label={t('orders_to')}>{(control) => <input {...control} type="date" className={fieldClass} value={filters.to} onChange={(event) => set('to', event.target.value)} />}</Field>
          </div>
        )}
        <div className="flex flex-wrap gap-2">
          <Button variant="ghost" aria-expanded={more} onClick={() => setMore((value) => !value)}>
            {more ? t('orders_lessFilters') : t('orders_moreFilters')}
          </Button>
          <Button variant="secondary" onClick={clear}>
            {t('orders_clear')}
          </Button>
        </div>
        {(filters.status === 'done' || filters.status === 'canceled') && !filters.from && !filters.to && <p className="text-caption text-fg-muted">{t('orders_finalToday')}</p>}
      </section>

      {orders.isError ? (
        <InlineError title={t('orders_loadError')} message={errorText(orders.error, t)} retryLabel={t('retry')} retrying={orders.isFetching} onRetry={() => void orders.refetch()} />
      ) : !orders.isSuccess ? (
        <CardSkeletons count={4} lines={3} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={ClipboardList} title={t('orders_empty')} text={t('orders_emptyText')} />
      ) : (
        <div className="flex max-w-5xl flex-col gap-3">
          <ul aria-label={t('orders_listLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <OrderListRow key={row.uuid} row={row} now={now} skew={skew} />
            ))}
          </ul>
          {orders.hasNextPage && (
            <Button variant="secondary" loading={orders.isFetchingNextPage} onClick={() => void orders.fetchNextPage()}>
              {t('orders_loadMore')}
            </Button>
          )}
        </div>
      )}

      <StoreQualityPanel enabled={allowed} />

      {slaOpen && sla.data && (
        <SlaSettingsDialog
          settings={sla.data}
          onClose={() => setSlaOpen(false)}
          onDone={(message) => {
            setSlaOpen(false);
            setNotice(message);
          }}
        />
      )}
    </>
  );
}

function countLabel(t: ReturnType<typeof useT>, group: keyof GroupCounts, counts: GroupCounts | undefined): string {
  const label = t(GROUP_LABEL[group]);
  return counts ? t('orders_groupCount', { label, count: counts[group] }) : label;
}

function OrderListRow({ row, now, skew }: { row: OrderRow; now: number; skew: number }) {
  const t = useT();
  const code = formatOrderCode(row);
  const place = [row.store?.name, row.branch?.name, row.region?.name].filter(Boolean).join(' · ');
  return (
    <li>
      <Card padded={false}>
        <Link
          href={`/orders/${row.uuid}`}
          aria-label={t('orders_open', { code })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="font-mono text-body font-bold text-fg">{code}</span>
              <StatusBadge status={row.status} label={row.status_label} />
              <SlaBadge sla={row.sla} now={now} skewMs={skew} />
            </span>
            <span className="break-words text-footnote text-fg">{place}</span>
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.customer_name ?? '—'}</span>
              <span className="font-bold text-fg">{money(row.total)}</span>
              <span>{row.channel_label}</span>
              <span>{row.courier ? row.courier.name : row.type === 'delivery' ? t('orders_courierNone') : null}</span>
              <span>{when(row.created_at)}</span>
            </span>
            {(row.refund_pending || row.manual_refund_required) && (
              <span className="flex flex-wrap gap-1.5">
                {row.refund_pending && <Pill tone="warning">{t('orders_refundPending')}</Pill>}
                {row.manual_refund_required && <Pill tone="danger">{t('orders_manualRefund')}</Pill>}
              </span>
            )}
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </Link>
      </Card>
    </li>
  );
}
