// The orders side of the fake backend: the board's lists and detail (GET /staff/orders*), the role-checked status change,
// couriers and the dashboard's numbers. Shaped like docs/API.md 9.1.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

export type OrderStatus = 'new' | 'accepted' | 'preparing' | 'ready' | 'courier_assigned' | 'on_the_way' | 'delivered' | 'canceled' | 'rejected' | 'refunded'

const FINISHED: readonly OrderStatus[] = ['delivered', 'canceled', 'rejected', 'refunded']

export const STATUS_LABELS: Record<string, string> = {
  new: 'Yangi',
  accepted: 'Qabul qilindi',
  preparing: 'Tayyorlanmoqda',
  ready: 'Tayyor',
  courier_assigned: 'Kuryer kutilmoqda',
  on_the_way: "Yo'lda",
  delivered: 'Yetkazildi',
  canceled: 'Bekor qilindi',
  rejected: 'Rad etildi',
  refunded: 'Qaytarildi',
}

export interface FakeLine {
  id: number
  product_name: string
  variant_name: string | null
  quantity: number
  unit: 'piece' | 'kg' | 'l'
  unit_price: string
  total_price: string
  modifiers?: Array<{ name: string; group_name: string; quantity: number; price_delta: string }>
  kitchen_status: 'pending' | 'ready'
}

export interface FakeOrder {
  uuid: string
  short_code: string
  status: OrderStatus
  type: 'delivery' | 'pickup' | 'dine_in'
  channel: string
  branch_id: number
  payment_method: string
  payment_status: string
  total_price: string
  notes: string | null
  customer: { name: string; phone: string } | null
  delivery: { address: string; latitude: number | null; longitude: number | null; zone_name: string | null } | null
  courier: { id: number; name: string; phone: string | null } | null
  items: FakeLine[]
  created_at: string
  accepted_at: string | null
  cancel_reason: string | null
  history: Array<{ from_status: string | null; status: string; created_at: string }>
}

export interface FakeCourier {
  id: number
  name: string
  phone: string | null
  telegram_username: string | null
  branch_id: number
}

const iso = (minutesAgo = 0) => new Date(Date.now() - minutesAgo * 60_000).toISOString()

// What a status may move to (App\Enums\OrderStatus transitions as the staff endpoint exposes them)
const NEXT: Partial<Record<OrderStatus, readonly OrderStatus[]>> = {
  new: ['accepted', 'rejected'],
  accepted: ['preparing', 'canceled'],
  preparing: ['ready', 'canceled'],
  ready: ['courier_assigned', 'delivered', 'canceled'],
  courier_assigned: ['on_the_way', 'canceled'],
  on_the_way: ['delivered'],
}

const STAFF_REASONS = ['customer_request', 'no_show', 'out_of_stock', 'store_closed', 'courier_unavailable', 'other']

export class FakeOrders {
  orders: FakeOrder[] = []
  couriers: FakeCourier[] = [{ id: 31, name: 'Jasur Kuryer', phone: '+998935550011', telegram_username: 'jasur_k', branch_id: 1 }]
  private counter = 100

  constructor(private readonly backend: FakeBackend) {}

  /** Adds an order the way a customer's checkout would have (newest first). */
  add(overrides: Partial<FakeOrder> = {}): FakeOrder {
    const n = ++this.counter
    const type = overrides.type ?? 'pickup'
    const order: FakeOrder = {
      uuid: `a1000000-0000-4000-8000-${String(n).padStart(12, '0')}`,
      short_code: `B${n}`,
      status: 'new',
      type,
      channel: 'telegram',
      branch_id: 1,
      payment_method: 'cash',
      payment_status: 'pending',
      total_price: '75000.00',
      notes: null,
      customer: { name: 'Aziz Mijoz', phone: '+998909998877' },
      delivery: type === 'delivery' ? { address: 'Amir Temur ko\'chasi, 12', latitude: 41.3111, longitude: 69.2797, zone_name: 'Markaz' } : null,
      courier: null,
      items: [
        { id: n * 10 + 1, product_name: 'Lavash', variant_name: 'Standart', quantity: 2, unit: 'piece', unit_price: '25000.00', total_price: '50000.00', kitchen_status: 'pending' },
        { id: n * 10 + 2, product_name: 'Cola', variant_name: '0.5 l', quantity: 1, unit: 'piece', unit_price: '25000.00', total_price: '25000.00', kitchen_status: 'pending' },
      ],
      created_at: iso(0),
      accepted_at: null,
      cancel_reason: null,
      history: [],
      ...overrides,
    }
    if (order.history.length === 0) order.history = [{ from_status: null, status: 'new', created_at: order.created_at }]
    this.orders.unshift(order)
    return order
  }

  find(uuid: string): FakeOrder | undefined {
    return this.orders.find((order) => order.uuid === uuid)
  }

  // --- Shapes ----------------------------------------------------------------------------------------------------------

  private branchName(id: number): string | null {
    return this.backend.team.branches.find((branch) => branch.id === id)?.name ?? null
  }

  private row(order: FakeOrder) {
    const itemsReady = order.items.filter((item) => item.kitchen_status === 'ready').length
    const closed = FINISHED.includes(order.status)
    return {
      uuid: order.uuid,
      short_code: order.short_code,
      status: order.status,
      status_label: STATUS_LABELS[order.status] ?? order.status,
      type: order.type,
      channel: order.channel,
      store_id: this.backend.account.storeId,
      branch_id: order.branch_id,
      branch_name: this.branchName(order.branch_id),
      payment_method: order.payment_method,
      payment_status: order.payment_status,
      subtotal: order.total_price,
      discount: '0.00',
      delivery_price: '0.00',
      service_charge_amount: '0.00',
      place_fee_amount: '0.00',
      total_price: order.total_price,
      notes: order.notes,
      guests_count: null,
      hall_ref: null,
      table_ref: null,
      cancel_reason: order.cancel_reason,
      cancel_note: null,
      customer: order.customer,
      delivery: order.delivery,
      courier: order.courier,
      items_total: order.items.length,
      items_ready: itemsReady,
      items: order.items.map((item) => ({
        id: item.id,
        product_name: item.product_name,
        variant_name: item.variant_name,
        quantity: item.quantity,
        unit: item.unit,
        quantity_label: item.unit === 'piece' ? String(item.quantity) : `${item.quantity} ${item.unit}`,
        modifiers: item.modifiers ?? [],
        kitchen_status: item.kitchen_status,
        ready_at: item.kitchen_status === 'ready' ? iso(1) : null,
        unit_price: item.unit_price,
        modifiers_total: '0.00',
        total_price: item.total_price,
      })),
      created_at: order.created_at,
      updated_at: order.history.at(-1)?.created_at ?? order.created_at,
      accepted_at: order.accepted_at,
      preparing_at: null,
      ready_at: null,
      closed_at: closed ? (order.history.at(-1)?.created_at ?? null) : null,
    }
  }

  private visible(): FakeOrder[] {
    const account = this.backend.account
    return this.orders.filter((order) => account.branchId === null || order.branch_id === account.branchId)
  }

  // --- Endpoints -------------------------------------------------------------------------------------------------------

  private list(query: Record<string, string>): Reply {
    const statuses = query.status ? query.status.split(',') : null
    const rows = this.visible().filter((order) => {
      if (query.branch_id && order.branch_id !== Number(query.branch_id)) return false
      if (query.type && order.type !== query.type) return false
      if (query.channel && order.channel !== query.channel) return false
      if (query.q && !order.short_code.toLowerCase().startsWith(query.q.toLowerCase())) return false
      if (statuses && !statuses.includes(order.status)) return false
      return query.scope === 'history' ? FINISHED.includes(order.status) : !FINISHED.includes(order.status)
    })
    const perPage = Number(query.per_page) || 20
    const offset = Number(query.cursor) || 0
    const end = offset + perPage
    return ok(rows.slice(offset, end).map((order) => this.row(order)), { meta: { next_cursor: end < rows.length ? String(end) : null, per_page: perPage } })
  }

  private show(uuid: string): Reply {
    const order = this.visible().find((candidate) => candidate.uuid === uuid)
    if (!order) return fail(404, 'Buyurtma topilmadi.')
    return ok({ ...this.row(order), status_history: order.history })
  }

  private move(order: FakeOrder, next: OrderStatus, body: Record<string, unknown>): Reply {
    if (order.status === next) return ok(this.row(order))
    if (!NEXT[order.status]?.includes(next)) {
      return fail(422, `Buyurtmani «${STATUS_LABELS[order.status]}» holatidan «${STATUS_LABELS[next]}» holatiga o'tkazib bo'lmaydi.`)
    }
    if (next === 'canceled' || next === 'rejected') {
      const reason = String(body.reason ?? '')
      if (!STAFF_REASONS.includes(reason)) return fail(422, 'The given data was invalid.', { reason: ['Bekor qilish sababini tanlang.'] })
      order.cancel_reason = reason
    }
    if (next === 'accepted') order.accepted_at = iso(0)
    order.history.push({ from_status: order.status, status: next, created_at: iso(0) })
    order.status = next
    // Ready by any road makes every line ready (docs/API.md 9.1)
    if (next === 'ready') for (const item of order.items) item.kitchen_status = 'ready'
    return ok(this.row(order))
  }

  private assign(order: FakeOrder, body: Record<string, unknown>): Reply {
    const courier = this.couriers.find((candidate) => candidate.id === Number(body.courier_id))
    if (!courier) return fail(422, 'The given data was invalid.', { courier_id: ['Kuryer topilmadi.'] })
    if (order.status !== 'ready' || order.type !== 'delivery') return fail(422, "Kuryerni faqat tayyor yetkazish buyurtmasiga biriktirish mumkin.")
    order.courier = { id: courier.id, name: courier.name, phone: courier.phone }
    order.history.push({ from_status: order.status, status: 'courier_assigned', created_at: iso(0) })
    order.status = 'courier_assigned'
    return ok(this.row(order))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    let match: RegExpExecArray | null
    if (req.method === 'GET' && req.path === '/v1/staff/orders') return this.list(req.query)
    if ((match = /^\/v1\/staff\/orders\/([^/]+)$/.exec(req.path)) && req.method === 'GET') return this.show(match[1])
    if ((match = /^\/v1\/(?:staff\/)?orders\/([^/]+)\/(status|assign-courier)$/.exec(req.path)) && req.method === 'POST') {
      const order = this.visible().find((candidate) => candidate.uuid === match![1])
      if (!order) return fail(404, 'Buyurtma topilmadi.')
      if (match[2] === 'assign-courier') return this.assign(order, input)
      return this.move(order, String(input.status) as OrderStatus, input)
    }
    if (req.method === 'GET' && req.path === '/v1/couriers') {
      const branch = req.query.branch_id ? Number(req.query.branch_id) : null
      return ok(this.couriers.filter((courier) => branch === null || courier.branch_id === branch))
    }
    return null
  }
}
