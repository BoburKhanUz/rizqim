'use client';

import { useEffect, useState } from 'react';
import { ChevronRight, MonitorSmartphone, Search } from 'lucide-react';
import { Card, CardSkeletons, cx, Field, fieldClass, InlineError, Notice, Pill, Button } from '@rizqim/ui';
import { DEFAULT_DEVICE_FILTERS, DEVICE_STATES, useDevice, useDevices, useRevokeDevice, type DeviceFilters, type DeviceRow } from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { DEVICE_LABEL, DEVICE_TONE, minutesSince } from './posModel';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager, ReasonDialog } from './PosParts';

// Qurilmalar (P4-24): every store's devices, online or offline by the last contact (10 minutes), the app version and the
// versions older than the newest in use. Revoking (stores.manage) asks for a reason and the step-up.

export function DevicesTab({ selected, onSelect }: { selected: string | null; onSelect: (uuid: string | null) => void }) {
  if (selected) return <DeviceCardView uuid={selected} onBack={() => onSelect(null)} />;
  return <DeviceList onSelect={onSelect} />;
}

// "oxirgi aloqa 5 daqiqa oldin"
function SeenText({ row }: { row: DeviceRow }) {
  const t = useT();
  const minutes = minutesSince(row.last_seen_at);
  if (minutes === null) return <>{t('pos_dev_neverSeen')}</>;
  if (minutes < 60) return <>{t('pos_dev_seenMinutes', { minutes })}</>;
  if (minutes < 60 * 24) return <>{t('pos_dev_seenHours', { hours: Math.floor(minutes / 60) })}</>;
  return <>{t('pos_dev_seenDays', { days: Math.floor(minutes / 60 / 24) })}</>;
}

function DeviceList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<DeviceFilters>(DEFAULT_DEVICE_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const devices = useDevices(filters);
  const page = devices.data;
  const counts = page?.counts;
  const set = <K extends keyof DeviceFilters>(name: K, value: DeviceFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));
  const latest = Object.entries(page?.latest_versions ?? {});

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('pos_dev_stateFilter')}>
        <Chip on={filters.state === '' && !filters.outdated} onClick={() => setFilters((previous) => ({ ...previous, state: '', outdated: false, page: 1 }))}>
          {counts ? t('pos_countLabel', { label: t('pos_all'), count: counts.total }) : t('pos_all')}
        </Chip>
        {DEVICE_STATES.map((state) => (
          <Chip key={state} on={filters.state === state && !filters.outdated} onClick={() => setFilters((previous) => ({ ...previous, state, outdated: false, page: 1 }))}>
            {counts ? t('pos_countLabel', { label: t(DEVICE_LABEL[state]), count: counts[state] }) : t(DEVICE_LABEL[state])}
          </Chip>
        ))}
        <Chip on={filters.outdated} onClick={() => setFilters((previous) => ({ ...previous, state: '', outdated: !previous.outdated, page: 1 }))}>
          {counts ? t('pos_countLabel', { label: t('pos_dev_outdated'), count: counts.outdated }) : t('pos_dev_outdated')}
        </Chip>
      </ChipRow>
      {latest.length > 0 && <p className="text-footnote text-fg-muted">{t('pos_dev_latest', { versions: latest.map(([type, version]) => `${type} ${version}`).join(', ') })}</p>}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={t('pos_search')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('pos_dev_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
        <Field label={t('pos_dev_type')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.type} onChange={(event) => set('type', event.target.value)}>
              <option value="">{t('pos_all')}</option>
              {Object.keys(counts?.by_type ?? {}).map((type) => (
                <option key={type} value={type}>
                  {type}
                </option>
              ))}
              {filters.type && !(filters.type in (counts?.by_type ?? {})) && <option value={filters.type}>{filters.type}</option>}
            </select>
          )}
        </Field>
      </div>

      <ListState
        loading={!page}
        error={devices.isError ? devices.error : null}
        fetching={devices.isFetching}
        onRetry={() => void devices.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={t('pos_dev_empty')}
        emptyText={t('pos_dev_emptyText')}
        icon={MonitorSmartphone}
        errorTitle={t('pos_loadError')}
      >
        <p className="text-footnote text-fg-muted" aria-live="polite">
          {t('pos_total', { count: page?.total ?? 0 })}
        </p>
        <ul aria-label={t('pos_dev_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => (
            <li key={row.uuid}>
              <Card padded={false}>
                <button
                  type="button"
                  onClick={() => onSelect(row.uuid)}
                  aria-label={t('pos_dev_open', { name: row.name })}
                  className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                  <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="break-words text-body font-bold text-fg">
                        {row.name} · {row.store?.name ?? '—'}
                      </span>
                      <Pill tone={DEVICE_TONE[row.state]}>{t(DEVICE_LABEL[row.state])}</Pill>
                      {row.outdated && <Pill tone="warning">{t('pos_dev_outdated')}</Pill>}
                      {row.sync.pending_ops > 0 && <Pill tone="info">{t('pos_dev_queue', { count: row.sync.pending_ops })}</Pill>}
                    </span>
                    <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
                      <span>{row.type_label}</span>
                      {row.branch && <span>{row.branch.name}</span>}
                      <span>{row.app_version ? t('pos_dev_version', { version: row.app_version }) : t('pos_dev_noVersion')}</span>
                      <span>
                        <SeenText row={row} />
                      </span>
                    </span>
                  </span>
                  <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                </button>
              </Card>
            </li>
          ))}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => set('page', next)} />}
      </ListState>
    </div>
  );
}

function DeviceCardView({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const t = useT();
  const { can } = useCan();
  const card = useDevice(uuid);
  const [revoking, setRevoking] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const revoke = useRevokeDevice();

  if (card.isError) return <InlineError title={t('pos_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;
  const device = card.data;

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">{device.name}</h2>
          <Pill tone={DEVICE_TONE[device.state]}>{t(DEVICE_LABEL[device.state])}</Pill>
          {device.outdated && <Pill tone="warning">{t('pos_dev_outdated')}</Pill>}
        </div>
        <Facts
          rows={[
            [t('pos_store'), device.store?.name ?? '—'],
            [t('pos_branch'), device.branch?.name ?? '—'],
            [t('pos_dev_type'), device.type_label],
            [t('pos_dev_platform'), device.platform ?? '—'],
            [t('pos_dev_appVersion'), device.app_version ?? '—'],
            [t('pos_dev_newest'), device.latest_version ?? '—'],
            [t('pos_dev_lastSeen'), when(device.last_seen_at, t('pos_dev_neverSeen'))],
            [t('pos_dev_paired'), when(device.paired_at)],
            ...(device.revoked_at ? ([[t('pos_dev_revokedAt'), when(device.revoked_at)]] as const) : []),
          ]}
        />
      </Card>
      <Card>
        <h3 className="mb-2 text-body font-bold text-fg">{t('pos_dev_queueTitle')}</h3>
        <Facts
          rows={[
            [t('pos_dev_pending'), String(device.sync.pending_ops)],
            [t('pos_dev_oldestOp'), when(device.sync.oldest_op_at)],
            [t('pos_dev_reported'), when(device.sync.reported_at)],
            [t('pos_dev_skew'), device.sync.clock_skew_seconds === null ? '—' : `${device.sync.clock_skew_seconds} ${t('pos_seconds')}${device.sync.clock_warning ? ` · ${t('pos_dev_skewWarning')}` : ''}`],
          ]}
        />
      </Card>
      {can('stores.manage') && device.state !== 'revoked' && (
        <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
          <Button variant="danger" onClick={() => setRevoking(true)}>
            {t('pos_dev_revoke')}
          </Button>
        </div>
      )}
      {revoking && (
        <ReasonDialog
          title={t('pos_dev_revokeTitle')}
          text={t('pos_dev_revokeText', { name: device.name })}
          submitLabel={t('pos_dev_revokeConfirm')}
          tone="danger"
          busy={revoke.isPending}
          error={revoke.error ? errorText(revoke.error, t) : null}
          reasonError={fieldError(revoke.error, 'reason')}
          onClose={() => setRevoking(false)}
          onSubmit={(reason) =>
            revoke.mutate(
              { uuid: device.uuid, reason },
              {
                onSuccess: () => {
                  setRevoking(false);
                  setNotice(t('pos_dev_revokeDone'));
                },
              },
            )
          }
        />
      )}
    </div>
  );
}
