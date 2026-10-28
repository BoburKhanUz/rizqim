'use client';

import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call, download, type DownloadedFile } from './client';

// Moliya (P4-23, docs/API.md "Moliya"): the payout queue with two-member approval from 1 000 000 so'm, the netting of a
// store's debt ("Hisoblangan — Ushlangan qarz — O'tkaziladi"), top-ups, settlement failures, balances and debts, manual
// adjustments and the monthly revenue report with its Excel export. Typed by hand until the generated client is regenerated
// (the shapes follow AdminFinance*Controller). Money is a decimal string ("120000.00"), times are ISO 8601.

export interface Named {
  id: number;
  name: string;
}

// --- Summary ------------------------------------------------------------------------------------------------------------

export interface FinanceSummary {
  payouts: { pending: number; awaiting_second: number; open_amount: string };
  adjustments: { pending: number };
  settlement_failures: { count: number; gave_up: number };
  topups: { stuck: number };
  balances: { over_limit: number; wallet_total: string; debt_total: string; subscription_debt_total: string };
  threshold: string;
}

// --- Payouts ------------------------------------------------------------------------------------------------------------

export const PAYOUT_FILTERS = ['open', 'pending', 'awaiting_second', 'completed', 'rejected', 'all'] as const;
export type PayoutFilter = (typeof PAYOUT_FILTERS)[number];
export type PayoutStatus = 'pending' | 'awaiting_second' | 'completed' | 'rejected';

export interface NettingLine {
  kind: 'invoice' | 'commission';
  label: string;
  amount: string;
  invoice: { id: number; uuid: string; number: string; period: string } | null;
  transaction_id: number | null;
}

export interface PayoutRow {
  id: number;
  store: Named | null;
  amount: string;
  card: string;
  status: PayoutStatus;
  status_label: string;
  needs_second_approval: boolean;
  first_approval: { by: Named | null; at: string | null; note: string | null } | null;
  netted_amount: string | null;
  transferred_amount: string | null;
  created_at: string | null;
  processed_at: string | null;
}

export interface PayoutCard extends PayoutRow {
  threshold: string;
  notes: string | null;
  resolution_notes: string | null;
  processed_by: Named | null;
  breakdown: { accrued: string; netted: string; transfer: string; preview: boolean; lines: NettingLine[] } | null;
  balances: { wallet: string; debt: string; subscription_debt: string; commission_debt: string; credit_limit: string } | null;
  actions: { can_approve: boolean; can_reject: boolean; approve_completes: boolean };
}

export interface PayoutPage {
  data: PayoutRow[];
  next_cursor: string | null;
  per_page: number;
  counts: { pending: number; awaiting_second: number; open_amount: string };
  threshold: string;
}

// --- Top-ups, settlement failures, balances, ledger -------------------------------------------------------------------

export const TOPUP_STATUSES = ['all', 'pending', 'paid', 'cancelled'] as const;
export type TopupStatus = (typeof TOPUP_STATUSES)[number];

export interface TopupFilters {
  status: TopupStatus;
  provider: '' | 'click' | 'payme';
  stuck: boolean;
}

export interface TopupRow {
  id: number;
  uuid: string;
  store: Named | null;
  amount: string;
  provider: string;
  status: string;
  status_label: string;
  provider_transaction_id: string | null;
  stuck: boolean;
  transaction_id: number | null;
  created_at: string | null;
  paid_at: string | null;
}

export interface TopupPage {
  data: TopupRow[];
  next_cursor: string | null;
  per_page: number;
  counts: { pending: number; stuck: number };
}

export interface SettlementFailure {
  uuid: string;
  short_code: string | null;
  store: Named | null;
  total: string;
  commission: string;
  payment_method: string | null;
  payment_owner: string | null;
  failed_at: string | null;
  attempts: number;
  max_attempts: number;
  gave_up: boolean;
  error: string | null;
}

export const BALANCE_FILTERS = ['all', 'over_limit', 'debt', 'subscription_debt', 'wallet'] as const;
export type BalanceFilter = (typeof BALANCE_FILTERS)[number];

export interface BalanceRow {
  store: Named & { status: string; status_label: string };
  wallet_balance: string;
  debt_balance: string;
  subscription_debt: string;
  commission_debt: string;
  credit_limit: string;
  over_limit: boolean;
  is_order_locked: boolean;
  pending_payouts: string;
}

export interface BalanceTotals {
  over_limit: number;
  wallet_total: string;
  debt_total: string;
  subscription_debt_total: string;
  commission_debt_total: string;
}

export interface BalancePage {
  data: BalanceRow[];
  page: number;
  per_page: number;
  total: number;
  totals: BalanceTotals;
}

export interface LedgerRow {
  id: number;
  type: string;
  amount: string;
  balance_before: string;
  balance_after: string;
  notes: string | null;
  order: { uuid: string; short_code: string | null } | null;
  subscription_invoice: { id: number; number: string; period: string } | null;
  payout_request_id: number | null;
  reference_id: string | null;
  performed_by: Named | null;
  created_at: string | null;
}

export interface LedgerPage {
  store: Named;
  data: LedgerRow[];
  next_cursor: string | null;
  per_page: number;
}

// --- Adjustments --------------------------------------------------------------------------------------------------------

export const ADJUSTMENT_KINDS = ['debt_charge', 'debt_credit', 'debt_payment', 'wallet_credit', 'wallet_debit'] as const;
export type AdjustmentKind = (typeof ADJUSTMENT_KINDS)[number];
export const ADJUSTMENT_FILTERS = ['all', 'pending', 'applied', 'rejected'] as const;
export type AdjustmentFilter = (typeof ADJUSTMENT_FILTERS)[number];

export interface Adjustment {
  id: number;
  uuid: string;
  store: Named | null;
  kind: AdjustmentKind;
  kind_label: string;
  ledger: 'debt' | 'wallet';
  amount: string;
  signed_amount: string;
  reason: string;
  status: 'pending' | 'applied' | 'rejected';
  status_label: string;
  needs_second_approval: boolean;
  requested_by: Named | null;
  requested_at: string | null;
  approved_by: Named | null;
  approved_at: string | null;
  rejected_by: Named | null;
  rejected_at: string | null;
  decision_note: string | null;
  applied_at: string | null;
  document: { name: string; mime: string | null; size: number | null } | null;
  transactions: { ledger: 'debt' | 'wallet'; id: number; type: string; amount: string; balance_before: string; balance_after: string; notes: string | null; subscription_invoice_id: number | null }[];
  mine: boolean;
  can_approve: boolean;
  can_reject: boolean;
}

export interface AdjustmentPage {
  data: Adjustment[];
  next_cursor: string | null;
  per_page: number;
  counts: { pending: number };
  kinds: { value: AdjustmentKind; label: string; ledger: 'debt' | 'wallet'; sign: 1 | -1 }[];
  threshold: string;
}

export interface NewAdjustment {
  store_id: number;
  kind: AdjustmentKind;
  amount: string;
  reason: string;
  document?: File | null;
}

// --- Report -------------------------------------------------------------------------------------------------------------

export interface MonthlyReport {
  period: string;
  timezone: string;
  totals: { orders: number; gmv: string; commission: string; commission_refunded: string; subscription: string; total: string };
  channels: { channel: string; label: string; orders: number; gmv: string; commission: string; commission_refunded: string }[];
  subscriptions: { invoices: number; invoiced: string; paid: string; outstanding: string };
  stores: { store: Named; orders: number; gmv: string; commission: string; commission_by_channel: Record<string, string>; subscription: string; total: string }[];
}

// --- Calls --------------------------------------------------------------------------------------------------------------

const data = async <T>(promise: ReturnType<typeof call>): Promise<T> => (await promise).data as T;
const money = (key: string) => ({ headers: { 'Idempotency-Key': key } });

export const getSummary = (signal?: AbortSignal) => data<FinanceSummary>(call('GET', '/finance/summary', { signal }));

export const listPayouts = (status: PayoutFilter, cursor: string | null, signal?: AbortSignal) =>
  data<PayoutPage>(call('GET', '/finance/payouts', { query: { status, per_page: 30, cursor }, signal }));
export const getPayout = (id: number, signal?: AbortSignal) => data<PayoutCard>(call('GET', `/finance/payouts/${id}`, { signal }));
// Money actions carry an Idempotency-Key: one per dialog, so a retried click never runs twice
export const decidePayout = (id: number, decision: 'approve' | 'reject', reason: string, key: string) =>
  data<PayoutCard>(call('POST', `/finance/payouts/${id}/${decision}`, { body: { reason }, ...money(key) }));

export const listTopups = (filters: TopupFilters, cursor: string | null, signal?: AbortSignal) =>
  data<TopupPage>(call('GET', '/finance/topups', { query: { status: filters.status, provider: filters.provider || undefined, stuck: filters.stuck ? 1 : undefined, per_page: 30, cursor }, signal }));

export const listSettlementFailures = (signal?: AbortSignal) =>
  data<{ data: SettlementFailure[]; count: number; max_attempts: number }>(call('GET', '/finance/settlement-failures', { signal }));
export const retrySettlement = (uuid: string, reason: string, key: string) =>
  data<{ booked: boolean; order: SettlementFailure | null }>(call('POST', `/finance/settlement-failures/${encodeURIComponent(uuid)}/retry`, { body: { reason }, ...money(key) }));

export const listBalances = (filter: BalanceFilter, q: string, page: number, signal?: AbortSignal) =>
  data<BalancePage>(call('GET', '/finance/balances', { query: { filter, q: q.trim() || undefined, page, per_page: 30 }, signal }));
export const getLedger = (storeId: number, ledger: 'debt' | 'wallet', cursor: string | null, signal?: AbortSignal) =>
  data<LedgerPage>(call('GET', `/finance/stores/${storeId}/ledger`, { query: { ledger, per_page: 50, cursor }, signal }));

export const listAdjustments = (status: AdjustmentFilter, cursor: string | null, signal?: AbortSignal) =>
  data<AdjustmentPage>(call('GET', '/finance/adjustments', { query: { status, per_page: 30, cursor }, signal }));
export const getAdjustment = (id: number, signal?: AbortSignal) => data<Adjustment>(call('GET', `/finance/adjustments/${id}`, { signal }));

// A document goes as multipart (FormData); without one, plain JSON
export function createAdjustment(input: NewAdjustment, key: string): Promise<Adjustment> {
  const { document, ...fields } = input;
  if (document) {
    const form = new FormData();
    for (const [name, value] of Object.entries(fields)) form.set(name, String(value));
    form.set('document', document, document.name);
    return data<Adjustment>(call('POST', '/finance/adjustments', { body: form, ...money(key) }));
  }
  return data<Adjustment>(call('POST', '/finance/adjustments', { body: fields, ...money(key) }));
}
export const decideAdjustment = (id: number, decision: 'approve' | 'reject', reason: string, key: string) =>
  data<Adjustment>(call('POST', `/finance/adjustments/${id}/${decision}`, { body: { reason }, ...money(key) }));
export const downloadAdjustmentDocument = (adjustment: Pick<Adjustment, 'id' | 'document'>): Promise<DownloadedFile> =>
  download(`/finance/adjustments/${adjustment.id}/document`, undefined, adjustment.document?.name ?? 'hujjat');

export const getMonthlyReport = (period: string, signal?: AbortSignal) => data<MonthlyReport>(call('GET', '/finance/reports/monthly', { query: { period }, signal }));
export const downloadMonthlyReport = (period: string): Promise<DownloadedFile> => download('/finance/reports/monthly/export', { period }, `rizqim-daromad-${period}.xlsx`);

// --- Hooks --------------------------------------------------------------------------------------------------------------

export const FINANCE_KEYS = {
  all: ['admin-finance'] as const,
  summary: ['admin-finance', 'summary'] as const,
  payouts: (status: PayoutFilter) => ['admin-finance', 'payouts', status] as const,
  payout: (id: number) => ['admin-finance', 'payout', id] as const,
  topups: (filters: TopupFilters) => ['admin-finance', 'topups', filters] as const,
  failures: ['admin-finance', 'settlement-failures'] as const,
  balances: (filter: BalanceFilter, q: string, page: number) => ['admin-finance', 'balances', filter, q, page] as const,
  ledger: (storeId: number, ledger: 'debt' | 'wallet') => ['admin-finance', 'ledger', storeId, ledger] as const,
  adjustments: (status: AdjustmentFilter) => ['admin-finance', 'adjustments', status] as const,
  adjustment: (id: number) => ['admin-finance', 'adjustment', id] as const,
  report: (period: string) => ['admin-finance', 'report', period] as const,
};

const POLL_MS = 60_000;

const useInfinitePages = <T extends { next_cursor: string | null }>(key: readonly unknown[], fetch: (cursor: string | null, signal: AbortSignal) => Promise<T>, enabled: boolean) =>
  useInfiniteQuery({
    queryKey: key,
    queryFn: ({ pageParam, signal }) => fetch(pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
  });

export const useFinanceSummary = (enabled = true) => useQuery({ queryKey: FINANCE_KEYS.summary, queryFn: ({ signal }) => getSummary(signal), enabled, refetchInterval: POLL_MS });
export const usePayouts = (status: PayoutFilter, enabled = true) => useInfinitePages(FINANCE_KEYS.payouts(status), (cursor, signal) => listPayouts(status, cursor, signal), enabled);
export const usePayout = (id: number | null) => useQuery({ queryKey: FINANCE_KEYS.payout(id ?? 0), queryFn: ({ signal }) => getPayout(id ?? 0, signal), enabled: id !== null });
export const useTopups = (filters: TopupFilters, enabled = true) => useInfinitePages(FINANCE_KEYS.topups(filters), (cursor, signal) => listTopups(filters, cursor, signal), enabled);
export const useSettlementFailures = (enabled = true) => useQuery({ queryKey: FINANCE_KEYS.failures, queryFn: ({ signal }) => listSettlementFailures(signal), enabled, refetchInterval: POLL_MS });
export const useBalances = (filter: BalanceFilter, q: string, page: number, enabled = true) =>
  useQuery({ queryKey: FINANCE_KEYS.balances(filter, q, page), queryFn: ({ signal }) => listBalances(filter, q, page, signal), enabled, placeholderData: keepPreviousData });
export const useLedger = (storeId: number | null, ledger: 'debt' | 'wallet') =>
  useInfinitePages(FINANCE_KEYS.ledger(storeId ?? 0, ledger), (cursor, signal) => getLedger(storeId ?? 0, ledger, cursor, signal), storeId !== null);
export const useAdjustments = (status: AdjustmentFilter, enabled = true) => useInfinitePages(FINANCE_KEYS.adjustments(status), (cursor, signal) => listAdjustments(status, cursor, signal), enabled);
export const useAdjustment = (id: number | null) => useQuery({ queryKey: FINANCE_KEYS.adjustment(id ?? 0), queryFn: ({ signal }) => getAdjustment(id ?? 0, signal), enabled: id !== null });
export const useMonthlyReport = (period: string, enabled = true) => useQuery({ queryKey: FINANCE_KEYS.report(period), queryFn: ({ signal }) => getMonthlyReport(period, signal), enabled: enabled && /^\d{4}-\d{2}$/.test(period) });

// Every write refreshes the whole section (the summary, the queues and the card it answered)
function useFinanceWrite<I, R>(fn: (input: I) => Promise<R>, seed?: (client: ReturnType<typeof useQueryClient>, result: R) => void) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (result) => {
      seed?.(client, result);
      void client.invalidateQueries({ queryKey: FINANCE_KEYS.all });
    },
  });
}

export const useDecidePayout = () =>
  useFinanceWrite(
    (input: { id: number; decision: 'approve' | 'reject'; reason: string; key: string }) => decidePayout(input.id, input.decision, input.reason, input.key),
    (client, card) => client.setQueryData(FINANCE_KEYS.payout(card.id), card),
  );
export const useRetrySettlement = () => useFinanceWrite((input: { uuid: string; reason: string; key: string }) => retrySettlement(input.uuid, input.reason, input.key));
export const useCreateAdjustment = () =>
  useFinanceWrite(
    (input: NewAdjustment & { key: string }) => {
      const { key, ...rest } = input;
      return createAdjustment(rest, key);
    },
    (client, adjustment) => client.setQueryData(FINANCE_KEYS.adjustment(adjustment.id), adjustment),
  );
export const useDecideAdjustment = () =>
  useFinanceWrite(
    (input: { id: number; decision: 'approve' | 'reject'; reason: string; key: string }) => decideAdjustment(input.id, input.decision, input.reason, input.key),
    (client, adjustment) => client.setQueryData(FINANCE_KEYS.adjustment(adjustment.id), adjustment),
  );
