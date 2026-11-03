// P6-34 of the fake backend (docs/API.md "Qadoq, 24/7 va olib ketish vaqti"): the branch's packaging rules (the same checks as
// the server: an item rule names one category or product, at least one order type, a 17-digit MXIK), the pickup-time rules
// (the whole view, only what is sent changes, the same limits) and the business days of a 24/7 branch (seeded by the test).
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export interface FakePackagingRule {
  id: number
  branch_id: number
  name: string
  basis: 'order' | 'item'
  basis_label: string
  category_id: number | null
  product_id: number | null
  quantity: number
  unit_price: string
  mxik: string | null
  package_code: string | null
  unit: string
  order_types: string[]
  is_active: boolean
  sort_order: number
}

const BASIS_LABELS = { order: 'Buyurtmaga', item: 'Taomga' }
const TYPES = ['takeaway', 'pickup', 'delivery']

export class FakeTakeaway {
  rules: FakePackagingRule[] = []
  private nextId = 1
  pickup = {
    branch_id: 1,
    configured: false,
    pickup_min_lead_minutes: 20,
    pickup_slot_minutes: 15,
    pickup_max_days_ahead: 1,
    pickup_prep_minutes: 15,
    round_the_clock: false,
    opening_hours: { open: '09:00', close: '22:00' },
    updated_at: null as string | null,
  }
  businessDays: Array<Record<string, unknown>> = []

  constructor(readonly backend: FakeBackend) {}

  private save(body: Record<string, unknown>, current: FakePackagingRule | null): Reply {
    const errors: Record<string, string[]> = {}
    const next = { ...(current ?? { id: this.nextId, branch_id: 1, quantity: 1, mxik: null, package_code: null, unit: 'dona', is_active: true, sort_order: this.rules.length + 1, category_id: null, product_id: null }), ...body } as FakePackagingRule
    if (typeof next.name !== 'string' || next.name.trim() === '') errors.name = ['Qadoq nomi majburiy.']
    if (!(next.basis in BASIS_LABELS)) errors.basis = ["Hisob turi noto'g'ri."]
    if (!Array.isArray(next.order_types) || next.order_types.length === 0 || next.order_types.some((type) => !TYPES.includes(type))) errors.order_types = ["Buyurtma turlari noto'g'ri."]
    if (next.mxik !== null && !/^\d{17}$/.test(String(next.mxik))) errors.mxik = ["MXIK kodi 17 ta raqamdan iborat bo'lishi kerak."]
    if (next.basis === 'item' && (next.category_id === null) === (next.product_id === null)) errors.category_id = ['Taomga qadoq uchun bitta kategoriya yoki bitta taomni tanlang.']
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
    if (next.basis === 'order') {
      next.category_id = null
      next.product_id = null
    }
    next.unit_price = Number(next.unit_price).toFixed(2)
    next.basis_label = BASIS_LABELS[next.basis]
    if (current === null) {
      this.nextId++
      this.rules.push(next)
      return created(next)
    }
    this.rules = this.rules.map((rule) => (rule.id === current.id ? next : rule))
    return ok(next)
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path === '/v1/owner/branches/1/packaging-rules') {
      if (req.method === 'GET') return ok(this.rules)
      if (req.method === 'POST') return this.save(body ?? {}, null)
    }
    const one = /^\/v1\/owner\/branches\/1\/packaging-rules\/(\d+)$/.exec(req.path)
    if (one) {
      const rule = this.rules.find((item) => item.id === Number(one[1]))
      if (!rule) return fail(404, 'Qadoq qoidasi topilmadi.')
      if (req.method === 'PUT') return this.save(body ?? {}, rule)
      if (req.method === 'DELETE') {
        this.rules = this.rules.filter((item) => item.id !== rule.id)
        return ok({ id: rule.id })
      }
    }
    if (req.path === '/v1/owner/branches/1/pickup-settings') {
      if (req.method === 'GET') return ok(this.pickup)
      if (req.method === 'PUT') {
        const limits: Record<string, [number, number]> = { pickup_min_lead_minutes: [0, 720], pickup_slot_minutes: [5, 120], pickup_max_days_ahead: [0, 7], pickup_prep_minutes: [0, 240] }
        const errors: Record<string, string[]> = {}
        for (const [key, [min, max]] of Object.entries(limits)) {
          const value = (body ?? {})[key]
          if (value !== undefined && (typeof value !== 'number' || !Number.isInteger(value) || value < min || value > max)) errors[key] = [`${key} ${min} dan ${max} gacha bo'lishi kerak.`]
        }
        if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
        this.pickup = { ...this.pickup, ...(body ?? {}), configured: true, updated_at: '2026-11-05T10:00:00Z' }
        return ok(this.pickup)
      }
    }
    // The 24/7 tab reads the day start from the branch's POS settings (only that part is answered here)
    if (req.method === 'GET' && req.path === '/v1/owner/branches/1/pos-settings') {
      return ok({ branch_id: 1, timezone: 'Asia/Tashkent', business_day_starts_at: '05:00', pos_settings: {} })
    }
    if (req.method === 'GET' && req.path === '/v1/owner/branches/1/business-days') {
      return ok({
        branch_id: 1,
        business_day: { business_date: '2026-10-23', starts_at: '05:00', started_at: '2026-10-23T00:00:00.000000Z', next_starts_at: '2026-10-24T00:00:00.000000Z', round_the_clock: true },
        days: this.businessDays,
      })
    }
    return null
  }
}
