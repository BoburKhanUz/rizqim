'use client';

import { useState } from 'react';
import { Inbox } from 'lucide-react';
import { Card, EmptyState, InlineError, Skeleton, formatDateTime, formatMoney, formatOrderCode } from '@rizqim/ui';
import { getApiErrorMessage, getApiErrorStatus, ONLINE_FISCAL_STATUSES, useLedger, usePayments, usePayouts, useWalletHistory } from '@rizqim/staff';
import type { Translate } from '@/i18n';
import { paymentLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { useBranchScope } from '../shell/BranchScope';
import { labelFor } from './format';
import { FiscalPaymentCell } from './FiscalPaymentCell';
import { HistoryFilters, SelectFilter, usableDates, type DateFilter } from './HistoryFilters';
import { DataTable, Pager, Pill, Td, Th, type Tone } from './ui';

const LEDGER_TYPES = ['commission', 'topup', 'refund', 'adjustment', 'subscription', 'settlement', 'payout'] as const;
const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'expired', 'refunded'] as const;
const PAYMENT_PROVIDERS = ['click', 'payme'] as const;
const PAYOUT_STATUSES = ['pending', 'completed', 'rejected'] as const;

const PAYMENT_TONE: Record<string, Tone> = { pending: 'warning', paid: 'success', failed: 'danger', cancelled: 'neutral', expired: 'neutral', refunded: 'info' };
const PAYOUT_TONE: Record<string, Tone> = { pending: 'warning', completed: 'success', rejected: 'danger' };

const PER_PAGE = 25;

// The words for a refused history request: the server's own for a 403 or 422, else the generic one
function useErrorText(error: unknown, t: Translate): string {
  const status = getApiErrorStatus(error);
  return (status === 403 || status === 422 ? getApiErrorMessage(error) : undefined) ?? t('loadErrorText');
}

interface PageState<T> {
  data: { data: T[]; current_page: number; last_page: number; total: number } | undefined;
  isPending: boolean;
  isError: boolean;
  isFetching: boolean;
  isPlaceholderData: boolean;
  error: unknown;
  refetch: () => unknown;
}

// The shared body of a paginated list: loading, failure, nothing found, or the table and its pager
function HistoryList<T>({ query, caption, head, row, page, onPage }: { query: PageState<T>; caption: string; head: React.ReactNode; row: (item: T) => React.ReactNode; page: number; onPage: (page: number) => void }) {
  const { t } = useI18n();
  const errorText = useErrorText(query.error, t);
  if (query.isError && !query.data) {
    return <InlineError title={t('loadErrorTitle')} message={errorText} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />;
  }
  if (!query.data) {
    return (
      <div role="status" aria-busy="true" className="flex flex-col gap-2">
        {[0, 1, 2, 3].map((n) => (
          <Skeleton key={n} className="h-10 w-full" />
        ))}
      </div>
    );
  }
  if (query.data.data.length === 0) return <EmptyState icon={Inbox} title={t('fin_empty')} text={t('fin_emptyText')} />;
  return (
    <div aria-busy={query.isPlaceholderData || undefined} className={query.isPlaceholderData ? 'opacity-60 transition-opacity' : undefined}>
      <DataTable caption={caption}>
        <thead>{head}</thead>
        <tbody>{query.data.data.map(row)}</tbody>
      </DataTable>
      <Pager page={query.data.current_page || page} lastPage={query.data.last_page} total={query.data.total} busy={query.isFetching} onPage={onPage} />
    </div>
  );
}

const emptyDates: DateFilter = { from: '', to: '' };

// --- Debt ledger and wallet --------------------------------------------------------------------------------------------

export function TransactionsPanel({ kind }: { kind: 'ledger' | 'wallet' }) {
  const { t, language } = useI18n();
  const [type, setType] = useState('');
  const [dates, setDates] = useState<DateFilter>(emptyDates);
  const [page, setPage] = useState(1);
  const usable = usableDates(dates);
  const query = { ...(type ? { type } : {}), ...(usable.from ? { from: usable.from } : {}), ...(usable.to ? { to: usable.to } : {}), page, per_page: PER_PAGE };
  const ledger = useLedger(query, { enabled: kind === 'ledger' && !usable.reversed });
  const wallet = useWalletHistory(query, { enabled: kind === 'wallet' && !usable.reversed });
  const active = kind === 'ledger' ? ledger : wallet;

  return (
    <Card>
      <HistoryFilters
        value={dates}
        reversed={usable.reversed}
        onChange={(next) => {
          setDates(next);
          setPage(1);
        }}
      >
        <SelectFilter
          label={t('fin_type')}
          allLabel={t('fin_allTypes')}
          value={type}
          onChange={(value) => {
            setType(value);
            setPage(1);
          }}
          options={LEDGER_TYPES.map((value) => ({ value, label: labelFor(t, 'fin_t_', value) }))}
        />
      </HistoryFilters>
      {kind === 'ledger' && <p className="mb-3 text-footnote text-fg-muted">{t('fin_ledgerHint')}</p>}
      <HistoryList
        query={active}
        caption={kind === 'ledger' ? t('fin_tab_ledger') : t('fin_tab_wallet')}
        page={page}
        onPage={setPage}
        head={
          <tr>
            <Th>{t('fin_colDate')}</Th>
            <Th>{t('fin_colType')}</Th>
            <Th numeric>{t('fin_colAmount')}</Th>
            <Th numeric>{t('fin_colBalance')}</Th>
            <Th>{t('fin_colOrder')}</Th>
            <Th>{t('fin_colNotes')}</Th>
          </tr>
        }
        row={(entry) => (
          <tr key={entry.id}>
            <Td className="whitespace-nowrap">{formatDateTime(entry.created_at, language)}</Td>
            <Td>{labelFor(t, 'fin_t_', entry.type)}</Td>
            <Td numeric className="font-bold whitespace-nowrap">
              {formatMoney(entry.amount, language)}
            </Td>
            <Td numeric muted className="whitespace-nowrap">
              {formatMoney(entry.balance_before, language)} → {formatMoney(entry.balance_after, language)}
            </Td>
            <Td>{entry.order ? formatOrderCode(entry.order) : '—'}</Td>
            <Td muted className="max-w-64 min-w-32 break-words">
              {entry.notes || '—'}
            </Td>
          </tr>
        )}
      />
    </Card>
  );
}

// --- Online payments ---------------------------------------------------------------------------------------------------

export function PaymentsPanel() {
  const { t, language } = useI18n();
  const [status, setStatus] = useState('');
  const [provider, setProvider] = useState('');
  const [fiscalStatus, setFiscalStatus] = useState('');
  const [dates, setDates] = useState<DateFilter>(emptyDates);
  const [page, setPage] = useState(1);
  const { branchId } = useBranchScope();
  const usable = usableDates(dates);
  const query = usePayments(
    { ...(branchId ? { branch_id: branchId } : {}), ...(status ? { status } : {}), ...(provider ? { provider } : {}), ...(fiscalStatus ? { fiscal_status: fiscalStatus } : {}), ...(usable.from ? { from: usable.from } : {}), ...(usable.to ? { to: usable.to } : {}), page, per_page: PER_PAGE },
    { enabled: !usable.reversed },
  );
  const reset = <T,>(set: (value: T) => void) => (value: T) => {
    set(value);
    setPage(1);
  };

  return (
    <Card>
      <HistoryFilters value={dates} reversed={usable.reversed} onChange={reset(setDates)}>
        <SelectFilter label={t('fin_status')} allLabel={t('fin_allStatuses')} value={status} onChange={reset(setStatus)} options={PAYMENT_STATUSES.map((value) => ({ value, label: labelFor(t, 'fin_ps_', value) }))} />
        <SelectFilter label={t('fin_colProvider')} allLabel={t('fin_allTypes')} value={provider} onChange={reset(setProvider)} options={PAYMENT_PROVIDERS.map((value) => ({ value, label: paymentLabel(language, value) }))} />
        <SelectFilter label={t('fiscal_pay_filter')} allLabel={t('fin_allStatuses')} value={fiscalStatus} onChange={reset(setFiscalStatus)} options={ONLINE_FISCAL_STATUSES.map((value) => ({ value, label: labelFor(t, 'fiscal_pay_st_', value) }))} />
      </HistoryFilters>
      <HistoryList
        query={query}
        caption={t('fin_tab_payments')}
        page={page}
        onPage={setPage}
        head={
          <tr>
            <Th>{t('fin_colDate')}</Th>
            <Th>{t('fin_colProvider')}</Th>
            <Th>{t('fin_colTransaction')}</Th>
            <Th>{t('fin_colOrder')}</Th>
            <Th numeric>{t('fin_colAmount')}</Th>
            <Th>{t('fin_colStatus')}</Th>
            <Th>{t('fin_colPaidAt')}</Th>
            <Th>{t('fiscal_pay_col')}</Th>
          </tr>
        }
        row={(payment) => (
          <tr key={payment.uuid}>
            <Td className="whitespace-nowrap">{formatDateTime(payment.created_at, language)}</Td>
            <Td>{paymentLabel(language, payment.provider)}</Td>
            <Td muted className="font-mono text-footnote break-all">
              {payment.provider_transaction_id || '—'}
            </Td>
            <Td>{formatOrderCode(payment.order)}</Td>
            <Td numeric className="font-bold whitespace-nowrap">
              {formatMoney(payment.amount, language)}
            </Td>
            <Td>
              <Pill tone={PAYMENT_TONE[payment.status] ?? 'neutral'}>{labelFor(t, 'fin_ps_', payment.status)}</Pill>
            </Td>
            <Td muted className="whitespace-nowrap">
              {payment.paid_at ? formatDateTime(payment.paid_at, language) : '—'}
            </Td>
            <Td className="min-w-56 max-w-80">
              <FiscalPaymentCell fiscal={payment.fiscal} />
            </Td>
          </tr>
        )}
      />
    </Card>
  );
}

// --- Payout requests ---------------------------------------------------------------------------------------------------

export function PayoutsPanel() {
  const { t, language } = useI18n();
  const [status, setStatus] = useState('');
  const [dates, setDates] = useState<DateFilter>(emptyDates);
  const [page, setPage] = useState(1);
  const usable = usableDates(dates);
  const query = usePayouts(
    { ...(status ? { status: status as (typeof PAYOUT_STATUSES)[number] } : {}), ...(usable.from ? { from: usable.from } : {}), ...(usable.to ? { to: usable.to } : {}), page, per_page: PER_PAGE },
    { enabled: !usable.reversed },
  );

  return (
    <Card>
      <HistoryFilters
        value={dates}
        reversed={usable.reversed}
        onChange={(next) => {
          setDates(next);
          setPage(1);
        }}
      >
        <SelectFilter
          label={t('fin_status')}
          allLabel={t('fin_allStatuses')}
          value={status}
          onChange={(value) => {
            setStatus(value);
            setPage(1);
          }}
          options={PAYOUT_STATUSES.map((value) => ({ value, label: labelFor(t, 'fin_po_', value) }))}
        />
      </HistoryFilters>
      <HistoryList
        query={query}
        caption={t('fin_tab_payouts')}
        page={page}
        onPage={setPage}
        head={
          <tr>
            <Th>{t('fin_colDate')}</Th>
            <Th numeric>{t('fin_colAmount')}</Th>
            <Th>{t('fin_colCard')}</Th>
            <Th>{t('fin_colStatus')}</Th>
            <Th>{t('fin_colNotes')}</Th>
            <Th>{t('fin_colResolution')}</Th>
            <Th>{t('fin_colProcessed')}</Th>
          </tr>
        }
        row={(payout) => (
          <tr key={payout.id}>
            <Td className="whitespace-nowrap">{formatDateTime(payout.created_at, language)}</Td>
            <Td numeric className="font-bold whitespace-nowrap">
              {formatMoney(payout.amount, language)}
            </Td>
            <Td className="font-mono whitespace-nowrap">{payout.card}</Td>
            <Td>
              <Pill tone={PAYOUT_TONE[payout.status] ?? 'neutral'}>{labelFor(t, 'fin_po_', payout.status)}</Pill>
            </Td>
            <Td muted className="max-w-56 min-w-28 break-words">
              {payout.notes || '—'}
            </Td>
            <Td muted className="max-w-56 min-w-28 break-words">
              {payout.resolution_notes || '—'}
            </Td>
            <Td muted className="whitespace-nowrap">
              {payout.processed_at ? formatDateTime(payout.processed_at, language) : '—'}
            </Td>
          </tr>
        )}
      />
    </Card>
  );
}
