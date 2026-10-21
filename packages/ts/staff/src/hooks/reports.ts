import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';
import {
  downloadReport,
  fetchLedger,
  fetchPayments,
  fetchPayouts,
  fetchSalesReport,
  fetchTopProducts,
  fetchWalletHistory,
  type LedgerPage,
  type LedgerQuery,
  type PaymentsPage,
  type PaymentsQuery,
  type PayoutsPage,
  type PayoutsQuery,
  type ReportDownload,
  type ReportDownloadQuery,
  type SalesReport,
  type SalesReportQuery,
  type TopProductsQuery,
  type TopProductsReport,
  type WalletPage,
} from '../api/reports';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// Query keys of the reports and the money history. `history` is a prefix: invalidating it refreshes every list (a
// payout request changes the payouts, the wallet and the ledger).
export const reportKeys = {
  reports: ['owner', 'reports'] as const,
  sales: (query: SalesReportQuery) => ['owner', 'reports', 'sales', query] as const,
  topProducts: (query: TopProductsQuery) => ['owner', 'reports', 'top-products', query] as const,
  history: ['owner', 'finance-history'] as const,
  ledger: (query: LedgerQuery) => ['owner', 'finance-history', 'ledger', query] as const,
  wallet: (query: LedgerQuery) => ['owner', 'finance-history', 'wallet', query] as const,
  payouts: (query: PayoutsQuery) => ['owner', 'finance-history', 'payouts', query] as const,
  payments: (query: PaymentsQuery) => ['owner', 'finance-history', 'payments', query] as const,
};

// A report of a period. Pass `null` while the period is not known or not valid: nothing is requested.
export function useSalesReport(query: SalesReportQuery | null, options: QueryExtras<SalesReport> = {}) {
  const api = useStaffApi();
  return useQuery({
    placeholderData: keepPreviousData,
    ...options,
    enabled: query !== null && options.enabled !== false,
    queryKey: reportKeys.sales(query ?? { from: '', to: '' }),
    queryFn: () => fetchSalesReport(api, query as SalesReportQuery),
  });
}

export function useTopProducts(query: TopProductsQuery | null, options: QueryExtras<TopProductsReport> = {}) {
  const api = useStaffApi();
  return useQuery({
    placeholderData: keepPreviousData,
    ...options,
    enabled: query !== null && options.enabled !== false,
    queryKey: reportKeys.topProducts(query ?? { from: '', to: '' }),
    queryFn: () => fetchTopProducts(api, query as TopProductsQuery),
  });
}

// The CSV of a report. The caller saves `data.blob` as `data.filename`; a refusal is a ReportDownloadError
// (`tooManyRows`, `rateLimited` + `retryAfter`, else the server's `message`).
export function useDownloadReport(options: MutationExtras<ReportDownload, ReportDownloadQuery> = {}) {
  const api = useStaffApi();
  return useMutation({ ...options, mutationFn: (query: ReportDownloadQuery) => downloadReport(api, query) });
}

// The money history, a page at a time: the previous page stays on screen while the next one loads.
export function useLedger(query: LedgerQuery = {}, options: QueryExtras<LedgerPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: reportKeys.ledger(query), queryFn: () => fetchLedger(api, query) });
}

export function useWalletHistory(query: LedgerQuery = {}, options: QueryExtras<WalletPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: reportKeys.wallet(query), queryFn: () => fetchWalletHistory(api, query) });
}

export function usePayouts(query: PayoutsQuery = {}, options: QueryExtras<PayoutsPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: reportKeys.payouts(query), queryFn: () => fetchPayouts(api, query) });
}

export function usePayments(query: PaymentsQuery = {}, options: QueryExtras<PaymentsPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: reportKeys.payments(query), queryFn: () => fetchPayments(api, query) });
}
