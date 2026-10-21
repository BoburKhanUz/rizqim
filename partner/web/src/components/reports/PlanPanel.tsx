'use client';

import { useState } from 'react';
import { keepPreviousData } from '@tanstack/react-query';
import { FileText } from 'lucide-react';
import { Card, EmptyState, InlineError, Section, Skeleton, formatMoney, formatNumber } from '@rizqim/ui';
import { getApiErrorMessage, getApiErrorStatus, useInvoices, useSubscription, type InvoiceQuery } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { formatDay, formatMonth, labelFor } from './format';
import { HistoryFilters, SelectFilter } from './HistoryFilters';
import { DataTable, Pager, Pill, Td, Th, type Tone } from './ui';

const INVOICE_STATUSES = ['issued', 'paid', 'void'] as const;
const INVOICE_TONE: Record<string, Tone> = { issued: 'warning', paid: 'success', void: 'neutral' };
const SUBSCRIPTION_TONE: Record<string, Tone> = { trialing: 'info', active: 'success', past_due: 'danger', cancelled: 'neutral' };

// The invoice list takes a `page` that the generated types of the endpoint do not list
type InvoiceListQuery = InvoiceQuery & { page?: number };

// Tarif va hisob-fakturalar: the plan and its period, the estimate of the running period's invoice and the invoices
export function PlanPanel() {
  return (
    <div className="flex flex-col gap-6">
      <PlanCard />
      <InvoicesCard />
    </div>
  );
}

function PlanCard() {
  const { t, language } = useI18n();
  const query = useSubscription();
  if (query.isError) {
    const status = getApiErrorStatus(query.error);
    return <InlineError title={t('loadErrorTitle')} message={(status === 403 ? getApiErrorMessage(query.error) : undefined) ?? t('loadErrorText')} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  }
  if (!query.data) return <Skeleton className="h-40 w-full" />;

  const { subscription, next_invoice: nextInvoice } = query.data;
  if (!subscription) {
    return (
      <Card className="flex flex-col gap-2">
        <h2 className="text-headline font-bold text-fg">{t('fin_planCurrent')}</h2>
        <p className="text-body text-fg-muted">{t('fin_planNone')}</p>
        <p className="text-footnote text-fg-muted">{t('fin_planCommission', { n: formatNumber(query.data.commission_rate, language) })}</p>
      </Card>
    );
  }

  const { plan } = subscription;
  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-headline font-bold text-fg">{t('fin_planCurrent')}</h2>
          <Pill tone={SUBSCRIPTION_TONE[subscription.status] ?? 'neutral'}>{labelFor(t, 'fin_s_', subscription.status) === subscription.status ? subscription.status_label : labelFor(t, 'fin_s_', subscription.status)}</Pill>
        </div>
        <p className="text-title font-bold text-fg">{plan.name}</p>
        {plan.summary && <p className="text-footnote text-fg-muted">{plan.summary}</p>}
        <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-body">
          <dt className="text-fg-muted">{t('fin_planBaseFee')}</dt>
          <dd className="text-right font-medium text-fg">{formatMoney(plan.monthly_base_fee, language)}</dd>
          <dt className="text-fg-muted">{t('fin_planPerTerminal')}</dt>
          <dd className="text-right font-medium text-fg">{formatMoney(plan.monthly_fee_per_terminal, language)}</dd>
          <dt className="text-fg-muted">{t('fin_planIncluded')}</dt>
          <dd className="text-right font-medium text-fg">{formatNumber(plan.included_terminals, language)}</dd>
        </dl>
        <ul className="flex flex-col gap-1 text-footnote text-fg-muted">
          <li>{t('fin_planCommission', { n: formatNumber(query.data.commission_rate, language) })}</li>
          <li>{t('fin_planPeriod', { from: formatDay(subscription.current_period.start.slice(0, 10), language), to: formatDay(subscription.current_period.end.slice(0, 10), language) })}</li>
          {subscription.trial_ends_at && <li>{t('fin_planTrial', { date: formatDay(subscription.trial_ends_at.slice(0, 10), language) })}</li>}
          {subscription.next_plan && <li>{t('fin_planNext', { name: subscription.next_plan.name })}</li>}
          {subscription.cancel_at_period_end && <li>{t('fin_planCancel')}</li>}
          <li>{t('fin_planTerminals', { now: query.data.terminals.active_now, counted: query.data.terminals.counted ?? '—' })}</li>
        </ul>
      </Card>

      {nextInvoice && (
        <Card className="flex flex-col gap-3">
          <h2 className="text-headline font-bold text-fg">{t('fin_nextInvoice')}</h2>
          {nextInvoice.trial ? (
            <p className="text-body text-fg-muted">{t('fin_nextInvoiceTrial')}</p>
          ) : (
            <>
              <ul className="flex flex-col divide-y divide-line">
                {nextInvoice.lines.map((line, index) => (
                  <li key={`${line.type}-${index}`} className="flex items-baseline justify-between gap-3 py-2">
                    <span className="text-body text-fg">
                      {line.label}
                      {line.quantity > 1 && <span className="text-fg-muted"> × {formatNumber(line.quantity, language)}</span>}
                    </span>
                    <span className="text-body font-medium text-fg">{formatMoney(line.amount, language)}</span>
                  </li>
                ))}
              </ul>
              <p className="flex items-baseline justify-between gap-3 border-t border-line pt-2 text-headline font-bold text-fg">
                <span>{formatMonth(`${nextInvoice.period.slice(0, 7)}-01`, language)}</span>
                <span>{formatMoney(nextInvoice.amount, language)}</span>
              </p>
            </>
          )}
        </Card>
      )}
    </div>
  );
}

function InvoicesCard() {
  const { t, language } = useI18n();
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const params: InvoiceListQuery = { per_page: 15, page, ...(status ? { status: status as (typeof INVOICE_STATUSES)[number] } : {}) };
  const query = useInvoices(params, { placeholderData: keepPreviousData });

  return (
    <Section title={t('fin_invoices')}>
      <Card>
        <HistoryFilters value={{ from: '', to: '' }} reversed={false} onChange={() => undefined} hideDates>
          <SelectFilter
            label={t('fin_status')}
            allLabel={t('fin_allStatuses')}
            value={status}
            onChange={(value) => {
              setStatus(value);
              setPage(1);
            }}
            options={INVOICE_STATUSES.map((value) => ({ value, label: labelFor(t, 'fin_i_', value) }))}
          />
        </HistoryFilters>
        {query.isError && !query.data ? (
          <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
        ) : !query.data ? (
          <Skeleton className="h-32 w-full" />
        ) : query.data.data.length === 0 ? (
          <EmptyState icon={FileText} title={t('fin_invEmpty')} text={t('fin_invEmptyText')} />
        ) : (
          <div className={query.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
            <DataTable caption={t('fin_invoices')}>
              <thead>
                <tr>
                  <Th>{t('fin_invNumber')}</Th>
                  <Th>{t('fin_invPeriod')}</Th>
                  <Th numeric>{t('fin_invAmount')}</Th>
                  <Th>{t('fin_invDue')}</Th>
                  <Th>{t('fin_colStatus')}</Th>
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((invoice) => (
                  <tr key={invoice.uuid}>
                    <Td className="font-mono whitespace-nowrap">{invoice.number}</Td>
                    <Td className="whitespace-nowrap">
                      {formatDay(invoice.period_start.slice(0, 10), language)} – {formatDay(invoice.period_end.slice(0, 10), language)}
                    </Td>
                    <Td numeric className="font-bold whitespace-nowrap">
                      {formatMoney(invoice.amount, language)}
                    </Td>
                    <Td muted className="whitespace-nowrap">
                      {formatDay(invoice.due_date.slice(0, 10), language)}
                    </Td>
                    <Td>
                      <Pill tone={INVOICE_TONE[invoice.status] ?? 'neutral'}>{labelFor(t, 'fin_i_', invoice.status)}</Pill>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
            <Pager page={query.data.current_page} lastPage={query.data.last_page} total={query.data.total} busy={query.isFetching} onPage={setPage} />
          </div>
        )}
      </Card>
    </Section>
  );
}
