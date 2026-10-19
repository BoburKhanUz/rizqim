// An in-memory stand-in for the backend API (/api/v1), shaped like the real responses (see src/types).
// The browser never reaches a server: e2e/fixtures.ts routes every API request of the page into FakeApi.handle().
import { makeOrder, makeOrderItem, makeProduct, makeStore, makeUser, makeVariant } from '../src/test/factories'
import type {
  ApiUser,
  BranchSummary,
  CancelReason,
  Category,
  Order,
  OrderStatus,
  Product,
  Store,
  StoreListItem,
  UserRole,
} from '../src/types'

export interface ApiCall {
  method: string
  path: string // without the /api/v1 prefix and the query string
  query: Record<string, string>
  body: Record<string, unknown> | null
  authorization: string | null
  status: number // what the fake answered
}

interface ApiReply {
  status: number
  body: unknown
}

// The Telegram account every test user signs in with (the app compares it with the one Telegram reports)
export const TELEGRAM_ID = '555000111'

const FINISHED: readonly OrderStatus[] = ['delivered', 'canceled', 'rejected', 'refunded']

// Reasons a member of staff may give (App\Enums\CancelReason::staffCases)
const STAFF_REASONS: readonly CancelReason[] = ['customer_request', 'no_show', 'out_of_stock', 'store_closed', 'courier_unavailable', 'other']

// A pickup order can be canceled as a no-show only in these statuses
const NO_SHOW_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready']

// Where each status can go next when staff change it by hand (POST /orders/{uuid}/status)
const NEXT_STATUSES: Partial<Record<OrderStatus, readonly OrderStatus[]>> = {
  new: ['accepted', 'rejected'],
  accepted: ['preparing', 'canceled'],
  preparing: ['ready', 'canceled'],
  ready: ['on_the_way', 'delivered', 'canceled'],
  courier_assigned: ['on_the_way'],
  on_the_way: ['delivered'],
}

const BRANCH: BranchSummary = {
  id: 1,
  store_id: 1,
  name: 'Chilonzor filiali',
  latitude: 41.2753,
  longitude: 69.2037,
  radius_km: 15,
  address: 'Chilonzor, 9-kvartal',
  phone: '+998901112233',
}

const CATEGORY: Category = {
  id: 1,
  uuid: 'c0000000-0000-4000-8000-000000000001',
  name: 'Fast food',
  slug: 'fast-food',
  parent_id: null,
  image: null,
  image_url: null,
}

const reply = (status: number, body: unknown): ApiReply => ({ status, body })
const ok = (data: unknown, extra: Record<string, unknown> = {}): ApiReply => reply(200, { success: true, message: '', data, ...extra })
const fail = (status: number, message: string, errors?: Record<string, string[]>): ApiReply =>
  reply(status, errors ? { success: false, message, errors } : { success: false, message })

// A deterministic uuid, so a test can name an order by its first eight characters (the list shows them as "#AAAAAAAA")
export const uuidOf = (n: number): string => `e2e${String(n).padStart(5, '0')}-0000-4000-8000-${String(n).padStart(12, '0')}`

export class FakeApi {
  user: ApiUser = makeUser({ telegram_id: TELEGRAM_ID, name: 'Ali Valiyev', phone: '+998901234567' })
  readonly store: Store = makeStore({ min_order: 0, delivery_fee: 12000, address: 'Toshkent, Chilonzor' })
  readonly products: Product[] = [
    makeProduct({
      id: 1,
      uuid: 'a0000000-0000-4000-8000-000000000001',
      store_id: 1,
      name: 'Lavash',
      variants: [makeVariant({ id: 1, uuid: 'b0000000-0000-4000-8000-000000000001', product_id: 1, name: 'Standart', price: '25000.00', stock: 50 })],
    }),
    makeProduct({
      id: 2,
      uuid: 'a0000000-0000-4000-8000-000000000002',
      store_id: 1,
      name: 'Cola',
      variants: [makeVariant({ id: 2, uuid: 'b0000000-0000-4000-8000-000000000002', product_id: 2, name: '0.5 l', price: '8000.00', stock: 50 })],
    }),
  ]

  orders: Order[] = [] // newest first, like the API lists them
  calls: ApiCall[] = []
  /** Calls that matched no endpoint of the fake: a test fails when the app starts using one it does not know. */
  unhandled: string[] = []

  private validTokens = new Set<string>()
  private tokenCounter = 0
  private orderCounter = 100

  /** Signs in as a user of `role` (the Telegram account is the same, the account behind it differs). */
  signInAs(role: UserRole, overrides: Partial<ApiUser> = {}): ApiUser {
    const names: Record<UserRole, string> = {
      customer: 'Ali Valiyev',
      manager: 'Bobur Menejer',
      courier: 'Jasur Kuryer',
      owner: 'Olim Egasi',
      super_admin: 'Super Admin',
    }
    const ids: Record<UserRole, number> = { customer: 1, manager: 2, courier: 3, owner: 4, super_admin: 5 }
    this.user = makeUser({
      id: ids[role],
      telegram_id: TELEGRAM_ID,
      name: names[role],
      phone: '+998901234567',
      role,
      store_id: role === 'customer' ? null : 1,
      branch_id: role === 'manager' || role === 'courier' ? 1 : null,
      ...overrides,
    })
    return this.user
  }

  /** Adds an order of the current customer (or, for staff, of this store) to the fake database. */
  addOrder(overrides: Partial<Order> = {}): Order {
    const n = ++this.orderCounter
    const order = makeOrder({
      id: n,
      uuid: uuidOf(n),
      short_code: `E${n}`,
      store_id: 1,
      user_id: this.user.role === 'customer' ? this.user.id : 99,
      status: 'new',
      payment_status: 'pending',
      payment_method: 'cash',
      order_type: 'pickup',
      type: 'pickup',
      branch_id: 1,
      items: [makeOrderItem({ id: n, order_id: n, product_name: 'Lavash', variant_name: 'Standart' })],
      created_at: new Date(Date.now() - n * 60_000).toISOString(),
      updated_at: new Date(Date.now() - n * 60_000).toISOString(),
      ...overrides,
    })
    this.orders.push(order)
    this.orders.sort((a, b) => b.created_at.localeCompare(a.created_at) || b.id - a.id)
    return order
  }

  /** Makes every token issued so far invalid, like an expired or pruned one. */
  expireTokens(): void {
    this.validTokens.clear()
  }

  /** The recorded calls to `method path`, oldest first. */
  callsTo(method: string, path: string | RegExp): ApiCall[] {
    return this.calls.filter((call) => call.method === method && (typeof path === 'string' ? call.path === path : path.test(call.path)))
  }

  handle(method: string, path: string, query: Record<string, string>, body: Record<string, unknown> | null, authorization: string | null): ApiReply {
    const call: ApiCall = { method, path, query, body, authorization, status: 0 }
    this.calls.push(call)
    const result = this.route(method, path, query, body, authorization)
    call.status = result.status
    return result
  }

  private route(method: string, path: string, query: Record<string, string>, body: Record<string, unknown> | null, authorization: string | null): ApiReply {
    if (method === 'POST' && path === '/auth/telegram') return this.login()

    const token = authorization?.replace(/^Bearer /, '') ?? ''
    if (!this.validTokens.has(token)) return fail(401, 'Unauthenticated.')

    if (method === 'GET' && path === '/user') {
      return ok({ user: this.user, store: this.userStore(), sms_verification_required: false })
    }
    if (method === 'GET' && path === '/stores') return ok([this.storeListItem()])
    if (method === 'GET' && path === '/catalog') return this.catalog(query)
    if (method === 'GET' && path === '/couriers') return ok([])
    if (method === 'GET' && path === '/orders') return this.listOrders(query)
    if (method === 'POST' && path === '/orders') return this.placeOrder(body ?? {})

    const orderAction = /^\/orders\/([^/]+)\/(cancel|status|complete)$/.exec(path)
    if (method === 'POST' && orderAction) {
      const order = this.orders.find((o) => o.uuid === orderAction[1])
      if (!order) return fail(404, 'Buyurtma topilmadi.')
      if (orderAction[2] === 'cancel') return this.cancelByCustomer(order, body ?? {})
      if (orderAction[2] === 'status') return this.changeStatus(order, body ?? {})
      return this.complete(order)
    }

    this.unhandled.push(`${method} ${path}`)
    return fail(404, `E2E fake API: no endpoint for ${method} ${path}`)
  }

  // POST /auth/telegram
  private login(): ApiReply {
    const token = `token-${++this.tokenCounter}`
    this.validTokens.add(token)
    return ok({ token, user: this.user, store: this.userStore(), sms_verification_required: false })
  }

  private userStore(): Store | null {
    return this.user.role === 'super_admin' ? null : { ...this.store, branches: [] }
  }

  private storeListItem(): StoreListItem {
    const { id, uuid, name, address, logo, logo_url, banner, banner_url, description, is_open, is_premium, review_count } = this.store
    const { payment_settings, status, open_time, close_time, min_order_amount, min_order, delivery_fee, is_order_locked } = this.store
    return {
      id, uuid, name, address, logo, logo_url, banner, banner_url, description, is_open, is_premium, review_count,
      payment_settings, status, open_time, close_time, min_order_amount, min_order, delivery_fee, is_order_locked,
      branches: [BRANCH],
    }
  }

  // GET /catalog (the global feed) and GET /catalog?store_id=... (one store, by uuid or id)
  private catalog(query: Record<string, string>): ApiReply {
    if (!query.store_id) {
      const products = this.products.map((product) => ({ ...product, store: this.store }))
      return ok({ categories: [CATEGORY], products, promos: [], highlight_product: null })
    }
    if (query.store_id !== this.store.uuid && query.store_id !== String(this.store.id)) return fail(404, 'Do\'kon topilmadi.')
    return ok({ store: { ...this.store, branches: [BRANCH], ratings: [] }, categories: [CATEGORY], products: this.products })
  }

  // What GET /orders shows the signed-in user: a customer their own orders, staff the orders of their store
  // (a courier only the ones assigned to them).
  private visibleOrders(): Order[] {
    const { role, id } = this.user
    const own = this.orders.filter((order) => {
      if (role === 'customer') return order.user_id === id
      if (role === 'courier') return order.courier_id === id
      return order.store_id === this.user.store_id
    })
    // Staff get the customer (and courier) with the order, customers only the items
    return role === 'customer' ? own : own.map((order) => ({ ...order, user: order.user ?? this.customerOf(order) }))
  }

  private customerOf(order: Order): ApiUser {
    return makeUser({ id: order.user_id, name: 'Mijoz Test', phone: '+998909998877', role: 'customer' })
  }

  // GET /orders?scope=active | history
  private listOrders(query: Record<string, string>): ApiReply {
    const visible = this.visibleOrders()
    if (query.scope === 'active') {
      return ok(visible.filter((order) => !FINISHED.includes(order.status)), { meta: { truncated: false } })
    }
    if (query.scope === 'history') {
      // The cursor is opaque to the app: here, the offset of the page
      const finished = visible.filter((order) => FINISHED.includes(order.status))
      const perPage = Number(query.per_page) || 20
      const offset = Number(query.cursor) || 0
      const end = offset + perPage
      return ok(finished.slice(offset, end), { meta: { next_cursor: end < finished.length ? String(end) : null, per_page: perPage } })
    }
    return ok(visible)
  }

  // POST /orders
  private placeOrder(body: Record<string, unknown>): ApiReply {
    const lines = Array.isArray(body.items) ? (body.items as Array<{ variant_id: string; quantity: number }>) : []
    const items = lines.flatMap((line, index) => {
      const variant = this.products.flatMap((product) => product.variants).find((v) => v.uuid === line.variant_id)
      if (!variant) return []
      const total = Number(variant.price) * line.quantity
      const product = this.products.find((p) => p.id === variant.product_id)
      return [makeOrderItem({
        id: index + 1,
        product_variant_id: variant.id,
        product_name: product?.name ?? 'Mahsulot',
        variant_name: variant.name,
        quantity: line.quantity,
        unit_price: variant.price,
        total_price: total.toFixed(2),
      })]
    })
    if (items.length === 0) return fail(422, 'The given data was invalid.', { items: ['Savat bo\'sh.'] })

    const isDelivery = body.type === 'delivery'
    const subtotal = items.reduce((sum, item) => sum + Number(item.total_price), 0)
    const deliveryPrice = isDelivery ? this.store.delivery_fee : 0
    const address = typeof body.address === 'string' ? body.address : null
    const order = this.addOrder({
      user_id: this.user.id,
      status: 'new',
      payment_status: 'pending',
      payment_method: body.payment_method === 'cash' ? 'cash' : 'click',
      order_type: isDelivery ? 'delivery' : 'pickup',
      type: isDelivery ? 'delivery' : 'pickup',
      branch_id: typeof body.branch_id === 'number' ? body.branch_id : null,
      subtotal: subtotal.toFixed(2),
      delivery_price: deliveryPrice.toFixed(2),
      total_price: (subtotal + deliveryPrice).toFixed(2),
      address,
      shipping_address: isDelivery ? { address, latitude: Number(body.latitude), longitude: Number(body.longitude) } : null,
      notes: typeof body.notes === 'string' ? body.notes : null,
      items,
      created_at: new Date().toISOString(), // the newest of all
    })
    return reply(201, { success: true, message: 'Order created successfully.', data: { order_uuid: order.uuid, order_number: order.short_code, status: order.status, payment_url: null } })
  }

  private touch(order: Order, status: OrderStatus, patch: Partial<Order> = {}): void {
    Object.assign(order, patch, { status, updated_at: new Date().toISOString() })
  }

  // POST /orders/{uuid}/cancel: the customer's own order, only while new and unpaid
  private cancelByCustomer(order: Order, body: Record<string, unknown>): ApiReply {
    if (order.user_id !== this.user.id) return fail(403, 'Faqat o\'zingizning buyurtmangizni bekor qila olasiz.')
    if (body.reason !== undefined && body.reason !== 'customer_request') {
      return fail(422, 'The given data was invalid.', { reason: ['Bekor qilish sababi noto\'g\'ri.'] })
    }
    if (order.status !== 'new' || order.payment_status === 'paid' || order.payment_status === 'refunded') {
      return fail(422, 'Buyurtmani endi bekor qilib bo\'lmaydi.')
    }
    this.touch(order, 'canceled', { cancel_reason: 'customer_request', cancel_note: typeof body.note === 'string' ? body.note : null })
    return ok(order)
  }

  // POST /orders/{uuid}/status: staff move the order along, or cancel / reject it with a reason
  private changeStatus(order: Order, body: Record<string, unknown>): ApiReply {
    const next = body.status as OrderStatus
    if (!NEXT_STATUSES[order.status]?.includes(next)) {
      return fail(422, `Buyurtmani "${order.status}" holatidan "${String(next)}" holatiga o'tkazib bo'lmaydi.`)
    }
    if (next === 'canceled' || next === 'rejected') {
      const reason = body.reason as CancelReason | undefined
      if (!reason || !STAFF_REASONS.includes(reason)) {
        return fail(422, 'The given data was invalid.', { reason: ['Bekor qilish sababini tanlang.'] })
      }
      if (reason === 'no_show' && !(order.order_type === 'pickup' && NO_SHOW_STATUSES.includes(order.status))) {
        return fail(422, 'The given data was invalid.', { reason: ['Bu sabab faqat olib ketiladigan buyurtma uchun.'] })
      }
      this.touch(order, next, { cancel_reason: reason, cancel_note: typeof body.note === 'string' ? body.note : null })
    } else {
      this.touch(order, next)
    }
    return ok(order)
  }

  // POST /orders/{uuid}/complete: the courier hands the order over
  private complete(order: Order): ApiReply {
    if (order.status !== 'on_the_way' && order.status !== 'delivery') return fail(422, 'Buyurtma yo\'lda emas.')
    this.touch(order, 'delivered', { payment_status: 'paid' })
    return ok(order)
  }
}
