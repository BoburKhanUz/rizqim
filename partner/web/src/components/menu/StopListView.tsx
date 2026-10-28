'use client';

import { useMemo, useState } from 'react';
import { Ban, Clock, PackageX, Search, Undo2 } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, formatDateTime, IconButton, InlineError, PageHeader } from '@rizqim/ui';
import {
  useAddStopListEntry,
  useEmployees,
  useModifierGroups,
  useOwnerProducts,
  useRemoveStopListEntry,
  useStopList,
  type StopListEntry,
  type StopListInput,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useMenuAccess } from './access';
import { BranchSelect, useBranchChoice } from './BranchChoice';
import { ErrorBanner } from './ErrorBanner';
import { describeError } from './serverError';
import { StockTabs } from './StockTabs';

type Subject = StopListInput['type'];

interface Row {
  type: Subject;
  id: number;
  name: string;
  // Under which product or group
  context?: string;
}

const entryKey = (type: string, id: number) => `${type}:${id}`;

// Stop-list: what a branch cannot sell right now. One tap stops a product, a variant or a modifier, one tap puts it back.
export function StopListView() {
  const { t } = useI18n();
  const access = useMenuAccess();
  const choice = useBranchChoice();
  const canManage = access.can('stoplist.manage');

  return (
    <>
      <PageHeader title={t('nav_stoplist')} subtitle={t('menu_stop_subtitle')} />
      <StockTabs current="stoplist" />
      {choice.isPending ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : choice.isError ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={choice.isFetching} onRetry={choice.refetch} />
      ) : choice.branchId === null ? (
        <EmptyState icon={PackageX} title={t('menu_no_branches')} />
      ) : (
        <div className="flex flex-col gap-6">
          <BranchSelect branches={choice.branches} value={choice.branchId} onChange={choice.choose} className="max-w-xs" />
          <StopListBranch key={choice.branchId} branchId={choice.branchId} canManage={canManage} canSeeStaff={access.can('staff.view')} />
        </div>
      )}
    </>
  );
}

function StopListBranch({ branchId, canManage, canSeeStaff }: { branchId: number; canManage: boolean; canSeeStaff: boolean }) {
  const { t, language } = useI18n();
  const entries = useStopList(branchId, { refetchInterval: 30_000 });
  const products = useOwnerProducts();
  const groups = useModifierGroups();
  // Who stopped it: the API gives the user's id; the staff list (owner) turns it into a name
  const employees = useEmployees({ enabled: canSeeStaff, retry: false });
  const add = useAddStopListEntry();
  const remove = useRemoveStopListEntry();
  const [search, setSearch] = useState('');
  const [detail, setDetail] = useState<Row | null>(null);
  const [reason, setReason] = useState('');
  const [until, setUntil] = useState('');

  const stopped = useMemo(() => new Map((entries.data ?? []).map((entry) => [entryKey(entry.type, entry.subject_id), entry])), [entries.data]);

  const rows = useMemo<Row[]>(() => {
    const list: Row[] = [];
    for (const product of products.data ?? []) {
      list.push({ type: 'product', id: product.id, name: product.name });
      const variants = product.variants ?? [];
      if (variants.length > 1) for (const variant of variants) list.push({ type: 'variant', id: variant.id, name: variant.name ?? '', context: product.name });
    }
    for (const group of groups.data ?? []) for (const modifier of group.modifiers) list.push({ type: 'modifier', id: modifier.id, name: modifier.name, context: group.name });
    return list;
  }, [products.data, groups.data]);

  const needle = search.trim().toLowerCase();
  const shown = rows.filter((row) => needle === '' || row.name.toLowerCase().includes(needle) || (row.context ?? '').toLowerCase().includes(needle));
  const typeLabel = (type: string) => (type === 'product' ? t('menu_stop_type_product') : type === 'variant' ? t('menu_stop_type_variant') : t('menu_stop_type_modifier'));
  const whoName = (entry: StopListEntry): string | null => {
    if (entry.created_by === null) return null;
    return employees.data?.find((employee) => employee.id === entry.created_by)?.name ?? null;
  };

  const stop = (row: Row, extra: { reason?: string; until?: string } = {}) => {
    add.mutate({
      branch_id: branchId,
      type: row.type,
      id: row.id,
      ...(extra.reason ? { reason: extra.reason } : {}),
      ...(extra.until ? { until: new Date(extra.until).toISOString() } : {}),
    });
  };
  const pendingKey = add.isPending && add.variables ? entryKey(add.variables.type, add.variables.id) : remove.isPending && remove.variables !== undefined ? `entry:${remove.variables}` : null;
  const failure = add.error ?? remove.error;
  const failureView = failure ? describeError(failure, t) : null;

  if (entries.isPending) return <CardSkeletons count={3} lines={2} label={t('loading')} />;
  if (entries.isError) {
    return <InlineError title={t('loadErrorTitle')} message={describeError(entries.error, t).message} retryLabel={t('retry')} retrying={entries.isFetching} onRetry={() => void entries.refetch()} />;
  }

  return (
    <div className="flex flex-col gap-6">
      {failureView && <ErrorBanner message={failureView.message} details={Object.values(failureView.fields)} />}

      <section aria-labelledby="stopped-title" className="flex flex-col gap-2">
        <h2 id="stopped-title" className="px-1 text-footnote font-bold text-fg-muted">
          {t('menu_stop_current', { n: entries.data.length })}
        </h2>
        {entries.data.length === 0 ? (
          <EmptyState icon={PackageX} title={t('menu_stop_empty_title')} text={t('menu_stop_empty_text')} className="py-8" />
        ) : (
          <ul className="flex flex-col gap-2">
            {entries.data.map((entry) => {
              const who = whoName(entry);
              return (
                <li key={entry.id}>
                  <Card className="flex flex-wrap items-center gap-3">
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg">
                        {String(entry.name ?? '—')}
                        <span className="rounded-control bg-surface-2 px-2 py-0.5 text-caption font-bold text-fg-muted">{typeLabel(entry.type)}</span>
                      </p>
                      <p className="text-footnote text-fg-muted">
                        {[
                          entry.reason,
                          entry.until ? t('menu_stop_until', { time: formatDateTime(entry.until, language) }) : null,
                          entry.created_at ? t('menu_stop_since', { time: formatDateTime(entry.created_at, language) }) : null,
                          who ? t('menu_stop_by', { name: who }) : null,
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </div>
                    {canManage && (
                      <Button variant="secondary" icon={Undo2} loading={pendingKey === `entry:${entry.id}`} onClick={() => remove.mutate(entry.id)}>
                        {t('menu_stop_restore')}
                        <span className="sr-only"> {String(entry.name ?? '')}</span>
                      </Button>
                    )}
                  </Card>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <section aria-labelledby="menu-title" className="flex flex-col gap-3">
        <h2 id="menu-title" className="px-1 text-footnote font-bold text-fg-muted">
          {t('menu_stop_menu')}
        </h2>
        <label className="relative">
          <span className="sr-only">{t('menu_search')}</span>
          <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-muted" />
          <input type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('menu_search')} className={`${fieldClass} pl-10`} />
        </label>
        {products.isPending || groups.isPending ? (
          <CardSkeletons count={3} lines={1} label={t('loading')} />
        ) : products.isError ? (
          <InlineError title={t('loadErrorTitle')} message={describeError(products.error, t).message} retryLabel={t('retry')} retrying={products.isFetching} onRetry={() => void products.refetch()} />
        ) : shown.length === 0 ? (
          <EmptyState icon={Search} title={t('menu_nothing_found')} text={t('menu_nothing_found_text')} className="py-8" />
        ) : (
          <ul className="flex flex-col gap-2">
            {shown.map((row) => {
              const entry = stopped.get(entryKey(row.type, row.id));
              const key = entryKey(row.type, row.id);
              return (
                <li key={key}>
                  <Card className={`flex flex-wrap items-center gap-3 ${row.type !== 'product' ? 'sm:ml-6' : ''}`}>
                    <div className="min-w-0 flex-1">
                      <p className="text-body font-bold text-fg">{row.name}</p>
                      <p className="text-footnote text-fg-muted">{[typeLabel(row.type), row.context].filter(Boolean).join(' · ')}</p>
                    </div>
                    {entry ? (
                      <>
                        <span className="rounded-control bg-danger-soft px-2 py-1 text-caption font-bold text-danger-soft-fg">{t('menu_stop_stopped')}</span>
                        {canManage && (
                          <Button variant="secondary" icon={Undo2} loading={pendingKey === `entry:${entry.id}`} onClick={() => remove.mutate(entry.id)}>
                            {t('menu_stop_restore')}
                            <span className="sr-only"> {row.name}</span>
                          </Button>
                        )}
                      </>
                    ) : (
                      canManage && (
                        <>
                          <IconButton icon={Clock} label={`${t('menu_stop_with_reason')}: ${row.name}`} onClick={() => { setReason(''); setUntil(''); setDetail(row); }} />
                          <Button variant="danger" icon={Ban} loading={pendingKey === key} onClick={() => stop(row)}>
                            {t('menu_stop_stop')}
                            <span className="sr-only"> {row.name}</span>
                          </Button>
                        </>
                      )
                    )}
                  </Card>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <Dialog
        open={detail !== null}
        onClose={() => setDetail(null)}
        title={t('menu_stop_with_reason')}
        description={detail?.name}
        busy={add.isPending}
        closeLabel={t('menu_close')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setDetail(null)} disabled={add.isPending}>
              {t('menu_cancel')}
            </Button>
            <Button
              variant="danger"
              loading={add.isPending}
              onClick={() => {
                if (!detail) return;
                add.mutate(
                  { branch_id: branchId, type: detail.type, id: detail.id, ...(reason.trim() ? { reason: reason.trim() } : {}), ...(until ? { until: new Date(until).toISOString() } : {}) },
                  { onSuccess: () => setDetail(null) },
                );
              }}
            >
              {t('menu_stop_stop')}
            </Button>
          </>
        }
      >
        <Field label={t('menu_stop_reason')}>
          {(control) => <input {...control} className={fieldClass} value={reason} maxLength={255} onChange={(event) => setReason(event.target.value)} />}
        </Field>
        <Field label={t('menu_stop_until_label')} hint={t('menu_stop_until_hint')}>
          {(control) => <input {...control} type="datetime-local" className={fieldClass} value={until} onChange={(event) => setUntil(event.target.value)} />}
        </Field>
        {add.error && <ErrorBanner message={describeError(add.error, t).message} details={Object.values(describeError(add.error, t).fields)} />}
      </Dialog>
    </div>
  );
}
