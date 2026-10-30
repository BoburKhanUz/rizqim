// KDS v2 of the fake backend (docs/API.md "KDS v2", P6-33): the branch's kitchen stations with their kinds and time targets,
// the dishes' own targets and the prep time report. Answers like the server: a target 10–7200 seconds (422 otherwise), setting
// one needs the `kds_expo` module (403 module_disabled), clearing one never does; the report needs the `kds` module.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const KINDS: Array<{ value: string; label: string; target: number | null }> = [
  { value: 'osh', label: 'Osh va qozon', target: null },
  { value: 'mangal', label: 'Mangal', target: null },
  { value: 'salad_bar', label: 'Salat va bar', target: null },
  { value: 'tea', label: 'Choy', target: null },
  { value: 'kitchen', label: 'Oshxona', target: null },
  { value: 'other', label: 'Boshqa', target: null },
  { value: 'grill', label: 'Grill', target: 300 },
  { value: 'fryer', label: 'Fritur', target: 240 },
  { value: 'drinks', label: 'Ichimliklar', target: 90 },
  { value: 'barista', label: 'Barista', target: 180 },
  { value: 'expo', label: "Yig'ish", target: 120 },
]

export interface FakeStation {
  id: number
  branch_id: number
  name: string
  kind: string
  sort_order: number
  is_active: boolean
  show_on_kds: boolean
  target_seconds: number | null
}

const MIN = 10
const MAX = 7200

const stats = (over: Record<string, unknown> = {}) => ({ count: 0, median_seconds: null, p90_seconds: null, with_target: 0, over_target: 0, over_target_share: null, ...over })

export class FakeKds {
  stations: FakeStation[] = [
    { id: 1, branch_id: 1, name: 'Grill', kind: 'grill', sort_order: 1, is_active: true, show_on_kds: true, target_seconds: 300 },
    { id: 2, branch_id: 1, name: 'Ichimliklar', kind: 'drinks', sort_order: 2, is_active: true, show_on_kds: true, target_seconds: 90 },
  ]
  /** product id => its own target */
  productTargets = new Map<number, number>()
  private nextId = 10

  constructor(readonly backend: FakeBackend) {}

  private label(kind: string): string {
    return KINDS.find((entry) => entry.value === kind)?.label ?? kind
  }

  private present(station: FakeStation) {
    return { ...station, kind_label: this.label(station.kind) }
  }

  private moduleRefusal(): Reply {
    return fail(403, "Yig'ish ekrani va sex vaqtlari bu filial formatida o'chirilgan.", undefined, { reason: 'module_disabled', module: 'kds_expo' })
  }

  private badTarget(value: unknown): boolean {
    return value !== null && (typeof value !== 'number' || !Number.isInteger(value) || value < MIN || value > MAX)
  }

  private targetsPage(branchId: number) {
    const products = this.backend.menu.products as Array<{ id: number; name: string; category_id: number }>
    return {
      branch_id: branchId,
      enabled: this.backend.format.has('kds_expo'),
      limits: { min: MIN, max: MAX },
      stations: this.stations
        .filter((station) => station.branch_id === branchId)
        .map((station) => ({ id: station.id, name: station.name, kind: station.kind, kind_label: this.label(station.kind), is_active: station.is_active, target_seconds: station.target_seconds, default_target_seconds: KINDS.find((entry) => entry.value === station.kind)?.target ?? null })),
      products: [...this.productTargets.entries()]
        .map(([id, target]) => {
          const product = products.find((candidate) => candidate.id === id)
          return { product_id: id, name: product?.name ?? `#${id}`, category_id: product?.category_id ?? 0, target_seconds: target }
        })
        .sort((a, b) => a.name.localeCompare(b.name)),
    }
  }

  private save(station: FakeStation | null, body: Record<string, unknown>): Reply {
    if ('target_seconds' in body) {
      if (this.badTarget(body.target_seconds)) return fail(422, 'The given data was invalid.', { target_seconds: [`Vaqt maqsadi kamida ${MIN} soniya bo'lishi kerak.`] })
      if (body.target_seconds !== null && !this.backend.format.has('kds_expo')) return this.moduleRefusal()
    }
    if (typeof body.kind === 'string' && !KINDS.some((entry) => entry.value === body.kind)) return fail(422, 'The given data was invalid.', { kind: ["Sex turi noto'g'ri."] })
    const target =
      station ??
      ({ id: ++this.nextId, branch_id: Number(body.branch_id ?? 1), name: '', kind: 'kitchen', sort_order: this.stations.length + 1, is_active: true, show_on_kds: true, target_seconds: null } as FakeStation)
    if (typeof body.name === 'string') target.name = body.name
    if (typeof body.kind === 'string') target.kind = body.kind
    if (typeof body.sort_order === 'number') target.sort_order = body.sort_order
    if (typeof body.is_active === 'boolean') target.is_active = body.is_active
    if (typeof body.show_on_kds === 'boolean') target.show_on_kds = body.show_on_kds
    if ('target_seconds' in body) target.target_seconds = body.target_seconds as number | null
    if (!station) this.stations.push(target)
    return station ? ok(this.present(target)) : { status: 201, body: { success: true, message: "Sex qo'shildi.", data: this.present(target) } }
  }

  private report(query: Record<string, string>): Reply {
    if (!this.backend.format.has('kds')) return fail(403, "Oshxona ekrani (KDS) bu filial formatida o'chirilgan.", undefined, { reason: 'module_disabled', module: 'kds' })
    const stationId = query.station_id ? Number(query.station_id) : null
    const grill = { station_id: 1, name: 'Grill', kind: 'grill', target_seconds: 300, ...stats({ count: 3, median_seconds: 240, p90_seconds: 336, with_target: 3, over_target: 1, over_target_share: '33.3' }) }
    const drinks = { station_id: 2, name: 'Ichimliklar', kind: 'drinks', target_seconds: 90, ...stats({ count: 3, median_seconds: 60, p90_seconds: 92, with_target: 3, over_target: 1, over_target_share: '33.3' }) }
    const stations = [grill, drinks].filter((row) => stationId === null || row.station_id === stationId)
    return ok({
      branch_id: Number(query.branch_id ?? 1),
      from: query.from,
      to: query.to ?? query.from,
      timezone: 'Asia/Tashkent',
      day_starts_at: '05:00',
      station_id: stationId,
      summary: stationId === null ? stats({ count: 6, median_seconds: 110, p90_seconds: 300, with_target: 6, over_target: 2, over_target_share: '33.3' }) : { ...grill, station_id: undefined },
      orders: stats({ count: 4, median_seconds: 210, p90_seconds: 440 }),
      stations,
      products: [{ product_id: 8, name: 'Burger', ...stats({ count: 3, median_seconds: 240, p90_seconds: 336, with_target: 3, over_target: 1, over_target_share: '33.3' }) }],
      hours: Array.from({ length: 24 }, (_, hour) => ({ hour, ...(hour === 13 ? stats({ count: 6, median_seconds: 110, p90_seconds: 300, with_target: 6, over_target: 2, over_target_share: '33.3' }) : stats()) })),
      expo: stationId === null ? { station_id: 3, name: "Yig'ish", target_seconds: 120, ...stats({ count: 2, median_seconds: 100, p90_seconds: 132, with_target: 2, over_target: 1, over_target_share: '50.0' }) } : null,
    })
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path
    if (req.method === 'GET' && path === '/v1/owner/kitchen-stations') {
      const branchId = req.query.branch_id ? Number(req.query.branch_id) : null
      return ok({
        kinds: KINDS.map((entry) => ({ value: entry.value, label: entry.label })),
        stations: this.stations.filter((station) => branchId === null || station.branch_id === branchId).map((station) => this.present(station)),
      })
    }
    if (req.method === 'POST' && path === '/v1/owner/kitchen-stations') return this.save(null, body ?? {})
    const station = /^\/v1\/owner\/kitchen-stations\/(\d+)$/.exec(path)
    if (station && req.method === 'PATCH') {
      const found = this.stations.find((candidate) => candidate.id === Number(station[1]))
      return found ? this.save(found, body ?? {}) : fail(404, 'Sex topilmadi.')
    }
    if (req.method === 'GET' && path === '/v1/owner/kds/targets') return ok(this.targetsPage(Number(req.query.branch_id ?? 1)))
    if (req.method === 'PUT' && path === '/v1/owner/kds/targets/products') {
      const items = (body?.items as Array<{ product_id: number; target_seconds: number | null }> | undefined) ?? []
      if (items.some((item) => item.target_seconds !== null) && !this.backend.format.has('kds_expo')) return this.moduleRefusal()
      if (items.some((item) => this.badTarget(item.target_seconds))) return fail(422, 'The given data was invalid.', { 'items.0.target_seconds': ['Vaqt maqsadi noto‘g‘ri.'] })
      for (const item of items) {
        if (item.target_seconds === null) this.productTargets.delete(item.product_id)
        else this.productTargets.set(item.product_id, item.target_seconds)
      }
      return ok(this.targetsPage(Number(body?.branch_id ?? 1)))
    }
    if (req.method === 'GET' && path === '/v1/owner/reports/kitchen-times') return this.report(req.query)
    return null
  }
}
