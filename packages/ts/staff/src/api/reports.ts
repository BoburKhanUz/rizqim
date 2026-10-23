import type { StaffApi } from './client';
import { StaffApiError, unwrap } from './errors';
import type { OkData } from './types';

// The sales reports and the money history of the partner web panel (docs/API.md, 12). Every figure is computed by the
// server; nothing here counts, sums or groups. Money arrives as a two-decimals string ("1250000.00").

// --- Sales reports ---------------------------------------------------------------------------------------------------

export type ReportGroupBy = 'day' | 'week' | 'month';

// `from` / `to`: calendar days ("YYYY-MM-DD", both included) of the store's timezone, at most 366 days apart.
export interface ReportPeriodQuery {
  from: string;
  to: string;
  // A branch-bound manager always gets their own branch, whatever is asked
  branch_id?: number;
}

export interface SalesReportQuery extends ReportPeriodQuery {
  group_by?: ReportGroupBy;
}

export type TopProductsSort = 'quantity' | 'revenue';

export interface TopProductsQuery extends ReportPeriodQuery {
  // 1-50, the server's default is 10
  limit?: number;
  sort?: TopProductsSort;
}

// GET /owner/reports/sales: summary, series (one row per bucket, zeros included) and the breakdowns
export type SalesReport = OkData<'report.sales'>;
export type TopProductsReport = OkData<'report.topProducts'>;

export async function fetchSalesReport(api: StaffApi, query: SalesReportQuery): Promise<SalesReport> {
  const body = unwrap(await api.client.GET('/v1/owner/reports/sales', { params: { query } }));
  return body.data;
}

// GET /owner/reports/top-products: the best sellers among the delivered orders
export async function fetchTopProducts(api: StaffApi, query: TopProductsQuery): Promise<TopProductsReport> {
  const body = unwrap(await api.client.GET('/v1/owner/reports/top-products', { params: { query } }));
  return body.data;
}

// --- CSV download ----------------------------------------------------------------------------------------------------

export type ReportKind = 'orders' | 'sales' | 'products';

export interface ReportDownloadQuery extends ReportPeriodQuery {
  report: ReportKind;
  // `sales` only
  group_by?: ReportGroupBy;
  // `products` only
  sort?: TopProductsSort;
}

export interface ReportDownload {
  blob: Blob;
  // The server's name from Content-Disposition, else one built from the query
  filename: string;
}

// A refused download. `reason` is the server's machine reason ("too_many_rows"), `limit` the row limit it names, and
// `retryAfter` the seconds of a 429's Retry-After header (the CSV endpoint allows 6 a minute and 100 a day).
export class ReportDownloadError extends StaffApiError {
  readonly limit: number | undefined;
  readonly retryAfter: number | undefined;

  constructor(status: number, body: unknown, retryAfter?: number) {
    super(status, body);
    this.name = 'ReportDownloadError';
    const data = (body as { data?: { limit?: unknown } } | null)?.data;
    this.limit = typeof data?.limit === 'number' ? data.limit : undefined;
    this.retryAfter = retryAfter;
  }

  get tooManyRows(): boolean {
    return this.reason === 'too_many_rows';
  }

  get rateLimited(): boolean {
    return this.status === 429;
  }
}

export const reportFilename = (query: ReportDownloadQuery): string => `rizqim-${query.report}-${query.from}_${query.to}.csv`;

// `filename="x.csv"` / `filename*=UTF-8''x.csv` of a Content-Disposition header
function filenameFrom(header: string | null): string | undefined {
  if (!header) return undefined;
  const encoded = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded.trim());
    } catch {
      // fall through to the plain name
    }
  }
  const plain = /filename\s*=\s*"?([^";]+)"?/i.exec(header)?.[1]?.trim();
  return plain || undefined;
}

// GET /owner/reports/export: the CSV (UTF-8 with a BOM) as a Blob. The request goes through the API's own fetch, so it
// carries the session's token (a plain <a href> could not).
export async function downloadReport(api: StaffApi, query: ReportDownloadQuery): Promise<ReportDownload> {
  const result = await api.client.GET('/v1/owner/reports/export', { params: { query }, parseAs: 'blob' });
  if (!result.response.ok) {
    const retryAfter = Number(result.response.headers.get('Retry-After'));
    throw new ReportDownloadError(result.response.status, result.error, Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : undefined);
  }
  const blob = result.data as unknown as Blob;
  return { blob, filename: filenameFrom(result.response.headers.get('Content-Disposition')) ?? reportFilename(query) };
}

// --- Money history ---------------------------------------------------------------------------------------------------

// Filters the history endpoints share: days of the store's timezone, a Laravel page (1-based) and its size (1-100,
// 25 by default).
export interface HistoryQuery {
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export interface LedgerQuery extends HistoryQuery {
  // commission | topup | refund | adjustment | subscription | settlement | payout
  type?: string;
}

export interface PayoutsQuery extends HistoryQuery {
  status?: 'pending' | 'completed' | 'rejected';
}

export interface PaymentsQuery extends HistoryQuery {
  // pending | paid | failed | cancelled | expired | refunded
  status?: string;
  // click | payme | ...
  provider?: string;
  // P3-14: the fiscal receipt of the payment - blocked (the owner has to fix the menu or the settings) | pending | failed |
  // recorded
  fiscal_status?: string;
  branch_id?: number;
}

// GET /owner/finance/ledger: the debt ledger. A positive amount raises the debt.
export type LedgerPage = OkData<'financeHistory.ledger'>;
export type LedgerEntry = LedgerPage['data'][number];
// GET /owner/finance/wallet: the wallet (online payments settled to the store, payouts)
export type WalletPage = OkData<'financeHistory.wallet'>;
// GET /owner/payouts: payout requests; the card number is masked by the server
export type PayoutsPage = OkData<'financeHistory.payouts'>;
export type PayoutEntry = PayoutsPage['data'][number];
// GET /owner/payments: customers' online payments
export type PaymentsPage = OkData<'financeHistory.payments'>;
export type PaymentEntry = PaymentsPage['data'][number];

export async function fetchLedger(api: StaffApi, query: LedgerQuery = {}): Promise<LedgerPage> {
  const body = unwrap(await api.client.GET('/v1/owner/finance/ledger', { params: { query } }));
  return body.data;
}

export async function fetchWalletHistory(api: StaffApi, query: LedgerQuery = {}): Promise<WalletPage> {
  const body = unwrap(await api.client.GET('/v1/owner/finance/wallet', { params: { query } }));
  return body.data;
}

export async function fetchPayouts(api: StaffApi, query: PayoutsQuery = {}): Promise<PayoutsPage> {
  const body = unwrap(await api.client.GET('/v1/owner/payouts', { params: { query } }));
  return body.data;
}

export async function fetchPayments(api: StaffApi, query: PaymentsQuery = {}): Promise<PaymentsPage> {
  const body = unwrap(await api.client.GET('/v1/owner/payments', { params: { query } }));
  return body.data;
}
