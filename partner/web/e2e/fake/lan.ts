// The branch's local bridge of the fake backend (docs/API.md "Lokal ko'prik (P6)"): the hub till, the port and the key
// version. Answers like the server: the whole view every time; the hub is the owner's choice, else the first cashier till
// (the fake has no printing till); a hub that is not one of the candidates (a waiter's phone) is refused; the port is
// 1024–65535; a rotation bumps `key_id`.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeLanTill {
  uuid: string
  name: string
  platform: string | null
  app: string | null
  last_seen_at: string | null
}

export class FakeLan {
  tills: FakeLanTill[] = [
    { uuid: 'till-1', name: 'Kassa 1', platform: 'windows', app: 'cashier', last_seen_at: '2026-11-20T08:00:00Z' },
    { uuid: 'till-2', name: 'Kassa 2', platform: 'android', app: null, last_seen_at: null },
  ]
  chosen: string | null = null
  port = 18480
  keyId = 1
  rotatedAt: string | null = null

  constructor(readonly backend: FakeBackend) {}

  private view(branchId: number) {
    const hub = this.tills.find((till) => till.uuid === this.chosen) ?? this.tills.find((till) => till.app === 'cashier') ?? this.tills[0] ?? null
    return {
      branch_id: branchId,
      key_id: this.keyId,
      rotated_at: this.rotatedAt,
      port: this.port,
      hub: hub ? { device: hub.uuid, name: hub.name, chosen: hub.uuid === this.chosen } : null,
      candidates: this.tills.map((till) => ({ ...till })),
    }
  }

  private update(body: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    const hub = body.hub_device_id
    if ('hub_device_id' in body && hub !== null && !this.tills.some((till) => till.uuid === hub)) errors.hub_device_id = ['Markaz kassa filialning faol kassasi emas.']
    if ('port' in body && !(Number.isInteger(body.port) && Number(body.port) >= 1024 && Number(body.port) <= 65535)) errors.port = ["Port 1024 dan 65535 gacha bo'lishi kerak."]
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
    if ('hub_device_id' in body) this.chosen = hub === null ? null : String(hub)
    if ('port' in body) this.port = Number(body.port)
    return ok(this.view(Number(body.branch_id ?? 1)))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.method === 'GET' && req.path === '/v1/owner/pos/lan') return ok(this.view(Number(req.query.branch_id ?? 1)))
    if (req.method === 'PUT' && req.path === '/v1/owner/pos/lan') return this.update(body ?? {})
    if (req.method === 'POST' && req.path === '/v1/owner/pos/lan/rotate') {
      this.keyId += 1
      this.rotatedAt = '2026-11-20T09:30:00Z'
      return ok(this.view(Number(body?.branch_id ?? 1)))
    }
    return null
  }
}
