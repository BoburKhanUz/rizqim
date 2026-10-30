// The table booking rules of a branch and the owner's view of the hostess' waitlist of the fake backend (docs/API.md "Hostes,
// ofitsiantni almashtirish va stol broni", P6-24/P6-26). The rules answer like the server: the whole view with every key, only
// what is sent changes, the same limits; the waitlist is seeded by the test and read masked.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeRules {
  branch_id: number
  configured: boolean
  table_slot_minutes: number
  room_slot_minutes: number | null
  tapchan_slot_minutes: number | null
  table_min_advance_minutes: number
  room_min_advance_minutes: number
  tapchan_min_advance_minutes: number
  max_advance_days: number | null
  deposit_mode: string
  deposit_mode_label: string
  deposit_amount: string
  deposit_from_guests: number
  deposit_required_to_confirm: boolean
  updated_at: string | null
}

export interface FakeWaitlistEntry {
  uuid: string
  business_date: string
  guest_name: string
  guest_phone_masked: string | null
  party_size: number
  quoted_wait_minutes: number | null
  status: 'waiting' | 'seated' | 'canceled' | 'no_show'
  waited_minutes: number
  table_name: string | null
  created_at: string
}

const MODE_LABELS: Record<string, string> = { off: "Bo'nak olinmaydi", flat: "Bron uchun bir xil bo'nak", per_guest: "Har bir mehmon uchun bo'nak" }
const STATUS_LABELS: Record<string, string> = { waiting: 'Kutmoqda', seated: 'Joylashtirildi', canceled: 'Bekor qilindi', no_show: 'Kelmadi' }

export class FakeHostess {
  rules: FakeRules = {
    branch_id: 1,
    configured: false,
    table_slot_minutes: 120,
    room_slot_minutes: null,
    tapchan_slot_minutes: null,
    table_min_advance_minutes: 0,
    room_min_advance_minutes: 0,
    tapchan_min_advance_minutes: 0,
    max_advance_days: null,
    deposit_mode: 'off',
    deposit_mode_label: MODE_LABELS.off,
    deposit_amount: '0.00',
    deposit_from_guests: 1,
    deposit_required_to_confirm: false,
    updated_at: null,
  }
  waitlist: FakeWaitlistEntry[] = []

  constructor(readonly backend: FakeBackend) {}

  private update(body: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    const whole = (key: string, min: number, max: number, nullable: boolean) => {
      if (!(key in body)) return
      const value = body[key]
      if (value === null && nullable) return
      if (typeof value !== 'number' || !Number.isInteger(value) || value < min || value > max) errors[key] = [`${key} ${min} dan ${max} gacha bo'lishi kerak.`]
    }
    whole('table_slot_minutes', 30, 480, false)
    whole('room_slot_minutes', 30, 1440, true)
    whole('tapchan_slot_minutes', 30, 1440, true)
    whole('table_min_advance_minutes', 0, 1440, false)
    whole('room_min_advance_minutes', 0, 1440, false)
    whole('tapchan_min_advance_minutes', 0, 1440, false)
    whole('max_advance_days', 1, 365, true)
    whole('deposit_from_guests', 1, 1000, false)
    if ('deposit_mode' in body && !(String(body.deposit_mode) in MODE_LABELS)) errors.deposit_mode = ["Bo'nak turi noto'g'ri."]
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)

    const next = { ...this.rules, ...body, configured: true, updated_at: '2026-11-05T10:00:00Z' } as FakeRules
    if ('deposit_amount' in body) next.deposit_amount = Number(body.deposit_amount).toFixed(2)
    next.deposit_mode_label = MODE_LABELS[next.deposit_mode] ?? next.deposit_mode
    this.rules = next
    return ok(this.rules)
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path === '/v1/owner/branches/1/booking-rules') {
      if (req.method === 'GET') return ok(this.rules)
      if (req.method === 'PUT') return this.update(body ?? {})
    }
    if (req.method === 'GET' && req.path === '/v1/owner/waitlist') {
      const status = req.query.status
      const rows = this.waitlist.filter((entry) => !status || entry.status === status)
      const seated = this.waitlist.filter((entry) => entry.status === 'seated')
      const quoted = this.waitlist.filter((entry) => entry.quoted_wait_minutes !== null)
      const average = (values: number[]) => (values.length === 0 ? null : Math.round(values.reduce((sum, value) => sum + value, 0) / values.length))
      return ok({
        branch_id: 1,
        from: req.query.from ?? '2026-11-05',
        to: req.query.to ?? '2026-11-05',
        entries: rows.map((entry) => ({
          ...entry,
          branch_id: 1,
          guest_phone: null,
          note: null,
          status_label: STATUS_LABELS[entry.status],
          position: null,
          dining_table_id: null,
          order_uuid: null,
          notified_at: null,
          seated_at: null,
          closed_at: null,
          version: 1,
        })),
        summary: {
          total: this.waitlist.length,
          waiting: this.waitlist.filter((entry) => entry.status === 'waiting').length,
          seated: seated.length,
          canceled: this.waitlist.filter((entry) => entry.status === 'canceled').length,
          no_show: this.waitlist.filter((entry) => entry.status === 'no_show').length,
          party_size_total: this.waitlist.reduce((sum, entry) => sum + entry.party_size, 0),
          avg_quoted_wait_minutes: average(quoted.map((entry) => entry.quoted_wait_minutes as number)),
          avg_seated_wait_minutes: average(seated.map((entry) => entry.waited_minutes)),
          longest_waiting_minutes: null,
        },
      })
    }
    return null
  }
}
