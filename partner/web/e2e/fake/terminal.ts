// Fiscal terminal flows of the fake backend (docs/API.md P6-16 "Fiskal terminal oqimlari"): how each branch takes cards, the
// installation checklist, the smart-kassa tills and the bank terminal's day totals against the Z. The Rizqim card money of a
// shift is fixed (the card line of the fake Z report) so a test can read the difference.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const CHECKLIST = [
  { key: 'old_kassa_unused', label: 'Eski kassa sotuvda ishlatilmaydi', hint: "Eski kassa apparati faqat zaxira." },
  { key: 'bank_terminal_mode', label: 'Bank terminali kerakli rejimda', hint: "Terminal faqat karta qabul qiladi." },
  { key: 'scanner_2d', label: '2D skaner ulangan', hint: 'QR va markirovka kodlarini o‘qiydi.' },
] as const

const CARD_MODE_LABELS: Record<string, string> = { terminal_only: 'Faqat bank terminali', smart_kassa: 'Rizqim smart-kassada', none: 'Karta qabul qilinmaydi' }

/** The card money Rizqim recorded in the fake shifts' Z report (shifts.ts: by_tender card). */
export const RIZQIM_CARD_TOTAL = '1200000.00'

interface Setup {
  card_mode: string | null
  checklist: Record<string, { done: boolean; done_at: string | null; done_by: { id: number; name: string } | null }>
  difference_threshold: string
  ask_bank_total: boolean
}

export interface FakeBankReport {
  uuid: string
  shift_uuid: string
  z_number: number | null
  branch_id: number
  business_date: string
  source: 'manual' | 'import' | 'provider'
  bank_total: string
  rizqim_total: string
  difference: string
  threshold: string
  over_threshold: boolean
  transactions: Array<{ rrn: string | null; amount: string; matched: boolean }> | null
}

export class FakeTerminal {
  /** False: the branch's format lacks `terminal_card_flow` (the setup shows "modul o'chirilgan"). */
  moduleEnabled = true
  setup: Setup = {
    card_mode: null,
    checklist: Object.fromEntries(CHECKLIST.map((item) => [item.key, { done: false, done_at: null, done_by: null }])),
    difference_threshold: '0.00',
    ask_bank_total: true,
  }
  devices = [{ uuid: 'dev-1', name: 'Kassa 1', branch_id: 1, smart_kassa: false, fiscal_kassa_id: null as string | null, last_seen_at: null }]
  reports: FakeBankReport[] = []

  constructor(readonly backend: FakeBackend) {}

  private setupView() {
    const items = CHECKLIST.map((item) => ({ ...item, ...this.setup.checklist[item.key] }))
    return {
      branch_id: 1,
      card_mode: this.setup.card_mode,
      card_mode_label: this.setup.card_mode ? CARD_MODE_LABELS[this.setup.card_mode] : null,
      checklist: items,
      checklist_complete: items.every((item) => item.done),
      difference_threshold: this.setup.difference_threshold,
      ask_bank_total: this.setup.ask_bank_total,
      updated_at: null,
    }
  }

  private deviceView(device: (typeof this.devices)[number]) {
    return { ...device, kassa_status: device.smart_kassa ? { registered: Boolean(device.fiscal_kassa_id), kassa_id: device.fiscal_kassa_id, message: null } : null }
  }

  /** The bank terminal row of a shift's detail (null: no report entered). */
  reportOf(shiftUuid: string): FakeBankReport | null {
    return this.reports.find((report) => report.shift_uuid === shiftUuid) ?? null
  }

  private reportView(report: FakeBankReport) {
    return {
      ...report,
      bank_count: report.transactions?.length ?? null,
      terminal_ref: null,
      rizqim_count: 5,
      rizqim_breakdown: { payments: report.rizqim_total, deposits: '0.00', repayments: '0.00', refunds: '0.00', deposit_refunds: '0.00' },
      unmatched: null,
      note: null,
      entered_by: { id: this.backend.account.id, name: this.backend.account.name },
      alerted_at: null,
      created_at: null,
      updated_at: null,
    }
  }

  private enter(shiftUuid: string, body: Record<string, unknown>): Reply {
    const shift = this.backend.shifts.shifts.find((candidate) => candidate.uuid === shiftUuid)
    if (!shift) return fail(404, 'Smena topilmadi.')
    if (shift.status !== 'closed') return fail(422, 'The given data was invalid.', undefined, { reason: 'shift_not_closed' })
    const source = body.source === 'import' ? 'import' : 'manual'
    let bankTotal: string
    let transactions: FakeBankReport['transactions'] = null
    if (source === 'import') {
      const rows = Array.isArray(body.transactions) ? (body.transactions as Array<{ rrn?: string | null; amount: string }>) : []
      if (rows.length === 0) return fail(422, 'The given data was invalid.', undefined, { reason: 'bank_transactions_required' })
      bankTotal = rows.reduce((sum, row) => sum + Number(row.amount), 0).toFixed(2)
      transactions = rows.map((row) => ({ rrn: row.rrn ?? null, amount: row.amount, matched: true }))
    } else {
      if (typeof body.bank_total !== 'string') return fail(422, 'The given data was invalid.', undefined, { reason: 'bank_total_required' })
      bankTotal = Number(body.bank_total).toFixed(2)
    }
    const difference = (Number(bankTotal) - Number(RIZQIM_CARD_TOTAL)).toFixed(2)
    const report: FakeBankReport = {
      uuid: this.reportOf(shiftUuid)?.uuid ?? `b0000000-0000-4000-8000-${String(this.reports.length + 1).padStart(12, '0')}`,
      shift_uuid: shift.uuid,
      z_number: shift.z_number,
      branch_id: shift.branch_id,
      business_date: shift.business_date,
      source,
      bank_total: bankTotal,
      rizqim_total: RIZQIM_CARD_TOTAL,
      difference,
      threshold: this.setup.difference_threshold,
      over_threshold: Math.abs(Number(difference)) > Number(this.setup.difference_threshold),
      transactions,
    }
    this.reports = [report, ...this.reports.filter((candidate) => candidate.shift_uuid !== shiftUuid)]
    return ok(this.reportView(report))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path
    let match: RegExpExecArray | null
    if (req.method === 'GET' && path === '/v1/owner/fiscal/terminal-flow') {
      return ok({
        fiscal: { enabled: true, active_driver: 'sandbox', smart_kassa_driver: 'sandbox' },
        card_modes: Object.entries(CARD_MODE_LABELS).map(([value, label]) => ({ value, label })),
        checklist_items: CHECKLIST,
        branches: [{ branch: { id: 1, name: 'Chilonzor' }, module_enabled: this.moduleEnabled, setup: this.setupView(), devices: this.devices.map((device) => this.deviceView(device)) }],
      })
    }
    if (req.method === 'PUT' && (match = /^\/v1\/owner\/branches\/(\d+)\/terminal-flow$/.exec(path))) {
      const input = body ?? {}
      if (typeof input.difference_threshold === 'string' && !/^\d+(\.\d{1,2})?$/.test(input.difference_threshold)) {
        return fail(422, 'The given data was invalid.', { difference_threshold: ["Chegara musbat summa bo'lishi kerak."] })
      }
      if ('card_mode' in input) this.setup.card_mode = (input.card_mode as string | null) ?? null
      if (typeof input.difference_threshold === 'string') this.setup.difference_threshold = Number(input.difference_threshold).toFixed(2)
      if (typeof input.ask_bank_total === 'boolean') this.setup.ask_bank_total = input.ask_bank_total
      for (const [key, done] of Object.entries((input.checklist as Record<string, boolean> | undefined) ?? {})) {
        // Ticking an item records who and when
        this.setup.checklist[key] = done ? { done: true, done_at: '2026-11-14T09:00:00+05:00', done_by: { id: this.backend.account.id, name: this.backend.account.name } } : { done: false, done_at: null, done_by: null }
      }
      return ok(this.setupView())
    }
    if (req.method === 'PUT' && (match = /^\/v1\/owner\/fiscal\/devices\/([\w-]+)$/.exec(path))) {
      const device = this.devices.find((candidate) => candidate.uuid === match![1])
      if (!device) return fail(404, 'Terminal topilmadi.')
      if (typeof body?.smart_kassa === 'boolean') device.smart_kassa = body.smart_kassa
      if (body && 'fiscal_kassa_id' in body) device.fiscal_kassa_id = (body.fiscal_kassa_id as string | null) ?? null
      return ok(this.deviceView(device))
    }
    if (req.method === 'GET' && path === '/v1/owner/fiscal/bank-reports') {
      const rows = this.reports.filter((report) => !['1', 'true'].includes(req.query.over_threshold) || report.over_threshold)
      return ok(rows.map((report) => this.reportView(report)))
    }
    if (req.method === 'PUT' && (match = /^\/v1\/owner\/fiscal\/bank-reports\/([\w-]+)$/.exec(path))) return this.enter(match[1], body ?? {})
    return null
  }
}
