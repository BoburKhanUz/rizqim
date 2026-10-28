// The staff side of the e2e fake backend (P2-25): the staff order board (GET /staff/orders*), the stop-list, the batches
// ("qozon"), branches, staff accounts and the owner's product list, shaped like the real answers (docs/API.md 9.1 and 12).
// FakeApi (e2e/fakeApi.ts) hands every request it does not know to FakeStaff.handle().
import type { ApiUser, ModifierGroup, Order, OrderStatus, Product } from '../src/types'

export interface StaffReply {
  status: number
  body: unknown
}

export interface FakeBranch {
  id: number
  name: string
  phone: string | null
  address: string | null
  region_id: number | null
  district_id: number | null
  latitude: number | null
  longitude: number | null
  radius_km: number
  status: 'active' | 'inactive' | 'temporarily_closed'
  is_active: boolean
  working_hours: { open: string | null; close: string | null; all_day: boolean; overnight: boolean; timezone: string }
  is_open_now: boolean
  created_at: string | null
  updated_at: string | null
}

export interface FakeStopEntry {
  id: number
  branch_id: number
  type: string
  subject_id: number
  subject_uuid: string | null
  name: string | null
  reason: string | null
  until: string | null
  created_by: number | null
  created_at: string | null
}

export interface FakeBatch {
  id: number
  uuid: string
  branch_id: number
  product_id: number
  product_name: string
  product_variant_id: number | null
  variant_name: string | null
  unit: string | null
  label: string | null
  quantity_produced: number | null
  quantity_left: number | null
  is_open: boolean
  started_at: string | null
  sold_out_at: string | null
  closed_at: string | null
}

export interface FakeStaffMember {
  id: number
  name: string
  role: string
  role_label: string
  branch: { id: number; name: string } | null
  courier_type: string | null
  phone: string | null
  login_phone_enabled: boolean
  has_pin: boolean
  staff_code: string | null
  telegram_linked: boolean
  invite_pending: boolean
  can_edit: boolean
  created_at: string | null
  // Not in the API's answer: the person proved the phone by SMS themselves, so the owner may not change it
  smsProven?: boolean
}

// What a test can say about the kitchen side of one order (by uuid); the rest of the row comes from the order itself
export interface OrderExtras {
  // Ids of the order lines the cook has marked ready
  readyItemIds?: number[]
  // The order was accepted this many minutes ago (the waiting time)
  acceptedMinutesAgo?: number
  hallRef?: string
  tableRef?: string
  courier?: { id: number; name: string; phone: string | null }
}

// What FakeStaff needs from the FakeApi it lives in
export interface StaffContext {
  orders: () => Order[]
  user: () => ApiUser
  products: () => Product[]
  modifierGroups: () => ModifierGroup[]
  // POST /orders/{uuid}/status of the fake: moves the order along (or refuses it, 422)
  changeStatus: (order: Order, body: Record<string, unknown>) => StaffReply
}

const FINISHED: readonly OrderStatus[] = ['delivered', 'canceled', 'rejected', 'refunded']

const STATUS_LABELS: Partial<Record<OrderStatus, string>> = {
  new: 'Yangi',
  accepted: 'Qabul qilindi',
  preparing: 'Tayyorlanmoqda',
  ready: 'Tayyor',
  delivered: 'Yetkazildi',
  canceled: 'Bekor qilindi',
  rejected: 'Rad etildi',
}

const ROLE_LABELS: Record<string, string> = { manager: 'Menejer', cashier: 'Kassir', waiter: 'Ofitsiant', cook: 'Oshpaz', courier: 'Kuryer' }

// Pins the backend refuses as too easy (StaffPinService)
const EASY_PINS = ['0000', '1111', '1234', '2580']

const ok = (data: unknown, extra: Record<string, unknown> = {}): StaffReply => ({ status: 200, body: { success: true, message: '', data, ...extra } })
const created = (data: unknown): StaffReply => ({ status: 201, body: { success: true, message: '', data } })
const fail = (status: number, message: string, errors?: Record<string, string[]>): StaffReply => ({ status, body: errors ? { success: false, message, errors } : { success: false, message } })

const iso = (minutesAgo: number) => new Date(Date.now() - minutesAgo * 60_000).toISOString()

function makeBranch(id: number, name: string, address: string): FakeBranch {
  return {
    id,
    name,
    phone: '+998712001122',
    address,
    region_id: 1,
    district_id: 1,
    latitude: 41.2753,
    longitude: 69.2037,
    radius_km: 15,
    status: 'active',
    is_active: true,
    working_hours: { open: '09:00', close: '22:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' },
    is_open_now: true,
    created_at: null,
    updated_at: null,
  }
}

export class FakeStaff {
  readonly branches: FakeBranch[] = [makeBranch(1, 'Chilonzor filiali', 'Chilonzor, 9-kvartal'), makeBranch(2, 'Yunusobod filiali', 'Yunusobod, 4-kvartal')]
  stopList: FakeStopEntry[] = []
  batches: FakeBatch[] = []
  staff: FakeStaffMember[] = [
    this.member({ id: 11, name: 'Bobur Menejer', role: 'manager', branch: { id: 1, name: 'Chilonzor filiali' }, phone: '+998901112233', login_phone_enabled: true, has_pin: true, staff_code: 'M-11', telegram_linked: true }),
    this.member({ id: 12, name: 'Aziza Oshpaz', role: 'cook', branch: { id: 1, name: 'Chilonzor filiali' }, phone: '+998907776655', login_phone_enabled: true, smsProven: true }),
    this.member({ id: 13, name: 'Sardor Kuryer', role: 'courier', branch: { id: 2, name: 'Yunusobod filiali' }, invite_pending: true }),
  ]
  readonly extras: Record<string, OrderExtras> = {}
  /** The status changes asked of POST /staff/orders/{uuid}/status */
  private nextId = 100
  private readonly ctx: StaffContext

  constructor(ctx: StaffContext) {
    this.ctx = ctx
  }

  member(fields: Partial<FakeStaffMember> & Pick<FakeStaffMember, 'id' | 'name' | 'role'>): FakeStaffMember {
    return {
      role_label: ROLE_LABELS[fields.role] ?? fields.role,
      branch: null,
      courier_type: fields.role === 'courier' ? 'own' : null,
      phone: null,
      login_phone_enabled: false,
      has_pin: false,
      staff_code: null,
      telegram_linked: false,
      invite_pending: false,
      can_edit: true,
      created_at: null,
      ...fields,
    }
  }

  // The branch a branch-bound caller works in; null for the owner
  private boundBranch(): number | null {
    const user = this.ctx.user()
    return user.role === 'owner' ? null : user.branch_id
  }

  private branchName(id: number | null): string | null {
    return this.branches.find((branch) => branch.id === id)?.name ?? null
  }

  // --- orders (docs/API.md 9.1) ----------------------------------------------------------------------------------

  private row(order: Order) {
    const extras = this.extras[order.uuid] ?? {}
    const items = (order.items ?? []).map((item) => ({
      id: item.id,
      product_name: item.product_name,
      variant_name: item.variant_name,
      quantity: item.quantity,
      unit: item.unit ?? 'piece',
      quantity_label: `${item.quantity}${item.unit && item.unit !== 'piece' ? ` ${item.unit}` : ''}`,
      modifiers: (item.modifiers ?? []).map((modifier) => ({ name: modifier.name, group_name: modifier.group_name, quantity: modifier.quantity, price_delta: modifier.price_delta })),
      kitchen_status: extras.readyItemIds?.includes(item.id) ? 'ready' : 'pending',
      ready_at: extras.readyItemIds?.includes(item.id) ? iso(1) : null,
      unit_price: item.unit_price,
      modifiers_total: item.modifiers_total ?? '0.00',
      total_price: item.total_price,
    }))
    const shipping = order.shipping_address
    return {
      uuid: order.uuid,
      short_code: order.short_code,
      status: order.status,
      status_label: STATUS_LABELS[order.status] ?? order.status,
      type: order.order_type,
      channel: 'telegram',
      store_id: order.store_id,
      branch_id: order.branch_id,
      branch_name: this.branchName(order.branch_id),
      payment_method: order.payment_method,
      payment_status: order.payment_status,
      subtotal: order.subtotal,
      discount: order.discount,
      delivery_price: order.delivery_price,
      service_charge_amount: '0.00',
      place_fee_amount: '0.00',
      total_price: order.total_price,
      notes: order.notes,
      guests_count: null,
      hall_ref: extras.hallRef ?? null,
      table_ref: extras.tableRef ?? null,
      cancel_reason: order.cancel_reason,
      cancel_note: order.cancel_note,
      customer: { name: 'Mijoz Test', phone: '+998909998877' },
      delivery: order.address ? { address: order.address, latitude: shipping?.latitude ?? null, longitude: shipping?.longitude ?? null, zone_name: 'Markaz' } : null,
      courier: extras.courier ?? null,
      items_total: items.length,
      items_ready: items.filter((item) => item.kitchen_status === 'ready').length,
      items,
      created_at: order.created_at,
      updated_at: order.updated_at,
      accepted_at: extras.acceptedMinutesAgo === undefined ? null : iso(extras.acceptedMinutesAgo),
      preparing_at: null,
      ready_at: null,
      closed_at: FINISHED.includes(order.status) ? order.updated_at : null,
    }
  }

  private visibleOrders(query: Record<string, string>): Order[] {
    const bound = this.boundBranch()
    const wanted = query.branch_id ? Number(query.branch_id) : null
    return this.ctx.orders().filter((order) => {
      if (order.store_id !== this.ctx.user().store_id) return false
      if (bound !== null && order.branch_id !== bound) return false
      if (wanted !== null && order.branch_id !== wanted) return false
      return query.scope === 'history' ? FINISHED.includes(order.status) : !FINISHED.includes(order.status)
    })
  }

  private listOrders(query: Record<string, string>): StaffReply {
    const all = this.visibleOrders(query)
    const perPage = Number(query.per_page) || 20
    const offset = Number(query.cursor) || 0
    const end = offset + perPage
    return ok(all.slice(offset, end).map((order) => this.row(order)), { meta: { next_cursor: end < all.length ? String(end) : null, per_page: perPage } })
  }

  private showOrder(uuid: string): StaffReply {
    const order = this.ctx.orders().find((candidate) => candidate.uuid === uuid)
    const bound = this.boundBranch()
    if (!order || order.store_id !== this.ctx.user().store_id || (bound !== null && order.branch_id !== bound)) return fail(404, 'Buyurtma topilmadi.')
    const history: Array<{ from_status: string | null; status: string; created_at: string }> = [{ from_status: null, status: 'new', created_at: order.created_at }]
    if (order.status !== 'new') history.push({ from_status: 'new', status: order.status, created_at: order.updated_at })
    return ok({ ...this.row(order), status_history: history })
  }

  // --- the stop-list and the batches -------------------------------------------------------------------------------

  // The branch of a stop-list / batches request: the owner names one, a branch-bound caller works in their own
  private requestBranch(query: Record<string, string>, bodyBranch?: unknown): number | StaffReply {
    const bound = this.boundBranch()
    if (bound !== null) return bound
    const id = Number(query.branch_id ?? bodyBranch)
    if (!id) return fail(422, 'The given data was invalid.', { branch_id: ['Filialni tanlang.'] })
    return id
  }

  private nameOf(type: string, id: number): string | null {
    if (type === 'product') return this.ctx.products().find((product) => product.id === id)?.name ?? null
    if (type === 'variant') return this.ctx.products().flatMap((product) => product.variants).find((variant) => variant.id === id)?.name ?? null
    return this.ctx.modifierGroups().flatMap((group) => group.modifiers).find((modifier) => modifier.id === id)?.name ?? null
  }

  private addStop(body: Record<string, unknown>): StaffReply {
    const branch = this.requestBranch({}, body.branch_id)
    if (typeof branch !== 'number') return branch
    const type = String(body.type)
    const id = Number(body.id)
    if (!['product', 'variant', 'modifier'].includes(type) || this.nameOf(type, id) === null) return fail(422, 'The given data was invalid.', { id: ['Tanlangan element topilmadi.'] })
    const existing = this.stopList.find((entry) => entry.branch_id === branch && entry.type === type && entry.subject_id === id)
    if (existing) return created(existing)
    const entry: FakeStopEntry = { id: ++this.nextId, branch_id: branch, type, subject_id: id, subject_uuid: null, name: this.nameOf(type, id), reason: null, until: null, created_by: this.ctx.user().id, created_at: iso(0) }
    this.stopList.push(entry)
    return created(entry)
  }

  private openBatch(body: Record<string, unknown>): StaffReply {
    const branch = this.requestBranch({}, body.branch_id)
    if (typeof branch !== 'number') return branch
    const product = this.ctx.products().find((candidate) => candidate.id === Number(body.product_id))
    const quantity = Number(body.quantity)
    if (!product) return fail(422, 'The given data was invalid.', { product_id: ['Taom topilmadi.'] })
    if (!(quantity > 0)) return fail(422, 'The given data was invalid.', { quantity: ["Miqdor noldan katta bo'lsin."] })
    if (this.batches.some((batch) => batch.is_open && batch.branch_id === branch && batch.product_id === product.id)) {
      return fail(422, 'The given data was invalid.', { product_id: [`'${product.name}' uchun bu filialda qozon allaqachon ochiq.`] })
    }
    const variant = body.product_variant_id ? product.variants.find((candidate) => candidate.id === Number(body.product_variant_id)) : null
    const batch: FakeBatch = {
      id: ++this.nextId,
      uuid: `batch-${this.nextId}`,
      branch_id: branch,
      product_id: product.id,
      product_name: product.name,
      product_variant_id: variant?.id ?? null,
      variant_name: variant?.name ?? null,
      unit: variant?.unit ?? 'piece',
      label: typeof body.label === 'string' ? body.label : null,
      quantity_produced: quantity,
      quantity_left: quantity,
      is_open: true,
      started_at: iso(0),
      sold_out_at: null,
      closed_at: null,
    }
    this.batches.push(batch)
    return created(batch)
  }

  private adjustBatch(id: number, body: Record<string, unknown>): StaffReply {
    const batch = this.batches.find((candidate) => candidate.id === id && candidate.is_open)
    if (!batch) return fail(404, 'Qozon topilmadi.')
    if (typeof body.add === 'number') batch.quantity_left = (batch.quantity_left ?? 0) + body.add
    else if (typeof body.quantity_left === 'number') {
      if (body.quantity_left > (batch.quantity_produced ?? 0)) return fail(422, 'The given data was invalid.', { quantity_left: ["Qoldiq ishlab chiqarilgandan ko'p bo'lmasin."] })
      batch.quantity_left = body.quantity_left
    }
    batch.quantity_produced = Math.max(batch.quantity_produced ?? 0, batch.quantity_left ?? 0)
    batch.sold_out_at = (batch.quantity_left ?? 0) <= 0 ? iso(0) : null
    return ok(batch)
  }

  // --- branches and staff --------------------------------------------------------------------------------------------

  private patchBranch(id: number, body: Record<string, unknown>): StaffReply {
    const branch = this.branches.find((candidate) => candidate.id === id)
    if (!branch) return fail(404, 'Filial topilmadi.')
    const bound = this.boundBranch()
    if (bound !== null && bound !== id) return fail(403, "Siz faqat o'z filialingizni o'zgartira olasiz.")
    const hasOpen = 'open_time' in body
    const hasClose = 'close_time' in body
    if (hasOpen !== hasClose || (hasOpen && (body.open_time === null) !== (body.close_time === null))) {
      return fail(422, 'The given data was invalid.', { close_time: ["Ochilish va yopilish vaqti birga kiritiladi."] })
    }
    if (hasOpen) {
      const open = (body.open_time as string | null) ?? null
      const close = (body.close_time as string | null) ?? null
      branch.working_hours = { open, close, all_day: open === null, overnight: open !== null && close !== null && close < open, timezone: 'Asia/Tashkent' }
    }
    if (typeof body.status === 'string') branch.status = body.status as FakeBranch['status']
    branch.is_open_now = branch.status === 'active'
    return ok(branch)
  }

  private createStaff(body: Record<string, unknown>): StaffReply {
    const phone = typeof body.phone === 'string' && body.phone !== '' ? body.phone : null
    if (phone && this.staff.some((member) => member.phone === phone)) return fail(422, 'The given data was invalid.', { phone: ["Bu raqam do'koningizning boshqa xodimiga biriktirilgan."] })
    const branch = this.branches.find((candidate) => candidate.id === Number(body.branch_id))
    const created_ = this.member({
      id: ++this.nextId,
      name: String(body.name),
      role: String(body.role),
      branch: branch ? { id: branch.id, name: branch.name } : null,
      phone,
      login_phone_enabled: phone !== null,
      invite_pending: true,
    })
    this.staff.push(created_)
    return created({ staff: created_, invite_link: `https://t.me/rizqimbot?start=invite_${created_.id}` })
  }

  private patchStaff(id: number, body: Record<string, unknown>): StaffReply {
    const member = this.staff.find((candidate) => candidate.id === id)
    if (!member) return fail(404, 'Xodim topilmadi.')
    if ('phone' in body) {
      if (member.smsProven) {
        return fail(422, 'The given data was invalid.', { phone: ["Bu raqamni xodimning o'zi SMS kod bilan tasdiqlagan, uni bu yerda o'zgartirib yoki o'chirib bo'lmaydi."] })
      }
      member.phone = (body.phone as string | null) ?? null
      member.login_phone_enabled = member.phone !== null
    }
    if (typeof body.name === 'string') member.name = body.name
    if (typeof body.branch_id === 'number') {
      const branch = this.branches.find((candidate) => candidate.id === body.branch_id)
      if (!branch) return fail(422, 'The given data was invalid.', { branch_id: ['Filial topilmadi.'] })
      member.branch = { id: branch.id, name: branch.name }
    }
    return ok(member)
  }

  private setPin(id: number, body: Record<string, unknown>): StaffReply {
    const member = this.staff.find((candidate) => candidate.id === id)
    if (!member) return fail(404, 'Xodim topilmadi.')
    const pin = String(body.pin ?? '')
    if (!/^\d{4,6}$/.test(pin)) return fail(422, "PIN 4–6 ta raqamdan iborat bo'lsin.", { pin: ["PIN 4–6 ta raqamdan iborat bo'lsin."] })
    if (EASY_PINS.includes(pin)) return fail(422, 'PIN juda oddiy.', { pin: ['PIN juda oddiy: boshqasini tanlang.'] })
    member.has_pin = true
    member.staff_code = member.staff_code ?? `K-${member.id}`
    return ok({ user_id: id, staff_code: member.staff_code, pin_set_at: iso(0) })
  }

  // --- the owner's product list (the product screen reads it) --------------------------------------------------------

  private ownerProducts() {
    return this.ctx.products().map((product) => ({ ...product, batch_tracked: product.id === 5, category: { id: 1, name: 'Fast food' } }))
  }

  // Returns the answer, or null when the request is not one of the staff endpoints
  handle(method: string, path: string, query: Record<string, string>, body: Record<string, unknown> | null): StaffReply | null {
    const input = body ?? {}
    let match: RegExpExecArray | null

    if (method === 'GET' && path === '/staff/orders') return this.listOrders(query)
    if ((match = /^\/staff\/orders\/([^/]+)$/.exec(path)) && method === 'GET') return this.showOrder(match[1])
    if ((match = /^\/staff\/orders\/([^/]+)\/status$/.exec(path)) && method === 'POST') {
      const order = this.ctx.orders().find((candidate) => candidate.uuid === match![1])
      return order ? this.ctx.changeStatus(order, input) : fail(404, 'Buyurtma topilmadi.')
    }

    if (method === 'GET' && path === '/owner/branches') {
      const bound = this.boundBranch()
      return ok(this.branches.filter((branch) => bound === null || branch.id === bound))
    }
    if ((match = /^\/owner\/branches\/(\d+)$/.exec(path)) && method === 'PATCH') return this.patchBranch(Number(match[1]), input)

    if (method === 'GET' && path === '/owner/branch-variants') {
      return ok(
        this.ctx.products().map((product, index) => ({
          product_id: product.id,
          name: product.name,
          description: null,
          image_url: null,
          variants: product.variants.map((variant) => ({ variant_id: variant.id, variant_uuid: variant.uuid, name: variant.name, price: variant.price, is_active: true, hidden_reason: null, sort_order: index })),
        })),
      )
    }
    if (method === 'GET' && path === '/owner/modifier-groups') {
      return ok(
        this.ctx.modifierGroups().map((group) => ({
          ...group,
          name_i18n: null,
          is_active: true,
          modifiers: group.modifiers.map((modifier) => ({ ...modifier, name_i18n: null, product_variant_id: null })),
          product_ids: this.ctx.products().filter((product) => product.modifier_groups?.some((candidate) => candidate.id === group.id)).map((product) => product.id),
        })),
      )
    }

    if (method === 'GET' && path === '/owner/stop-list') {
      const branch = this.requestBranch(query)
      return typeof branch === 'number' ? ok({ branch_id: branch, entries: this.stopList.filter((entry) => entry.branch_id === branch) }) : branch
    }
    if (method === 'POST' && path === '/owner/stop-list') return this.addStop(input)
    if ((match = /^\/owner\/stop-list\/(\d+)$/.exec(path)) && method === 'DELETE') {
      this.stopList = this.stopList.filter((entry) => entry.id !== Number(match![1]))
      return ok(null)
    }

    if (method === 'GET' && path === '/owner/batches') {
      const branch = this.requestBranch(query)
      return typeof branch === 'number' ? ok({ branch_id: branch, batches: this.batches.filter((batch) => batch.branch_id === branch && (batch.is_open || query.include_closed)) }) : branch
    }
    if (method === 'POST' && path === '/owner/batches') return this.openBatch(input)
    if ((match = /^\/owner\/batches\/(\d+)\/adjust$/.exec(path)) && method === 'POST') return this.adjustBatch(Number(match[1]), input)
    if ((match = /^\/owner\/batches\/(\d+)\/close$/.exec(path)) && method === 'POST') {
      const batch = this.batches.find((candidate) => candidate.id === Number(match![1]))
      if (!batch) return fail(404, 'Qozon topilmadi.')
      batch.is_open = false
      batch.closed_at = iso(0)
      return ok(batch)
    }

    if (method === 'GET' && path === '/owner/staff') return ok(this.staff)
    if (method === 'POST' && path === '/owner/staff') return this.createStaff(input)
    if ((match = /^\/owner\/staff\/(\d+)$/.exec(path)) && method === 'PATCH') return this.patchStaff(Number(match[1]), input)
    if ((match = /^\/owner\/staff\/(\d+)\/pin$/.exec(path))) {
      if (method === 'PUT') return this.setPin(Number(match[1]), input)
      if (method === 'DELETE') {
        const member = this.staff.find((candidate) => candidate.id === Number(match![1]))
        if (member) member.has_pin = false
        return ok(null)
      }
    }
    if ((match = /^\/owner\/employees\/(\d+)$/.exec(path)) && method === 'DELETE') {
      this.staff = this.staff.filter((member) => member.id !== Number(match![1]))
      return ok(null)
    }

    if (method === 'GET' && path === '/owner/products') return ok(this.ownerProducts())
    if (method === 'GET' && path === '/owner/categories') return ok([{ id: 1, uuid: 'c0000000-0000-4000-8000-000000000001', name: 'Fast food', slug: 'fast-food', parent_id: null, image: null, image_url: null }])
    if ((match = /^\/owner\/products\/(\d+)$/.exec(path)) && method === 'POST') return ok(this.ownerProducts().find((product) => product.id === Number(match![1])) ?? null)

    return null
  }
}
