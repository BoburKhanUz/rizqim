// Staff pay of the fake backend (docs/API.md P6-23 "Ish haqi qoidalari"): the wage rules of branch 1, its work sessions and
// pay statements. A statement's lines are worked out simply (2 shifts of the waiter at the role's shift rate, an advance of
// 50 000): enough for the panel's flows; the real arithmetic is the backend's (StaffPayCalculator) and its tests.
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

interface Rule {
  uuid: string
  branch_id: number
  user_id: number | null
  user_name: string | null
  role: string | null
  role_label: string | null
  effective_from: string
  components: Array<Record<string, unknown>>
  note: string | null
  updated_at: string | null
}

interface Line {
  uuid: string
  user_id: number
  user_name: string
  role: string
  role_label: string
  metrics: Record<string, unknown>
  components: Array<Record<string, unknown>>
  gross: string
  advances: string
  adjustment: string
  adjustment_reason: string | null
  payable: string
  paid: boolean
  paid_at: string | null
  paid_method: string | null
  paid_note: string | null
  cash_movement_id: number | null
}

interface Statement {
  uuid: string
  branch_id: number
  period: string
  period_label: string
  period_from: string
  period_to: string
  status: 'draft' | 'closed' | 'paid'
  status_label: string
  gross_total: string
  payable_total: string
  paid_total: string
  warnings: Array<Record<string, unknown>>
  computed_at: string | null
  closed_at: string | null
  version: number
  created_at: string | null
  lines: Line[]
}

const option = (value: string, label: string) => ({ value, label })
const LABELS: Record<string, string> = { shift_rate: 'Smena stavkasi', hourly_rate: 'Soatbay stavka', sales_percent: 'Sotuvdan foiz', service_charge_percent: 'Xizmat haqidan foiz', guarantee: 'Garant + foiz', event_rate: 'Tadbir stavkasi', tips: 'Choy puli' }
const STATUS: Record<Statement['status'], string> = { draft: 'Qoralama', closed: 'Yopilgan', paid: "To'langan" }
const ROLES = [option('manager', 'Menejer'), option('cashier', 'Kassir'), option('waiter', 'Ofitsiant'), option('cook', 'Oshpaz'), option('hostess', 'Hostes'), option('courier', 'Kuryer')]
const WAITER = { id: 31, name: 'Aziz Ofitsiant', role: 'waiter', role_label: 'Ofitsiant', branch_id: 1 }
const money = (n: number) => n.toFixed(2)

export class FakeStaffPay {
  period = 'month'
  rules: Rule[] = []
  statements: Statement[] = []
  openShifts = [{ uuid: 'aaaaaaaa-0000-4000-8000-000000000001', device_name: 'Kassa 1', business_date: '2026-11-30', opened_at: '2026-11-30T04:00:00+00:00', expected_cash: '1000000.00' }]
  private seq = 0

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private uuid(): string {
    return `bbbbbbbb-0000-4000-8000-${String(++this.seq).padStart(12, '0')}`
  }

  private summary(statement: Statement) {
    const { lines, ...rest } = statement
    return { ...rest, lines_count: lines.length }
  }

  private detail(statement: Statement) {
    return { ...this.summary(statement), lines: statement.lines, open_shifts: this.openShifts }
  }

  private totals(statement: Statement) {
    statement.gross_total = money(statement.lines.reduce((sum, line) => sum + Number(line.gross), 0))
    statement.payable_total = money(statement.lines.reduce((sum, line) => sum + Number(line.payable), 0))
    statement.paid_total = money(statement.lines.filter((line) => line.paid).reduce((sum, line) => sum + Number(line.payable), 0))
  }

  private compute(statement: Statement) {
    const rule = this.rules.find((candidate) => candidate.role === 'waiter' || candidate.user_id === WAITER.id)
    const rate = Number(rule?.components.find((component) => component.type === 'shift_rate')?.amount ?? 0)
    const previous = statement.lines[0]
    statement.lines = rule
      ? [
          {
            uuid: previous?.uuid ?? this.uuid(),
            user_id: WAITER.id,
            user_name: WAITER.name,
            role: 'waiter',
            role_label: 'Ofitsiant',
            metrics: { shifts: 2, minutes: 1080, bills: 6, sales: '2400000.00', service_charge: '240000.00', events: 0, tips: '0.00', uncounted_sessions: 0, rules: [rule.uuid], own_rule: rule.user_id !== null },
            components: [{ type: 'shift_rate', label: LABELS.shift_rate, rule_uuid: rule.uuid, days: 30, quantity: '2', rate: money(rate), base: null, amount: money(rate * 2), detail: `2 smena × ${rate} so'm` }],
            gross: money(rate * 2),
            advances: '50000.00',
            adjustment: previous?.adjustment ?? '0.00',
            adjustment_reason: previous?.adjustment_reason ?? null,
            payable: money(rate * 2 - 50000 + Number(previous?.adjustment ?? 0)),
            paid: false,
            paid_at: null,
            paid_method: null,
            paid_note: null,
            cash_movement_id: null,
          },
        ]
      : []
    statement.warnings = rule ? [] : [{ type: 'no_rule', user_id: WAITER.id, name: WAITER.name }]
    statement.version++
    this.totals(statement)
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (!req.path.startsWith('/v1/owner/staff-pay/')) return null
    const path = req.path.slice('/v1/owner/staff-pay'.length)

    if (req.method === 'GET' && path === '/rules') {
      return ok({
        branch: { id: 1, name: 'Chilonzor' },
        period: this.period,
        period_label: this.period === 'month' ? 'Oylik' : this.period,
        periods: [option('day', 'Kunlik'), option('week', 'Haftalik'), option('half_month', 'Yarim oylik'), option('month', 'Oylik')],
        component_types: Object.entries(LABELS).map(([value, label]) => option(value, label)),
        guarantee_modes: [option('max', 'Garant yoki foiz (qaysi katta)'), option('plus', 'Garant + foiz')],
        guarantee_per: [option('shift', 'Har smena uchun'), option('period', 'Butun davr uchun')],
        percent_bases: [option('sales', 'Sotuvdan'), option('service_charge', 'Xizmat haqidan')],
        event_types: [option('ziyofat', 'Ziyofat'), option('tugilgan_kun', "Tug'ilgan kun"), option('other', 'Boshqa')],
        roles: ROLES,
        staff: [WAITER],
        rules: this.rules,
      })
    }
    if (req.method === 'PUT' && path === '/settings') {
      this.period = String(body?.period ?? 'month')
      return ok({ period: this.period, period_label: this.period })
    }
    if (req.method === 'POST' && path === '/rules') {
      const role = typeof body?.role === 'string' ? body.role : null
      const rule: Rule = {
        uuid: this.uuid(),
        branch_id: 1,
        user_id: typeof body?.user_id === 'number' ? body.user_id : null,
        user_name: typeof body?.user_id === 'number' ? WAITER.name : null,
        role,
        role_label: ROLES.find((candidate) => candidate.value === role)?.label ?? null,
        effective_from: String(body?.effective_from ?? ''),
        components: ((body?.components as Array<Record<string, unknown>>) ?? []).map((component) => ({ ...component, label: LABELS[String(component.type)] ?? '' })),
        note: (body?.note as string | null) ?? null,
        updated_at: null,
      }
      this.rules.push(rule)
      return created(rule)
    }
    const ruleMatch = /^\/rules\/([\w-]+)$/.exec(path)
    if (ruleMatch) {
      const rule = this.rules.find((candidate) => candidate.uuid === ruleMatch[1])
      if (!rule) return fail(404, 'Qoida topilmadi.')
      if (req.method === 'DELETE') {
        this.rules = this.rules.filter((candidate) => candidate !== rule)
        return ok(null)
      }
      if (req.method === 'PUT') {
        if (body?.components) rule.components = (body.components as Array<Record<string, unknown>>).map((component) => ({ ...component, label: LABELS[String(component.type)] ?? '' }))
        if (typeof body?.effective_from === 'string') rule.effective_from = body.effective_from
        return ok(rule)
      }
    }

    if (req.method === 'GET' && path === '/work-sessions') return ok([])

    if (req.method === 'GET' && path === '/statements') return ok(this.statements.map((statement) => this.summary(statement)))
    if (req.method === 'POST' && path === '/statements') {
      const month = String(body?.date ?? '2026-11-15').slice(0, 7)
      const existing = this.statements.find((statement) => statement.period_from.startsWith(month))
      if (existing) return fail(409, 'Bu davr uchun hisob-kitob allaqachon bor.', undefined, { reason: 'statement_exists', statement_uuid: existing.uuid })
      const statement: Statement = {
        uuid: this.uuid(),
        branch_id: 1,
        period: 'month',
        period_label: 'Oylik',
        period_from: `${month}-01`,
        period_to: `${month}-30`,
        status: 'draft',
        status_label: STATUS.draft,
        gross_total: '0.00',
        payable_total: '0.00',
        paid_total: '0.00',
        warnings: [],
        computed_at: null,
        closed_at: null,
        version: 1,
        created_at: null,
        lines: [],
      }
      this.compute(statement)
      this.statements.unshift(statement)
      return created(this.detail(statement))
    }
    const statementMatch = /^\/statements\/([\w-]+)(\/(recompute|close|reopen)|\/lines\/([\w-]+)(\/pay)?)?$/.exec(path)
    if (statementMatch) {
      const statement = this.statements.find((candidate) => candidate.uuid === statementMatch[1])
      if (!statement) return fail(404, 'Hisob-kitob topilmadi.')
      const action = statementMatch[3]
      const lineUuid = statementMatch[4]
      if (req.method === 'GET' && !action && !lineUuid) return ok(this.detail(statement))
      if (req.method === 'DELETE' && !action && !lineUuid) {
        this.statements = this.statements.filter((candidate) => candidate !== statement)
        return ok(null)
      }
      if (req.method === 'POST' && action === 'recompute') {
        this.compute(statement)
        return ok(this.detail(statement))
      }
      if (req.method === 'POST' && action === 'close') {
        if (statement.status !== 'draft') return fail(409, 'Hisob-kitob yopilgan.', undefined, { reason: 'statement_not_draft' })
        this.compute(statement)
        statement.status = 'closed'
        statement.status_label = STATUS.closed
        return ok(this.detail(statement))
      }
      if (req.method === 'POST' && action === 'reopen') {
        statement.status = 'draft'
        statement.status_label = STATUS.draft
        return ok(this.detail(statement))
      }
      const line = statement.lines.find((candidate) => candidate.uuid === lineUuid)
      if (lineUuid && !line) return fail(404, 'Qator topilmadi.')
      if (line && req.method === 'PUT') {
        line.adjustment = money(Number(body?.adjustment ?? 0))
        line.adjustment_reason = (body?.reason as string | null) ?? null
        line.payable = money(Number(line.gross) - Number(line.advances) + Number(line.adjustment))
        this.totals(statement)
        return ok(this.detail(statement))
      }
      if (line && req.method === 'POST' && statementMatch[5]) {
        if (statement.status === 'draft') return fail(409, "Avval hisob-kitobni yoping, keyin to'lang.", undefined, { reason: 'statement_not_closed' })
        line.paid = true
        line.paid_method = String(body?.method ?? 'other')
        line.paid_note = (body?.note as string | null) ?? null
        line.paid_at = '2026-11-30T15:00:00+00:00'
        this.totals(statement)
        if (statement.lines.every((candidate) => candidate.paid)) {
          statement.status = 'paid'
          statement.status_label = STATUS.paid
        }
        return ok(this.detail(statement))
      }
    }
    return null
  }
}
