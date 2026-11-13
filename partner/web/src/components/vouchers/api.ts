import { StaffApiError, unwrap, type StaffApi } from '@rizqim/staff';

// The owner's gift certificates of P6-43 (docs/API.md "Sertifikat va vaucher"). Called through the generated client's
// untyped door and read into the types below, so this page does not wait for the spec to be regenerated; the server
// keeps every balance and does every sum.

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
  parseAs?: 'blob';
}
interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string, init?: Init): Promise<Result>;
  POST(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export type VoucherStatus = 'active' | 'used' | 'blocked' | 'expired';
export const VOUCHER_STATUSES: readonly VoucherStatus[] = ['active', 'used', 'blocked', 'expired'];

export interface Person {
  id: number;
  name: string | null;
}

export interface Voucher {
  uuid: string;
  // XXXX-XXXX-XXXX; the printed QR carries the same text. Review S1: masked ("K7QM-****-9HTR") for a reader without
  // vouchers.manage — the code is the money
  code: string;
  kind: 'gift' | 'discount';
  face_value: string;
  balance: string;
  currency: string;
  valid_from: string;
  // The last business day it pays; null: no end
  valid_until: string | null;
  status: VoucherStatus;
  status_label: string;
  branch: { id: number; name: string | null } | null;
  sale: { tender: 'cash' | 'card' | 'qr'; card_type: string | null; transaction_id: string | null; reference: string | null; shift_id: number | null; sold_at: string | null; sold_by: Person | null };
  // The phone is masked except in one certificate's own view for vouchers.manage
  buyer: { name: string | null; phone: string | null };
  note: string | null;
  fiscal: { receipt_uuid: string | null; status: string | null };
  blocked: { at: string | null; reason: string | null } | null;
  // What the certificate slip prints; null for a reader without vouchers.manage
  print?: { store: string | null; code: string; qr: string; value: string; valid_from: string; valid_until: string | null } | null;
  created_at: string | null;
}

export type VoucherEntryType = 'sale' | 'redemption' | 'refund' | 'block' | 'unblock';

export interface VoucherEntry {
  uuid: string;
  type: VoucherEntryType;
  type_label: string;
  amount: string;
  balance_after: string;
  business_date: string | null;
  bill_uuid: string | null;
  refund_uuid: string | null;
  tender: string | null;
  branch_id: number | null;
  shift_id: number | null;
  user: Person | null;
  note: string | null;
  created_at: string | null;
}

export interface VoucherPage {
  vouchers: Voucher[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface VoucherDetail {
  voucher: Voucher;
  entries: VoucherEntry[];
}

export interface CountAmount {
  count: number;
  amount: string;
}

export interface VoucherReport {
  from: string;
  to: string;
  branch_id: number | null;
  sold: CountAmount & { by_tender: { tender: 'cash' | 'card' | 'qr'; count: number; amount: string }[] };
  redeemed: CountAmount;
  refunded: CountAmount;
  // What the period added to the liability (sold − redeemed + refunded back)
  net_change: string;
  // As of `as_of` (today): what certificates can still pay, what is blocked, what expired unused
  outstanding: CountAmount;
  blocked: CountAmount;
  expired: CountAmount;
  as_of: string;
}

export interface VoucherFilter {
  status?: VoucherStatus;
  q?: string;
  branch_id?: number;
  from?: string;
  to?: string;
}

const query = (filter: VoucherFilter): Query => ({ ...filter });

export const fetchVouchers = (api: StaffApi, filter: VoucherFilter, page: number, perPage = 30) =>
  data<VoucherPage>(door(api).GET('/v1/owner/pos/vouchers', { params: { query: { ...query(filter), page, per_page: perPage } } }));

export const fetchVoucher = (api: StaffApi, uuid: string) => data<VoucherDetail>(door(api).GET(`/v1/owner/pos/vouchers/${uuid}`));

export const fetchVoucherReport = (api: StaffApi, range: Pick<VoucherFilter, 'branch_id' | 'from' | 'to'>) =>
  data<VoucherReport>(door(api).GET('/v1/owner/pos/vouchers/report', { params: { query: query(range) } }));

export const blockVoucher = (api: StaffApi, uuid: string, reason: string) => data<Voucher>(door(api).POST(`/v1/owner/pos/vouchers/${uuid}/block`, { body: { reason } }));

export const unblockVoucher = (api: StaffApi, uuid: string, reason: string | null) =>
  data<Voucher>(door(api).POST(`/v1/owner/pos/vouchers/${uuid}/unblock`, { body: reason ? { reason } : {} }));

function filenameOf(header: string | null): string | undefined {
  if (!header) return undefined;
  const encoded = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded.trim());
    } catch {
      // the plain name below
    }
  }
  return /filename\s*=\s*"?([^";]+)"?/i.exec(header)?.[1]?.trim() || undefined;
}

// GET /owner/pos/vouchers/export: the .xlsx ("Sertifikatlar" and "Hisobot") with the session's token
export async function downloadVouchers(api: StaffApi, filter: VoucherFilter): Promise<{ blob: Blob; filename: string }> {
  const result = await door(api).GET('/v1/owner/pos/vouchers/export', { params: { query: query(filter) }, parseAs: 'blob' });
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  return { blob: result.data as Blob, filename: filenameOf(result.response.headers.get('Content-Disposition')) ?? `sertifikatlar-${filter.from ?? ''}-${filter.to ?? ''}.xlsx` };
}
