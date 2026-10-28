// The fake of Integratsiyalar (P4-31): /admin/integrations/connections, /events, /stuck-orders, shaped like the controllers'
// answers (App\Http\Controllers\Api\V1\Admin\AdminIntegration*). Empty by default, as before phase 5; a test calls seed() for a
// connection, its journal and a stuck order. Reading needs integrations.view; pausing and resuming integrations.manage, a step-up
// and a reason.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

export const CONNECTION_UUID = 'c0000000-0000-4000-8000-000000000001'
export const EVENT_UUID = 'e0000000-0000-4000-8000-000000000001'

interface Connection {
  uuid: string
  store: { id: number; name: string }
  branch: { id: number; name: string } | null
  provider: string
  provider_label: string
  direction: string
  direction_label: string
  mode: string
  mode_label: string
  status: 'configuring' | 'active' | 'paused' | 'disconnected'
  status_before_pause: Connection['status'] | null
  health: { score: number | null; verdict: string; last_success_at: string | null; last_webhook_at: string | null; last_error_at: string | null; last_error: string | null; errors_today: number; checked_at: string | null }
  paused: { at: string; by: { id: number; name: string }; reason: string } | null
  created_at: string
  credentials: Array<{ key: string; configured: boolean; last4: string | null }>
  settings: Record<string, unknown>
}

// The list row of an event: the detail without its bodies and attempts' details
const DETAIL_ONLY = ['request_body', 'response_body', 'idempotency_key', 'signature_valid', 'last_attempt_at']
const listRow = (event: Record<string, unknown>) => Object.fromEntries(Object.entries(event).filter(([key]) => !DETAIL_ONLY.includes(key)))

const STATUS_LABEL = { configuring: 'Sozlanmoqda', active: 'Faol', paused: "To'xtatilgan", disconnected: 'Uzilgan' } as const

export class FakeIntegrations {
  connections: Connection[] = []
  events: Array<Record<string, unknown>> = []
  stuck: Array<Record<string, unknown>> = []

  /** One iiko connection of Lazzat Oshxona with two journal rows and one stuck order */
  seed(): void {
    this.connections = [
      {
        uuid: CONNECTION_UUID,
        store: { id: 12, name: 'Lazzat Oshxona' },
        branch: null,
        provider: 'iiko',
        provider_label: 'iiko',
        direction: 'rizqim_to_pos',
        direction_label: "RIZQIM → joyning POS'i",
        mode: 'sandbox',
        mode_label: 'Sinov (sandbox)',
        status: 'active',
        status_before_pause: null,
        health: { score: 62, verdict: 'attention', last_success_at: minutesAgo(3), last_webhook_at: minutesAgo(9), last_error_at: minutesAgo(5), last_error: 'Timeout', errors_today: 3, checked_at: minutesAgo(1) },
        paused: null,
        created_at: minutesAgo(60 * 24 * 3),
        credentials: [{ key: 'api_login', configured: true, last4: '4321' }],
        settings: { terminal_id: 'T-9', webhook_secret: '[redacted]' },
      },
    ]
    const connection = { uuid: CONNECTION_UUID, provider: 'iiko', provider_label: 'iiko', store: { id: 12, name: 'Lazzat Oshxona' } }
    this.events = [
      { uuid: EVENT_UUID, connection, direction: 'outbound', direction_label: 'Chiquvchi', type: 'order.push', status: 'failed', status_label: 'Xato', http_status: 502, attempts: 3, next_attempt_at: minutesAgo(-4), error: 'Timeout for +99890*****33', subject: { type: 'order', id: 5 }, duration_ms: 900, created_at: minutesAgo(5), idempotency_key: 'ord-5', signature_valid: null, last_attempt_at: minutesAgo(1), request_body: { order: 'AB12CD', customer: { phone: '+99890*****33' }, access_token: '[redacted]' }, response_body: { error: 'Bad gateway' } },
      { uuid: 'e0000000-0000-4000-8000-000000000002', connection, direction: 'inbound', direction_label: 'Kiruvchi', type: 'webhook.status', status: 'acknowledged', status_label: 'Tasdiqlandi', http_status: 200, attempts: 1, next_attempt_at: null, error: null, subject: null, duration_ms: 40, created_at: minutesAgo(9), idempotency_key: null, signature_valid: true, last_attempt_at: minutesAgo(9), request_body: { status: 'CookingStarted' }, response_body: null },
    ]
    this.stuck = [
      { event_uuid: EVENT_UUID, connection, order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', code: 'AB12CD', status: 'new' }, status: 'failed', status_label: 'Xato', attempts: 3, error: 'Timeout for +99890*****33', since: minutesAgo(5), waiting_minutes: 5 },
    ]
  }

  private row(connection: Connection) {
    return {
      uuid: connection.uuid, store: connection.store, branch: connection.branch, provider: connection.provider, provider_label: connection.provider_label,
      direction: connection.direction, direction_label: connection.direction_label, mode: connection.mode, mode_label: connection.mode_label,
      status: connection.status, status_label: STATUS_LABEL[connection.status], health: connection.health, paused: connection.paused, created_at: connection.created_at,
    }
  }

  private card(connection: Connection) {
    return {
      ...this.row(connection),
      credentials: connection.credentials,
      settings: connection.settings,
      recent_events: this.events.filter((e) => (e.connection as { uuid: string } | null)?.uuid === connection.uuid).map(listRow),
      menu_map: { total: 0, by_type: {} },
      stuck_orders: this.stuck.length,
    }
  }

  /** The reply for a path below /v1/admin, or null when it is not an Integratsiyalar path */
  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    if (!path.startsWith('/integrations/')) return null
    const forbidden = fail(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' })
    if (!can('admin.integrations.view')) return forbidden

    if (req.method === 'GET' && path === '/integrations/connections') {
      const status = req.query.status ?? ''
      const rows = this.connections.filter((c) => (status ? c.status === status : true)).map((c) => this.row(c))
      const count = (s: Connection['status']) => this.connections.filter((c) => c.status === s).length
      return ok({
        data: rows, current_page: 1, last_page: 1, per_page: 30, total: rows.length,
        counts: { total: this.connections.length, configuring: count('configuring'), active: count('active'), paused: count('paused'), disconnected: count('disconnected'), attention: this.connections.filter((c) => (c.health.score ?? 100) < 80).length },
        stuck_orders: this.stuck.length,
      })
    }
    if (req.method === 'GET' && path === '/integrations/events') {
      const rows = this.events.filter((e) => (req.query.status ? e.status === req.query.status : true) && (req.query.direction ? e.direction === req.query.direction : true) && (req.query.type ? e.type === req.query.type : true))
      return ok({ data: rows.map(listRow), next_cursor: null, per_page: 25 })
    }
    if (req.method === 'GET' && path === '/integrations/stuck-orders') return ok({ count: this.stuck.length, data: this.stuck, stuck_after_minutes: 2 })

    const event = /^\/integrations\/events\/([0-9a-f-]{36})$/.exec(path)
    if (req.method === 'GET' && event) {
      const row = this.events.find((e) => e.uuid === event[1])
      return row ? ok(row) : fail(404, 'Hodisa topilmadi.', { reason: 'not_found' })
    }

    const connection = /^\/integrations\/connections\/([0-9a-f-]{36})(?:\/(pause|resume))?$/.exec(path)
    if (connection) {
      const row = this.connections.find((c) => c.uuid === connection[1])
      if (!row) return fail(404, 'Ulanish topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && !connection[2]) return ok(this.card(row))
      if (req.method === 'POST' && connection[2]) {
        if (!can('admin.integrations.manage')) return forbidden
        const recent = needsRecent()
        if (recent) return recent
        if (typeof body.reason !== 'string' || body.reason.trim().length < 3) return validation('reason', 'Sabab majburiy.')
        if (connection[2] === 'pause' && row.status !== 'paused') {
          row.status_before_pause = row.status
          row.status = 'paused'
          row.paused = { at: new Date().toISOString(), by: actor, reason: body.reason.trim() }
        }
        if (connection[2] === 'resume' && row.status === 'paused') {
          row.status = row.status_before_pause ?? 'configuring'
          row.status_before_pause = null
          row.paused = null
        }
        return ok(this.card(row))
      }
    }

    return null
  }
}
