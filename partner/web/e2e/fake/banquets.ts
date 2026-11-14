// The banquet calendar of the fake backend (docs/API.md P6-51/P6-52 "Tadbirlar"): two halls of the branch, one held
// event (the payment schedule's, e2e/fake/banquetpay.ts, so the event page shows it), the catalogue (a package, an
// extra, the halls' rent) and the owner's setup. A hold prices itself with the formula (package × max(guaranteed,
// minimum) + rent + extras) and a taken hall/slot is 409 slot_taken; enough for the panel's flows, the rest is the
// backend's and its tests.
import type { FakeBackend } from './backend'
import { BANQUET_EVENT } from './banquetpay'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

const SLOTS = { morning: { start: '06:00', end: '11:00' }, day: { start: '12:00', end: '16:00' }, evening: { start: '18:00', end: '23:00' } } as const
type Named = keyof typeof SLOTS
const HALLS = [
  { id: 41, name: 'Oq zal' },
  { id: 42, name: "Ko'k zal" },
]

interface Event {
  uuid: string
  event_date: string
  slot: Named
  hall_ids: number[]
  customer_name: string
  guests_guaranteed: number
  guests_minimum: number
  package_id: number | null
  extras: Array<{ extra_id: number; quantity: string }>
  frozen: boolean
  version: number
}

export class FakeBanquets {
  settings = { slots: SLOTS, hold_hours: 72, guest_warning_thresholds: [200, 250, 300], window_start: '06:00', window_end: '23:00', advance_mxik: null as string | null, advance_package_code: null, cancellation_mxik: null as string | null, cancellation_package_code: null, quote_currency: null, version: 1 }
  packages = [{ id: 3, uuid: 'pppppppp-0000-4000-8000-000000000003', name: 'Standart', per_person_price: '150000.00', minimum_guests: 200, included: ['Osh', 'Salatlar'], description: null, is_active: true, sort_order: 0, items: [] as unknown[] }]
  extras = [{ id: 7, uuid: 'xxxxxxxx-0000-4000-8000-000000000007', name: 'Tort', price: '800000.00', pricing: 'per_event', pricing_label: 'Tadbirga', is_active: true, sort_order: 0 }]
  rates: Record<number, Record<string, string | null>> = { 41: { morning: null, day: '3000000.00', evening: '5000000.00', custom: null }, 42: { morning: null, day: null, evening: '4000000.00', custom: null } }
  events: Event[] = [{ uuid: BANQUET_EVENT, event_date: '2027-01-20', slot: 'evening', hall_ids: [41], customer_name: 'Karimovlar oilasi', guests_guaranteed: 200, guests_minimum: 200, package_id: 3, extras: [], frozen: false, version: 1 }]

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private lines(event: Event) {
    const billed = Math.max(event.guests_guaranteed, event.guests_minimum)
    const pkg = this.packages.find((p) => p.id === event.package_id)
    const rows: Array<Record<string, unknown>> = []
    if (pkg) rows.push({ uuid: `${event.uuid}-p`, kind: 'package', kind_label: 'Paket', name: pkg.name, extra_id: null, hall_id: null, quantity: '1', persons: billed, per_person: true, unit_price: pkg.per_person_price, amount: (Number(pkg.per_person_price) * billed).toFixed(2), frozen: event.frozen, note: null })
    for (const hall of event.hall_ids) {
      const price = this.rates[hall]?.[event.slot] ?? '0.00'
      rows.push({ uuid: `${event.uuid}-h${hall}`, kind: 'hall_rent', kind_label: 'Zal ijarasi', name: `Zal ijarasi: ${HALLS.find((h) => h.id === hall)?.name}`, extra_id: null, hall_id: hall, quantity: '1', persons: null, per_person: false, unit_price: price, amount: Number(price).toFixed(2), frozen: event.frozen, note: null })
    }
    for (const line of event.extras) {
      const extra = this.extras.find((x) => x.id === line.extra_id)
      if (!extra) continue
      rows.push({ uuid: `${event.uuid}-x${extra.id}`, kind: 'extra', kind_label: "Qo'shimcha", name: extra.name, extra_id: extra.id, hall_id: null, quantity: line.quantity, persons: null, per_person: false, unit_price: extra.price, amount: (Number(extra.price) * Number(line.quantity)).toFixed(2), frozen: event.frozen, note: null })
    }
    return rows
  }

  private view(event: Event, full = true) {
    const lines = this.lines(event)
    const sum = (kind: string) => lines.filter((line) => line.kind === kind).reduce((total, line) => total + Number(line.amount), 0)
    const total = (sum('package') + sum('hall_rent') + sum('extra')).toFixed(2)
    const summary = {
      uuid: event.uuid,
      branch_id: 1,
      event_date: event.event_date,
      slot: event.slot,
      slot_label: { morning: 'Ertalab', day: 'Kunduzi', evening: 'Kechqurun' }[event.slot],
      start_time: SLOTS[event.slot].start,
      end_time: SLOTS[event.slot].end,
      starts_at: `${event.event_date}T${SLOTS[event.slot].start}:00+05:00`,
      ends_at: `${event.event_date}T${SLOTS[event.slot].end}:00+05:00`,
      hall_ids: event.hall_ids,
      halls: HALLS.filter((hall) => event.hall_ids.includes(hall.id)),
      event_type: 'toy',
      event_type_label: "To'y",
      status: 'hold',
      status_label: 'Vaqtincha band',
      title: null,
      customer_name: event.customer_name,
      customer_phone: '+998901234567',
      guests_guaranteed: event.guests_guaranteed,
      guests_minimum: event.guests_minimum,
      guests_actual: null,
      guests_billed: Math.max(event.guests_guaranteed, event.guests_minimum),
      package_id: event.package_id,
      package_name: this.packages.find((p) => p.id === event.package_id)?.name ?? null,
      total,
      paid_total: '0.00',
      outstanding: total,
      hold_expires_at: '2026-11-19T10:00:00+05:00',
      frozen: event.frozen,
      contract_no: null,
      version: event.version,
    }
    if (!full) return summary
    return {
      ...summary,
      package_snapshot: null,
      package_total: sum('package').toFixed(2),
      hall_total: sum('hall_rent').toFixed(2),
      extras_total: sum('extra').toFixed(2),
      orders_total: '0.00',
      quote: null,
      frozen_at: event.frozen ? '2026-11-16T12:00:00+05:00' : null,
      note: null,
      order_uuid: null,
      cancel_reason: null,
      confirmed_at: null,
      cancelled_at: null,
      released_at: null,
      lines,
      payments: [],
      warnings: summary.guests_billed > 250 ? [{ code: 'guests_over_threshold', message: `Mehmonlar soni ${summary.guests_billed}: 250 dan ko'p. Zal sig'imi va xodimlarni tekshiring.` }] : [],
    }
  }

  private taken(date: string, slot: Named, halls: number[], except?: string) {
    return this.events.find((event) => event.uuid !== except && event.event_date === date && event.slot === slot && event.hall_ids.some((id) => halls.includes(id)))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const OWNER = '/v1/owner/pos/banquets'
    if (req.path === `${OWNER}/settings`) {
      if (req.method === 'GET') return ok(this.settings)
      if (req.method === 'PUT') {
        this.settings = { ...this.settings, ...(body as object), version: this.settings.version + 1 }
        return ok(this.settings)
      }
    }
    if (req.path === `${OWNER}/packages`) {
      if (req.method === 'GET') return ok({ packages: this.packages })
      if (req.method === 'POST') {
        const pkg = { id: this.packages.length + 10, uuid: `pppppppp-0000-4000-8000-0000000000${this.packages.length + 10}`, description: null, sort_order: 0, is_active: true, items: [], included: [], minimum_guests: 0, per_person_price: '0.00', name: '', ...(body as object) }
        pkg.per_person_price = Number(pkg.per_person_price).toFixed(2)
        this.packages.push(pkg)
        return created(pkg)
      }
    }
    if (req.path === `${OWNER}/extras` && req.method === 'GET') return ok({ extras: this.extras })
    if (req.path === `${OWNER}/halls` && req.method === 'GET') return ok({ halls: HALLS.map((hall) => ({ hall_id: hall.id, hall_name: hall.name, branch_id: 1, is_active: true, rates: this.rates[hall.id] })) })
    const rates = new RegExp(`^${OWNER}/halls/(\\d+)/rates$`).exec(req.path)
    if (rates && req.method === 'PUT') {
      const id = Number(rates[1])
      this.rates[id] = { ...this.rates[id], ...((body?.rates as object) ?? {}) }
      return ok({ hall_id: id, hall_name: HALLS.find((hall) => hall.id === id)?.name, branch_id: 1, is_active: true, rates: this.rates[id] })
    }

    if (!req.path.startsWith('/v1/pos/banquets')) return null
    const path = req.path.slice('/v1/pos/banquets'.length)
    if (req.method === 'GET' && path === '/calendar') {
      const from = req.query.from ?? '2027-01-01'
      const to = req.query.to ?? '2027-01-31'
      const inRange = this.events.filter((event) => event.event_date >= from && event.event_date <= to)
      return ok({ branch_id: 1, from, to, timezone: 'Asia/Tashkent', slots: SLOTS, halls: HALLS.map((hall) => ({ ...hall, is_active: true, events: inRange.filter((event) => event.hall_ids.includes(hall.id)).map((event) => this.view(event, false)) })) })
    }
    if (req.method === 'GET' && path === '/catalog') {
      return ok({ packages: this.packages, extras: this.extras, halls: HALLS.map((hall) => ({ hall_id: hall.id, hall_name: hall.name, branch_id: 1, is_active: true, rates: this.rates[hall.id] })), settings: this.settings, event_types: [] })
    }
    if (req.method === 'POST' && path === '') {
      const slot = String(body?.slot) as Named
      const halls = (body?.hall_ids as number[]) ?? []
      if (this.taken(String(body?.event_date), slot, halls)) return fail(409, 'Bu vaqtda zal band: Oq zal. Boshqa sana, vaqt yoki zalni tanlang.', undefined, { reason: 'slot_taken', conflicts: [] })
      const event: Event = {
        uuid: `eeeeeeee-0000-4000-8000-00000000010${this.events.length}`,
        event_date: String(body?.event_date),
        slot,
        hall_ids: halls,
        customer_name: String(body?.customer_name),
        guests_guaranteed: Number(body?.guests_guaranteed ?? 0),
        guests_minimum: Number(body?.guests_minimum ?? this.packages.find((p) => p.id === body?.banquet_package_id)?.minimum_guests ?? 0),
        package_id: (body?.banquet_package_id as number | null) ?? null,
        extras: ((body?.extras as Array<{ extra_id?: number; quantity?: string }>) ?? []).filter((x) => x.extra_id).map((x) => ({ extra_id: Number(x.extra_id), quantity: x.quantity ?? '1' })),
        frozen: false,
        version: 1,
      }
      this.events.push(event)
      return created(this.view(event))
    }
    const match = /^\/([0-9a-f-]{36})(\/freeze|\/unfreeze)?$/.exec(path)
    const event = match ? this.events.find((row) => row.uuid === match[1]) : undefined
    if (!match || !event) return null
    if (req.method === 'GET' && !match[2]) return ok(this.view(event))
    if (body?.version !== undefined && Number(body.version) !== event.version) return fail(409, "Tadbir boshqa joyda o'zgartirilgan.", undefined, { reason: 'stale_version', version: event.version })
    if (req.method === 'PATCH' && !match[2]) {
      if (body?.guests_guaranteed !== undefined) event.guests_guaranteed = Number(body.guests_guaranteed)
      if (body?.guests_minimum !== undefined) event.guests_minimum = Number(body.guests_minimum)
      if (body?.customer_name !== undefined) event.customer_name = String(body.customer_name)
      event.version++
      return ok(this.view(event))
    }
    if (req.method === 'POST' && match[2] === '/freeze') {
      event.frozen = true
      event.version++
      return ok(this.view(event))
    }
    if (req.method === 'POST' && match[2] === '/unfreeze') {
      if (String(body?.reason ?? '').trim().length < 3) return fail(422, 'Sababni yozing.', { reason: ['Sababni yozing.'] })
      event.frozen = false
      event.version++
      return ok(this.view(event))
    }
    return null
  }
}
