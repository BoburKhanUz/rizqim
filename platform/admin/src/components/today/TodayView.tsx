'use client';

import { useEffect, useRef } from 'react';
import Link from 'next/link';
import { AlertOctagon, AlertTriangle, ArrowDownRight, ArrowUpRight, ChevronRight, CircleCheck, Info, MapPin, Minus, RefreshCw, type LucideIcon } from 'lucide-react';
import { Card, CardSkeletons, cx, EmptyState, formatDate, formatDateTime, formatMoney, formatNumber, formatTime, IconButton, InlineError, PageHeader, Pill, Section, Skeleton, type PillTone } from '@rizqim/ui';
import type { MessageKey, Translate } from '@/i18n';
import { errorText } from '@/lib/errors';
import { useTodayAttention, useTodaySummary, type AttentionGroup, type AttentionItem, type Change, type Kpi, type Severity, type TodaySummary } from '@/lib/api/today';
import { OPS_EVENTS, usePlatformOps } from '@/lib/realtime';
import { useT } from '../I18nProvider';

// Bugun: the home page of the control center (docs/PHASE4_PLAN.md 4.1). Today's numbers against yesterday and last week up to
// the same time, "Diqqat talab qiladi" (every row opens its card), channels and regions. Both parts are asked again every 30
// seconds; each part fails on its own, so a broken list never hides the numbers.

// Events of platform.ops that change the numbers or the list (Buyurtmalar P4-14, Monitoring P4-15)
export const TODAY_EVENTS = [OPS_EVENTS.orderCreated, OPS_EVENTS.orderUpdated, 'ops.alert.opened', 'ops.alert.acknowledged', 'ops.alert.resolved'] as const;

// A burst of events (a busy lunch hour) is one refetch: at most one per this many milliseconds
export const TODAY_EVENT_COALESCE_MS = 3_000;

// The stats job runs every 5 minutes: much later than that is worth a quiet word
const STATS_LATE_MS = 30 * 60_000;

const SEVERITY: Record<Severity, { tone: PillTone; icon: LucideIcon; label: MessageKey }> = {
  critical: { tone: 'danger', icon: AlertOctagon, label: 'today_severity_critical' },
  warning: { tone: 'warning', icon: AlertTriangle, label: 'today_severity_warning' },
  info: { tone: 'info', icon: Info, label: 'today_severity_info' },
};

const money = (value: string | number) => formatMoney(value, 'uz');
const count = (value: string | number) => formatNumber(value, 'uz');

// "14:05" today, "9-noy, 14:05" another day
function sinceText(value: string, now: Date): string {
  const at = new Date(value);
  return at.toDateString() === now.toDateString() ? formatTime(at) : formatDateTime(at, 'uz', now);
}

export function TodayView() {
  const t = useT();
  const summary = useTodaySummary();
  const attention = useTodayAttention();
  const refreshing = summary.isFetching || attention.isFetching;

  const refresh = () => {
    void summary.refetch();
    void attention.refetch();
  };

  // Real-time on top of the 30 s polling (which runs anyway, so pollMs 0): an event asks again a moment later, once per burst
  const pending = useRef<ReturnType<typeof setTimeout> | null>(null);
  const refreshRef = useRef(refresh);
  useEffect(() => {
    refreshRef.current = refresh;
  });
  useEffect(() => () => {
    if (pending.current) clearTimeout(pending.current);
  }, []);
  usePlatformOps(
    TODAY_EVENTS,
    () => {
      if (pending.current) return;
      pending.current = setTimeout(() => {
        pending.current = null;
        refreshRef.current();
      }, TODAY_EVENT_COALESCE_MS);
    },
    { pollMs: 0 },
  );

  const updatedAt = summary.data?.generated_at ?? attention.data?.generated_at;
  return (
    <>
      <PageHeader
        title={t('today_title')}
        subtitle={summary.data ? t('today_subtitle', { date: formatDate(`${summary.data.date}T12:00:00`, 'uz') }) : undefined}
        action={
          <div className="flex items-center gap-1">
            {updatedAt && (
              <p className="text-caption text-fg-muted" aria-live="polite">
                {t('today_updated', { time: formatTime(updatedAt) })}
              </p>
            )}
            <IconButton icon={RefreshCw} label={t('today_refresh')} onClick={refresh} disabled={refreshing} className={cx(refreshing && 'animate-spin motion-reduce:animate-none')} />
          </div>
        }
      />

      <div className="flex flex-col gap-6">
        {summary.isError && !summary.data ? (
          <InlineError title={t('today_loadError')} message={errorText(summary.error, t)} retryLabel={t('retry')} retrying={summary.isFetching} onRetry={() => void summary.refetch()} />
        ) : !summary.data ? (
          <KpiSkeleton label={t('loading')} />
        ) : (
          <Kpis data={summary.data} t={t} />
        )}

        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
          <Section title={t('today_attention')}>
            {attention.isError && !attention.data ? (
              <InlineError title={t('today_attentionLoadError')} message={errorText(attention.error, t)} retryLabel={t('retry')} retrying={attention.isFetching} onRetry={() => void attention.refetch()} />
            ) : !attention.data ? (
              <CardSkeletons count={2} lines={2} label={t('loading')} />
            ) : attention.data.groups.length === 0 ? (
              <Card>
                <EmptyState icon={CircleCheck} title={t('today_allGood')} text={t('today_allGoodText')} className="py-8" />
              </Card>
            ) : (
              <ul aria-label={t('today_attention')} className="flex flex-col gap-3">
                {attention.data.groups.map((group) => (
                  <li key={group.kind}>
                    <AttentionCard group={group} t={t} />
                  </li>
                ))}
              </ul>
            )}
          </Section>

          {summary.data && (
            <div className="flex flex-col gap-6">
              <Channels data={summary.data} t={t} />
              <Regions data={summary.data} t={t} />
            </div>
          )}
        </div>
      </div>
    </>
  );
}

function KpiSkeleton({ label }: { label: string }) {
  return (
    <div role="status" aria-label={label} className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
      {[0, 1, 2, 3].map((n) => (
        <Card key={n} className="flex flex-col gap-3">
          <Skeleton className="h-4 w-24" />
          <Skeleton className="h-8 w-32" />
          <Skeleton className="h-4 w-40" />
        </Card>
      ))}
    </div>
  );
}

function Kpis({ data, t }: { data: TodaySummary; t: Translate }) {
  const late = data.stats_computed_at !== null && new Date(data.generated_at).getTime() - new Date(data.stats_computed_at).getTime() > STATS_LATE_MS;
  return (
    <Section title={t('today_kpis')}>
      <ul aria-label={t('today_kpis')} className="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        <KpiCard label={t('today_gmv')} kpi={data.kpis.gmv} format={money} t={t} brand />
        <KpiCard label={t('today_orders')} kpi={data.kpis.orders} format={count} t={t} note={t('today_canceled', { n: count(data.kpis.canceled_orders.value) })} />
        <KpiCard label={t('today_commission')} kpi={data.kpis.commission} format={money} t={t} />
        <KpiCard label={t('today_subscription')} kpi={data.kpis.subscription_revenue} format={money} t={t} />
      </ul>
      {late && data.stats_computed_at && <p className="px-1 text-caption text-fg-muted">{t('today_statsLate', { time: formatTime(data.stats_computed_at) })}</p>}
    </Section>
  );
}

interface KpiCardProps<V extends string | number> {
  label: string;
  kpi: Kpi<V>;
  format: (value: V) => string;
  t: Translate;
  note?: string;
  brand?: boolean;
}

function KpiCard<V extends string | number>({ label, kpi, format, t, note, brand }: KpiCardProps<V>) {
  return (
    <li>
      <Card className="flex h-full flex-col gap-1">
        <h3 className="text-footnote font-bold text-fg-muted">{label}</h3>
        <p className={cx('text-display font-extrabold', brand ? 'text-brand' : 'text-fg')}>{format(kpi.value)}</p>
        {note && <p className="text-caption text-fg-muted">{note}</p>}
        <dl className="mt-2 flex flex-col gap-1.5 text-footnote">
          <ChangeLine label={t('today_vsYesterday')} change={kpi.vs_yesterday} before={format(kpi.yesterday_same_time)} t={t} />
          <ChangeLine label={t('today_vsLastWeek')} change={kpi.vs_last_week} before={format(kpi.last_week_same_time)} t={t} />
        </dl>
        <p className="mt-1 text-caption text-fg-muted">{t('today_wholeYesterday', { value: format(kpi.yesterday) })}</p>
      </Card>
    </li>
  );
}

const DIRECTION: Record<Change['direction'], { icon: LucideIcon; tone: string }> = {
  up: { icon: ArrowUpRight, tone: 'text-success' },
  down: { icon: ArrowDownRight, tone: 'text-danger' },
  same: { icon: Minus, tone: 'text-fg-muted' },
};

// "Kechagi shu vaqtga: 20% ko'p (100 000 so'm)": the words carry the meaning, the arrow and colour only help
export function changeText(change: Change, before: string, t: Translate): string {
  if (change.direction === 'same') return t('today_same', { before });
  if (change.percent === null) return t('today_moreFromZero', { before });
  return t(change.direction === 'up' ? 'today_more' : 'today_less', { percent: change.percent.replace(/\.0$/, '').replace('.', ','), before });
}

function ChangeLine({ label, change, before, t }: { label: string; change: Change; before: string; t: Translate }) {
  const { icon: Icon, tone } = DIRECTION[change.direction];
  return (
    <div className="flex flex-wrap items-baseline gap-x-1.5">
      <dt className="text-fg-muted">{label}:</dt>
      <dd className={cx('inline-flex items-center gap-1 font-bold', tone)}>
        <Icon aria-hidden="true" className="size-4 shrink-0 self-center" />
        <span>{changeText(change, before, t)}</span>
      </dd>
    </div>
  );
}

function AttentionCard({ group, t }: { group: AttentionGroup; t: Translate }) {
  const severity = SEVERITY[group.severity] ?? SEVERITY.info;
  const now = new Date();
  const more = group.count - group.items.length;
  return (
    <Card padded={false} className="overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h3 className="flex min-w-0 items-center gap-2 text-headline font-bold text-fg">
          <span className="min-w-0 break-words">{group.label}</span>
          <span className="shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-caption font-bold text-fg-muted">{t('today_count', { n: count(group.count) })}</span>
        </h3>
        <Pill tone={severity.tone} icon={severity.icon}>
          {t(severity.label)}
        </Pill>
      </div>
      <ul aria-label={group.label} className="divide-y divide-line">
        {group.items.map((item, index) => (
          <li key={`${item.href}-${index}`}>
            <AttentionRow item={item} now={now} t={t} />
          </li>
        ))}
      </ul>
      {more > 0 && <p className="border-t border-line px-4 py-2.5 text-footnote text-fg-muted">{t('today_andMore', { n: count(more) })}</p>}
    </Card>
  );
}

function AttentionRow({ item, now, t }: { item: AttentionItem; now: Date; t: Translate }) {
  const severity = SEVERITY[item.severity] ?? SEVERITY.info;
  const Icon = severity.icon;
  return (
    <Link href={item.href} className="flex min-h-11 items-center gap-3 px-4 py-3 hover:bg-surface-2 focus-visible:bg-surface-2">
      <Icon aria-hidden="true" className={cx('size-5 shrink-0', item.severity === 'critical' ? 'text-danger' : item.severity === 'warning' ? 'text-warning' : 'text-info')} />
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="break-words text-body font-bold text-fg">{item.title}</span>
        {item.subtitle && <span className="break-words text-footnote text-fg-muted">{item.subtitle}</span>}
        {item.since && <span className="text-caption text-fg-muted">{t('today_since', { time: sinceText(item.since, now) })}</span>}
      </span>
      <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
    </Link>
  );
}

function Channels({ data, t }: { data: TodaySummary; t: Translate }) {
  const rows = data.channels.filter((row) => row.marketplace || row.orders > 0);
  return (
    <Section title={t('today_channels')}>
      <Card className="py-2">
        <ul aria-label={t('today_channels')} className="divide-y divide-line">
          {rows.map((row) => (
            <li key={row.channel} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2.5">
              <span className="flex min-w-0 flex-col">
                <span className="text-body text-fg">{row.label}</span>
                {!row.marketplace && <span className="text-caption text-fg-muted">{t('today_notInGmv')}</span>}
              </span>
              <span className="text-body font-medium text-fg-muted">
                {t('today_ordersN', { n: count(row.orders) })} · {money(row.gmv)}
              </span>
            </li>
          ))}
        </ul>
      </Card>
    </Section>
  );
}

function Regions({ data, t }: { data: TodaySummary; t: Translate }) {
  return (
    <Section title={t('today_regions')}>
      <Card className="py-2">
        {data.regions.length === 0 ? (
          <p className="py-2.5 text-footnote text-fg-muted">{t('today_noRegions')}</p>
        ) : (
          <ul aria-label={t('today_regions')} className="divide-y divide-line">
            {data.regions.map((region) => (
              <li key={region.region_id ?? 'none'} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2.5">
                <span className="flex min-w-0 items-center gap-2">
                  <MapPin aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
                  <span className="min-w-0 break-words text-body text-fg">{region.name}</span>
                </span>
                <span className="flex flex-col items-end text-footnote">
                  <span className="font-bold text-fg">
                    {t('today_ordersN', { n: count(region.orders) })} · {money(region.gmv)}
                  </span>
                  <span className="text-fg-muted">{t('today_activeStores', { n: count(region.active_stores) })}</span>
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}
