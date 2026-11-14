// The banquet payment schedule of the fake backend (docs/API.md P6-53 "Tadbirlar: to'lov jadvali"): one event with a
// paid deposit, an overdue interim and the final; a bank transfer against a row, the cancellation quote and the
// cancellation, the payment rules and the "Tadbirlar" report. Enough for the panel's flows; the schedule maths, the
// receipts and the report's sums are the backend's and its tests.
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export const BANQUET_EVENT = 'eeeeeeee-0000-4000-8000-000000000001'
const ROWS = ['eeeeeeee-0000-4000-8000-0000000000a1', 'eeeeeeee-0000-4000-8000-0000000000a2', 'eeeeeeee-0000-4000-8000-0000000000a3']
const KASSIR = { id: 1, name: 'Kassir' }

type Row = Record<string, unknown> & { uuid: string; kind: string; amount: string; status: string }

export class FakeBanquetPay {
  status = 'confirmed'
  version = 3
  rows: Row[] = []
  entries: Array<Record<string, unknown>> = []
  cancellation: Record<string, unknown> | null = null
  rules = {
    schedule_template: [
      { kind: 'deposit', percent: '30', days_before: null },
      { kind: 'interim', percent: '40', days_before: 14 },
      { kind: 'final', percent: null, days_before: 0 },
    ],
    cancellation_rules: [
      { min_days: 30, retained_percent: '0' },
      { min_days: 14, retained_percent: '30' },
      { min_days: 0, retained_percent: '100' },
    ],
    reminder_days: [3, 1],
    overdue_reminders: true,
    version: 0,
  }

  constructor(private readonly backend: FakeBackend) {
    void this.backend
    const row = (uuid: string, kind: string, due: string, amount: string, over: Record<string, unknown> = {}): Row => ({
      uuid, kind, kind_label: kind, due_date: due, amount, status: 'due', status_label: "To'lanmagan", overdue: false, tender: null, card_type: null, transaction_id: null, reference: null, shift_id: null, paid_at: null, paid_by: null,
      fiscal: { receipt_uuid: null, status: null }, refunded_amount: '0.00', retained_amount: '0.00', settle_receipt_uuid: null, note: null, ...over,
    })
    this.rows = [
      row(ROWS[0], 'deposit', '2026-11-01', '3000000.00', { status: 'paid', status_label: "To'langan", tender: 'cash', fiscal: { receipt_uuid: 'r-1', status: 'issued' } }),
      row(ROWS[1], 'interim', '2026-11-10', '4000000.00', { overdue: true }),
      row(ROWS[2], 'final', '2027-01-20', '3000000.00'),
    ]
    this.entries = [{ uuid: 'be-1', type: 'payment', type_label: "To'lov (avans)", payment_uuid: ROWS[0], amount: '3000000.00', tender: 'cash', card_type: null, reference: null, business_date: '2026-11-01', shift_id: 1, user: KASSIR, approved_by: null, reason: null, transfer_pending: false, fiscal: { receipt_uuid: 'r-1', status: 'issued' }, original_receipt_uuid: null, created_at: '2026-11-01T10:00:00+05:00' }]
  }

  private sum(rows: Row[]): string {
    return rows.reduce((total, row) => total + Number(row.amount), 0).toFixed(2)
  }

  private schedule() {
    const due = this.rows.filter((row) => row.status === 'due')
    const overdue = due.filter((row) => row.overdue === true)
    const paid = this.rows.filter((row) => row.status !== 'due')
    const live = this.status !== 'cancelled'
    return {
      event: { uuid: BANQUET_EVENT, status: this.status, status_label: live ? 'Tasdiqlangan' : 'Bekor qilingan', event_date: '2027-01-20', total: '10000000.00', paid_total: this.sum(paid), version: this.version },
      summary: {
        total: '10000000.00', paid: this.sum(paid), outstanding: live ? this.sum(due) : '0.00', overdue: live && overdue.length > 0, overdue_amount: live ? this.sum(overdue) : '0.00', overdue_count: live ? overdue.length : 0,
        next_due: live && due[0] ? { date: String(due[0].due_date), amount: due[0].amount, kind: due[0].kind } : null,
      },
      rows: this.rows,
      entries: this.entries,
      cancellation: this.cancellation,
    }
  }

  private quote(retained?: string) {
    const paid = this.sum(this.rows.filter((row) => row.status === 'paid'))
    const keep = retained !== undefined && retained !== '' ? Number(retained).toFixed(2) : (Number(paid) * 0.3).toFixed(2)
    const refund = (Number(paid) - Number(keep)).toFixed(2)
    return {
      days_before: 20, rule_percent: '30', paid, computed_retained: (Number(paid) * 0.3).toFixed(2), retained: keep, refund, overridden: retained !== undefined && retained !== '', cash_refund: refund, transfer_refund: '0.00',
      rows: [{ payment_uuid: ROWS[0], kind: 'deposit', tender: 'cash', amount: paid, retain: keep, refund, refund_path: 'cash_drawer' }],
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path === '/v1/owner/pos/banquets/payment-settings') {
      if (req.method === 'GET') return ok(this.rules)
      if (req.method === 'PUT') {
        this.rules = { ...this.rules, ...(body as object), version: this.rules.version + 1 }
        return ok(this.rules)
      }
    }
    if (req.method === 'GET' && req.path === '/v1/owner/pos/banquets/report') {
      const manages = this.backend.account.permissions.includes('banquets.manage')
      return ok({
        from: req.query.from ?? '2026-11-01', to: req.query.to ?? '2026-11-30', branch_id: null, events: 1, cancelled: 0, by_status: [], guests: 200,
        total: '10000000.00', paid: '3000000.00', outstanding: '7000000.00', overdue: { count: 1, amount: '4000000.00' }, retained: '0.00', refunded: '0.00', transfer_pending: '0.00',
        rows: [{ uuid: BANQUET_EVENT, event_date: '2027-01-20', slot: 'evening', event_type: 'toy', title: "Karimovlar to'yi", branch_id: 1, customer_name: 'Karimovlar oilasi', customer_phone: manages ? '+998901234567' : '+99890*****67', status: this.status, status_label: 'Tasdiqlangan', guests_guaranteed: 200, guests_minimum: 200, guests_actual: null, guests: 200, total: '10000000.00', paid: '3000000.00', refunded: '0.00', retained: '0.00', outstanding: '7000000.00', overdue: '4000000.00', transfer_pending: '0.00' }],
      })
    }
    if (req.method === 'GET' && req.path === '/v1/owner/pos/banquets/export') {
      return { status: 200, body: null, raw: 'xlsx', headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': `attachment; filename=tadbirlar-${req.query.from}-${req.query.to}.xlsx` } }
    }

    const prefix = `/v1/pos/banquets/${BANQUET_EVENT}`
    if (!req.path.startsWith(prefix)) return null
    const path = req.path.slice(prefix.length)
    if (req.method === 'GET' && path === '/payments') return ok(this.schedule())
    if (req.method === 'GET' && path === '/cancellation') return ok(this.quote(req.query.retained))
    if (req.method === 'POST' && path === '/cancellation') {
      if (String(body?.reason ?? '').trim().length < 3) return fail(422, 'Sababni yozing.', { reason: ['Sababni yozing.'] })
      const quote = this.quote(body?.retained !== undefined ? String(body.retained) : undefined)
      this.status = 'cancelled'
      this.version++
      this.rows = this.rows.filter((row) => row.status !== 'due').map((row) => ({ ...row, status: Number(quote.retained) > 0 ? 'retained' : 'refunded', status_label: Number(quote.retained) > 0 ? 'Ushlab qolingan' : 'Qaytarilgan', retained_amount: quote.retained, refunded_amount: quote.refund }))
      this.cancellation = { days_before: 20, rule_percent: '30', paid: quote.paid, computed_retained: quote.computed_retained, retained: quote.retained, refund: quote.refund, override_reason: body?.override_reason ?? null, reason: body?.reason, business_date: '2026-11-16', cancelled_by: 7, created_at: '2026-11-16T12:00:00+05:00' }
      return ok(this.schedule())
    }
    const transfer = /^\/payments\/([0-9a-f-]{36})\/bank-transfer$/.exec(path)
    if (req.method === 'POST' && transfer) {
      const row = this.rows.find((item) => item.uuid === transfer[1])
      if (!row || row.status !== 'due') return fail(409, "Bu qator allaqachon to'langan.")
      Object.assign(row, { status: 'paid', status_label: "To'langan", overdue: false, tender: 'bank_transfer', reference: body?.reference ?? null })
      const entry = { uuid: `be-${this.entries.length + 1}`, type: 'transfer', type_label: "Bank o'tkazmasi", payment_uuid: row.uuid, amount: row.amount, tender: 'bank_transfer', card_type: null, reference: body?.reference ?? null, business_date: '2026-11-16', shift_id: null, user: { id: 7, name: 'Olim Egamov' }, approved_by: null, reason: null, transfer_pending: false, fiscal: { receipt_uuid: null, status: null }, original_receipt_uuid: null, created_at: '2026-11-16T12:00:00+05:00' }
      this.entries.push(entry)
      return created({ schedule: this.schedule(), entry })
    }
    return null
  }
}
