// An in-memory stand-in for the backend API (/api/v1), shaped like the real responses (see src/types).
// The browser never reaches a server: e2e/fixtures.ts routes every API request of the page into FakeApi.handle().
import { makeOrder, makeOrderItem, makeProduct, makeStore, makeUser, makeVariant } from '../src/test/factories'
import { FakeStaff } from './fakeStaff'
import type {
  ApiUser,
  BranchSummary,
  CancelReason,
  Category,
  ModifierGroup,
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
  idempotencyKey: string | null // the Idempotency-Key header of POST /orders
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

// A place the geocoder knows: what GET /geocode/search lists and GET /geocode/reverse answers
export interface FakePlace {
  address: string
  lat: number
  lng: number
}

// Distance in km between two points on the globe (the backend's CreateOrderAction does the same to pick a branch)
function distanceKm(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const rad = (degrees: number) => (degrees * Math.PI) / 180
  const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2
  return 2 * 6371 * Math.asin(Math.sqrt(h))
}

// The modifier groups of the "Burger" (the shape CatalogPresenter sends)
const SAUCE_GROUP: ModifierGroup = {
  id: 1,
  uuid: 'd0000000-0000-4000-8000-000000000001',
  name: 'Sous',
  min_select: 1,
  max_select: 1,
  is_required: true,
  sort_order: 0,
  modifiers: [
    { id: 1, uuid: 'e0000000-0000-4000-8000-000000000001', modifier_group_id: 1, name: 'Ketchup', price_delta: '0.00', max_quantity: 1, sort_order: 0, is_active: true },
    { id: 2, uuid: 'e0000000-0000-4000-8000-000000000002', modifier_group_id: 1, name: 'Pishloqli', price_delta: '3000.00', max_quantity: 1, sort_order: 1, is_active: true },
  ],
}
const EXTRAS_GROUP: ModifierGroup = {
  id: 2,
  uuid: 'd0000000-0000-4000-8000-000000000002',
  name: "Qo'shimchalar",
  min_select: 0,
  max_select: 2,
  is_required: false,
  sort_order: 1,
  modifiers: [
    { id: 3, uuid: 'e0000000-0000-4000-8000-000000000003', modifier_group_id: 2, name: 'Zaytun', price_delta: '2000.00', max_quantity: 1, sort_order: 0, is_active: true },
    { id: 4, uuid: 'e0000000-0000-4000-8000-000000000004', modifier_group_id: 2, name: "Go'sht", price_delta: '9000.00', max_quantity: 1, sort_order: 1, is_active: true },
  ],
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
    // A dish with a required choice (the sauce) and an optional one
    makeProduct({
      id: 3,
      uuid: 'a0000000-0000-4000-8000-000000000003',
      store_id: 1,
      name: 'Burger',
      variants: [makeVariant({ id: 3, uuid: 'b0000000-0000-4000-8000-000000000003', product_id: 3, name: 'Standart', price: '50000.00', stock: 50 })],
      modifier_groups: [SAUCE_GROUP, EXTRAS_GROUP],
    }),
    // Sold by the kilogram, in steps of 0.5 kg
    makeProduct({
      id: 4,
      uuid: 'a0000000-0000-4000-8000-000000000004',
      store_id: 1,
      name: 'Qazi',
      variants: [
        makeVariant({ id: 4, uuid: 'b0000000-0000-4000-8000-000000000004', product_id: 4, name: 'kg', price: '33333.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.500', stock: 20 }),
      ],
    }),
    // On the branch's stop-list (see stoppedVariantIds)
    makeProduct({
      id: 5,
      uuid: 'a0000000-0000-4000-8000-000000000005',
      store_id: 1,
      name: 'Osh',
      variants: [makeVariant({ id: 5, uuid: 'b0000000-0000-4000-8000-000000000005', product_id: 5, name: 'Standart', price: '35000.00', stock: 50 })],
    }),
  ]

  /** Variant ids on the branch's stop-list: GET /catalog/availability lists them, POST /orders refuses them ("tugadi"). */
  stoppedVariantIds: number[] = [5]
  /** Modifier ids on the stop-list. */
  stoppedModifierIds: number[] = []
  /** The delivery zone of the store (one zone: the branch's service area). Set zoneMinOrder to give it a minimum order. */
  zoneMinOrder = 0
  /** false: the store has drawn no zones yet, so GET /delivery/quote answers zones_not_configured. */
  zonesConfigured = true

  orders: Order[] = [] // newest first, like the API lists them

  /** The staff side: the order board (GET /staff/orders), stop-list, batches, branches, staff accounts (e2e/fakeStaff.ts). */
  readonly staff = new FakeStaff({
    orders: () => this.orders,
    user: () => this.user,
    products: () => this.products,
    modifierGroups: () => [SAUCE_GROUP, EXTRAS_GROUP],
    changeStatus: (order, body) => this.changeStatus(order, body),
  })

  /** What GET /geocode/search finds: the places whose address contains the query (case-insensitive). */
  places: FakePlace[] = []
  /** What GET /geocode/reverse answers for any point; null = "no address there" (404), like the real endpoint. */
  reverseAddress: string | null = 'Bunyodkor ko\'chasi, 12-uy'
  /** The geocoding provider is down: both endpoints answer 503, as the real ones do. */
  geocoderDown = false

  calls: ApiCall[] = []
  /** Calls that matched no endpoint of the fake: a test fails when the app starts using one it does not know. */
  unhandled: string[] = []

  private validTokens = new Set<string>()
  private tokenCounter = 0
  private orderCounter = 100
  /** The Idempotency-Key of every order that was placed, oldest first. */
  readonly idempotencyKeys: Array<string | null> = []

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

  handle(method: string, path: string, query: Record<string, string>, body: Record<string, unknown> | null, authorization: string | null, idempotencyKey: string | null = null): ApiReply {
    const call: ApiCall = { method, path, query, body, authorization, idempotencyKey, status: 0 }
    this.calls.push(call)
    const result = this.route(method, path, query, body, authorization, idempotencyKey)
    call.status = result.status
    return result
  }

  private route(method: string, path: string, query: Record<string, string>, body: Record<string, unknown> | null, authorization: string | null, idempotencyKey: string | null): ApiReply {
    if (method === 'POST' && path === '/auth/telegram') return this.login()

    const token = authorization?.replace(/^Bearer /, '') ?? ''
    if (!this.validTokens.has(token)) return fail(401, 'Unauthenticated.')

    if (method === 'GET' && path === '/user') {
      return ok({ user: this.user, store: this.userStore(), sms_verification_required: false })
    }
    if (method === 'GET' && path === '/stores') return ok([this.storeListItem()])
    if (method === 'GET' && path === '/catalog') return this.catalog(query)
    if (method === 'GET' && path === '/couriers') return ok([])
    // The unread badge of the home screen's bell: no notifications
    if (method === 'GET' && path === '/notifications') return ok({ items: [], unread_count: 0, pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
    if (method === 'POST' && path === '/notifications/read') return ok(null)
    if (method === 'GET' && path === '/geocode/search') return this.geocodeSearch(query)
    if (method === 'GET' && path === '/geocode/reverse') return this.geocodeReverse(query)
    if (method === 'GET' && path === '/orders') return this.listOrders(query)
    if (method === 'GET' && path === '/catalog/availability') return this.availabilityReply(query)
    if (method === 'GET' && path === '/delivery/quote') return this.deliveryQuote(query)
    if (method === 'POST' && path === '/orders') return this.placeOrder(body ?? {}, idempotencyKey)

    const staffReply = this.staff.handle(method, path, query, body)
    if (staffReply) return staffReply

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
    return ok({ store: { ...this.store, branches: [BRANCH], ratings: [] }, categories: [CATEGORY], products: this.products, availability: this.availabilityBranches() })
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

  // GET /geocode/search?q=
  private geocodeSearch(query: Record<string, string>): ApiReply {
    if (this.geocoderDown) return reply(503, { success: false, message: 'Manzil qidirish xizmati hozir ishlamayapti.', data: [] })
    const q = (query.q ?? '').trim().toLowerCase()
    if (q.length < 3) return fail(422, 'The given data was invalid.', { q: ['The q field must be at least 3 characters.'] })
    return ok(this.places.filter((place) => place.address.toLowerCase().includes(q)))
  }

  // GET /geocode/reverse?lat=&lng=
  private geocodeReverse(query: Record<string, string>): ApiReply {
    if (this.geocoderDown) return reply(503, { success: false, message: 'Manzil qidirish xizmati hozir ishlamayapti.', data: [] })
    const lat = Number(query.lat)
    const lng = Number(query.lng)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return fail(422, 'The given data was invalid.', { lat: ['The lat field is required.'] })
    if (this.reverseAddress === null) return fail(404, "Bu nuqta uchun manzil topilmadi. Manzilni qo'lda kiriting.")
    return ok({ address: this.reverseAddress, lat, lng })
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

  // The branch's availability, shaped like MenuAvailability::forStore
  private availabilityBranches() {
    return [
      {
        branch_id: BRANCH.id,
        stopped: { products: [], variants: this.stoppedVariantIds, modifiers: this.stoppedModifierIds },
        sold_out: { variants: [] },
        stop_list: [],
        batches: [],
      },
    ]
  }

  // GET /catalog/availability?store_id=
  private availabilityReply(query: Record<string, string>): ApiReply {
    if (query.store_id !== this.store.uuid && query.store_id !== String(this.store.id)) return fail(404, 'Store not found.')
    return ok({ store_id: this.store.id, branches: this.availabilityBranches(), generated_at: new Date().toISOString() })
  }

  /** The zone covers the branch's service area; a point outside it is "outside_zone". */
  private zoneCovers(lat: number, lng: number): boolean {
    return distanceKm({ lat, lng }, { lat: Number(BRANCH.latitude), lng: Number(BRANCH.longitude) }) <= BRANCH.radius_km
  }

  // GET /delivery/quote?store=&lat=&lng=&subtotal= (docs/ZONES.md)
  private deliveryQuote(query: Record<string, string>): ApiReply {
    if (query.store !== this.store.uuid && query.store !== String(this.store.id)) return fail(404, "Do'kon topilmadi.")
    const lat = Number(query.lat)
    const lng = Number(query.lng)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return fail(422, 'The given data was invalid.', { lat: ['The lat field is required.'] })
    if (!this.zonesConfigured) return reply(422, { success: false, message: "Bu do'kon uchun yetkazib berish hududlari hali sozlanmagan.", data: { reason: 'zones_not_configured' } })
    if (!this.zoneCovers(lat, lng)) return reply(422, { success: false, message: 'Manzil yetkazib berish hududidan tashqarida.', data: { reason: 'outside_zone' } })

    const subtotal = query.subtotal === undefined ? null : Number(query.subtotal)
    const fee = this.store.delivery_fee
    return ok({
      zone: { id: 7, name: 'Markaz', delivery_price: fee.toFixed(2), min_order_amount: this.zoneMinOrder.toFixed(2), free_delivery_from: null, eta_minutes: 40 },
      branch: { id: BRANCH.id, name: BRANCH.name, distance_km: 1.2 },
      delivery_price: fee.toFixed(2),
      min_order_amount: this.zoneMinOrder.toFixed(2),
      free_delivery_applies: false,
      meets_min_order: subtotal === null ? null : subtotal >= this.zoneMinOrder,
      eta: { minutes: 40 },
    })
  }

  // POST /orders
  private placeOrder(body: Record<string, unknown>, idempotencyKey: string | null): ApiReply {
    type Line = { variant_id: string; quantity: number; modifiers?: Array<{ id: string; quantity?: number } | string> }
    const lines = Array.isArray(body.items) ? (body.items as Line[]) : []
    const items: Order['items'] = []

    for (const [index, line] of lines.entries()) {
      const variant = this.products.flatMap((product) => product.variants).find((v) => v.uuid === line.variant_id)
      const product = this.products.find((p) => p.id === variant?.product_id)
      if (!variant || !product) return fail(400, 'Product variant not found or unavailable.')
      const name = `${product.name}${variant.name ? ` (${variant.name})` : ''}`

      if (this.stoppedVariantIds.includes(variant.id)) {
        return fail(422, 'The given data was invalid.', { items: [`Uzr, '${name}' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang.`] })
      }

      // Pieces are whole; a weighed item moves in its step and is at least its minimum
      const measured = variant.unit === 'kg' || variant.unit === 'l'
      const step = Number(variant.quantity_step ?? 0.1)
      if (!measured && !Number.isInteger(line.quantity)) {
        return fail(422, 'The given data was invalid.', { [`items.${index}.quantity`]: [`'${name}' dona bilan sotiladi: miqdor butun son bo'lishi kerak.`] })
      }
      if (measured && (line.quantity < Number(variant.min_quantity ?? step) || Math.abs(Math.round(line.quantity / step) * step - line.quantity) > 1e-9)) {
        return fail(422, 'The given data was invalid.', { [`items.${index}.quantity`]: [`'${name}' miqdori ${step} kg qadam bilan bo'lishi kerak.`] })
      }

      // The modifiers: belonging, stop-list, and the group rules (the backend's OrderLineBuilder)
      const chosen = (line.modifiers ?? []).map((choice) => (typeof choice === 'string' ? { id: choice, quantity: 1 } : { id: choice.id, quantity: choice.quantity ?? 1 }))
      const groups = product.modifier_groups ?? []
      const snapshot: NonNullable<Order['items']>[number]['modifiers'] = []
      let modifiersTotal = 0
      for (const choice of chosen) {
        const group = groups.find((g) => g.modifiers.some((m) => m.uuid === choice.id))
        const modifier = group?.modifiers.find((m) => m.uuid === choice.id)
        if (!group || !modifier) return fail(422, 'The given data was invalid.', { [`items.${index}.modifiers`]: [`Tanlangan qo'shimcha '${name}' taomiga tegishli emas yoki hozir sotilmaydi. Iltimos, savatni yangilang.`] })
        if (this.stoppedModifierIds.includes(modifier.id)) {
          return fail(422, 'The given data was invalid.', { [`items.${index}.modifiers`]: [`Uzr, '${modifier.name}' hozir ushbu filialda mavjud emas. Iltimos, boshqasini tanlang.`] })
        }
        modifiersTotal += Number(modifier.price_delta) * choice.quantity
        snapshot?.push({ id: modifier.id, uuid: modifier.uuid, group_id: group.id, group_name: group.name, name: modifier.name, price_delta: modifier.price_delta, quantity: choice.quantity, total: (Number(modifier.price_delta) * choice.quantity).toFixed(2) })
      }
      for (const group of groups) {
        const count = chosen.filter((choice) => group.modifiers.some((m) => m.uuid === choice.id)).reduce((sum, choice) => sum + choice.quantity, 0)
        const min = group.is_required ? Math.max(1, group.min_select) : group.min_select
        if (count < min) return fail(422, 'The given data was invalid.', { [`items.${index}.modifiers`]: [`'${name}': '${group.name}' tanlovi majburiy.`] })
        if (group.max_select !== null && count > group.max_select) {
          return fail(422, 'The given data was invalid.', { [`items.${index}.modifiers`]: [`'${name}': '${group.name}' guruhidan ko'pi bilan ${group.max_select} ta tanlash mumkin.`] })
        }
      }

      // price × quantity + modifiers × (pieces ? quantity : 1), rounded once, half up, to whole so'm (docs/MENU.md)
      const exact = Number(variant.price) * line.quantity + modifiersTotal * (measured ? 1 : line.quantity)
      const total = Math.floor(exact + 0.5)
      items.push(makeOrderItem({
        id: index + 1,
        product_variant_id: variant.id,
        product_name: product.name,
        variant_name: variant.name,
        quantity: line.quantity,
        unit: variant.unit ?? 'piece',
        unit_price: variant.price,
        modifiers: snapshot,
        modifiers_total: modifiersTotal.toFixed(2),
        total_price: total.toFixed(2),
      }))
    }
    if (items.length === 0) return fail(422, 'The given data was invalid.', { items: ['Savat bo\'sh.'] })

    const isDelivery = body.type === 'delivery'
    // A delivery order is priced by the zone that covers the point, and refused (422 data.reason) when none does
    if (isDelivery && typeof body.latitude === 'number' && typeof body.longitude === 'number') {
      if (!this.zoneCovers(body.latitude, body.longitude)) {
        // A store without zones keeps the old radius rule (a 400 with the message)
        if (!this.zonesConfigured) return fail(400, 'Uzr, siz kiritgan manzil ushbu restoranning yetkazib berish hududidan tashqarida joylashgan.')
        return reply(422, { success: false, message: 'Manzil yetkazib berish hududidan tashqarida.', data: { reason: 'outside_zone' } })
      }
    }
    const subtotal = items.reduce((sum, item) => sum + Number(item.total_price), 0)
    if (isDelivery && this.zonesConfigured && subtotal < this.zoneMinOrder) {
      return reply(422, { success: false, message: 'Bu hududda eng kam buyurtma summasi.', data: { reason: 'below_minimum_order', min_order_amount: this.zoneMinOrder.toFixed(2), subtotal: subtotal.toFixed(2) } })
    }
    const deliveryPrice = isDelivery ? this.store.delivery_fee : 0
    const address = typeof body.address === 'string' ? body.address : null
    this.idempotencyKeys.push(idempotencyKey)
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
