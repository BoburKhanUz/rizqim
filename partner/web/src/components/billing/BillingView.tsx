'use client';

import { useState } from 'react';
import Link from 'next/link';
import { keepPreviousData } from '@tanstack/react-query';
import { Check, FileText, Lock } from 'lucide-react';
import { Card, EmptyState, InlineError, PageHeader, Section, Skeleton, formatMoney, formatNumber } from '@rizqim/ui';
import {
  GATED_FEATURES,
  daysUntilLock,
  invoiceOutstanding,
  requiredPlanName,
  subscriptionState,
  trialDaysLeft,
  useEntitlements,
  useInvoices,
  useSubscription,
  type DeviceEntitlement,
  type Entitlements,
  type InvoiceQuery,
  type InvoiceRow,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { formatDay, labelFor } from '../reports/format';
import { HistoryFilters, SelectFilter } from '../reports/HistoryFilters';
import { DataTable, Pager, Pill, Td, Th, type Tone } from '../reports/ui';
import { featureLabel, inPlanText } from './refusal';

const STATUS_TONE: Record<string, Tone> = { none: 'neutral', trialing: 'info', active: 'success', past_due: 'warning', locked: 'danger' };
const INVOICE_STATUSES = ['issued', 'paid', 'void'] as const;
const INVOICE_TONE: Record<string, Tone> = { issued: 'warning', paid: 'success', void: 'neutral' };

// Tarif va to'lov (P4-21): the plan, the trial, the devices and what they cost, which features the plan has, the invoices with what
// is still unpaid, and how to pay. The unpaid-invoice banner is the shell's (BillingBanner); this page is where it leads.
export function BillingView() {
  const { t } = useI18n();
  const entitlements = useEntitlements();

  return (
    <>
      <PageHeader title={t('nav_billing')} subtitle={t('billing_subtitle')} />
      <div className="flex flex-col gap-6">
        {entitlements.isError && !entitlements.data ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(entitlements.error, t)} retryLabel={t('retry')} retrying={entitlements.isFetching} onRetry={() => void entitlements.refetch()} />
        ) : !entitlements.data ? (
          <div role="status" aria-busy="true" className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <Skeleton className="h-48 w-full" />
            <Skeleton className="h-48 w-full" />
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
              <PlanCard data={entitlements.data} />
              <DevicesCard data={entitlements.data} />
            </div>
            <FeaturesCard data={entitlements.data} />
          </>
        )}
        <PayCard />
        <InvoicesCard />
      </div>
    </>
  );
}

function PlanCard({ data }: { data: Entitlements }) {
  const { t, language } = useI18n();
  const subscription = useSubscription();
  const state = subscriptionState(subscription.data);
  const trial = trialDaysLeft(data);
  const lockIn = daysUntilLock(data);

  if (!data.plan) {
    return (
      <Card className="flex flex-col gap-2">
        <h2 className="text-headline font-bold text-fg">{t('billing_planTitle')}</h2>
        <p className="text-title font-bold text-fg">{t('billing_none')}</p>
        <p className="text-body text-fg-muted">{t('billing_noneText')}</p>
      </Card>
    );
  }

  const status = data.status;
  const period = data.period ?? state?.billing_period ?? null;
  const price = state?.plan.monthly_base_fee;
  return (
    <Card className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-headline font-bold text-fg">{t('billing_planTitle')}</h2>
        <Pill tone={STATUS_TONE[status] ?? 'neutral'}>{labelFor(t, 'billing_s_', status)}</Pill>
      </div>
      <p className="text-title font-bold text-fg">{data.plan.name}</p>
      {data.legacy && <p className="text-footnote text-fg-muted">{t('billing_legacyText')}</p>}
      <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-body">
        {period && (
          <>
            <dt className="text-fg-muted">{t('billing_period')}</dt>
            <dd className="text-right font-medium text-fg">{t(period === 'yearly' ? 'billing_p_yearly' : 'billing_p_monthly')}</dd>
          </>
        )}
        {price !== undefined && price > 0 && (
          <>
            <dt className="text-fg-muted">{t('billing_price')}</dt>
            <dd className="text-right font-medium text-fg">{formatMoney(price, language)}</dd>
          </>
        )}
        {data.current_period && (
          <>
            <dt className="text-fg-muted">{t('billing_currentPeriod')}</dt>
            <dd className="text-right font-medium text-fg">{t('billing_periodRange', { from: formatDay(data.current_period.start.slice(0, 10), language), to: formatDay(data.current_period.end.slice(0, 10), language) })}</dd>
          </>
        )}
      </dl>
      <ul className="flex flex-col gap-1 text-footnote text-fg-muted">
        {trial !== null && <li className="font-bold text-fg">{trial === 0 ? t('billing_trialLastDay') : t('billing_trialLeft', { n: trial })}</li>}
        {trial !== null && data.trial_ends_at && <li>{t('billing_trialEnds', { date: formatDay(data.trial_ends_at.slice(0, 10), language) })}</li>}
        {period === 'yearly' && data.term_ends_at && <li>{t('billing_termEnds', { date: formatDay(data.term_ends_at.slice(0, 10), language) })}</li>}
        {state?.next_plan && <li>{t('billing_nextPlan', { name: state.next_plan.name })}</li>}
        {state?.cancel_at_period_end && <li>{t('billing_cancelAtEnd')}</li>}
        {lockIn !== null && data.lock_at && <li className="font-bold text-warning-soft-fg">{t('billing_lockAt', { date: formatDay(data.lock_at.slice(0, 10), language) })}</li>}
        {data.settings_locked && data.locked_since && <li className="font-bold text-danger-soft-fg">{t('billing_lockedSince', { date: formatDay(data.locked_since.slice(0, 10), language) })}</li>}
      </ul>
    </Card>
  );
}

function DeviceRow({ label, device, locked, noPlanLabel }: { label: string; device: DeviceEntitlement; locked: boolean; noPlanLabel?: string | null }) {
  const { t, language } = useI18n();
  const active = device.active ?? 0;
  const extra = device.extra ?? 0;
  const price = device.extra_price && Number(device.extra_price) > 0 ? formatMoney(device.extra_price, language) : null;

  return (
    <li className="flex flex-col gap-0.5 py-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <span className="text-body font-bold text-fg">{label}</span>
        <span className="text-body font-medium text-fg">
          {device.unlimited
            ? t('billing_devUnlimited')
            : device.included === null
              ? t('billing_devNoLimit', { active: formatNumber(active, language) })
              : t('billing_devUsage', { active: formatNumber(active, language), included: formatNumber(device.included, language) })}
        </span>
      </div>
      {extra > 0 && price && <p className="text-footnote font-bold text-warning-soft-fg">{t('billing_devExtra', { n: formatNumber(extra, language), price })}</p>}
      {price && !device.unlimited && <p className="text-footnote text-fg-muted">{t('billing_devExtraPrice', { price })}</p>}
      {noPlanLabel && <p className="text-footnote text-fg-muted">{noPlanLabel}</p>}
      {!device.unlimited && !device.can_add && !noPlanLabel && locked && <p className="text-footnote text-fg-muted">{t('billing_devBlocked')}</p>}
    </li>
  );
}

function DevicesCard({ data }: { data: Entitlements }) {
  const { t } = useI18n();
  const kdsPlan = data.features.kds === false && !data.legacy ? requiredPlanName(data, 'kds') : null;
  const kdsOff = data.features.kds === false && !data.legacy;
  return (
    <Card className="flex flex-col gap-1">
      <h2 className="text-headline font-bold text-fg">{t('billing_devices')}</h2>
      <p className="text-footnote text-fg-muted">{t('billing_devicesText')}</p>
      <ul className="mt-1 flex flex-col divide-y divide-line">
        <DeviceRow label={t('billing_dev_pos')} device={data.devices.pos} locked={data.settings_locked} />
        <DeviceRow label={t('billing_dev_kds')} device={data.devices.kds} locked={data.settings_locked} noPlanLabel={kdsOff ? inPlanText(t, kdsPlan) : null} />
        <DeviceRow label={t('billing_dev_waiter')} device={data.devices.waiter} locked={false} />
      </ul>
    </Card>
  );
}

function FeaturesCard({ data }: { data: Entitlements }) {
  const { t } = useI18n();
  return (
    <Section title={t('billing_features')}>
      <Card className="flex flex-col gap-3">
        <p className="text-footnote text-fg-muted">{t('billing_featuresText')}</p>
        <ul className="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
          {GATED_FEATURES.map((feature) => {
            const on = data.legacy || data.features[feature] !== false;
            return (
              <li key={feature} className="flex items-center justify-between gap-3 border-t border-line py-2.5 first:border-t-0 sm:[&:nth-child(2)]:border-t-0">
                <span className="flex items-center gap-2 text-body text-fg">
                  {on ? <Check aria-hidden="true" className="size-4 shrink-0 text-success-soft-fg" /> : <Lock aria-hidden="true" className="size-4 shrink-0 text-fg-muted" />}
                  {featureLabel(t, feature)}
                </span>
                {on ? <span className="text-footnote font-bold text-success-soft-fg">{t('billing_featOn')}</span> : <Pill tone="info">{inPlanText(t, requiredPlanName(data, feature))}</Pill>}
              </li>
            );
          })}
        </ul>
      </Card>
    </Section>
  );
}

function PayCard() {
  const { t } = useI18n();
  const access = useCan();
  // The page itself needs finance.view; while the permissions load the button shows (the finance page checks again)
  const canPay = !access.ready || access.can('finance.view');
  return (
    <Section title={t('billing_payTitle')}>
      <Card className="flex flex-col gap-3">
        <ol className="flex list-decimal flex-col gap-1.5 pl-5 text-body text-fg">
          <li>{t('billing_pay1')}</li>
          <li>{t('billing_pay2')}</li>
          <li>{t('billing_pay3')}</li>
        </ol>
        <p className="text-footnote font-bold text-fg-muted">{t('billing_pay4')}</p>
        <div>
          {canPay ? (
            <Link href="/finance" className="inline-flex min-h-11 items-center rounded-control bg-brand px-4 text-body font-bold text-on-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
              {t('billing_payButton')}
            </Link>
          ) : (
            <p className="text-footnote text-fg-muted">{t('billing_payNoAccess')}</p>
          )}
        </div>
      </Card>
    </Section>
  );
}

function InvoicesCard() {
  const { t, language } = useI18n();
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const params: InvoiceQuery & { page?: number } = { per_page: 15, page, ...(status ? { status: status as (typeof INVOICE_STATUSES)[number] } : {}) };
  const query = useInvoices(params, { placeholderData: keepPreviousData });
  const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Tashkent' });

  return (
    <Section title={t('billing_invoices')}>
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
          <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
        ) : !query.data ? (
          <Skeleton className="h-32 w-full" />
        ) : query.data.data.length === 0 ? (
          <EmptyState icon={FileText} title={t('billing_invEmpty')} text={t('billing_invEmptyText')} />
        ) : (
          <div className={query.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
            <DataTable caption={t('billing_invoices')}>
              <thead>
                <tr>
                  <Th>{t('fin_invNumber')}</Th>
                  <Th>{t('fin_invPeriod')}</Th>
                  <Th numeric>{t('fin_invAmount')}</Th>
                  <Th numeric>{t('billing_invOutstanding')}</Th>
                  <Th>{t('fin_invDue')}</Th>
                  <Th>{t('fin_colStatus')}</Th>
                </tr>
              </thead>
              <tbody>
                {(query.data.data as InvoiceRow[]).map((invoice) => {
                  const outstanding = invoiceOutstanding(invoice);
                  const overdue = invoice.status === 'issued' && invoice.due_date.slice(0, 10) < today;
                  return (
                    <tr key={invoice.uuid}>
                      <Td className="font-mono whitespace-nowrap">
                        {invoice.number}
                        {invoice.kind && invoice.kind !== 'monthly' && <span className="block font-sans text-caption text-fg-muted">{labelFor(t, 'billing_k_', invoice.kind)}</span>}
                      </Td>
                      <Td className="whitespace-nowrap">
                        {formatDay(invoice.period_start.slice(0, 10), language)} – {formatDay(invoice.period_end.slice(0, 10), language)}
                      </Td>
                      <Td numeric className="font-bold whitespace-nowrap">
                        {formatMoney(invoice.amount, language)}
                      </Td>
                      <Td numeric className="whitespace-nowrap">
                        {outstanding > 0 ? <span className={overdue ? 'font-bold text-danger-soft-fg' : 'font-bold'}>{formatMoney(outstanding, language)}</span> : '—'}
                      </Td>
                      <Td muted className="whitespace-nowrap">
                        {formatDay(invoice.due_date.slice(0, 10), language)}
                      </Td>
                      <Td>
                        <div className="flex flex-wrap items-center gap-1">
                          <Pill tone={INVOICE_TONE[invoice.status] ?? 'neutral'}>{labelFor(t, 'fin_i_', invoice.status)}</Pill>
                          {overdue && <Pill tone="danger">{t('billing_invOverdue')}</Pill>}
                        </div>
                      </Td>
                    </tr>
                  );
                })}
              </tbody>
            </DataTable>
            <Pager page={query.data.current_page} lastPage={query.data.last_page} total={query.data.total} busy={query.isFetching} onPage={setPage} />
          </div>
        )}
      </Card>
    </Section>
  );
}
