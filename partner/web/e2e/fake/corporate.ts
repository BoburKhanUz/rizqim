// Customer accounts of the fake backend with the P6-44 corporate account (docs/API.md "Korxona hisobi va talon"): a nasiya
// account and a company with two members, the members' add / change / bulk paste, the monthly statement and its Excel file.
// Enough for the panel's flows; the limits, the business day and the statement's sums are the backend's and its tests.
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

const COMPANY = 'cccccccc-0000-4000-8000-000000000001'
const NASIYA = 'cccccccc-0000-4000-8000-000000000002'

interface Member {
  uuid: string
  name: string
  phone: string | null
  code: string
  daily_limit: string | null
  meals_per_day: number | null
  is_active: boolean
  note: string | null
  created_at: string | null
  updated_at: string | null
}

const account = (over: Record<string, unknown>): Record<string, unknown> => ({
  phone: null,
  group_name: null,
  branch_id: null,
  credit_limit: null,
  available_credit: null,
  is_blocked: false,
  note: null,
  company_name: null,
  inn: null,
  contact_name: null,
  contact_phone: null,
  invoice_email: null,
  monthly_limit: null,
  charge_approval: true,
  talon_mode: 'none',
  members_count: 0,
  created_at: null,
  updated_at: null,
  ...over,
})

const money = (value: unknown): string | null => (value === null || value === undefined || value === '' ? null : Number(value).toFixed(2))

export class FakeCorporate {
  members: Member[] = [
    { uuid: 'mmmmmmmm-0000-4000-8000-000000000001', name: 'Aziz Rahimov', phone: '+998901112233', code: '5550001111', daily_limit: '60000.00', meals_per_day: null, is_active: true, note: null, created_at: null, updated_at: null },
    { uuid: 'mmmmmmmm-0000-4000-8000-000000000002', name: 'Bobur Aliyev', phone: null, code: 'NAV-0002', daily_limit: null, meals_per_day: 1, is_active: true, note: null, created_at: null, updated_at: null },
  ]

  private next = 3

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private company() {
    return account({
      uuid: COMPANY,
      name: 'Navoiy zavodi',
      balance: '196000.00',
      kind: 'corporate',
      kind_label: 'Korxona',
      company_name: '"Navoiy Metall" MChJ',
      inn: '301234567',
      contact_name: 'Dilshod Karimov',
      contact_phone: '+998901234567',
      invoice_email: 'buxgalter@navoiy.uz',
      monthly_limit: '5000000.00',
      charge_approval: false,
      talon_mode: 'daily_limit',
      members_count: this.members.filter((member) => member.is_active).length,
    })
  }

  private nasiya() {
    return account({ uuid: NASIYA, name: 'Karim aka', phone: '+998909998877', credit_limit: '1000000.00', balance: '350000.00', available_credit: '650000.00', kind: 'customer', kind_label: 'Nasiya' })
  }

  private statement(month: string) {
    const [aziz, bobur] = this.members
    return {
      account: { uuid: COMPANY, name: 'Navoiy zavodi', company_name: '"Navoiy Metall" MChJ', inn: '301234567', contact_name: 'Dilshod Karimov', contact_phone: '+998901234567', invoice_email: 'buxgalter@navoiy.uz', talon_mode: 'daily_limit' },
      month,
      from: `${month}-01`,
      to: `${month}-30`,
      days: [],
      rows: [
        { member: { uuid: aziz!.uuid, name: aziz!.name, code: aziz!.code, is_active: aziz!.is_active }, cells: [{ date: `${month}-02`, amount: '42000.00', meals: 2 }], amount: '42000.00', meals: 2 },
        { member: { uuid: bobur!.uuid, name: bobur!.name, code: bobur!.code, is_active: bobur!.is_active }, cells: [{ date: `${month}-03`, amount: '28000.00', meals: 1 }], amount: '28000.00', meals: 1 },
      ],
      day_totals: [
        { date: `${month}-02`, amount: '42000.00', meals: 2 },
        { date: `${month}-03`, amount: '28000.00', meals: 1 },
      ],
      total: '70000.00',
      meals: 3,
      repaid_total: '50000.00',
      monthly_limit: '5000000.00',
      balance: '196000.00',
    }
  }

  private add(body: Record<string, unknown>): Member {
    const member: Member = {
      uuid: `mmmmmmmm-0000-4000-8000-${String(this.next).padStart(12, '0')}`,
      name: String(body.name ?? ''),
      phone: (body.phone as string | null) ?? null,
      code: body.code ? String(body.code).toUpperCase() : String(1000000000 + this.next),
      daily_limit: money(body.daily_limit),
      meals_per_day: (body.meals_per_day as number | null) ?? null,
      is_active: true,
      note: (body.note as string | null) ?? null,
      created_at: null,
      updated_at: null,
    }
    this.next += 1
    this.members.push(member)
    return member
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (!req.path.startsWith('/v1/pos/customer-accounts')) return null
    const path = req.path.slice('/v1/pos/customer-accounts'.length)

    if (req.method === 'GET' && path === '') {
      const all = [this.nasiya(), this.company()].filter((row) => !req.query.kind || row.kind === req.query.kind)
      // Lists mask the INN
      const accounts = all.map((row) => (row.inn ? { ...row, inn: '30*****67' } : row))
      return ok({ accounts, meta: { current_page: 1, last_page: 1, per_page: 30, total: accounts.length } })
    }
    if (req.method === 'GET' && path === '/debtors') return ok({ total_debt: '546000.00', count: 2, accounts: [this.nasiya(), this.company()] })

    const match = /^\/([0-9a-f-]{36})(\/members(?:\/bulk|\/([0-9a-z-]{36}))?|\/corporate-statement(?:\/export)?)?$/.exec(path)
    if (!match) return null
    const uuid = match[1]
    if (uuid !== COMPANY && uuid !== NASIYA) return fail(404, 'Hisob topilmadi.')
    const tail = match[2] ?? ''

    if (req.method === 'GET' && tail === '') return ok({ account: uuid === COMPANY ? this.company() : this.nasiya(), entries: [] })
    if (uuid !== COMPANY) return fail(422, 'Bu nasiya hisobi.', undefined, { reason: 'account_not_corporate' })

    if (req.method === 'GET' && tail === '/members') {
      const q = (req.query.q ?? '').toLowerCase()
      const members = this.members.filter((member) => !q || member.name.toLowerCase().includes(q) || member.code.toLowerCase() === q)
      return ok({ members: [...members].sort((a, b) => Number(b.is_active) - Number(a.is_active)), active_count: this.members.filter((member) => member.is_active).length })
    }
    if (req.method === 'POST' && tail === '/members') return created(this.add(body ?? {}))
    if (req.method === 'POST' && tail === '/members/bulk') {
      const lines = String(body?.lines ?? '')
        .split(/\r?\n/)
        .map((line, index) => ({ line: index + 1, parts: line.split(';').map((part) => part.trim()) }))
        .filter((line) => line.parts.join('') !== '')
      const bad = lines.filter((line) => line.parts[0] === '').map((line) => ({ line: line.line, reason: 'name_required', message: 'Ism yozilmagan.' }))
      if (bad.length > 0) return fail(422, "Ro'yxatda xato qatorlar bor: hech kim qo'shilmadi.", undefined, { reason: 'bulk_invalid', lines: bad })
      const members = lines.map((line) => this.add({ name: line.parts[0], phone: line.parts[1] || null, daily_limit: body?.daily_limit ?? null, meals_per_day: body?.meals_per_day ?? null }))
      return created({ created: members.length, members })
    }
    if (req.method === 'PATCH' && match[3]) {
      const member = this.members.find((row) => row.uuid === match[3])
      if (!member) return fail(404, 'Xodim topilmadi.', undefined, { reason: 'member_not_found' })
      const patch = body ?? {}
      if ('name' in patch) member.name = String(patch.name)
      if ('is_active' in patch) member.is_active = Boolean(patch.is_active)
      if ('daily_limit' in patch) member.daily_limit = money(patch.daily_limit)
      if ('meals_per_day' in patch) member.meals_per_day = (patch.meals_per_day as number | null) ?? null
      if (patch.regenerate_code) member.code = String(2000000000 + this.next++)
      return ok(member)
    }
    if (req.method === 'GET' && tail === '/corporate-statement') return ok(this.statement(req.query.month ?? '2026-11'))
    if (req.method === 'GET' && tail === '/corporate-statement/export') {
      return {
        status: 200,
        body: null,
        raw: 'xlsx',
        headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': `attachment; filename=korxona-navoiy-zavodi-${req.query.month}.xlsx` },
      }
    }
    return null
  }
}
