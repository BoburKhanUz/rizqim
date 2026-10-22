// Halls, places, the floor layout, room tariffs and the calendar of the fake backend (docs/API.md P3-12). Layout units are the
// panel's grid cells; the fake keeps what it is sent with two decimals, like the API.
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeTable {
  id: number
  branch_id: number
  hall_id: number
  name: string
  kind: 'table' | 'room' | 'tapchan'
  seats: number | null
  layout: { x: string | null; y: string | null; w: string | null; h: string | null }
  sort_order: number
  is_active: boolean
  min_spend: string | null
  min_spend_mode: 'per_table' | 'per_person'
}

export interface FakeHall {
  id: number
  branch_id: number
  name: string
  sort_order: number
  is_active: boolean
  service_fee: { service_charge_percent: string; place_fee_amount: string; place_fee_mode: string } | null
}

export interface FakeTariff {
  id: number
  branch_id: number
  dining_table_id: number | null
  dining_table_name: string | null
  hall_id: number | null
  table_kind: string | null
  day_type: 'weekday' | 'weekend' | 'holiday'
  price: string
  valid_from: string | null
  valid_to: string | null
}

const decimal = (value: unknown): string | null => (value === null || value === undefined ? null : Number(value).toFixed(2))

export class FakeFloor {
  halls: FakeHall[] = [{ id: 1, branch_id: 1, name: 'Asosiy zal', sort_order: 0, is_active: true, service_fee: null }]
  tables: FakeTable[] = [
    this.table({ id: 11, hall_id: 1, name: 'Xona 1', kind: 'room', seats: 12, layout: { x: 2, y: 1, w: 5, h: 4 }, min_spend: 600000 }),
    this.table({ id: 12, hall_id: 1, name: 'Xona 2', kind: 'room', seats: 8, layout: { x: 10, y: 1, w: 5, h: 4 } }),
    this.table({ id: 13, hall_id: 1, name: 'Stol 5', kind: 'table', seats: 4, layout: { x: 2, y: 8, w: 2, h: 2 } }),
  ]
  tariffs: FakeTariff[] = []
  calendar = { weekend_days: [6, 7], holidays: [] as string[] }
  private counter = 100

  constructor(private readonly backend: FakeBackend) {}

  private table(input: { id: number; hall_id: number; name: string; kind: FakeTable['kind']; seats: number | null; layout: { x: number; y: number; w: number; h: number }; min_spend?: number | null }): FakeTable {
    return {
      id: input.id,
      branch_id: 1,
      hall_id: input.hall_id,
      name: input.name,
      kind: input.kind,
      seats: input.seats,
      layout: { x: decimal(input.layout.x), y: decimal(input.layout.y), w: decimal(input.layout.w), h: decimal(input.layout.h) },
      sort_order: 0,
      is_active: true,
      min_spend: decimal(input.min_spend ?? null),
      min_spend_mode: 'per_table',
    }
  }

  private hallView(hall: FakeHall) {
    return { ...hall, tables: this.tables.filter((table) => table.hall_id === hall.id) }
  }

  private layout(input: Record<string, unknown>): Reply {
    const rows = (input.tables as Array<{ id: number; hall_id?: number; layout: Record<string, unknown> }> | undefined) ?? []
    if (rows.some((row) => !this.tables.some((table) => table.id === row.id && table.branch_id === Number(input.branch_id)))) return fail(422, 'The given data was invalid.', { 'tables.0.id': ['Stol bu filialda topilmadi.'] })
    for (const row of rows) {
      const table = this.tables.find((candidate) => candidate.id === row.id)!
      table.layout = { x: decimal(row.layout.x), y: decimal(row.layout.y), w: decimal(row.layout.w), h: decimal(row.layout.h) }
      if (row.hall_id) table.hall_id = row.hall_id
    }
    return ok(this.tables)
  }

  private saveTariff(input: Record<string, unknown>, existing: FakeTariff | null): Reply {
    const errors: Record<string, string[]> = {}
    if (!(Number(input.price) >= 0)) errors.price = ["Narx 0 dan kam bo'lmasin."]
    if (!existing && !input.dining_table_id && !input.table_kind) errors.table_kind = ['Xona yoki joy turini tanlang.']
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
    const table = this.tables.find((candidate) => candidate.id === Number(input.dining_table_id))
    const tariff: FakeTariff = {
      id: existing?.id ?? ++this.counter,
      branch_id: Number(input.branch_id ?? existing?.branch_id ?? 1),
      dining_table_id: 'dining_table_id' in input ? ((input.dining_table_id as number | null) ?? null) : (existing?.dining_table_id ?? null),
      dining_table_name: table?.name ?? null,
      hall_id: 'hall_id' in input ? ((input.hall_id as number | null) ?? null) : (existing?.hall_id ?? null),
      table_kind: 'table_kind' in input ? ((input.table_kind as string | null) ?? null) : (existing?.table_kind ?? null),
      day_type: (input.day_type as FakeTariff['day_type']) ?? existing?.day_type ?? 'weekday',
      price: decimal(input.price ?? existing?.price) ?? '0.00',
      valid_from: 'valid_from' in input ? ((input.valid_from as string | null) ?? null) : (existing?.valid_from ?? null),
      valid_to: 'valid_to' in input ? ((input.valid_to as string | null) ?? null) : (existing?.valid_to ?? null),
    }
    if (existing) this.tariffs[this.tariffs.indexOf(existing)] = tariff
    else this.tariffs.push(tariff)
    return existing ? ok(tariff) : created(tariff)
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    const path = req.path
    let match: RegExpExecArray | null

    if (req.method === 'GET' && path === '/v1/owner/halls') return ok(this.halls.filter((hall) => !req.query.branch_id || hall.branch_id === Number(req.query.branch_id)).map((hall) => this.hallView(hall)))
    if (req.method === 'PUT' && path === '/v1/owner/dining-tables/layout') return this.layout(input)
    if (req.method === 'GET' && path === '/v1/owner/room-tariffs') return ok({ calendar: { branch_id: 1, ...this.calendar }, tariffs: this.tariffs })
    if (req.method === 'POST' && path === '/v1/owner/room-tariffs') return this.saveTariff(input, null)
    if (req.method === 'PUT' && path === '/v1/owner/room-tariffs/calendar') {
      this.calendar = { weekend_days: input.weekend_days as number[], holidays: input.holidays as string[] }
      return ok({ branch_id: 1, ...this.calendar })
    }
    if ((match = /^\/v1\/owner\/room-tariffs\/(\d+)$/.exec(path))) {
      const tariff = this.tariffs.find((candidate) => candidate.id === Number(match![1]))
      if (!tariff) return fail(404, 'Narx topilmadi.')
      if (req.method === 'PATCH') return this.saveTariff(input, tariff)
      if (req.method === 'DELETE') {
        this.tariffs = this.tariffs.filter((candidate) => candidate !== tariff)
        return ok(null)
      }
    }
    if (req.method === 'GET' && path === '/v1/owner/auto-guest-items') return ok([])
    return null
  }
}
