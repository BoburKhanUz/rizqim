import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// Fiscal terminal flows (P6-16, D5; docs/API.md "Fiskal terminal oqimlari"): how each branch takes cards (a bank terminal in
// "faqat terminal" mode, Rizqim on a smart kassa, or none), the installation checklist, which tills are smart kassas, and the
// bank terminal's day total compared with the Z. The server works out every figure (Rizqim's card money, the difference, the
// RRN matching); these functions only call the endpoints. All of it sits behind the `terminal_card_flow` module (403
// `module_disabled` without it).

export const CARD_MODES = ['terminal_only', 'smart_kassa', 'none'] as const;
export type CardMode = (typeof CARD_MODES)[number];

export const TERMINAL_CHECKLIST = ['old_kassa_unused', 'bank_terminal_mode', 'scanner_2d'] as const;
export type TerminalChecklistKey = (typeof TERMINAL_CHECKLIST)[number];

export const BANK_REPORT_SOURCES = ['manual', 'import', 'provider'] as const;
export type BankReportSource = (typeof BANK_REPORT_SOURCES)[number];

// GET /owner/fiscal/terminal-flow (settings.manage): every branch with its module state, setup and POS tills
// Review P6 (C#M2): `shared_terminal` — the branch's tills share one bank terminal settled once per business day (one
// bank report covers every shift of the day). Spelled out until the regenerated spec carries it; harmless after.
type GeneratedBranch = OkData<'terminalFlow.index'>['branches'][number];
export type TerminalSetupView = GeneratedBranch['setup'] & { shared_terminal?: boolean };
export type TerminalFlowBranch = Omit<GeneratedBranch, 'setup'> & { setup: TerminalSetupView };
export type TerminalFlowOverview = Omit<OkData<'terminalFlow.index'>, 'branches'> & { branches: TerminalFlowBranch[] };
export type TerminalFlowDevice = TerminalFlowBranch['devices'][number];

export async function fetchTerminalFlow(api: StaffApi, branchId?: number): Promise<TerminalFlowOverview> {
  const query = branchId === undefined ? {} : { branch_id: branchId };
  return unwrap(await api.client.GET('/v1/owner/fiscal/terminal-flow', { params: { query } })).data;
}

// PUT /owner/branches/{branch}/terminal-flow: partial (what is not sent stays); ticking an item records who and when
export type TerminalSetupPatch = JsonRequest<'terminalFlow.update'> & { shared_terminal?: boolean };

export async function saveTerminalSetup(api: StaffApi, branchId: number, patch: TerminalSetupPatch): Promise<TerminalSetupView> {
  return unwrap(await api.client.PUT('/v1/owner/branches/{branch}/terminal-flow', { params: { path: { branch: branchId } }, body: patch })).data;
}

// PUT /owner/fiscal/devices/{uuid}: a POS till is (not) a smart kassa, and the provider's id of its kassa
export type SmartKassaPatch = JsonRequest<'terminalFlow.updateDevice'>;

export async function saveSmartKassa(api: StaffApi, uuid: string, patch: SmartKassaPatch): Promise<TerminalFlowDevice> {
  return unwrap(await api.client.PUT('/v1/owner/fiscal/devices/{uuid}', { params: { path: { uuid } }, body: patch })).data;
}

// GET /owner/fiscal/bank-reports (finance.view or reports.view): newest business day first. Review P6 (C#M2, S#5): a
// `shared` report covers several shifts (`shifts`); it is `final` once all are closed. Without finance.manage an entry's
// answer (and a shift's Z) carries Rizqim's side, the threshold and the difference as null until then; an alert stays
// (`over_threshold`) after a correction, whose earlier entry is kept in `history`. Spelled out until the regenerated
// spec carries it; harmless after.
export interface BankReportHistoryEntry {
  source: string;
  bank_total: string;
  bank_count: number | null;
  difference: string;
  over_threshold: boolean;
  entered_by: number | null;
  entered_at: string | null;
}

interface BankReportReviewFields {
  shared?: boolean;
  final?: boolean;
  shifts?: { uuid: string; z_number: number | null; status: string }[];
  rizqim_total: string | null;
  difference: string | null;
  threshold: string | null;
  over_threshold: boolean | null;
  history?: BankReportHistoryEntry[];
}

export type BankTerminalReport = Omit<OkData<'terminalFlow.reports'>[number], keyof BankReportReviewFields> & BankReportReviewFields;

export interface BankReportsQuery {
  branch_id?: number;
  from?: string;
  to?: string;
  // Only the reports whose difference is above the branch's threshold
  over_threshold?: boolean;
  limit?: number;
}

export async function fetchBankReports(api: StaffApi, query: BankReportsQuery = {}): Promise<BankTerminalReport[]> {
  const { over_threshold, ...rest } = query;
  const params = over_threshold ? { ...rest, over_threshold: true } : rest;
  return unwrap(await api.client.GET('/v1/owner/fiscal/bank-reports', { params: { query: params } })).data ?? [];
}

// PUT /owner/fiscal/bank-reports/{shift uuid}: enter or correct a shift's bank terminal day total — an open shift with
// finance.manage or pos.close_shift, a closed one (after its Z) with finance.manage only (403 `bank_report_locked`). 422
// `data.reason` bank_total_required, bank_transactions_required, bank_transaction_invalid; 409 provider_report_unavailable.
export type BankReportInput = JsonRequest<'terminalFlow.enter'>;

export async function enterBankReport(api: StaffApi, shiftUuid: string, input: BankReportInput): Promise<BankTerminalReport> {
  return unwrap(await api.client.PUT('/v1/owner/fiscal/bank-reports/{uuid}', { params: { path: { uuid: shiftUuid } }, body: input })).data;
}

// The rows of a bank terminal's report pasted as text, one per line: "RRN;amount" (also "," or a tab between, or just the
// amount). Blank lines are skipped; a line whose amount is not a number is returned in `bad` (1-based line numbers).
export function parseBankRows(text: string): { rows: { rrn: string | null; amount: string }[]; bad: number[] } {
  const rows: { rrn: string | null; amount: string }[] = [];
  const bad: number[] = [];
  text.split(/\r?\n/).forEach((raw, index) => {
    const line = raw.trim();
    if (line === '') return;
    const parts = line.split(/[;\t,]/).map((part) => part.trim());
    const amountText = (parts.length > 1 ? parts[parts.length - 1] : parts[0])?.replace(/\s/g, '') ?? '';
    const rrn = parts.length > 1 ? parts.slice(0, -1).join(' ').trim() : '';
    if (!/^-?\d+(\.\d{1,2})?$/.test(amountText)) {
      bad.push(index + 1);
      return;
    }
    rows.push({ rrn: rrn === '' ? null : rrn, amount: amountText });
  });
  return { rows, bad };
}
