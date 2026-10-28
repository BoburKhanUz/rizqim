'use client';

import Link from 'next/link';
import { useEffect, useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { ChevronRight, Plus, Search, ShieldAlert, Workflow } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Dialog, EmptyState, Field, fieldClass, FormError, InlineError, PageHeader, Pill } from '@rizqim/ui';
import { PIPELINE_STAGES, useApplications, useCreateApplication, type ApplicationRow } from '@/lib/api/stores';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { RegionSelect } from './StorePicker';
import { StoresNav } from './StoresNav';
import { day, SOURCE_LABEL } from './storesModel';

// Ulash jarayoni (P4-22, docs/PHASE4_PLAN.md 4.3): applications by stage — ariza → tekshiruv → menyu → qurilmalar →
// o'qitish → sinov → faol. On a wide screen a board (one column per stage), on a phone the same columns one under the
// other. The overdue ones are marked in words; a card opens the application.

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');

type View = 'open' | 'overdue' | 'mine' | 'rejected';

export function PipelineView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('stores.view');
  const [view, setView] = useState<View>('open');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [adding, setAdding] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setQ(search.trim().length >= 2 ? search.trim() : ''), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const query = { stage: view === 'mine' ? 'open' : view, assignee_id: view === 'mine' ? 'me' : undefined, q: q || undefined };
  const applications = useApplications(query, allowed);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_stores')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const page = applications.data;
  const stageLabel = (key: string) => page?.stages.find((stage) => stage.key === key)?.label ?? key;
  const counts = page?.stage_counts;
  const columns = view === 'rejected' ? ['rejected'] : PIPELINE_STAGES.filter((stage) => view !== 'overdue' || stage !== 'active');

  return (
    <>
      <PageHeader
        title={t('stores_pipelineTitle')}
        subtitle={t('stores_pipelineSubtitle')}
        action={
          can('stores.manage') ? (
            <Button icon={Plus} onClick={() => setAdding(true)}>
              {t('stores_addApplication')}
            </Button>
          ) : undefined
        }
      />
      <StoresNav current="pipeline" />

      <section aria-label={t('orders_filters')} className="mb-4 flex flex-col gap-3">
        <div role="group" aria-label={t('stores_pipelineBoard')} className="flex flex-wrap gap-2">
          <button type="button" aria-pressed={view === 'open'} className={cx(CHIP, chipTone(view === 'open'))} onClick={() => setView('open')}>
            {t('stores_filterOpen')}
          </button>
          <button type="button" aria-pressed={view === 'overdue'} className={cx(CHIP, chipTone(view === 'overdue'))} onClick={() => setView('overdue')}>
            {t('stores_filterOverdue', { count: counts?.overdue ?? 0 })}
          </button>
          <button type="button" aria-pressed={view === 'mine'} className={cx(CHIP, chipTone(view === 'mine'))} onClick={() => setView('mine')}>
            {t('stores_filterMine')}
          </button>
          <button type="button" aria-pressed={view === 'rejected'} className={cx(CHIP, chipTone(view === 'rejected'))} onClick={() => setView('rejected')}>
            {t('stores_filterRejected', { count: counts?.rejected ?? 0 })}
          </button>
        </div>
        <Field label={t('stores_search')} className="max-w-md">
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('stores_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
      </section>

      {applications.isError ? (
        <InlineError title={t('stores_applicationsLoadError')} message={errorText(applications.error, t)} retryLabel={t('retry')} retrying={applications.isFetching} onRetry={() => void applications.refetch()} />
      ) : !page ? (
        <CardSkeletons count={3} lines={3} label={t('loading')} />
      ) : page.total === 0 ? (
        <EmptyState icon={Workflow} title={t('stores_pipelineEmpty')} />
      ) : (
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:overflow-x-auto lg:pb-2">
          {columns.map((stage) => {
            const rows = page.data.filter((row) => row.stage === stage);
            const label = stageLabel(stage);
            return (
              <section key={stage} aria-label={label} className="flex flex-col gap-2 lg:w-64 lg:shrink-0">
                <h2 className="text-footnote font-bold text-fg">{t('stores_stageCount', { label, count: view === 'open' && counts ? (counts[stage] ?? rows.length) : rows.length })}</h2>
                {rows.length === 0 ? (
                  <p className="text-caption text-fg-muted">{t('stores_stageEmpty')}</p>
                ) : (
                  <ul className="flex flex-col gap-2">
                    {rows.map((row) => (
                      <ApplicationTile key={row.uuid} row={row} />
                    ))}
                  </ul>
                )}
              </section>
            );
          })}
        </div>
      )}

      {adding && <AddApplicationDialog formats={page?.formats ?? []} onClose={() => setAdding(false)} />}
    </>
  );
}

function ApplicationTile({ row }: { row: ApplicationRow }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <Link
          href={`/stores/applications/${row.uuid}`}
          aria-label={t('stores_openApplication', { name: row.name })}
          className="flex min-h-14 items-center gap-2 rounded-card p-3 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="break-words text-footnote font-bold text-fg">{row.name}</span>
            <span className="text-caption text-fg-muted">{[row.format_label, row.region?.name ?? row.city, row.owner_name].filter(Boolean).join(' · ')}</span>
            <span className="flex flex-wrap items-center gap-1.5 text-caption text-fg-muted">
              {row.overdue && <Pill tone="danger">{t('stores_overdue')}</Pill>}
              {row.due_on && <span>{t('stores_due', { date: day(row.due_on) })}</span>}
              <span>{row.assignee?.name ?? t('stores_noAssignee')}</span>
              <span>{t(SOURCE_LABEL[row.source] ?? 'stores_source_admin')}</span>
            </span>
            {row.stage === 'application' && <span className="text-caption text-fg-muted">{when(row.created_at)}</span>}
          </span>
          <ChevronRight aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />
        </Link>
      </Card>
    </li>
  );
}

function AddApplicationDialog({ formats, onClose }: { formats: { key: string; label: string }[]; onClose: () => void }) {
  const t = useT();
  const router = useRouter();
  const create = useCreateApplication();
  const [form, setForm] = useState({ name: '', format: formats[0]?.key ?? 'cafe', owner_name: '', phone: '', region_id: '', city: '', tin: '', comment: '' });
  const set = (name: keyof typeof form, value: string) => setForm((previous) => ({ ...previous, [name]: value }));
  const ready = form.name.trim().length >= 2 && form.owner_name.trim().length >= 2 && form.phone.trim().length >= 9;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!ready || create.isPending) return;
    create.mutate(
      {
        name: form.name.trim(),
        format: form.format,
        owner_name: form.owner_name.trim(),
        phone: form.phone.trim(),
        region_id: form.region_id ? Number(form.region_id) : null,
        city: form.city.trim() || undefined,
        tin: form.tin.trim() || undefined,
        comment: form.comment.trim() || undefined,
      },
      { onSuccess: (card) => router.push(`/stores/applications/${card.uuid}`) },
    );
  };
  const error = (field: string) => fieldError(create.error, field);
  return (
    <Dialog open title={t('stores_addTitle')} busy={create.isPending} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <Field label={t('stores_fieldName')} error={error('name')}>
          {(control) => <input {...control} data-autofocus type="text" maxLength={120} className={fieldClass} value={form.name} onChange={(event) => set('name', event.target.value)} />}
        </Field>
        <Field label={t('stores_fieldFormat')} error={error('format')}>
          {(control) => (
            <select {...control} className={fieldClass} value={form.format} onChange={(event) => set('format', event.target.value)}>
              {formats.map((format) => (
                <option key={format.key} value={format.key}>
                  {format.label}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('stores_fieldOwner')} error={error('owner_name')}>
          {(control) => <input {...control} type="text" maxLength={120} className={fieldClass} value={form.owner_name} onChange={(event) => set('owner_name', event.target.value)} />}
        </Field>
        <Field label={t('stores_fieldPhone')} hint={t('stores_fieldPhoneHint')} error={error('phone')}>
          {(control) => <input {...control} type="tel" maxLength={30} className={fieldClass} value={form.phone} onChange={(event) => set('phone', event.target.value)} />}
        </Field>
        <RegionSelect value={form.region_id} onChange={(value) => set('region_id', value)} label={t('stores_region')} />
        <Field label={t('stores_fieldCity')} error={error('city')}>
          {(control) => <input {...control} type="text" maxLength={120} className={fieldClass} value={form.city} onChange={(event) => set('city', event.target.value)} />}
        </Field>
        <Field label={t('stores_fieldTin')} error={error('tin')}>
          {(control) => <input {...control} type="text" inputMode="numeric" maxLength={9} className={fieldClass} value={form.tin} onChange={(event) => set('tin', event.target.value.replace(/\D+/g, ''))} />}
        </Field>
        <Field label={t('stores_fieldComment')} error={error('comment')}>
          {(control) => <textarea {...control} rows={2} maxLength={1000} className={fieldClass} value={form.comment} onChange={(event) => set('comment', event.target.value)} />}
        </Field>
        <FormError message={create.error && Object.keys((create.error as { fields?: object }).fields ?? {}).length === 0 ? errorText(create.error, t) : null} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('cancel')}
          </Button>
          <Button type="submit" loading={create.isPending} disabled={!ready}>
            {t('stores_addSave')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
