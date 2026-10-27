// The fake of Buyurtmalar (P4-14): /admin/orders, /orders/{uuid} and its actions, /orders/sla, /orders/store-quality and
// /refund-requests, shaped like AdminOrderController, AdminOrderInterventionController and AdminRefundRequestController
// answer. The rules that matter to the screens are here: viewing needs orders.view, acting orders.intervene, asking for a
// refund orders.refund_request, deciding orders.refund_approve (never the one who asked: 403 four_eyes), the SLA limits
// system.manage; every action a step-up and a reason (3+ characters). A test changes `orders` before (or while) the page is
// open.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()
const minutesFrom = (iso: string, minutes: number) => new Date(Date.parse(iso) + minutes * 60_000).toISOString()

type Group = 'active' | 'new' | 'kitchen' | 'en_route' | 'done' | 'canceled'
const GROUPS: Record<Group, string[]> = {
  active: ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'],
  new: ['new'],
  kitchen: ['accepted', 'preparing', 'ready'],
  en_route: ['courier_assigned', 'on_the_way', 'delivery'],
  done: ['delivered', 'refunded'],
  canceled: ['canceled', 'rejected'],
}
const LABELS: Record<string, string> = {
  new: 'Yangi', accepted: 'Qabul qilindi', preparing: 'Tayyorlanmoqda', ready: 'Tayyor', courier_assigned: 'Kuryer biriktirildi',
  on_the_way: "Yo'lda", delivery: 'Yetkazishda', delivered: 'Yetkazildi', canceled: 'Bekor qilindi', rejected: 'Rad etildi', refunded: 'Qaytarildi',
}
const NEXT: Record<string, string[]> = {
  new: ['accepted'], accepted: ['preparing'], preparing: ['ready', 'courier_assigned', 'on_the_way'], ready: ['courier_assigned', 'on_the_way'],
  courier_assigned: ['on_the_way'], on_the_way: ['delivered'], delivery: ['delivered'], delivered: [], canceled: [], rejected: [], refunded: [],
}
const STAGE_LABEL = { accept: 'Qabul qilish', prepare: 'Tayyorlash', deliver: 'Yetkazish' } as const
const groupOf = (status: string): Group => (['new', 'kitchen', 'en_route', 'done', 'canceled'] as Group[]).find((group) => GROUPS[group].includes(status)) ?? 'active'

export interface FakeOrder {
  uuid: string
  short_code: string
  status: string
  type: 'delivery' | 'pickup'
  store: { id: number; name: string }
  branch: { id: number; name: string }
  customer: string
  total: string
  paid: string
  payment_method: string
  created_at: string
  /** When the running stage started (accept: created_at) */
  stage_started_at: string
  courier: { id: number; name: string } | null
  history: Array<{ status: string; at: string; actor: string | null; notes: string | null }>
}

export interface FakeRefundRequest {
  id: number
  order: string
  amount: string
  status: 'pending' | 'approved' | 'rejected'
  reason: string
  requested_by: { id: number; name: string }
  requested_at: string
  decided_by: { id: number; name: string } | null
  decided_at: string | null
  decision_note: string | null
}

export function defaultOrders(): FakeOrder[] {
  const late = minutesAgo(17)
  const cooking = minutesAgo(40)
  return [
    {
      uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD', status: 'new', type: 'delivery', store: { id: 12, name: 'Lazzat Oshxona' }, branch: { id: 5, name: 'Chilonzor' },
      customer: 'Olim Egamov', total: '85000.00', paid: '0.00', payment_method: 'cash', created_at: late, stage_started_at: late, courier: null,
      history: [{ status: 'new', at: late, actor: null, notes: null }],
    },
    {
      uuid: 'ab99ef00-1111-4222-8333-444455556667', short_code: 'AB99EF', status: 'preparing', type: 'pickup', store: { id: 14, name: 'Shirin Qandolat' }, branch: { id: 8, name: 'Yunusobod' },
      customer: 'Dilnoza Sobirova', total: '42000.00', paid: '42000.00', payment_method: 'payme', created_at: cooking, stage_started_at: minutesAgo(26), courier: null,
      history: [{ status: 'new', at: cooking, actor: null, notes: null }, { status: 'accepted', at: minutesAgo(26), actor: null, notes: "To'lov tasdiqlandi" }, { status: 'preparing', at: minutesAgo(25), actor: 'Menejer 1', notes: null }],
    },
    {
      uuid: 'ab55aa00-1111-4222-8333-444455556668', short_code: 'AB55AA', status: 'delivered', type: 'delivery', store: { id: 12, name: 'Lazzat Oshxona' }, branch: { id: 5, name: 'Chilonzor' },
      customer: 'Olim Egamov', total: '120000.00', paid: '120000.00', payment_method: 'click', created_at: minutesAgo(180), stage_started_at: minutesAgo(180), courier: { id: 21, name: 'Kuryer 1' },
      history: [{ status: 'new', at: minutesAgo(180), actor: null, notes: null }, { status: 'delivered', at: minutesAgo(120), actor: 'Kuryer 1', notes: null }],
    },
  ]
}

export class FakeOrders {
  orders: FakeOrder[] = defaultOrders()
  refunds: FakeRefundRequest[] = []
  limits = { accept: 5, prepare: 30, deliver: 45 }
  messages: Array<{ uuid: string; to: string; text: string; reason: string }> = []
  private nextRefundId = 1

  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    if (!(path === '/orders' || path.startsWith('/orders/') || path === '/refund-requests' || path.startsWith('/refund-requests/'))) return null
    const forbidden = fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
    const act = (permission: string): Reply | null => {
      if (!can(permission)) return forbidden
      const recent = needsRecent()
      if (recent) return recent
      return typeof body.reason === 'string' && body.reason.trim().length >= 3 ? null : validation('reason', 'Sababni yozing (kamida 3 belgi).')
    }

    if (req.method === 'PUT' && path === '/orders/sla') {
      const refused = act('admin.system.manage')
      if (refused) return refused
      for (const key of ['accept', 'prepare', 'deliver'] as const) if (typeof body[key] === 'number') this.limits[key] = body[key] as number
      return ok(this.slaSettings(), { message: 'SLA chegaralari saqlandi.' })
    }
    if (!can('admin.orders.view')) return forbidden

    if (req.method === 'GET' && path === '/orders') return ok(this.list(req.query))
    if (req.method === 'GET' && path === '/orders/sla') return ok(this.slaSettings())
    if (req.method === 'GET' && path === '/orders/store-quality') {
      return ok({
        period: { from: minutesAgo(30 * 24 * 60), to: minutesAgo(0), days: 30 },
        stores: [
          { store: { id: 12, name: 'Lazzat Oshxona' }, orders: 40, accepted: 30, acceptance_rate: 0.75, avg_accept_minutes: 6.5, avg_prep_minutes: 28.1, canceled: 6, cancel_rate: 0.15, rating: '4.20', review_count: 31, ratings_in_period: 9, rating_in_period: 4.1 },
          { store: { id: 14, name: 'Shirin Qandolat' }, orders: 12, accepted: 12, acceptance_rate: 1, avg_accept_minutes: 2, avg_prep_minutes: 15, canceled: 0, cancel_rate: 0, rating: '4.80', review_count: 12, ratings_in_period: 3, rating_in_period: 5 },
        ],
      })
    }
    if (req.method === 'GET' && path === '/refund-requests') {
      const status = req.query.status ?? 'pending'
      const rows = this.refunds.filter((refund) => status === 'all' || refund.status === status).sort((a, b) => b.id - a.id)
      return ok({ data: rows.map((refund) => ({ ...this.presentRefund(refund, actor, can), order: this.orderSummary(refund.order), store: this.find(refund.order)?.store ?? null })), next_cursor: null, per_page: 50 })
    }

    const decide = /^\/refund-requests\/(\d+)\/(approve|reject)$/.exec(path)
    if (req.method === 'POST' && decide) {
      const refused = act('admin.orders.refund_approve')
      if (refused) return refused
      const refund = this.refunds.find((row) => row.id === Number(decide[1]))
      if (!refund) return fail(404, "So'rov topilmadi.", { reason: 'not_found' })
      if (refund.requested_by.id === actor.id) return fail(403, "O'zingiz yaratgan so'rovni tasdiqlay yoki rad eta olmaysiz: buni boshqa xodim qiladi.", { reason: 'four_eyes' })
      const decision = decide[2] === 'approve' ? 'approved' : 'rejected'
      if (refund.status !== 'pending' && refund.status !== decision) return fail(409, "So'rov allaqachon ko'rib chiqilgan.", { reason: 'refund_request_decided' })
      if (refund.status === 'pending') {
        refund.status = decision
        refund.decided_by = actor
        refund.decided_at = new Date().toISOString()
        refund.decision_note = String(body.reason).trim()
        const order = this.find(refund.order)
        if (order && decision === 'approved' && order.status === 'delivered' && Number(refund.amount) === Number(order.paid)) {
          order.status = 'refunded'
          order.history.push({ status: 'refunded', at: refund.decided_at, actor: actor.name, notes: `Boshqaruv markazi, qaytarish so'rovi #${refund.id}` })
        }
      }
      return ok(this.presentRefund(refund, actor, can), { message: decision === 'approved' ? 'Tasdiqlandi.' : "So'rov rad etildi." })
    }

    const action = /^\/orders\/([^/]+)(?:\/(cancel|status|message|refund-requests))?$/.exec(path)
    if (!action) return null
    const order = this.find(action[1]!)
    if (!order) return fail(404, 'Buyurtma topilmadi.', { reason: 'not_found' })
    const verb = action[2]

    if (req.method === 'GET' && !verb) return ok(this.card(order, actor, can))

    if (req.method === 'POST' && verb === 'cancel') {
      const refused = act('admin.orders.intervene')
      if (refused) return refused
      if (!GROUPS.active.includes(order.status)) return fail(422, `Buyurtmani «${LABELS[order.status]}» holatida bekor qilib bo'lmaydi.`, { reason: 'illegal' })
      order.status = 'canceled'
      order.history.push({ status: 'canceled', at: new Date().toISOString(), actor: actor.name, notes: `Boshqaruv markazi: ${String(body.reason)}` })
      return ok(this.card(order, actor, can), { message: 'Buyurtma bekor qilindi.' })
    }
    if (req.method === 'POST' && verb === 'status') {
      const refused = act('admin.orders.intervene')
      if (refused) return refused
      const to = String(body.status ?? '')
      if (!NEXT[order.status]?.includes(to)) return fail(422, `Buyurtmani «${LABELS[order.status]}» holatidan «${LABELS[to] ?? to}» holatiga o'tkazib bo'lmaydi.`, { reason: 'illegal' })
      order.status = to
      order.stage_started_at = new Date().toISOString()
      order.history.push({ status: to, at: order.stage_started_at, actor: actor.name, notes: `Boshqaruv markazi: holat tuzatildi. Sabab: ${String(body.reason)}` })
      return ok(this.card(order, actor, can), { message: 'Holat tuzatildi.' })
    }
    if (req.method === 'POST' && verb === 'message') {
      const refused = act('admin.orders.intervene')
      if (refused) return refused
      if (typeof body.text !== 'string' || body.text.trim() === '') return validation('text', 'Xabar matnini yozing.')
      this.messages.push({ uuid: order.uuid, to: String(body.to), text: body.text, reason: String(body.reason) })
      return ok({ recipients: body.to === 'store' ? 2 : 1 })
    }
    if (req.method === 'POST' && verb === 'refund-requests') {
      const refused = act('admin.orders.refund_request')
      if (refused) return refused
      if (GROUPS.active.includes(order.status)) return fail(422, "Pulni qaytarishni faqat yakunlangan buyurtma uchun so'rash mumkin.", { reason: 'order_active' })
      if (this.refunds.some((refund) => refund.order === order.uuid && refund.status === 'pending')) return fail(409, "Bu buyurtma bo'yicha so'rov allaqachon tasdiq kutmoqda.", { reason: 'refund_request_pending' })
      const refundable = this.refundable(order)
      if (!(Number(body.amount) > 0) || Number(body.amount) > refundable) return fail(422, 'Summa qaytariladigan puldan oshmasligi kerak.', { reason: 'amount_exceeds_refundable', refundable: refundable.toFixed(2) })
      const refund: FakeRefundRequest = {
        id: this.nextRefundId++, order: order.uuid, amount: Number(body.amount).toFixed(2), status: 'pending', reason: String(body.reason).trim(),
        requested_by: actor, requested_at: new Date().toISOString(), decided_by: null, decided_at: null, decision_note: null,
      }
      this.refunds.push(refund)
      return { status: 201, body: { success: true, message: "So'rov yaratildi. Tasdiq kutilmoqda.", data: this.presentRefund(refund, actor, can) } }
    }
    return null
  }

  find(uuid: string): FakeOrder | undefined {
    return this.orders.find((order) => order.uuid === uuid)
  }

  private slaSettings() {
    return { limits: { ...this.limits }, defaults: { accept: 5, prepare: 30, deliver: 45 }, bounds: { accept: [1, 120], prepare: [5, 300], deliver: [5, 300] }, warning_ratio: 0.8 }
  }

  private stage(order: FakeOrder): 'accept' | 'prepare' | 'deliver' | null {
    if (order.status === 'new') return order.payment_method === 'cash' || Number(order.paid) > 0 ? 'accept' : null
    if (order.status === 'accepted' || order.status === 'preparing') return 'prepare'
    if ((order.status === 'on_the_way' || order.status === 'delivery') && order.type === 'delivery') return 'deliver'
    return null
  }

  private sla(order: FakeOrder) {
    const stage = this.stage(order)
    if (!stage) return null
    const limit = this.limits[stage]
    const elapsed = (Date.now() - Date.parse(order.stage_started_at)) / 60_000
    const state = elapsed >= limit ? 'late' : elapsed >= limit * 0.8 ? 'warning' : 'ok'
    return {
      stage, stage_label: STAGE_LABEL[stage], state, limit_minutes: limit, started_at: order.stage_started_at,
      warning_at: minutesFrom(order.stage_started_at, limit * 0.8), due_at: minutesFrom(order.stage_started_at, limit),
      late_by_minutes: state === 'late' ? Math.floor(elapsed - limit) : 0, minutes_left: state === 'late' ? 0 : Math.ceil(limit - elapsed),
    }
  }

  private refundable(order: FakeOrder): number {
    const approved = this.refunds.filter((refund) => refund.order === order.uuid && refund.status === 'approved').reduce((sum, refund) => sum + Number(refund.amount), 0)
    return Math.max(0, Number(order.paid) - approved)
  }

  private row(order: FakeOrder) {
    return {
      uuid: order.uuid, short_code: order.short_code, status: order.status, status_label: LABELS[order.status] ?? order.status, status_group: groupOf(order.status),
      type: order.type, channel: 'telegram', channel_label: 'Telegram', store: order.store, branch: order.branch, region: { id: 1, name: 'Toshkent shahri' },
      customer_name: order.customer, total: order.total, payment_method: order.payment_method, payment_status: Number(order.paid) > 0 ? 'paid' : 'pending',
      courier: order.courier, sla: this.sla(order), refund_pending: this.refunds.some((refund) => refund.order === order.uuid && refund.status === 'pending'),
      manual_refund_required: false, created_at: order.created_at, updated_at: order.created_at,
    }
  }

  private list(query: Record<string, string>) {
    const status = (query.status as Group | undefined) ?? 'active'
    const q = (query.q ?? '').replace(/^#/, '').toLowerCase()
    const base = this.orders.filter((order) => !q || order.short_code.toLowerCase().startsWith(q) || order.uuid.startsWith(q))
    const counts = Object.fromEntries((Object.keys(GROUPS) as Group[]).map((group) => [group, base.filter((order) => GROUPS[group].includes(order.status)).length]))
    const rows = base
      .filter((order) => GROUPS[status]?.includes(order.status))
      .filter((order) => !query.sla || this.sla(order)?.state === query.sla)
      .sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at))
      .map((order) => this.row(order))
    return { data: rows, next_cursor: null, per_page: 30, counts, sla_limits: { ...this.limits }, server_time: new Date().toISOString() }
  }

  private orderSummary(uuid: string) {
    const order = this.find(uuid)
    return order ? { uuid: order.uuid, short_code: order.short_code, status: order.status, status_label: LABELS[order.status], total: order.total, payment_method: order.payment_method } : null
  }

  private presentRefund(refund: FakeRefundRequest, actor: { id: number }, can: (permission: string) => boolean) {
    const approvedManual = refund.status === 'approved' && this.find(refund.order)?.payment_method !== 'keshbek'
    return {
      id: refund.id, uuid: `refund-${refund.id}`, amount: refund.amount, status: refund.status,
      status_label: refund.status === 'pending' ? 'Tasdiq kutilmoqda' : refund.status === 'approved' ? 'Tasdiqlandi' : 'Rad etildi',
      reason: refund.reason, requested_by: refund.requested_by, requested_at: refund.requested_at, decided_by: refund.decided_by, decided_at: refund.decided_at,
      decision_note: refund.decision_note,
      execution: refund.status === 'approved'
        ? { mode: 'order_refunded', order_status_before: 'delivered', order_status_after: 'refunded', payment_method: 'click', automatic_amount: '0.00', manual_amount: approvedManual ? refund.amount : '0.00', provider_refund: approvedManual ? 'manual' : 'none' }
        : null,
      mine: refund.requested_by.id === actor.id,
      can_decide: refund.status === 'pending' && refund.requested_by.id !== actor.id && can('admin.orders.refund_approve'),
    }
  }

  private card(order: FakeOrder, actor: { id: number; name: string }, can: (permission: string) => boolean) {
    const intervene = can('admin.orders.intervene')
    const active = GROUPS.active.includes(order.status)
    const refundable = this.refundable(order)
    const requests = this.refunds.filter((refund) => refund.order === order.uuid).sort((a, b) => b.id - a.id)
    const pending = requests.some((refund) => refund.status === 'pending')
    return {
      ...this.row(order),
      notes: null,
      delivery_address: order.type === 'delivery' ? 'Amir Temur 1' : null,
      customer: { id: 7, name: order.customer, phone: '+998 90 *** ** 67', has_telegram: true },
      courier: order.courier ? { ...order.courier, phone: '+998 90 *** ** 12' } : null,
      store: { ...order.store, phone: '+998 71 *** ** 00' },
      branch: { ...order.branch, address: 'Bunyodkor 5' },
      items: [{ id: 1, name: 'Osh', variant: null, modifiers: null, quantity: '2', unit_price: (Number(order.total) / 2).toFixed(2), total: order.total, void: false }],
      totals: { subtotal: order.total, discount: '0.00', delivery_price: '0.00', service_charge: '0.00', place_fee: '0.00', total: order.total, commission: (Number(order.total) / 10).toFixed(2), payment_owner: 'PLATFORM' },
      cancel: order.status === 'canceled' ? { reason: 'other', reason_label: 'Boshqa sabab', note: null } : null,
      payments: Number(order.paid) > 0 ? [{ uuid: `p-${order.short_code}`, provider: order.payment_method, amount: order.paid, status: 'paid', paid_at: order.created_at, manual_refund_required: false, manual_refund_approved: [], created_at: order.created_at }] : [],
      refunds: { paid: order.paid, refunded: (Number(order.paid) - refundable).toFixed(2), refundable: refundable.toFixed(2), requests: requests.map((refund) => this.presentRefund(refund, actor, can)) },
      history: order.history.map((row, index) => ({ status: row.status, status_label: LABELS[row.status] ?? row.status, from_status: index > 0 ? order.history[index - 1]!.status : null, at: row.at, actor: row.actor ? { id: 1, name: row.actor, role: 'Operator', team: row.notes?.startsWith('Boshqaruv') ?? false } : null, notes: row.notes })),
      sla_timeline: [],
      sla_events: [],
      fiscal_receipts: [],
      audit: [],
      actions: {
        can_intervene: intervene,
        can_cancel: intervene && active,
        cancel_reasons: active ? [{ value: 'customer_request', label: "Mijoz so'rovi" }, { value: 'store_closed', label: "Do'kon yopiq" }, { value: 'other', label: 'Boshqa sabab' }] : [],
        transitions: (NEXT[order.status] ?? []).filter((status) => order.type === 'delivery' || !['courier_assigned', 'on_the_way'].includes(status)).map((status) => ({ status, label: LABELS[status] ?? status })),
        message_store: intervene,
        message_customer: intervene,
        can_request_refund: can('admin.orders.refund_request') && !active && refundable > 0 && !pending,
        can_approve_refund: can('admin.orders.refund_approve'),
      },
    }
  }
}
