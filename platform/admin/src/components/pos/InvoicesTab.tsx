'use client';

import { useEffect, useState } from 'react';
import { ChevronRight, FileText, Search } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, Field, fieldClass, InlineError, Notice, Pill, Section } from '@rizqim/ui';
import {
  DEFAULT_INVOICE_FILTERS,
  DUNNING_STAGES,
  INVOICE_STATUSES,
  useInvoice,
  useInvoices,
  useMarkInvoicePaid,
  useVoidInvoice,
  type InvoiceFilters,
  type InvoiceRow,
} from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { day, DUNNING_LABEL, dunningLabel, INVOICE_LABEL, INVOICE_TONE, invoiceLabel, money } from './posModel';
import { BackButton, Chip, ChipRow, Facts, ListState, Pager, ReasonDialog } from './PosParts';

// Hisob-fakturalar (P4-24): the subscription invoices of every store, how late each is and the last reminder sent. The
// card has the lines, every dunning step and the store's debt split; marking paid and voiding (finance.manage) ask for a
// reason, and the shell asks for the step-up.

export function InvoicesTab({ selected, onSelect }: { selected: string | null; onSelect: (uuid: string | null) => void }) {
  if (selected) return <InvoiceCardView uuid={selected} onBack={() => onSelect(null)} />;
  return <InvoiceList onSelect={onSelect} />;
}

function InvoiceList({ onSelect }: { onSelect: (uuid: string) => void }) {
  const t = useT();
  const [filters, setFilters] = useState<InvoiceFilters>(DEFAULT_INVOICE_FILTERS);
  const [search, setSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setFilters((previous) => (previous.q === search ? previous : { ...previous, q: search, page: 1 })), 300);
    return () => clearTimeout(timer);
  }, [search]);

  const invoices = useInvoices(filters);
  const page = invoices.data;
  const counts = page?.counts;
  const set = <K extends keyof InvoiceFilters>(name: K, value: InvoiceFilters[K]) => setFilters((previous) => ({ ...previous, [name]: value, page: name === 'page' ? (value as number) : 1 }));

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <ChipRow label={t('pos_inv_statusFilter')}>
        <Chip on={filters.status === ''} onClick={() => set('status', '')}>
          {t('pos_all')}
        </Chip>
        {INVOICE_STATUSES.map((status) => (
          <Chip key={status} on={filters.status === status} onClick={() => set('status', status)}>
            {counts ? t('pos_countLabel', { label: t(INVOICE_LABEL[status]), count: counts[status] }) : t(INVOICE_LABEL[status])}
          </Chip>
        ))}
      </ChipRow>
      {counts && Number(counts.outstanding) > 0 && <p className="text-footnote text-fg-muted">{t('pos_inv_outstanding', { amount: money(counts.outstanding) })}</p>}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Field label={t('pos_search')}>
          {(control) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-muted" />
              <input {...control} type="search" value={search} placeholder={t('pos_inv_searchPlaceholder')} onChange={(event) => setSearch(event.target.value)} className={cx(fieldClass, 'pl-9')} />
            </div>
          )}
        </Field>
        <Field label={t('pos_inv_dunning')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.dunning} onChange={(event) => set('dunning', event.target.value)}>
              <option value="">{t('pos_all')}</option>
              {DUNNING_STAGES.map((stage) => (
                <option key={stage} value={stage}>
                  {t(DUNNING_LABEL[stage])}
                </option>
              ))}
            </select>
          )}
        </Field>
      </div>

      <ListState
        loading={!page}
        error={invoices.isError ? invoices.error : null}
        fetching={invoices.isFetching}
        onRetry={() => void invoices.refetch()}
        empty={page?.data.length === 0}
        emptyTitle={t('pos_inv_empty')}
        emptyText={t('pos_inv_emptyText')}
        icon={FileText}
        errorTitle={t('pos_loadError')}
      >
        <p className="text-footnote text-fg-muted" aria-live="polite">
          {t('pos_total', { count: page?.total ?? 0 })}
        </p>
        <ul aria-label={t('pos_inv_listLabel')} className="flex flex-col gap-2">
          {page?.data.map((row) => <InvoiceListRow key={row.uuid} row={row} onSelect={() => onSelect(row.uuid)} />)}
        </ul>
        {page && <Pager page={page.current_page} lastPage={page.last_page} onPage={(next) => set('page', next)} />}
      </ListState>
    </div>
  );
}

function InvoiceListRow({ row, onSelect }: { row: InvoiceRow; onSelect: () => void }) {
  const t = useT();
  return (
    <li>
      <Card padded={false}>
        <button
          type="button"
          onClick={onSelect}
          aria-label={t('pos_inv_open', { number: row.number })}
          className="flex min-h-14 w-full items-center gap-3 rounded-card p-3 text-left hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        >
          <span className="flex min-w-0 flex-1 flex-col gap-1.5">
            <span className="flex flex-wrap items-center gap-2">
              <span className="break-words text-body font-bold text-fg">{row.store?.name ?? '—'}</span>
              <Pill tone={INVOICE_TONE[row.status] ?? 'neutral'}>{t(invoiceLabel(row.status))}</Pill>
              {row.overdue && <Pill tone="danger">{t('pos_inv_daysLate', { days: row.overdue_days })}</Pill>}
              {row.dunning_stage && <Pill tone="neutral">{t(dunningLabel(row.dunning_stage))}</Pill>}
            </span>
            <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
              <span>{row.number}</span>
              <span>{row.plan?.name ?? '—'}</span>
              <span className="font-bold text-fg">{money(row.amount)}</span>
              <span>{t('pos_inv_dueOn', { date: day(row.due_date) })}</span>
            </span>
          </span>
          <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-fg-muted" />
        </button>
      </Card>
    </li>
  );
}

type Action = 'paid' | 'void';

function InvoiceCardView({ uuid, onBack }: { uuid: string; onBack: () => void }) {
  const t = useT();
  const { can } = useCan();
  const card = useInvoice(uuid);
  const [action, setAction] = useState<Action | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (card.isError) return <InlineError title={t('pos_loadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />;
  if (!card.data) return <CardSkeletons count={2} lines={4} label={t('loading')} />;

  const { invoice, store } = card.data;
  const manage = can('finance.manage');

  return (
    <div className="flex max-w-3xl flex-col gap-4">
      <BackButton onClick={onBack} />
      <Notice message={notice} />
      <Card>
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h2 className="text-headline font-bold text-fg">{invoice.number}</h2>
          <Pill tone={INVOICE_TONE[invoice.status] ?? 'neutral'}>{t(invoiceLabel(invoice.status))}</Pill>
          {invoice.overdue && <Pill tone="danger">{t('pos_inv_daysLate', { days: invoice.overdue_days })}</Pill>}
        </div>
        <Facts
          rows={[
            [t('pos_store'), store?.name ?? '—'],
            [t('pos_plan'), invoice.plan?.name ?? '—'],
            [t('pos_inv_period'), invoice.period],
            [t('pos_inv_amount'), money(invoice.amount)],
            [t('pos_inv_paidPart'), money(invoice.paid_amount)],
            [t('pos_inv_outstandingPart'), money(invoice.outstanding)],
            [t('pos_inv_due'), day(invoice.due_date)],
            [t('pos_inv_issuedAt'), when(invoice.issued_at)],
            ...(invoice.paid_at ? ([[t('pos_inv_paidAt'), when(invoice.paid_at)]] as const) : []),
            ...(invoice.voided_at ? ([[t('pos_inv_voidedAt'), `${when(invoice.voided_at)}${invoice.void_reason ? ` · ${invoice.void_reason}` : ''}`]] as const) : []),
          ]}
        />
      </Card>

      <Section title={t('pos_inv_lines')}>
        <Card padded={false}>
          <ul aria-label={t('pos_inv_lines')} className="divide-y divide-line">
            {invoice.lines.map((line, index) => (
              <li key={`${line.type}-${index}`} className="flex items-center justify-between gap-3 p-3 text-body">
                <span className="min-w-0 break-words">
                  {line.label}
                  {line.quantity > 1 && <span className="text-fg-muted"> × {line.quantity}</span>}
                </span>
                <span className={cx('shrink-0 font-bold', Number(line.amount) < 0 ? 'text-success-soft-fg' : 'text-fg')}>{money(line.amount)}</span>
              </li>
            ))}
          </ul>
        </Card>
      </Section>

      <Section title={t('pos_inv_dunningHistory')}>
        {invoice.dunning.length === 0 ? (
          <p className="px-1 text-footnote text-fg-muted">{t('pos_inv_noDunning')}</p>
        ) : (
          <Card padded={false}>
            <ul aria-label={t('pos_inv_dunningHistory')} className="divide-y divide-line">
              {invoice.dunning.map((step) => (
                <li key={step.stage} className="flex flex-wrap items-center justify-between gap-2 p-3 text-body">
                  <span className="font-bold">{step.label}</span>
                  <span className="text-footnote text-fg-muted">
                    {when(step.occurred_at)}
                    {step.notified ? ` · ${t('pos_inv_notified', { count: step.recipients })}` : ''}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        )}
      </Section>

      {store && (
        <Section title={t('pos_inv_debt')}>
          <Card>
            <Facts
              rows={[
                [t('pos_debt_total'), money(store.debt_balance)],
                [t('pos_debt_subscription'), money(store.subscription_debt)],
                [t('pos_debt_commission'), money(store.commission_debt)],
              ]}
            />
          </Card>
        </Section>
      )}

      {manage && invoice.status !== 'void' && (
        <div role="group" aria-label={t('pos_actions')} className="flex flex-wrap gap-2">
          {invoice.status === 'issued' && <Button onClick={() => setAction('paid')}>{t('pos_inv_markPaid')}</Button>}
          <Button variant="danger" onClick={() => setAction('void')}>
            {t('pos_inv_voidAction')}
          </Button>
        </div>
      )}

      {action && (
        <InvoiceActionDialog
          kind={action}
          uuid={invoice.uuid}
          onClose={() => setAction(null)}
          onDone={(message) => {
            setAction(null);
            setNotice(message);
          }}
        />
      )}
    </div>
  );
}

function InvoiceActionDialog({ kind, uuid, onDone, onClose }: { kind: Action; uuid: string; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const paid = useMarkInvoicePaid();
  const voided = useVoidInvoice();
  const run = kind === 'paid' ? paid : voided;
  return (
    <ReasonDialog
      title={t(kind === 'paid' ? 'pos_inv_paidTitle' : 'pos_inv_voidTitle')}
      text={t(kind === 'paid' ? 'pos_inv_paidText' : 'pos_inv_voidText')}
      submitLabel={t(kind === 'paid' ? 'pos_inv_paidConfirm' : 'pos_inv_voidConfirm')}
      tone={kind === 'paid' ? 'primary' : 'danger'}
      busy={run.isPending}
      error={run.error ? errorText(run.error, t) : null}
      reasonError={fieldError(run.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => run.mutate({ uuid, reason }, { onSuccess: () => onDone(t(kind === 'paid' ? 'pos_inv_paidDone' : 'pos_inv_voidDone')) })}
    />
  );
}
