'use client';

import { useState } from 'react';
import { PackageOpen, Plus } from 'lucide-react';
import { Button, Card, Field, fieldClass, Notice, Pill, Switch } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import {
  DEFAULT_RELEASE_FILTERS,
  RELEASE_APPS,
  RELEASE_PLATFORMS,
  useCreateRelease,
  useDeleteRelease,
  useReleases,
  useRestoreRelease,
  useUpdateRelease,
  useWithdrawRelease,
  type ReleaseApp,
  type ReleaseFilters,
  type ReleasePlatform,
  type ReleaseRow,
} from '@/lib/api/releases';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { Chip, ChipRow, ListState, ReasonDialog } from './PosParts';

// Versiyalar (M5 S5-d): the registry of POS app versions. Reading needs devices.view; adding, editing, withdrawing, restoring and
// deleting need stores.manage, a reason and the step-up. The public page pos.rizqim.uz and the update notice in the tills read it.

const APP_LABEL: Record<ReleaseApp, MessageKey> = {
  cashier: 'pos_rel_app_cashier',
  waiter: 'pos_rel_app_waiter',
  kds: 'pos_rel_app_kds',
  customer_display: 'pos_rel_app_customer_display',
};
const PLATFORM_LABEL: Record<ReleasePlatform, MessageKey> = { android: 'pos_rel_platform_android', windows: 'pos_rel_platform_windows' };

// 12 345 678 bytes -> "11,8 MB"
export function sizeText(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
}

const SHA256 = /^[0-9a-fA-F]{64}$/;
const VERSION = /^\d{1,6}(\.\d{1,6}){1,3}$/;
const toDate = (iso: string | null) => (iso ? iso.slice(0, 10) : '');

type Dialog = { kind: 'add' } | { kind: 'edit' | 'withdraw' | 'restore' | 'delete'; release: ReleaseRow };

export function ReleasesTab() {
  const t = useT();
  const { can } = useCan();
  const manage = can('stores.manage');
  const [filters, setFilters] = useState<ReleaseFilters>(DEFAULT_RELEASE_FILTERS);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const releases = useReleases(filters);
  const rows = releases.data;

  const done = (message: string) => {
    setDialog(null);
    setNotice(message);
  };

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <Notice message={notice} />
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Field label={t('pos_rel_app')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.app} onChange={(event) => setFilters({ ...filters, app: event.target.value as ReleaseApp | '' })}>
              <option value="">{t('pos_all')}</option>
              {RELEASE_APPS.map((app) => (
                <option key={app} value={app}>
                  {t(APP_LABEL[app])}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('pos_rel_platform')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.platform} onChange={(event) => setFilters({ ...filters, platform: event.target.value as ReleasePlatform | '' })}>
              <option value="">{t('pos_all')}</option>
              {RELEASE_PLATFORMS.map((platform) => (
                <option key={platform} value={platform}>
                  {t(PLATFORM_LABEL[platform])}
                </option>
              ))}
            </select>
          )}
        </Field>
        <div className="flex flex-col justify-end">
          <ChipRow label={t('pos_rel_state')}>
            <Chip on={filters.withdrawn === ''} onClick={() => setFilters({ ...filters, withdrawn: '' })}>
              {t('pos_rel_stateAll')}
            </Chip>
            <Chip on={filters.withdrawn === 'no'} onClick={() => setFilters({ ...filters, withdrawn: 'no' })}>
              {t('pos_rel_stateActive')}
            </Chip>
            <Chip on={filters.withdrawn === 'yes'} onClick={() => setFilters({ ...filters, withdrawn: 'yes' })}>
              {t('pos_rel_stateWithdrawn')}
            </Chip>
          </ChipRow>
        </div>
      </div>
      {manage && (
        <div>
          <Button icon={Plus} onClick={() => setDialog({ kind: 'add' })}>
            {t('pos_rel_add')}
          </Button>
        </div>
      )}

      <ListState
        loading={!rows}
        error={releases.isError ? releases.error : null}
        fetching={releases.isFetching}
        onRetry={() => void releases.refetch()}
        empty={rows?.length === 0}
        emptyTitle={t('pos_rel_empty')}
        emptyText={t('pos_rel_emptyText')}
        icon={PackageOpen}
        errorTitle={t('pos_loadError')}
      >
        <ul aria-label={t('pos_rel_list')} className="flex flex-col gap-3">
          {(rows ?? []).map((release) => (
            <li key={release.uuid}>
              <ReleaseCard release={release} manage={manage} onAction={(kind) => setDialog({ kind, release })} />
            </li>
          ))}
        </ul>
      </ListState>

      {dialog?.kind === 'add' && <ReleaseDialog release={null} onClose={() => setDialog(null)} onDone={() => done(t('pos_rel_added'))} />}
      {dialog?.kind === 'edit' && <ReleaseDialog release={dialog.release} onClose={() => setDialog(null)} onDone={() => done(t('pos_rel_saved'))} />}
      {dialog && (dialog.kind === 'withdraw' || dialog.kind === 'restore' || dialog.kind === 'delete') && <StateDialog kind={dialog.kind} release={dialog.release} onClose={() => setDialog(null)} onDone={done} />}
    </div>
  );
}

function ReleaseCard({ release, manage, onAction }: { release: ReleaseRow; manage: boolean; onAction: (kind: 'edit' | 'withdraw' | 'restore' | 'delete') => void }) {
  const t = useT();
  const withdrawn = release.withdrawn_at !== null;
  return (
    <Card className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="text-headline font-bold text-fg">{t(APP_LABEL[release.app])}</h2>
        <Pill tone="info">{t(PLATFORM_LABEL[release.platform])}</Pill>
        <span className="text-body font-bold text-fg">{release.label}</span>
        {release.is_latest && <Pill tone="success">{t('pos_rel_latest')}</Pill>}
        {release.is_minimum && (
          <Pill tone="warning">{release.minimum_from ? `${t('pos_rel_minimum')} · ${t('pos_rel_minimumFrom', { date: when(release.minimum_from) })}` : t('pos_rel_minimum')}</Pill>
        )}
        {withdrawn && <Pill tone="neutral">{t('pos_rel_withdrawn')}</Pill>}
      </div>
      <dl className="grid grid-cols-1 gap-x-4 gap-y-1 text-footnote text-fg-muted sm:grid-cols-2">
        <div>
          <dt className="inline font-bold">{t('pos_rel_released')}: </dt>
          <dd className="inline">{when(release.released_at)}</dd>
        </div>
        <div>
          <dt className="inline font-bold">{t('pos_rel_size')}: </dt>
          <dd className="inline">{sizeText(release.size_bytes)}</dd>
        </div>
        <div className="sm:col-span-2 break-all">
          <dt className="inline font-bold">{t('pos_rel_sha')}: </dt>
          <dd className="inline font-mono">{release.sha256}</dd>
        </div>
        <div className="sm:col-span-2 break-all">
          <dt className="inline font-bold">{t('pos_rel_download')}: </dt>
          <dd className="inline">{release.file_url}</dd>
        </div>
      </dl>
      {release.notes && <p className="whitespace-pre-line text-body text-fg">{release.notes}</p>}
      {manage && (
        <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => onAction('edit')} aria-label={t('pos_rel_edit', { label: `${t(APP_LABEL[release.app])} ${t(PLATFORM_LABEL[release.platform])} ${release.label}` })}>
            {t('pos_rel_editShort')}
          </Button>
          {withdrawn ? (
            <Button variant="secondary" onClick={() => onAction('restore')}>
              {t('pos_rel_restore')}
            </Button>
          ) : (
            <Button variant="secondary" onClick={() => onAction('withdraw')}>
              {t('pos_rel_withdraw')}
            </Button>
          )}
          <Button variant="danger" onClick={() => onAction('delete')}>
            {t('pos_rel_delete')}
          </Button>
        </div>
      )}
    </Card>
  );
}

const fullLabel = (release: ReleaseRow, t: ReturnType<typeof useT>) => `${t(APP_LABEL[release.app])} ${t(PLATFORM_LABEL[release.platform])} ${release.label}`;

function StateDialog({ kind, release, onClose, onDone }: { kind: 'withdraw' | 'restore' | 'delete'; release: ReleaseRow; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const withdraw = useWithdrawRelease();
  const restore = useRestoreRelease();
  const remove = useDeleteRelease();
  const action = kind === 'withdraw' ? withdraw : kind === 'restore' ? restore : remove;
  const label = fullLabel(release, t);
  return (
    <ReasonDialog
      title={t(`pos_rel_${kind}Title`, { label })}
      text={t(`pos_rel_${kind}Text`)}
      submitLabel={t(`pos_rel_${kind}Confirm`)}
      tone={kind === 'delete' ? 'danger' : 'primary'}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ uuid: release.uuid, reason }, { onSuccess: () => onDone(t(`pos_rel_${kind}Done`)) })}
    />
  );
}

function ReleaseDialog({ release, onClose, onDone }: { release: ReleaseRow | null; onClose: () => void; onDone: () => void }) {
  const t = useT();
  const create = useCreateRelease();
  const update = useUpdateRelease();
  const action = release ? update : create;
  const [form, setForm] = useState({
    app: (release?.app ?? 'cashier') as ReleaseApp,
    platform: (release?.platform ?? 'android') as ReleasePlatform,
    version: release?.version ?? '',
    build: String(release?.build ?? ''),
    file_url: release?.file_url ?? '',
    sha256: release?.sha256 ?? '',
    size_bytes: release ? String(release.size_bytes) : '',
    notes: release?.notes ?? '',
    is_minimum: release?.is_minimum ?? false,
    minimum_from: toDate(release?.minimum_from ?? null),
  });
  const set = <K extends keyof typeof form>(name: K, value: (typeof form)[K]) => setForm((previous) => ({ ...previous, [name]: value }));

  const invalid = {
    version: !release && !VERSION.test(form.version.trim()),
    build: !release && !/^\d{1,9}$/.test(form.build.trim()),
    file_url: !/^https:\/\/\S+$/.test(form.file_url.trim()),
    sha256: !SHA256.test(form.sha256.trim()),
    size_bytes: !/^\d{1,12}$/.test(form.size_bytes.trim()),
  };
  const valid = !Object.values(invalid).some(Boolean);
  const bad = (name: keyof typeof invalid) => (invalid[name] && form[name].trim() !== '' ? t('pos_rel_invalid') : fieldError(action.error, name));

  const submit = (reason: string) => {
    const shared = {
      file_url: form.file_url.trim(),
      sha256: form.sha256.trim().toLowerCase(),
      size_bytes: Number(form.size_bytes),
      notes: form.notes.trim() === '' ? null : form.notes.trim(),
      is_minimum: form.is_minimum,
      // Empty with a minimum: the server counts 14 days from now
      minimum_from: form.is_minimum && form.minimum_from ? form.minimum_from : null,
    };
    if (release) update.mutate({ uuid: release.uuid, ...shared, reason }, { onSuccess: onDone });
    else create.mutate({ app: form.app, platform: form.platform, version: form.version.trim(), build: Number(form.build), ...shared, reason }, { onSuccess: onDone });
  };

  return (
    <ReasonDialog
      title={release ? t('pos_rel_editTitle', { label: fullLabel(release, t) }) : t('pos_rel_addTitle')}
      text={release ? t('pos_rel_editText') : t('pos_rel_addText')}
      submitLabel={release ? t('save') : t('pos_rel_addConfirm')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={valid}
      onClose={onClose}
      onSubmit={submit}
    >
      {!release && (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label={t('pos_rel_app')} error={fieldError(create.error, 'app')}>
            {(control) => (
              <select {...control} className={fieldClass} value={form.app} onChange={(event) => set('app', event.target.value as ReleaseApp)}>
                {RELEASE_APPS.map((app) => (
                  <option key={app} value={app}>
                    {t(APP_LABEL[app])}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field label={t('pos_rel_platform')} error={fieldError(create.error, 'platform')}>
            {(control) => (
              <select {...control} className={fieldClass} value={form.platform} onChange={(event) => set('platform', event.target.value as ReleasePlatform)}>
                {RELEASE_PLATFORMS.map((platform) => (
                  <option key={platform} value={platform}>
                    {t(PLATFORM_LABEL[platform])}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <Field label={t('pos_rel_version')} hint={t('pos_rel_versionHint')} error={bad('version')}>
            {(control) => <input {...control} type="text" inputMode="decimal" className={fieldClass} maxLength={32} value={form.version} onChange={(event) => set('version', event.target.value)} />}
          </Field>
          <Field label={t('pos_rel_build')} error={bad('build')}>
            {(control) => <input {...control} type="text" inputMode="numeric" className={fieldClass} maxLength={9} value={form.build} onChange={(event) => set('build', event.target.value)} />}
          </Field>
        </div>
      )}
      <Field label={t('pos_rel_fileUrl')} error={bad('file_url')}>
        {(control) => <input {...control} type="url" className={fieldClass} maxLength={500} value={form.file_url} onChange={(event) => set('file_url', event.target.value)} />}
      </Field>
      <Field label={t('pos_rel_sha256')} error={bad('sha256')}>
        {(control) => <input {...control} type="text" className={`${fieldClass} font-mono`} maxLength={64} value={form.sha256} onChange={(event) => set('sha256', event.target.value)} />}
      </Field>
      <Field label={t('pos_rel_sizeBytes')} error={bad('size_bytes')}>
        {(control) => <input {...control} type="text" inputMode="numeric" className={fieldClass} maxLength={12} value={form.size_bytes} onChange={(event) => set('size_bytes', event.target.value)} />}
      </Field>
      <Field label={t('pos_rel_notesField')} error={fieldError(action.error, 'notes')}>
        {(control) => <textarea {...control} rows={3} maxLength={2000} className={fieldClass} value={form.notes} onChange={(event) => set('notes', event.target.value)} />}
      </Field>
      <Switch checked={form.is_minimum} onChange={(on) => set('is_minimum', on)} label={t('pos_rel_isMinimum')} />
      {form.is_minimum && (
        <Field label={t('pos_rel_minimumFromField')} hint={t('pos_rel_minimumFromHint')} error={fieldError(action.error, 'minimum_from')}>
          {(control) => <input {...control} type="date" className={fieldClass} value={form.minimum_from} onChange={(event) => set('minimum_from', event.target.value)} />}
        </Field>
      )}
    </ReasonDialog>
  );
}
