// The fake of Moliya (P4-23): /admin/finance/*, shaped like AdminFinanceController, AdminFinancePayoutController,
// AdminFinanceAdjustmentController and AdminFinanceReportController answer. The rules the screens depend on are here:
// reads need finance.view; payout decisions payouts.approve, adjustments and retries finance.manage, the Excel export also
// export; every action a step-up and a reason (3+ characters); from 1 000 000 so'm two different members (403 four_eyes);
// approving nets the store's debt first. A test changes the state before (or while) the page is open. Lazzat Oshxona is
// store 12, as in e2e/fake/stores.ts (the store picker of the adjustment form looks it up there).
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const THRESHOLD = 1_000_000
const iso = (minutesAgo: number) => new Date(Date.now() - minutesAgo * 60_000).toISOString()
const money = (value: number) => value.toFixed(2)
const LABEL: Record<string, string> = { pending: 'Tasdiq kutilmoqda', awaiting_second: 'Ikkinchi tasdiq kutilmoqda', completed: "O'tkazildi", rejected: 'Rad etildi' }
const KINDS: Record<string, { label: string; ledger: 'debt' | 'wallet'; sign: 1 | -1 }> = {
  debt_charge: { label: "Qarzga qo'shish", ledger: 'debt', sign: 1 },
  debt_credit: { label: 'Komissiya qarzini kamaytirish', ledger: 'debt', sign: -1 },
  debt_payment: { label: "To'lov qabul qilindi (hisob raqami, naqd)", ledger: 'debt', sign: -1 },
  wallet_credit: { label: "Hamyonga qo'shish", ledger: 'wallet', sign: 1 },
  wallet_debit: { label: 'Hamyondan ayirish', ledger: 'wallet', sign: -1 },
}

interface Store {
  id: number
  name: string
  wallet: number
  invoices: Array<{ id: number; number: string; period: string; open: number }>
  commissionDebt: number
  creditLimit: number
}

export interface FakePayout {
  id: number
  storeId: number
  amount: number
  status: 'pending' | 'completed' | 'rejected'
  firstBy: { id: number; name: string } | null
  firstAt: string | null
  processedBy: { id: number; name: string } | null
  processedAt: string | null
  resolution: string | null
  netted: number | null
  lines: Array<{ kind: string; label: string; amount: string; invoice: { id: number; uuid: string; number: string; period: string } | null; transaction_id: number | null }> | null
  createdAt: string
}

export interface FakeAdjustment {
  id: number
  storeId: number
  kind: string
  amount: number
  reason: string
  status: 'pending' | 'applied' | 'rejected'
  requestedBy: { id: number; name: string }
  approvedBy: { id: number; name: string } | null
  rejectedBy: { id: number; name: string } | null
  document: string | null
  createdAt: string
}

export class FakeFinance {
  stores: Store[] = [
    { id: 12, name: 'Lazzat Oshxona', wallet: 500_000, invoices: [{ id: 1, number: 'OB-202609-3', period: '2026-09', open: 149_000 }, { id: 2, number: 'OB-202610-3', period: '2026-10', open: 149_000 }], commissionDebt: 0, creditLimit: 100_000 },
    { id: 40, name: 'Somsa Markazi', wallet: 3_000_000, invoices: [], commissionDebt: 120_000, creditLimit: 100_000 },
  ]
  payouts: FakePayout[] = [
    { id: 12, storeId: 12, amount: 400_000, status: 'pending', firstBy: null, firstAt: null, processedBy: null, processedAt: null, resolution: null, netted: null, lines: null, createdAt: iso(90) },
    { id: 20, storeId: 40, amount: 1_500_000, status: 'pending', firstBy: null, firstAt: null, processedBy: null, processedAt: null, resolution: null, netted: null, lines: null, createdAt: iso(60) },
  ]
  adjustments: FakeAdjustment[] = []
  /** What the page sent: every decision and its reason */
  decisions: Array<{ what: string; id: number; decision: string; reason: string; by: number; key: string | null }> = []
  exports: string[] = []
  private nextAdjustment = 1
  private nextTransaction = 100

  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }, headers: Record<string, string> = {}): Reply | null {
    if (!path.startsWith('/finance/')) return null
    if (!can('admin.finance.view')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })

    if (req.method === 'GET') {
      if (path === '/finance/summary') return ok(this.summary())
      if (path === '/finance/payouts') return ok(this.payoutPage(req.query.status ?? 'open'))
      const payout = /^\/finance\/payouts\/(\d+)$/.exec(path)
      if (payout) {
        const found = this.payouts.find((row) => row.id === Number(payout[1]))
        return found ? ok(this.card(found, actor, can)) : fail(404, "So'rov topilmadi.", { reason: 'not_found' })
      }
      if (path === '/finance/topups') return ok({ data: [], next_cursor: null, per_page: 30, counts: { pending: 0, stuck: 0 } })
      if (path === '/finance/settlement-failures') return ok({ data: [], count: 0, max_attempts: 12 })
      if (path === '/finance/balances') return ok(this.balances(req.query.filter ?? 'all'))
      if (path === '/finance/adjustments') return ok(this.adjustmentPage(req.query.status ?? 'all', actor, can))
      const adjustment = /^\/finance\/adjustments\/(\d+)$/.exec(path)
      if (adjustment) {
        const found = this.adjustments.find((row) => row.id === Number(adjustment[1]))
        return found ? ok(this.adjustment(found, actor, can)) : fail(404, 'Tuzatish topilmadi.', { reason: 'not_found' })
      }
      if (path === '/finance/reports/monthly') return ok(this.report(req.query.period ?? '2026-10'))
      if (path === '/finance/reports/monthly/export') {
        if (!can('admin.export')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
        this.exports.push(req.query.period ?? '')
        // A tiny zip header is enough: the proxy passes the bytes on by their type
        return { status: 200, raw: Buffer.from([80, 75, 3, 4, 20, 0]), headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': `attachment; filename=rizqim-daromad-${req.query.period ?? ''}.xlsx` } }
      }
      return null
    }

    const decide = /^\/finance\/(payouts|adjustments)\/(\d+)\/(approve|reject)$/.exec(path)
    if (req.method === 'POST' && decide) {
      const [, what, id, decision] = decide
      if (what === 'payouts' && !can('admin.payouts.approve')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
      if (what === 'adjustments' && !can('admin.finance.manage')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
      const recent = needsRecent()
      if (recent) return recent
      const reason = typeof body.reason === 'string' ? body.reason.trim() : ''
      if (reason.length < 3) return validation('reason', 'Sabab majburiy.')
      this.decisions.push({ what: what!, id: Number(id), decision: decision!, reason, by: actor.id, key: headers['idempotency-key'] ?? null })
      return what === 'payouts' ? this.decidePayout(Number(id), decision as 'approve' | 'reject', reason, actor, can) : this.decideAdjustment(Number(id), decision as 'approve' | 'reject', actor, can)
    }

    if (req.method === 'POST' && path === '/finance/adjustments') {
      if (!can('admin.finance.manage')) return fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
      const recent = needsRecent()
      if (recent) return recent
      const reason = typeof body.reason === 'string' ? body.reason.trim() : ''
      if (reason.length < 3) return validation('reason', 'Sabab majburiy.')
      const amount = Number(body.amount)
      if (!/^\d{1,10}(\.\d{1,2})?$/.test(String(body.amount ?? '')) || !(amount > 0)) return validation('amount', "Summa so'mda yozilishi kerak.")
      if (!KINDS[String(body.kind)]) return validation('kind', "Tur noto'g'ri.")
      const store = this.stores.find((row) => row.id === Number(body.store_id))
      if (!store) return fail(404, 'Ovqatxona topilmadi.', { reason: 'not_found' })
      const adjustment: FakeAdjustment = {
        id: this.nextAdjustment++,
        storeId: store.id,
        kind: String(body.kind),
        amount,
        reason,
        status: 'pending',
        requestedBy: actor,
        approvedBy: null,
        rejectedBy: null,
        document: typeof body.document === 'object' && body.document !== null ? String((body.document as { name?: unknown }).name ?? 'hujjat') : null,
        createdAt: new Date().toISOString(),
      }
      this.adjustments.unshift(adjustment)
      if (amount < THRESHOLD) this.apply(adjustment)
      return { status: 201, body: { success: true, message: '', data: this.adjustment(adjustment, actor, can) } }
    }

    if (req.method === 'POST' && /^\/finance\/settlement-failures\/[^/]+\/retry$/.test(path)) return fail(404, "Bu buyurtmaning hisob-kitobida xato yo'q.", { reason: 'not_found' })
    return null
  }

  // --- Payouts -----------------------------------------------------------------------------------------------------

  private stage(payout: FakePayout): string {
    return payout.status === 'pending' && payout.firstBy ? 'awaiting_second' : payout.status
  }

  private debtOf(store: Store): number {
    return store.invoices.reduce((sum, invoice) => sum + invoice.open, 0) + store.commissionDebt
  }

  // The store's debt that approving now would take: the oldest invoices first, then the commission debt
  private netting(store: Store, amount: number, book: boolean) {
    let left = Math.min(amount, this.debtOf(store))
    const lines: NonNullable<FakePayout['lines']> = []
    for (const invoice of store.invoices) {
      if (left <= 0) break
      const take = Math.min(left, invoice.open)
      if (take <= 0) continue
      lines.push({ kind: 'invoice', label: `Obuna hisob-fakturasi ${invoice.number} (${invoice.period})`, amount: money(take), invoice: { id: invoice.id, uuid: `inv-${invoice.id}`, number: invoice.number, period: invoice.period }, transaction_id: book ? this.nextTransaction++ : null })
      if (book) invoice.open -= take
      left -= take
    }
    if (left > 0) {
      lines.push({ kind: 'commission', label: 'Komissiya qarzi', amount: money(left), invoice: null, transaction_id: book ? this.nextTransaction++ : null })
      if (book) store.commissionDebt -= left
    }
    return { netted: lines.reduce((sum, line) => sum + Number(line.amount), 0), lines }
  }

  private row(payout: FakePayout) {
    const store = this.stores.find((row) => row.id === payout.storeId)!
    const stage = this.stage(payout)
    return {
      id: payout.id,
      store: { id: store.id, name: store.name },
      amount: money(payout.amount),
      card: '8600 **** **** 9012',
      status: stage,
      status_label: LABEL[stage],
      needs_second_approval: payout.amount >= THRESHOLD,
      first_approval: payout.firstBy ? { by: payout.firstBy, at: payout.firstAt, note: null } : null,
      netted_amount: payout.netted === null ? null : money(payout.netted),
      transferred_amount: payout.netted === null ? null : money(payout.amount - payout.netted),
      created_at: payout.createdAt,
      processed_at: payout.processedAt,
    }
  }

  private card(payout: FakePayout, actor: { id: number }, can: (permission: string) => boolean) {
    const store = this.stores.find((row) => row.id === payout.storeId)!
    const stage = this.stage(payout)
    const open = stage === 'pending' || stage === 'awaiting_second'
    const preview = open ? this.netting(store, payout.amount, false) : null
    const netted = preview ? preview.netted : (payout.netted ?? 0)
    const debt = this.debtOf(store)
    const subscription = store.invoices.reduce((sum, invoice) => sum + invoice.open, 0)
    return {
      ...this.row(payout),
      threshold: money(THRESHOLD),
      notes: null,
      resolution_notes: payout.resolution,
      processed_by: payout.processedBy,
      breakdown: stage === 'rejected' ? null : { accrued: money(payout.amount), netted: money(netted), transfer: money(payout.amount - netted), preview: open, lines: preview ? preview.lines : (payout.lines ?? []) },
      balances: { wallet: money(store.wallet), debt: money(debt), subscription_debt: money(subscription), commission_debt: money(store.commissionDebt), credit_limit: money(-store.creditLimit) },
      actions: {
        can_approve: open && can('admin.payouts.approve') && !(stage === 'awaiting_second' && payout.firstBy?.id === actor.id),
        can_reject: open && can('admin.payouts.approve'),
        approve_completes: payout.amount < THRESHOLD || stage === 'awaiting_second',
      },
    }
  }

  private payoutPage(status: string) {
    const rows = this.payouts.filter((payout) => {
      const stage = this.stage(payout)
      if (status === 'open') return stage === 'pending' || stage === 'awaiting_second'
      return status === 'all' || stage === status
    })
    const open = this.payouts.filter((payout) => payout.status === 'pending')
    return {
      data: rows.map((payout) => this.row(payout)),
      next_cursor: null,
      per_page: 30,
      counts: { pending: open.filter((payout) => !payout.firstBy).length, awaiting_second: open.filter((payout) => payout.firstBy).length, open_amount: money(open.reduce((sum, payout) => sum + payout.amount, 0)) },
      threshold: money(THRESHOLD),
    }
  }

  private decidePayout(id: number, decision: 'approve' | 'reject', reason: string, actor: { id: number; name: string }, can: (permission: string) => boolean): Reply {
    const payout = this.payouts.find((row) => row.id === id)
    if (!payout) return fail(404, "So'rov topilmadi.", { reason: 'not_found' })
    if (payout.status !== 'pending') return fail(409, "Bu so'rov allaqachon ko'rib chiqilgan.", { reason: 'payout_decided' })
    if (decision === 'reject') {
      Object.assign(payout, { status: 'rejected', processedBy: actor, processedAt: new Date().toISOString(), resolution: reason })
      return ok(this.card(payout, actor, can))
    }
    if (payout.amount >= THRESHOLD && !payout.firstBy) {
      Object.assign(payout, { firstBy: actor, firstAt: new Date().toISOString() })
      return ok(this.card(payout, actor, can))
    }
    if (payout.amount >= THRESHOLD && payout.firstBy?.id === actor.id) return fail(403, "1 000 000 so'm va undan katta payoutni ikki xodim tasdiqlaydi: ikkinchi tasdiqni boshqa xodim beradi.", { reason: 'four_eyes' })
    const store = this.stores.find((row) => row.id === payout.storeId)!
    if (store.wallet < payout.amount) return fail(422, "Hamyonda yetarli mablag' yo'q.", { reason: 'insufficient_wallet' })
    const { netted, lines } = this.netting(store, payout.amount, true)
    store.wallet -= payout.amount
    Object.assign(payout, { status: 'completed', processedBy: actor, processedAt: new Date().toISOString(), resolution: reason, netted, lines })
    return ok(this.card(payout, actor, can))
  }

  // --- Adjustments, balances, report -------------------------------------------------------------------------------

  private apply(adjustment: FakeAdjustment): void {
    const store = this.stores.find((row) => row.id === adjustment.storeId)!
    const kind = KINDS[adjustment.kind]!
    if (kind.ledger === 'wallet') store.wallet += kind.sign * adjustment.amount
    else if (adjustment.kind === 'debt_payment') this.netting(store, adjustment.amount, true)
    else store.commissionDebt += kind.sign * adjustment.amount
    adjustment.status = 'applied'
  }

  private adjustment(adjustment: FakeAdjustment, actor: { id: number }, can: (permission: string) => boolean) {
    const store = this.stores.find((row) => row.id === adjustment.storeId)!
    const kind = KINDS[adjustment.kind]!
    const pending = adjustment.status === 'pending'
    return {
      id: adjustment.id,
      uuid: `adj-${adjustment.id}`,
      store: { id: store.id, name: store.name },
      kind: adjustment.kind,
      kind_label: kind.label,
      ledger: kind.ledger,
      amount: money(adjustment.amount),
      signed_amount: money(kind.sign * adjustment.amount),
      reason: adjustment.reason,
      status: adjustment.status,
      status_label: pending ? 'Ikkinchi tasdiq kutilmoqda' : adjustment.status === 'applied' ? "Qo'llandi" : 'Rad etildi',
      needs_second_approval: adjustment.amount >= THRESHOLD,
      requested_by: adjustment.requestedBy,
      requested_at: adjustment.createdAt,
      approved_by: adjustment.approvedBy,
      approved_at: adjustment.approvedBy ? new Date().toISOString() : null,
      rejected_by: adjustment.rejectedBy,
      rejected_at: adjustment.rejectedBy ? new Date().toISOString() : null,
      decision_note: null,
      applied_at: adjustment.status === 'applied' ? new Date().toISOString() : null,
      document: adjustment.document ? { name: adjustment.document, mime: 'application/pdf', size: 1024 } : null,
      transactions: [],
      mine: adjustment.requestedBy.id === actor.id,
      can_approve: pending && adjustment.requestedBy.id !== actor.id && can('admin.finance.manage'),
      can_reject: pending && can('admin.finance.manage'),
    }
  }

  private adjustmentPage(status: string, actor: { id: number }, can: (permission: string) => boolean) {
    return {
      data: this.adjustments.filter((row) => status === 'all' || row.status === status).map((row) => this.adjustment(row, actor, can)),
      next_cursor: null,
      per_page: 30,
      counts: { pending: this.adjustments.filter((row) => row.status === 'pending').length },
      kinds: Object.entries(KINDS).map(([value, kind]) => ({ value, ...kind })),
      threshold: money(THRESHOLD),
    }
  }

  private decideAdjustment(id: number, decision: 'approve' | 'reject', actor: { id: number; name: string }, can: (permission: string) => boolean): Reply {
    const adjustment = this.adjustments.find((row) => row.id === id)
    if (!adjustment) return fail(404, 'Tuzatish topilmadi.', { reason: 'not_found' })
    if (adjustment.status !== 'pending') return fail(409, "Tuzatish allaqachon ko'rib chiqilgan.", { reason: 'adjustment_decided' })
    if (decision === 'reject') {
      Object.assign(adjustment, { status: 'rejected', rejectedBy: actor })
      return ok(this.adjustment(adjustment, actor, can))
    }
    if (adjustment.requestedBy.id === actor.id) return fail(403, "O'zingiz yaratgan tuzatishni tasdiqlay olmaysiz: buni boshqa xodim qiladi.", { reason: 'four_eyes' })
    adjustment.approvedBy = actor
    this.apply(adjustment)
    return ok(this.adjustment(adjustment, actor, can))
  }

  private summary() {
    const page = this.payoutPage('open')
    return {
      payouts: page.counts,
      adjustments: { pending: this.adjustments.filter((row) => row.status === 'pending').length },
      settlement_failures: { count: 0, gave_up: 0 },
      topups: { stuck: 0 },
      balances: { over_limit: this.stores.filter((store) => store.commissionDebt > store.creditLimit).length, wallet_total: money(this.stores.reduce((sum, store) => sum + store.wallet, 0)), debt_total: money(this.stores.reduce((sum, store) => sum + this.debtOf(store), 0)), subscription_debt_total: '0.00' },
      threshold: money(THRESHOLD),
    }
  }

  private balances(filter: string) {
    const rows = this.stores
      .map((store) => {
        const subscription = store.invoices.reduce((sum, invoice) => sum + invoice.open, 0)
        const over = store.commissionDebt > store.creditLimit
        return {
          store: { id: store.id, name: store.name, status: over ? 'payment_required' : 'active', status_label: over ? "To'lov kutilmoqda" : 'Faol' },
          wallet_balance: money(store.wallet),
          debt_balance: money(subscription + store.commissionDebt),
          subscription_debt: money(subscription),
          commission_debt: money(store.commissionDebt),
          credit_limit: money(-store.creditLimit),
          over_limit: over,
          is_order_locked: over,
          pending_payouts: money(this.payouts.filter((payout) => payout.storeId === store.id && payout.status === 'pending').reduce((sum, payout) => sum + payout.amount, 0)),
        }
      })
      .filter((row) => filter === 'all' || (filter === 'over_limit' && row.over_limit) || (filter === 'debt' && Number(row.debt_balance) > 0) || (filter === 'wallet' && Number(row.wallet_balance) > 0) || (filter === 'subscription_debt' && Number(row.subscription_debt) > 0))
    const summary = this.summary().balances
    return { data: rows, page: 1, per_page: 30, total: rows.length, totals: { over_limit: summary.over_limit, wallet_total: summary.wallet_total, debt_total: summary.debt_total, subscription_debt_total: '298000.00', commission_debt_total: '120000.00' } }
  }

  private report(period: string) {
    return {
      period,
      timezone: 'Asia/Tashkent',
      totals: { orders: 4, gmv: '260000.00', commission: '28500.00', commission_refunded: '5000.00', subscription: '149000.00', total: '177500.00' },
      channels: [
        { channel: 'rizqim_courier', label: 'RIZQIM kuryeri', orders: 2, gmv: '200000.00', commission: '25000.00', commission_refunded: '5000.00' },
        { channel: 'pickup', label: 'Olib ketish', orders: 1, gmv: '50000.00', commission: '2500.00', commission_refunded: '0.00' },
      ],
      subscriptions: { invoices: 1, invoiced: '149000.00', paid: '100000.00', outstanding: '49000.00' },
      stores: [
        { store: { id: 40, name: 'Somsa Markazi' }, orders: 0, gmv: '0.00', commission: '0.00', commission_by_channel: {}, subscription: '149000.00', total: '149000.00' },
        { store: { id: 12, name: 'Lazzat Oshxona' }, orders: 4, gmv: '260000.00', commission: '28500.00', commission_by_channel: { rizqim_courier: '25000.00', pickup: '2500.00' }, subscription: '0.00', total: '28500.00' },
      ],
    }
  }
}
