'use client';

import type { ReactNode } from 'react';
import { Activity, Archive, Bell, Bug, CheckCircle2, Database, ExternalLink, HardDrive, Info, ListChecks, Radio, RefreshCw, Server, TimerReset, TriangleAlert, XCircle, type LucideIcon } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, InlineError, Pill } from '@rizqim/ui';
import { useHealth, type MonitoringHealth } from '@/lib/api/monitoring';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { elapsed, span, verdictText, verdictTone } from './words';

const ICON: Record<string, LucideIcon> = { ok: CheckCircle2, fail: XCircle };

// Holat: what is working and what is not, each tile says it in words and in colour (a pill with the verdict and an icon)
export function StatusTab({ onOpenTab }: { onOpenTab: (tab: 'alerts' | 'jobs') => void }) {
  const t = useT();
  const health = useHealth();

  if (health.isError) {
    return <InlineError title={t('monitoring_status_loadError')} message={errorText(health.error, t)} retryLabel={t('retry')} retrying={health.isFetching} onRetry={() => void health.refetch()} />;
  }
  if (!health.data) return <CardSkeletons count={4} lines={2} label={t('loading')} />;

  const data = health.data;
  const Overall = ICON[data.status] ?? TriangleAlert;
  const tone = data.status === 'ok' ? 'bg-success-soft text-success-soft-fg' : data.status === 'fail' ? 'bg-danger-soft text-danger-soft-fg' : 'bg-warning-soft text-warning-soft-fg';
  const overallKey = data.status === 'ok' ? 'ok' : data.status === 'fail' ? 'fail' : 'degraded';

  return (
    <div className="flex flex-col gap-4">
      <div role="status" className={cx('flex flex-wrap items-center gap-3 rounded-card px-4 py-3', tone)}>
        <Overall aria-hidden="true" className="size-7 shrink-0" />
        <div className="min-w-0 flex-1">
          <p className="text-headline font-bold">{t(`monitoring_overall_${overallKey}`)}</p>
          <p className="text-footnote">{t(`monitoring_overallText_${overallKey}`)}</p>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-footnote">{t('monitoring_status_checkedAt', { time: when(data.checked_at) })}</span>
          <Button variant="secondary" icon={RefreshCw} loading={health.isFetching} onClick={() => void health.refetch()}>
            {t('monitoring_status_refresh')}
          </Button>
        </div>
      </div>

      <ul aria-label={t('monitoring_tab_status')} className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <DatabaseTile data={data} />
        <RedisTile data={data} />
        <QueueTile data={data} />
        <SchedulerTile data={data} />
        <ReverbTile data={data} />
        <BackupTile data={data} />
        <FailedTile data={data} onOpen={() => onOpenTab('jobs')} />
        <AlertsTile data={data} onOpen={() => onOpenTab('alerts')} />
        <AppTile data={data} />
        <SentryTile data={data} />
      </ul>
    </div>
  );
}

function Tile({ icon: Icon, title, verdict, children }: { icon: LucideIcon; title: string; verdict?: string; children: ReactNode }) {
  const t = useT();
  const Mark = verdict ? ICON[verdict] : undefined;
  return (
    <li>
      <Card className="flex h-full flex-col gap-2">
        <div className="flex items-start justify-between gap-2">
          <h3 className="flex min-w-0 items-center gap-2 text-body font-bold text-fg">
            <Icon aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
            <span className="min-w-0 break-words">{title}</span>
          </h3>
          {verdict && (
            <Pill tone={verdictTone(verdict)} icon={Mark ?? (verdict === 'not_used' || verdict === 'not_configured' ? Info : TriangleAlert)}>
              {verdictText(verdict, t)}
            </Pill>
          )}
        </div>
        <div className="flex flex-col gap-1 text-footnote text-fg-muted">{children}</div>
      </Card>
    </li>
  );
}

function DatabaseTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  return (
    <Tile icon={Database} title={t('monitoring_tile_database')} verdict={data.database.status}>
      <p>{data.database.status === 'fail' ? t('monitoring_database_fail') : t('monitoring_database_latency', { ms: data.database.latency_ms ?? 0 })}</p>
    </Tile>
  );
}

function RedisTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { status, latency_ms } = data.redis;
  return (
    <Tile icon={Server} title={t('monitoring_tile_redis')} verdict={status}>
      <p>{status === 'not_used' ? t('monitoring_redis_notUsed') : status === 'fail' ? t('monitoring_redis_fail') : t('monitoring_redis_latency', { ms: latency_ms ?? 0 })}</p>
    </Tile>
  );
}

function QueueTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  return (
    <Tile icon={ListChecks} title={t('monitoring_tile_queue')} verdict={data.queue.status}>
      <ul className="flex flex-col gap-0.5">
        {data.queue.queues.map((queue) => (
          <li key={queue.name}>
            {queue.size === null
              ? t('monitoring_queue_rowUnknown', { name: queue.name })
              : queue.size === 0
                ? t('monitoring_queue_rowEmpty', { name: queue.name })
                : queue.oldest_age_seconds !== null
                  ? t('monitoring_queue_row', { name: queue.name, size: queue.size, age: span(queue.oldest_age_seconds, t) })
                  : t('monitoring_queue_rowWaiting', { name: queue.name, size: queue.size })}
          </li>
        ))}
      </ul>
    </Tile>
  );
}

function SchedulerTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { status, last_heartbeat_age_seconds: age, max_age_seconds: max } = data.scheduler;
  return (
    <Tile icon={TimerReset} title={t('monitoring_tile_scheduler')} verdict={status}>
      <p>{age === null ? t('monitoring_scheduler_none') : t('monitoring_scheduler_beat', { age: span(age, t) })}</p>
      <p>{t('monitoring_scheduler_limit', { age: span(max, t) })}</p>
    </Tile>
  );
}

function ReverbTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { status, latency_ms } = data.reverb;
  return (
    <Tile icon={Radio} title={t('monitoring_tile_reverb')} verdict={status}>
      <p>{status === 'ok' ? t('monitoring_reverb_ok', { ms: latency_ms ?? 0 }) : status === 'not_configured' ? t('monitoring_reverb_off') : t('monitoring_reverb_fail')}</p>
    </Tile>
  );
}

function BackupTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { status, last_backup_at: last, max_age_hours: max } = data.backup;
  return (
    <Tile icon={Archive} title={t('monitoring_tile_backup')} verdict={status}>
      <p>
        {last === null
          ? t('monitoring_backup_missing')
          : status === 'stale'
            ? t('monitoring_backup_stale', { age: elapsed(last, t), hours: max })
            : t('monitoring_backup_ok', { age: elapsed(last, t), time: when(last) })}
      </p>
    </Tile>
  );
}

function FailedTile({ data, onOpen }: { data: MonitoringHealth; onOpen: () => void }) {
  const t = useT();
  const { total, last_24h: day, latest_failed_at: latest } = data.queue.failed_jobs;
  return (
    <Tile icon={HardDrive} title={t('monitoring_tile_failed')}>
      <p className={cx(total > 0 && 'font-bold text-fg')}>{total === 0 ? t('monitoring_failed_none') : t('monitoring_failed_summary', { total, day })}</p>
      {latest && <p>{t('monitoring_failed_last', { time: when(latest) })}</p>}
      {total > 0 && (
        <Button variant="secondary" onClick={onOpen} className="mt-1 self-start">
          {t('monitoring_failed_open')}
        </Button>
      )}
    </Tile>
  );
}

function AlertsTile({ data, onOpen }: { data: MonitoringHealth; onOpen: () => void }) {
  const t = useT();
  const { open, acknowledged, critical_unresolved: critical } = data.alerts;
  const any = open + acknowledged > 0;
  return (
    <Tile icon={Bell} title={t('monitoring_tile_alerts')}>
      <p className={cx(any && 'font-bold text-fg')}>{any ? t('monitoring_alerts_summary', { open, ack: acknowledged }) : t('monitoring_alerts_none')}</p>
      {critical > 0 && <p className="font-bold text-danger-soft-fg">{t('monitoring_alerts_critical', { count: critical })}</p>}
      {any && (
        <Button variant="secondary" onClick={onOpen} className="mt-1 self-start">
          {t('monitoring_alerts_open')}
        </Button>
      )}
    </Tile>
  );
}

function AppTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const unknown = t('monitoring_app_unknown');
  const rows: Array<[string, string]> = [
    [t('monitoring_app_version'), data.app.version ?? unknown],
    [t('monitoring_app_commit'), data.app.commit ?? unknown],
    [t('monitoring_app_env'), data.app.environment],
  ];
  return (
    <Tile icon={Activity} title={t('monitoring_tile_app')}>
      <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
        {rows.map(([name, value]) => (
          <div key={name} className="contents">
            <dt>{name}</dt>
            <dd className="break-all font-mono text-fg">{value}</dd>
          </div>
        ))}
      </dl>
    </Tile>
  );
}

function SentryTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { configured, project_url: url } = data.sentry;
  const safe = url && /^https:\/\//i.test(url) ? url : null;
  return (
    <Tile icon={Bug} title={t('monitoring_tile_sentry')} verdict={configured ? 'ok' : 'not_configured'}>
      <p>{configured ? t('monitoring_sentry_on') : t('monitoring_sentry_off')}</p>
      {safe ? (
        <a href={safe} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-1 self-start font-bold text-brand-soft-fg underline">
          {t('monitoring_sentry_link')}
          <ExternalLink aria-hidden="true" className="size-4" />
        </a>
      ) : (
        <p>{t('monitoring_sentry_noLink')}</p>
      )}
    </Tile>
  );
}
