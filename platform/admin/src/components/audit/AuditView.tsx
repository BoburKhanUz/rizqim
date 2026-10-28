'use client';

import { useState, type FormEvent } from 'react';
import { ChevronRight, History, ShieldAlert } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, Field, fieldClass, InlineError, PageHeader } from '@rizqim/ui';
import { useAuditLog } from '@/lib/api/hooks';
import { EMPTY_AUDIT_FILTERS, type AuditEntry, type AuditFilters } from '@/lib/api/types';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { AuditDetail } from './AuditDetail';

const text = (name: keyof AuditFilters, value: string, set: (value: string) => void) => ({ name, value, onChange: (event: { target: { value: string } }) => set(event.target.value) });

// Tizim → Harakatlar jurnali (audit.view): the append-only log of the whole platform. Filters are applied together with the
// button (one request per look, not per key), the list reads newest first and loads more by cursor, a row opens the detail.
export function AuditView() {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('audit.view');
  const [draft, setDraft] = useState<AuditFilters>(EMPTY_AUDIT_FILTERS);
  const [applied, setApplied] = useState<AuditFilters>(EMPTY_AUDIT_FILTERS);
  const [more, setMore] = useState(false);
  const [open, setOpen] = useState<AuditEntry | null>(null);
  const log = useAuditLog(applied, allowed);

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_audit')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const set = (name: keyof AuditFilters) => (value: string) => setDraft((previous) => ({ ...previous, [name]: value }));
  const apply = (event: FormEvent) => {
    event.preventDefault();
    setApplied({ ...draft });
  };
  const clear = () => {
    setDraft(EMPTY_AUDIT_FILTERS);
    setApplied(EMPTY_AUDIT_FILTERS);
  };

  const entries = log.data?.pages.flatMap((page) => page.data) ?? [];
  return (
    <>
      <PageHeader title={t('audit_title')} subtitle={t('audit_subtitle')} />
      <form onSubmit={apply} aria-label={t('audit_filters')} className="mb-4 flex max-w-5xl flex-col gap-3 rounded-card border border-line bg-surface p-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={t('audit_q')}>{(control) => <input {...control} {...text('q', draft.q, set('q'))} type="search" placeholder={t('audit_qPlaceholder')} className={fieldClass} />}</Field>
          <Field label={t('audit_action')} hint={t('audit_actionHint')}>
            {(control) => <input {...control} {...text('action', draft.action, set('action'))} type="text" placeholder={t('audit_actionPlaceholder')} autoCapitalize="none" className={fieldClass} />}
          </Field>
          <Field label={t('audit_from')}>{(control) => <input {...control} {...text('from', draft.from, set('from'))} type="date" className={fieldClass} />}</Field>
          <Field label={t('audit_to')}>{(control) => <input {...control} {...text('to', draft.to, set('to'))} type="date" className={fieldClass} />}</Field>
        </div>
        {more && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Field label={t('audit_actor')}>{(control) => <input {...control} {...text('actor_id', draft.actor_id, set('actor_id'))} type="text" inputMode="numeric" className={fieldClass} />}</Field>
            <Field label={t('audit_store')}>{(control) => <input {...control} {...text('store_id', draft.store_id, set('store_id'))} type="text" inputMode="numeric" className={fieldClass} />}</Field>
            <Field label={t('audit_subjectType')}>
              {(control) => <input {...control} {...text('subject_type', draft.subject_type, set('subject_type'))} type="text" placeholder={t('audit_subjectTypePlaceholder')} autoCapitalize="none" className={fieldClass} />}
            </Field>
            <Field label={t('audit_subjectId')}>{(control) => <input {...control} {...text('subject_id', draft.subject_id, set('subject_id'))} type="text" className={fieldClass} />}</Field>
          </div>
        )}
        <div className="flex flex-wrap gap-2">
          <Button type="submit">{t('audit_apply')}</Button>
          <Button variant="secondary" onClick={clear}>
            {t('audit_clear')}
          </Button>
          <Button variant="ghost" aria-expanded={more} onClick={() => setMore((value) => !value)}>
            {more ? t('audit_less') : t('audit_more')}
          </Button>
        </div>
      </form>

      {log.isError ? (
        <InlineError title={t('audit_loadError')} message={errorText(log.error, t)} retryLabel={t('retry')} retrying={log.isFetching} onRetry={() => void log.refetch()} />
      ) : !log.isSuccess ? (
        <CardSkeletons count={4} lines={2} label={t('loading')} />
      ) : entries.length === 0 ? (
        <EmptyState icon={History} title={t('audit_empty')} text={t('audit_emptyText')} />
      ) : (
        <div className="flex max-w-5xl flex-col gap-3">
          <ul aria-label={t('audit_listLabel')} className="flex flex-col gap-2">
            {entries.map((entry) => (
              <li key={entry.id}>
                <Card padded={false}>
                  <button
                    type="button"
                    aria-label={t('audit_open', { action: entry.action })}
                    onClick={() => setOpen(entry)}
                    className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                  >
                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                      <span className="break-all font-mono text-footnote font-bold text-fg">{entry.action}</span>
                      <span className="text-footnote text-fg-muted">
                        {entry.actor?.name ?? t('audit_system')} · {when(entry.created_at)}
                      </span>
                      <span className="break-words text-footnote text-fg-muted">
                        {[entry.subject_type && `${entry.subject_type}${entry.subject_id ? ` #${entry.subject_id}` : ''}`, entry.store && `${t('audit_storeLabel')}: ${entry.store.name}`, entry.reason].filter(Boolean).join(' · ')}
                      </span>
                    </span>
                    <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
                  </button>
                </Card>
              </li>
            ))}
          </ul>
          {log.hasNextPage && (
            <Button variant="secondary" loading={log.isFetchingNextPage} onClick={() => void log.fetchNextPage()}>
              {t('audit_loadMore')}
            </Button>
          )}
        </div>
      )}

      {open && <AuditDetail entry={open} onClose={() => setOpen(null)} />}
    </>
  );
}
