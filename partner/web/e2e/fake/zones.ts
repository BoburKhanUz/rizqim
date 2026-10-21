// Delivery zones of the fake backend (GET/POST/PATCH/DELETE /owner/delivery-zones; docs/ZONES.md).
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeZone {
  id: number
  name: string
  branch_id: number | null
  branch: { id: number; name: string } | null
  polygon: { type: 'Polygon' | 'MultiPolygon'; coordinates: unknown }
  bbox: { min_lat: string; max_lat: string; min_lng: string; max_lng: string }
  area_km2: string
  delivery_price: string
  min_order_amount: string
  free_delivery_from: string | null
  eta_minutes: number | null
  priority: number
  active: boolean
  created_at: string | null
  updated_at: string | null
}

type Ring = number[][]

// The outer ring of a Polygon, or the first one of a MultiPolygon
function rings(polygon: FakeZone['polygon']): Ring[] {
  const coordinates = polygon.coordinates as number[][][] | number[][][][]
  return polygon.type === 'Polygon' ? (coordinates as number[][][]) : (coordinates as number[][][][]).map((part) => part[0])
}

// Shoelace area in km² (good enough for a fake: a flat patch of the earth around the ring)
function areaKm2(ring: Ring): number {
  const lat0 = (ring.reduce((sum, [, lat]) => sum + lat, 0) / ring.length) * (Math.PI / 180)
  const kx = 111.32 * Math.cos(lat0)
  const ky = 110.57
  let sum = 0
  for (let i = 0; i < ring.length - 1; i++) {
    sum += ring[i][0] * kx * (ring[i + 1][1] * ky) - ring[i + 1][0] * kx * (ring[i][1] * ky)
  }
  return Math.abs(sum) / 2
}

export class FakeZones {
  zones: FakeZone[] = []
  private counter = 0

  constructor(private readonly backend: FakeBackend) {}

  private validate(input: Record<string, unknown>): Record<string, string[]> | null {
    const polygon = input.polygon as FakeZone['polygon'] | undefined
    if (!polygon || (polygon.type !== 'Polygon' && polygon.type !== 'MultiPolygon')) return { polygon: ['Hudud Polygon yoki MultiPolygon bo\'lishi kerak.'] }
    for (const ring of rings(polygon)) {
      if (ring.length < 4) return { polygon: ['Hudud kamida 3 ta nuqtadan iborat bo\'lishi kerak.'] }
      const first = ring[0]
      const last = ring[ring.length - 1]
      if (first[0] !== last[0] || first[1] !== last[1]) return { polygon: ['Hudud chegarasi yopilmagan.'] }
    }
    if (typeof input.name !== 'string' || input.name.trim() === '') return { name: ['Nomini kiriting.'] }
    return null
  }

  private build(id: number, input: Record<string, unknown>, previous?: FakeZone): FakeZone {
    const polygon = (input.polygon as FakeZone['polygon'] | undefined) ?? previous!.polygon
    const all = rings(polygon).flat()
    const lats = all.map(([, lat]) => lat)
    const lngs = all.map(([lng]) => lng)
    const branchId = 'branch_id' in input ? (input.branch_id as number | null) : (previous?.branch_id ?? null)
    const branch = this.backend.team.branches.find((candidate) => candidate.id === branchId)
    const money = (value: unknown, fallback: string) => (value === undefined || value === null ? fallback : Number(value).toFixed(2))
    return {
      id,
      name: typeof input.name === 'string' ? input.name : previous!.name,
      branch_id: branchId,
      branch: branch ? { id: branch.id, name: branch.name } : null,
      polygon,
      bbox: { min_lat: String(Math.min(...lats)), max_lat: String(Math.max(...lats)), min_lng: String(Math.min(...lngs)), max_lng: String(Math.max(...lngs)) },
      area_km2: rings(polygon).reduce((sum, ring) => sum + areaKm2(ring), 0).toFixed(2),
      delivery_price: money(input.delivery_price, previous?.delivery_price ?? '0.00'),
      min_order_amount: money(input.min_order_amount, previous?.min_order_amount ?? '0.00'),
      free_delivery_from: 'free_delivery_from' in input ? (input.free_delivery_from === null ? null : money(input.free_delivery_from, '0.00')) : (previous?.free_delivery_from ?? null),
      eta_minutes: 'eta_minutes' in input ? (input.eta_minutes as number | null) : (previous?.eta_minutes ?? null),
      priority: typeof input.priority === 'number' ? input.priority : (previous?.priority ?? 0),
      active: typeof input.active === 'boolean' ? input.active : (previous?.active ?? true),
      created_at: previous?.created_at ?? new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    let match: RegExpExecArray | null
    if (req.method === 'GET' && req.path === '/v1/owner/delivery-zones') return ok(this.zones)
    if (req.method === 'POST' && req.path === '/v1/owner/delivery-zones') {
      const errors = this.validate(input)
      if (errors) return fail(422, 'The given data was invalid.', errors)
      const zone = this.build(++this.counter, input)
      this.zones.push(zone)
      return created(zone)
    }
    if ((match = /^\/v1\/owner\/delivery-zones\/(\d+)$/.exec(req.path))) {
      const index = this.zones.findIndex((zone) => zone.id === Number(match![1]))
      if (index < 0) return fail(404, 'Hudud topilmadi.')
      if (req.method === 'PATCH') {
        if ('polygon' in input) {
          const errors = this.validate({ name: 'x', ...input })
          if (errors) return fail(422, 'The given data was invalid.', errors)
        }
        this.zones[index] = this.build(this.zones[index].id, input, this.zones[index])
        return ok(this.zones[index])
      }
      if (req.method === 'DELETE') {
        this.zones.splice(index, 1)
        return ok(null)
      }
    }
    return null
  }
}
