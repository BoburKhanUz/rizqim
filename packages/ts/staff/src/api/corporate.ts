import type { TalonMode } from './accounts';
import type { StaffApi } from './client';
import { StaffApiError, unwrap } from './errors';
import { newIdempotencyKey, withIdempotency } from './idempotency';

// P6-44 corporate accounts (docs/API.md "Korxona hisobi va talon"): a company's members, each with a talon code (QR chit or
// badge), a daily limit and/or meals per day; the till's member lookup; the monthly statement and its Excel file. The account
// itself (kind, company details, monthly limit, charge approval, talon mode) is a customer account (api/accounts.ts).
// Members need `customer_accounts.manage` (setup: the `corporate_accounts` module), the statement `reports.view`. The types are
// written here until the spec is regenerated with these endpoints.

export interface CorporateMember {
  uuid: string;
  name: string;
  phone: string | null;
  // The talon: 4–32 letters, digits or "-" (ten random digits by default), unique in the store
  code: string;
  // Sum per business day; null: none
  daily_limit: string | null;
  // Subscription (abonement); null: no count limit
  meals_per_day: number | null;
  is_active: boolean;
  note: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface CorporateMembers {
  members: CorporateMember[];
  active_count: number;
}

export interface CorporateMembersQuery {
  q?: string;
  active?: boolean;
}

export interface CorporateMemberInput {
  name: string;
  phone?: string | null;
  code?: string | null;
  daily_limit?: string | number | null;
  meals_per_day?: number | null;
  note?: string | null;
}

export type CorporateMemberPatch = Partial<CorporateMemberInput> & {
  is_active?: boolean;
  // A new talon code (the old chit stops working)
  regenerate_code?: boolean;
};

export interface CorporateBulkInput {
  // "name;phone" per line (a third column is the talon code)
  lines: string;
  daily_limit?: string | number | null;
  meals_per_day?: number | null;
}

export interface CorporateBulkResult {
  created: number;
  members: CorporateMember[];
}

// A refused paste (422 `bulk_invalid`): the bad lines, nobody added
export interface CorporateBulkLineError {
  line: number;
  reason: 'name_required' | 'phone_invalid' | 'phone_duplicate' | 'phone_taken' | 'code_invalid' | 'code_duplicate' | 'code_taken' | string;
  message: string;
}

export function bulkLineErrors(error: unknown): CorporateBulkLineError[] {
  if (!(error instanceof StaffApiError) || error.reason !== 'bulk_invalid') return [];
  const lines = (error.body as { data?: { lines?: unknown } } | null)?.data?.lines;
  return Array.isArray(lines) ? (lines as CorporateBulkLineError[]) : [];
}

export interface CorporateStatementCell {
  date: string;
  amount: string;
  meals: number;
}

export interface CorporateStatementRow {
  // null: charges without a member (the company as a whole)
  member: {
    uuid: string;
    name: string;
    code: string;
    is_active: boolean;
  } | null;
  cells: CorporateStatementCell[];
  amount: string;
  meals: number;
}

export interface CorporateStatement {
  account: {
    uuid: string;
    name: string;
    company_name: string | null;
    inn: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    invoice_email: string | null;
    talon_mode: TalonMode;
  };
  // YYYY-MM and its first and last day
  month: string;
  from: string;
  to: string;
  days: string[];
  rows: CorporateStatementRow[];
  day_totals: CorporateStatementCell[];
  total: string;
  meals: number;
  repaid_total: string;
  monthly_limit: string | null;
  balance: string;
}

export interface CorporateMemberLookup {
  account: {
    uuid: string;
    name: string;
    company_name: string | null;
    talon_mode: TalonMode;
    charge_approval: boolean;
    is_blocked: boolean;
    credit_limit: string | null;
    balance: string;
  };
  // The phone masked
  member: {
    uuid: string;
    name: string;
    phone: string | null;
    code: string;
    daily_limit: string | null;
    meals_per_day: number | null;
    is_active: boolean;
  };
  usage: {
    business_date: string;
    member: {
      amount: string;
      meals: number;
      daily_limit: string | null;
      daily_available: string | null;
      meals_per_day: number | null;
      meals_left: number | null;
    } | null;
    month: {
      amount: string;
      monthly_limit: string | null;
      available: string | null;
    };
    credit_available: string | null;
  };
}

// The generated client knows these paths once the spec is regenerated; until then each is typed as an existing path of the
// same shape (the runtime URL is the real one)
const MEMBERS_PATH = '/v1/pos/customer-accounts/{uuid}/members' as '/v1/pos/customer-accounts/{uuid}';
const MEMBERS_POST_PATH = '/v1/pos/customer-accounts/{uuid}/members' as '/v1/pos/customer-accounts/{uuid}/adjustments';
const BULK_PATH = '/v1/pos/customer-accounts/{uuid}/members/bulk' as '/v1/pos/customer-accounts/{uuid}/adjustments';
const MEMBER_PATH = '/v1/pos/customer-accounts/{uuid}/members/{member}' as '/v1/pos/customer-accounts/{uuid}';
const STATEMENT_PATH = '/v1/pos/customer-accounts/{uuid}/corporate-statement' as '/v1/pos/customer-accounts/{uuid}';
const EXPORT_PATH = '/v1/pos/customer-accounts/{uuid}/corporate-statement/export' as '/v1/pos/customer-accounts/{uuid}';
const LOOKUP_PATH = '/v1/pos/customer-accounts/members/lookup' as '/v1/pos/customer-accounts/debtors';

// GET .../members: everyone of the company, active first
export async function fetchCorporateMembers(api: StaffApi, uuid: string, query: CorporateMembersQuery = {}): Promise<CorporateMembers> {
  return unwrap(
    await api.client.GET(MEMBERS_PATH, {
      params: { path: { uuid }, query: query as never },
    }),
  ).data as unknown as CorporateMembers;
}

// POST .../members: one member; the code is generated when not given
export async function createCorporateMember(api: StaffApi, uuid: string, input: CorporateMemberInput, key: string = newIdempotencyKey()): Promise<CorporateMember> {
  return unwrap(
    await api.client.POST(MEMBERS_POST_PATH, {
      params: { path: { uuid } },
      body: input as never,
      ...withIdempotency(key),
    }),
  ).data as unknown as CorporateMember;
}

// PATCH .../members/{member}: only what changes (audited)
export async function updateCorporateMember(api: StaffApi, uuid: string, member: string, patch: CorporateMemberPatch, key: string = newIdempotencyKey()): Promise<CorporateMember> {
  const path = { uuid, member } as unknown as { uuid: string };
  return unwrap(
    await api.client.PATCH(MEMBER_PATH, {
      params: { path },
      body: patch as never,
      ...withIdempotency(key),
    }),
  ).data as unknown as CorporateMember;
}

// POST .../members/bulk: all or nothing (bulkLineErrors() reads a refusal)
export async function importCorporateMembers(api: StaffApi, uuid: string, input: CorporateBulkInput, key: string = newIdempotencyKey()): Promise<CorporateBulkResult> {
  return unwrap(
    await api.client.POST(BULK_PATH, {
      params: { path: { uuid } },
      body: input as never,
      ...withIdempotency(key),
    }),
  ).data as unknown as CorporateBulkResult;
}

// GET .../corporate-statement?month=YYYY-MM (reports.view)
export async function fetchCorporateStatement(api: StaffApi, uuid: string, month?: string): Promise<CorporateStatement> {
  return unwrap(
    await api.client.GET(STATEMENT_PATH, {
      params: { path: { uuid }, query: (month ? { month } : {}) as never },
    }),
  ).data as unknown as CorporateStatement;
}

// GET /pos/customer-accounts/members/lookup?code= | ?phone= (the till; the panel's checks)
export async function lookupCorporateMember(api: StaffApi, query: { code?: string; phone?: string }): Promise<CorporateMemberLookup> {
  return unwrap(await api.client.GET(LOOKUP_PATH, { params: { query: query as never } })).data as unknown as CorporateMemberLookup;
}

export interface CorporateStatementFile {
  blob: Blob;
  filename: string;
}

export const corporateStatementFilename = (month: string): string => `korxona-hisob-${month}.xlsx`;

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

// GET .../corporate-statement/export: the .xlsx (sheets "Hisobot" and "Hisob-faktura") with the session's token
export async function downloadCorporateStatement(api: StaffApi, uuid: string, month: string): Promise<CorporateStatementFile> {
  const result = await api.client.GET(EXPORT_PATH, {
    params: { path: { uuid }, query: { month } as never },
    parseAs: 'blob',
  });
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  return {
    blob: result.data as unknown as Blob,
    filename: filenameOf(result.response.headers.get('Content-Disposition')) ?? corporateStatementFilename(month),
  };
}
