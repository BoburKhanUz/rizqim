'use client';

import { useEffect, useMemo, useState } from 'react';
import { ChevronRight, MessageSquareText, Search } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, InlineError, Pill, Switch } from '@rizqim/ui';
import { COMPLAINT_FILTERS, DEFAULT_COMPLAINT_FILTERS, useComplaints, type ComplaintFilters, type ComplaintRow } from '@/lib/api/customers';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { CannedRepliesDialog } from './CannedRepliesDialog';
import { ComplaintDialog } from './ComplaintDialog';
import { COMPLAINT_FILTER_LABEL, SUPPORT_TONE } from './customersModel';
import { CHIP, chipTone } from './UsersPanel';

// Shikoyatlar qutisi: every customer complaint with its support status. The open ones (new, in progress) come first, the
// longest waiting on top; "Kutmoqda" marks one waiting longer than the backend's limit (and not answered by the store). A
// row opens the complaint: take it, give it to a colleague, reply to the customer through the bot, resolve it with a note.
export function ComplaintsPanel({ initialComplaint, canManage, onNotice }: { initialComplaint: string | null; canManage: boolean; onNotice: (message: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<ComplaintFilters>(DEFAULT_COMPLAINT_FILTERS);
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState<string | null>(initialComplaint);
  const [canned, setCanned] = useState(false);
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const complaints = useComplaints(filters);
  const rows = useMemo(() => complaints.data?.pages.flatMap((page) => page.data) ?? [], [complaints.data]);
  const first = complaints.data?.pages[0];
  const counts = first?.counts;
  const set = <K extends keyof ComplaintFilters>(name: K, value: ComplaintFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div role="group" aria-label={t('customers_complaintFilter')} className="flex flex-wrap gap-2">
        {COMPLAINT_FILTERS.map((value) => {
          const count = value === 'all' ? undefined : counts?.[value];
          const label = t(COMPLAINT_FILTER_LABEL[value]);
          return (
            <button key={value} type="button" aria-pressed={filters.status === value} className={cx(CHIP, chipTone(filters.status === value))} onClick={() => set('status', value)}>
              {count === undefined ? label : t('customers_count', { label, count })}
            </button>
          );
        })}
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Field label={t('customers_complaintSearch')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('customers_complaintSearchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
        <Field label={t('customers_assigned')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.assigned} onChange={(event) => set('assigned', event.target.value as ComplaintFilters['assigned'])}>
              <option value="">{t('customers_assignedAny')}</option>
              <option value="me">{t('customers_assignedMe')}</option>
              <option value="none">{t('customers_assignedNone')}</option>
            </select>
          )}
        </Field>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <Switch checked={filters.waiting} onChange={(value) => set('waiting', value)} label={t('customers_waitingOnly', { hours: first?.waiting_hours ?? 4, count: counts?.waiting ?? 0 })} />
        <Button variant="secondary" icon={MessageSquareText} onClick={() => setCanned(true)}>
          {t('customers_cannedTitle')}
        </Button>
      </div>

      {complaints.isError ? (
        <InlineError title={t('customers_loadError')} message={errorText(complaints.error, t)} retryLabel={t('retry')} retrying={complaints.isFetching} onRetry={() => void complaints.refetch()} />
      ) : !complaints.data ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={MessageSquareText} title={t('customers_complaintsEmpty')} />
      ) : (
        <>
          <ul aria-label={t('customers_complaintsLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <ComplaintListRow key={row.uuid} row={row} onOpen={() => setOpen(row.uuid)} />
            ))}
          </ul>
          {complaints.hasNextPage && (
            <Button variant="secondary" loading={complaints.isFetchingNextPage} onClick={() => void complaints.fetchNextPage()}>
              {t('customers_loadMore')}
            </Button>
          )}
        </>
      )}

      {open && <ComplaintDialog key={open} uuid={open} assignees={first?.assignees ?? []} onClose={() => setOpen(null)} onDone={onNotice} />}
      {canned && <CannedRepliesDialog canManage={canManage} onClose={() => setCanned(false)} />}
    </div>
  );
}

function ComplaintListRow({ row, onOpen }: { row: ComplaintRow; onOpen: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onOpen}
          aria-label={t('customers_openComplaint', { type: row.type_label, name: row.customer?.name ?? '—' })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="flex flex-wrap items-center gap-2">
              <span className="text-body font-bold text-fg">{row.type_label}</span>
              <Pill tone={SUPPORT_TONE[row.support_status]}>{row.support_status_label}</Pill>
              {row.waiting && <Pill tone="danger">{t('customers_waiting')}</Pill>}
              {row.escalated && <Pill tone="warning">{t('customers_escalated')}</Pill>}
              {row.store_replied && !row.escalated && <Pill tone="neutral">{t('customers_storeReplied')}</Pill>}
            </span>
            {row.description && <span className="break-words text-footnote text-fg">{row.description}</span>}
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.customer?.name ?? '—'}</span>
              {row.store && <span className="break-words">{row.store.name}</span>}
              {row.order?.short_code && <span className="font-mono">#{row.order.short_code}</span>}
              <span>{when(row.created_at)}</span>
              <span>{row.assignee ? t('customers_assignee', { name: row.assignee.name }) : t('customers_noAssignee')}</span>
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}
