'use client';

import type { ReactNode } from 'react';
import { Activity, Archive, Bell, Bug, CalendarRange, CheckCircle2, Database, ExternalLink, Gauge, HardDrive, History, Info, LifeBuoy, ListChecks, Radio, RefreshCw, Server, TimerReset, TriangleAlert, XCircle, type LucideIcon } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, InlineError, Pill } from '@rizqim/ui';
import { useHealth, type MonitoringHealth } from '@/lib/api/monitoring';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { bytes, elapsed, exact, span, verdictText, verdictTone } from './words';

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
        {data.pitr && <PitrTile pitr={data.pitr} />}
        {data.drill && <DrillTile drill={data.drill} />}
        {data.disk && <DiskTile disk={data.disk} />}
        {data.partitions && <PartitionsTile partitions={data.partitions} />}
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
            <Pill tone={verdictTone(verdict)} icon={Mark ?? (verdict === 'not_used' || verdict === 'not_configured' || verdict === 'off' ? Info : verdict === 'failed' ? XCircle : TriangleAlert)}>
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
    <Tile icon={Database} title={t('monitoring_tile_database')} verdict={data.database.status === 'ok' && data.postgres?.status === 'warning' ? 'warning' : data.database.status}>
      <p>{data.database.status === 'fail' ? t('monitoring_database_fail') : t('monitoring_database_latency', { ms: data.database.latency_ms ?? 0 })}</p>
      {data.database.status !== 'fail' && data.postgres && <PostgresLines postgres={data.postgres} />}
    </Tile>
  );
}

function PostgresLines({ postgres }: { postgres: NonNullable<MonitoringHealth['postgres']> }) {
  const t = useT();
  if (postgres.status === 'unknown' && postgres.connections === null) return <p>{t('monitoring_database_unknown')}</p>;
  const { connections: c } = postgres;
  return (
    <>
      {c && <p>{t('monitoring_database_connections', { used: c.used, max: c.max, percent: Math.round(c.percent) })}</p>}
      {(postgres.longest_transaction_seconds ?? 0) >= 60 && <p>{t('monitoring_database_longest', { age: span(postgres.longest_transaction_seconds ?? 0, t) })}</p>}
      {(postgres.idle_transaction_seconds ?? 0) >= 60 && <p className="font-bold text-fg">{t('monitoring_database_idle', { age: span(postgres.idle_transaction_seconds ?? 0, t) })}</p>}
      {(postgres.lock_waits ?? 0) > 0 && <p className="font-bold text-fg">{t('monitoring_database_locks', { count: postgres.lock_waits ?? 0 })}</p>}
    </>
  );
}

function RedisTile({ data }: { data: MonitoringHealth }) {
  const t = useT();
  const { status, latency_ms, memory } = data.redis;
  const high = memory?.percent !== null && memory?.percent !== undefined && memory.percent >= 80;
  return (
    <Tile icon={Server} title={t('monitoring_tile_redis')} verdict={status === 'ok' && high ? 'warning' : status}>
      <p>{status === 'not_used' ? t('monitoring_redis_notUsed') : status === 'fail' ? t('monitoring_redis_fail') : t('monitoring_redis_latency', { ms: latency_ms ?? 0 })}</p>
      {memory && (
        <p className={cx(high && 'font-bold text-fg')}>
          {memory.max_bytes !== null && memory.percent !== null
            ? t('monitoring_redis_memory', { used: bytes(memory.used_bytes), max: bytes(memory.max_bytes), percent: Math.round(memory.percent) })
            : t('monitoring_redis_memoryNoCap', { used: bytes(memory.used_bytes) })}
        </p>
      )}
      {high && <p>{t('monitoring_redis_memoryHigh')}</p>}
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
      {data.queue.worker && <WorkerLine worker={data.queue.worker} />}
    </Tile>
  );
}

function WorkerLine({ worker }: { worker: NonNullable<MonitoringHealth['queue']['worker']> }) {
  const t = useT();
  const age = worker.last_heartbeat_age_seconds;
  if (age === null) return <p>{t('monitoring_queue_workerNone')}</p>;
  if (age > worker.max_age_seconds) return <p className="font-bold text-fg">{t('monitoring_queue_workerStale', { age: span(age, t) })}</p>;
  return <p>{t('monitoring_queue_worker', { age: span(age, t) })}</p>;
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

// M3: the continuous backup (point-in-time recovery), in the owner's words: when the last copy was taken, whether the
// changes keep reaching the backup store, and whether a copy lives off this server
function PitrTile({ pitr }: { pitr: NonNullable<MonitoringHealth['pitr']> }) {
  const t = useT();
  if (pitr.status === 'off') {
    return (
      <Tile icon={History} title={t('monitoring_tile_pitr')} verdict="off">
        <p>{t('monitoring_pitr_off')}</p>
      </Tile>
    );
  }
  if (pitr.status === 'missing') {
    return (
      <Tile icon={History} title={t('monitoring_tile_pitr')} verdict="missing">
        <p>{t('monitoring_pitr_missing')}</p>
      </Tile>
    );
  }
  const offsite = pitr.offsite === 'on' ? 'monitoring_pitr_offsite_on' : pitr.offsite === 'off' || pitr.offsite === null ? 'monitoring_pitr_offsite_off' : 'monitoring_pitr_offsite_bad';
  return (
    <Tile icon={History} title={t('monitoring_tile_pitr')} verdict={pitr.status}>
      {pitr.status === 'stale' && <p className="font-bold text-fg">{t('monitoring_pitr_stale', { age: span(pitr.marker_age_seconds ?? 0, t) })}</p>}
      {pitr.message && <p className="font-bold text-fg">{t('monitoring_pitr_error', { message: pitr.message })}</p>}
      <p>{pitr.last_backup_at ? t('monitoring_pitr_lastBackup', { age: elapsed(pitr.last_backup_at, t), time: when(pitr.last_backup_at) }) : t('monitoring_pitr_lastBackupNone')}</p>
      {pitr.last_full_at && <p>{t('monitoring_pitr_lastFull', { age: elapsed(pitr.last_full_at, t) })}</p>}
      {pitr.archive_failing ? (
        <p className="font-bold text-danger-soft-fg">{t('monitoring_pitr_walFailing')}</p>
      ) : (
        <p>{pitr.last_wal_archived_at ? t('monitoring_pitr_wal', { age: elapsed(pitr.last_wal_archived_at, t) }) : t('monitoring_pitr_walNone')}</p>
      )}
      {(pitr.ready_wal ?? 0) > 1 && <p>{t('monitoring_pitr_walWaiting', { count: pitr.ready_wal ?? 0 })}</p>}
      <p className={cx(pitr.offsite !== 'on' && 'font-bold text-fg')}>{t(offsite)}</p>
    </Tile>
  );
}

function DrillTile({ drill }: { drill: NonNullable<MonitoringHealth['drill']> }) {
  const t = useT();
  const simple = drill.status === 'off' ? 'monitoring_drill_off' : drill.status === 'never' ? 'monitoring_drill_never' : drill.status === 'unknown' ? 'monitoring_drill_unknown' : null;
  return (
    <Tile icon={LifeBuoy} title={t('monitoring_tile_drill')} verdict={drill.status}>
      {simple ? (
        <p>{t(simple)}</p>
      ) : (
        <>
          {drill.last_at && <p>{t('monitoring_drill_last', { time: when(drill.last_at), age: elapsed(drill.last_at, t) })}</p>}
          {drill.status === 'failed' && <p className="font-bold text-danger-soft-fg">{t('monitoring_drill_failed')}</p>}
          {drill.status === 'failed' && drill.error && <p className="break-words">{drill.error}</p>}
          {drill.status === 'stale' && <p className="font-bold text-fg">{t('monitoring_drill_stale', { days: drill.max_age_days })}</p>}
          {drill.restore_seconds !== null && <p>{t('monitoring_drill_restore', { span: exact(drill.restore_seconds, t) })}</p>}
          {drill.data_loss_seconds !== null && <p>{t('monitoring_drill_loss', { span: exact(drill.data_loss_seconds, t) })}</p>}
          {drill.status !== 'failed' && drill.app_check === 'skipped' && (
            <>
              <p className="font-bold text-fg">{t('monitoring_drill_appSkipped')}</p>
              <p>{drill.last_app_check_ok_at ? t('monitoring_drill_appLast', { age: elapsed(drill.last_app_check_ok_at, t) }) : t('monitoring_drill_appNever')}</p>
            </>
          )}
        </>
      )}
    </Tile>
  );
}

function DiskTile({ disk }: { disk: NonNullable<MonitoringHealth['disk']> }) {
  const t = useT();
  if (disk.status === 'missing') {
    return (
      <Tile icon={Gauge} title={t('monitoring_tile_disk')} verdict="unknown">
        <p>{t('monitoring_disk_missing')}</p>
      </Tile>
    );
  }
  const high = disk.status === 'warning' || disk.status === 'fail';
  return (
    <Tile icon={Gauge} title={t('monitoring_tile_disk')} verdict={disk.status}>
      {disk.status === 'stale' && <p className="font-bold text-fg">{t('monitoring_disk_stale', { age: span(disk.age_seconds ?? 0, t) })}</p>}
      {disk.used_percent !== null && (
        <p className={cx(high && 'font-bold text-fg')}>{t('monitoring_disk_used', { percent: Math.round(disk.used_percent), free: bytes(disk.avail_bytes), total: bytes(disk.total_bytes) })}</p>
      )}
      {disk.db_bytes !== null && <p>{t('monitoring_disk_db', { size: bytes(disk.db_bytes) })}</p>}
      {disk.pg_wal_bytes !== null && <p>{t('monitoring_disk_wal', { size: bytes(disk.pg_wal_bytes) })}</p>}
      {disk.repo_used_percent !== null && disk.repo_used_percent !== disk.used_percent && <p>{t('monitoring_disk_repo', { percent: Math.round(disk.repo_used_percent) })}</p>}
      <p>{t('monitoring_disk_limits', { warn: disk.warn_percent, critical: disk.critical_percent })}</p>
    </Tile>
  );
}

function PartitionsTile({ partitions }: { partitions: NonNullable<MonitoringHealth['partitions']> }) {
  const t = useT();
  if (partitions.status === 'unknown') {
    return (
      <Tile icon={CalendarRange} title={t('monitoring_tile_partitions')} verdict="unknown">
        <p>{t('monitoring_partitions_unknown')}</p>
      </Tile>
    );
  }
  return (
    <Tile icon={CalendarRange} title={t('monitoring_tile_partitions')} verdict={partitions.status}>
      <p className={cx(partitions.status === 'fail' && 'font-bold text-fg')}>
        {partitions.months_ahead === null ? t('monitoring_partitions_aheadNone') : t('monitoring_partitions_ahead', { months: partitions.months_ahead, min: partitions.min_months_ahead })}
      </p>
      {partitions.default_rows > 0 && <p className="font-bold text-fg">{t('monitoring_partitions_default', { count: partitions.default_rows })}</p>}
      {(partitions.archive_failed > 0 || partitions.archive_stale > 0) && (
        <p className="font-bold text-fg">{t('monitoring_partitions_archive', { failed: partitions.archive_failed, stale: partitions.archive_stale })}</p>
      )}
      {partitions.problems.length === 0 ? (
        <p>{t('monitoring_partitions_ok')}</p>
      ) : (
        <ul className="flex list-disc flex-col gap-0.5 ps-4">
          {partitions.problems.map((problem) => (
            <li key={`${problem.code}:${problem.table ?? ''}`} className="break-words">
              {problem.message}
            </li>
          ))}
        </ul>
      )}
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
