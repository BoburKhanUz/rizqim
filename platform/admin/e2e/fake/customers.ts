// The fake of Mijozlar va yordam (P4-31): /admin/users (list, card, block, unblock, close sessions), /complaints (inbox,
// card, take / assign, reply, resolve), /support/canned-replies, /support/suspicious, shaped like the AdminCustomer*,
// AdminComplaint* and AdminSupport* controllers answer. The rules the screens depend on are here: users.view reads users,
// support.view reads complaints and the suspicious cases, support.manage changes; block, unblock and closing sessions also a
// step-up and a reason (3+ characters); a reply needs the customer's Telegram; RIZQIM team accounts are not users here.
// User 7 (Olim Egamov) is the search palette's user (e2e/fake/backend.ts).
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const iso = (minutesAgo: number) => new Date(Date.now() - minutesAgo * 60_000).toISOString()
const mask = (phone: string) => `${phone.slice(0, 4)} ${phone.slice(4, 6)} *** ** ${phone.slice(-2)}`
const SUPPORT_LABEL: Record<string, string> = { new: 'Yangi', in_progress: "Ko'rib chiqilmoqda", answered: 'Javob berildi', resolved: 'Hal qilindi' }
const TYPE_LABEL: Record<string, string> = { QUALITY_ISSUE: 'Sifat muammosi', ORDER_NOT_RECEIVED: 'Buyurtma kelmadi', COURIER_ISSUE: 'Kuryer muammosi' }
const FLOW_LABEL: Record<string, string> = { pending: 'Ovqatxona javobini kutmoqda', owner_replied: 'Ovqatxona javob berdi', in_review: 'Mijoz RIZQIMga yubordi', closed: 'Yopilgan' }

export interface FakeUser {
  id: number
  name: string
  role: 'customer' | 'courier' | 'owner' | 'manager'
  phone: string
  telegram: boolean
  store: { id: number; name: string } | null
  orders: number
  blocked: { at: string; reason: string; by: { id: number; name: string } } | null
  sessions: number
  createdAt: string
}

export interface FakeComplaint {
  uuid: string
  type: string
  description: string
  supportStatus: 'new' | 'in_progress' | 'answered' | 'resolved'
  flow: string
  storeReply: string | null
  customerId: number
  order: { uuid: string; short_code: string }
  store: { id: number; name: string }
  assignee: { id: number; name: string } | null
  replies: Array<{ id: number; body: string; author: { id: number; name: string }; created_at: string }>
  resolution: { note: string; by: { id: number; name: string }; at: string } | null
  createdAt: string
}

const ROLE_LABEL: Record<FakeUser['role'], string> = { customer: 'Mijoz', courier: 'Kuryer', owner: 'Ega', manager: 'Menejer' }

export class FakeCustomers {
  users: FakeUser[] = [
    { id: 7, name: 'Olim Egamov', role: 'customer', phone: '+998901234567', telegram: true, store: null, orders: 12, blocked: null, sessions: 2, createdAt: iso(60 * 24 * 40) },
    { id: 8, name: 'Kuryer Bek', role: 'courier', phone: '+998935550022', telegram: true, store: { id: 12, name: 'Lazzat Oshxona' }, orders: 0, blocked: null, sessions: 1, createdAt: iso(60 * 24 * 20) },
    { id: 9, name: 'Telegramsiz Mijoz', role: 'customer', phone: '+998977001133', telegram: false, store: null, orders: 1, blocked: null, sessions: 0, createdAt: iso(60 * 24 * 5) },
  ]

  complaints: FakeComplaint[] = [
    {
      uuid: 'cc000000-1111-4222-8333-444455556666', type: 'QUALITY_ISSUE', description: 'Ovqat sovuq keldi, kuryer 1 soat kechikdi.', supportStatus: 'new', flow: 'in_review',
      storeReply: 'Uzr, kuryer adashib qoldi.', customerId: 7, order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD' }, store: { id: 12, name: 'Lazzat Oshxona' },
      assignee: null, replies: [], resolution: null, createdAt: iso(60 * 6),
    },
    {
      uuid: 'cc000001-1111-4222-8333-444455556666', type: 'ORDER_NOT_RECEIVED', description: 'Buyurtma kelmadi.', supportStatus: 'new', flow: 'pending',
      storeReply: null, customerId: 9, order: { uuid: 'ab99ef00-1111-4222-8333-444455556667', short_code: 'AB99EF' }, store: { id: 14, name: 'Shirin Qandolat' },
      assignee: null, replies: [], resolution: null, createdAt: iso(30),
    },
  ]

  canned: Array<{ id: number; title: string; body: string }> = [{ id: 1, title: 'Kechikish uchun uzr', body: "Buyurtmangiz kechikkani uchun uzr so'raymiz. Pulingiz qaytariladi." }]
  /** What reached the customers' Telegram */
  sent: Array<{ userId: number; text: string }> = []
  private nextId = 100

  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    const forbidden = fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })

    // --- Users (users.view; actions support.manage + step-up + reason)
    if (req.method === 'GET' && path === '/users') {
      if (!can('admin.users.view')) return forbidden
      let rows = [...this.users].sort((a, b) => b.id - a.id)
      const q = (req.query.q ?? '').trim().toLowerCase()
      if (req.query.role) rows = rows.filter((user) => (req.query.role === 'staff' ? ['owner', 'manager'].includes(user.role) : user.role === req.query.role))
      if (req.query.status === 'blocked') rows = rows.filter((user) => user.blocked)
      if (req.query.status === 'active') rows = rows.filter((user) => !user.blocked)
      if (q) {
        const digits = q.replace(/\D/g, '')
        // Without pii.view only the whole number finds anybody
        rows = rows.filter((user) => user.name.toLowerCase().includes(q) || (digits.length >= 9 && user.phone.endsWith(digits.slice(-9))))
      }
      return ok({ data: rows.map((user) => this.row(user)), next_cursor: null, per_page: 30, roles: { customer: 'Mijoz', courier: 'Kuryer', staff: 'Ovqatxona xodimlari (barchasi)' } })
    }
    const user = /^\/users\/(\d+)(?:\/(block|unblock|close-sessions))?$/.exec(path)
    if (user) {
      const found = this.users.find((candidate) => candidate.id === Number(user[1]))
      if (req.method === 'GET' && !user[2]) {
        if (!can('admin.users.view')) return forbidden
        return found ? ok(this.card(found, can)) : fail(404, 'Foydalanuvchi topilmadi.', { reason: 'not_found' })
      }
      if (req.method === 'POST' && user[2]) {
        if (!can('admin.support.manage')) return forbidden
        const recent = needsRecent()
        if (recent) return recent
        if (String(body.reason ?? '').trim().length < 3) return validation('reason', "Sabab kamida 3 ta belgidan iborat bo'lsin.")
        if (!found) return fail(404, 'Foydalanuvchi topilmadi.', { reason: 'not_found' })
        if (user[2] === 'block') {
          if (found.blocked) return fail(409, 'Hisob allaqachon bloklangan.', { reason: 'already_blocked' })
          found.blocked = { at: new Date().toISOString(), reason: String(body.reason).trim(), by: actor }
          found.sessions = 0
          return ok(this.card(found, can), { message: 'Foydalanuvchi bloklandi. Uning barcha sessiyalari yopildi.' })
        }
        if (user[2] === 'unblock') {
          if (!found.blocked) return fail(409, 'Hisob bloklanmagan.', { reason: 'not_blocked' })
          found.blocked = null
          return ok(this.card(found, can))
        }
        found.sessions = 0
        return ok(this.card(found, can))
      }
    }

    // --- Complaints (support.view; changes support.manage)
    if (req.method === 'GET' && path === '/complaints') {
      if (!can('admin.support.view')) return forbidden
      const status = req.query.status ?? 'open'
      let rows = [...this.complaints]
      if (status === 'open') rows = rows.filter((row) => row.supportStatus === 'new' || row.supportStatus === 'in_progress')
      else if (status !== 'all') rows = rows.filter((row) => row.supportStatus === status)
      if (req.query.assigned === 'none') rows = rows.filter((row) => !row.assignee)
      if (req.query.assigned === 'me') rows = rows.filter((row) => row.assignee?.id === actor.id)
      if (req.query.waiting === '1') rows = rows.filter((row) => this.waiting(row))
      rows.sort((a, b) => (status === 'open' ? a.createdAt.localeCompare(b.createdAt) : b.createdAt.localeCompare(a.createdAt)))
      const count = (value: string) => this.complaints.filter((row) => row.supportStatus === value).length
      return ok({
        data: rows.map((row) => this.complaintRow(row)),
        next_cursor: null,
        per_page: 30,
        counts: { new: count('new'), in_progress: count('in_progress'), answered: count('answered'), resolved: count('resolved'), open: count('new') + count('in_progress'), waiting: this.complaints.filter((row) => this.waiting(row)).length },
        waiting_hours: 4,
        assignees: [{ id: 1, name: 'Aziza Karimova' }, { id: 3, name: 'Madina Yangi' }],
        types: TYPE_LABEL,
        statuses: SUPPORT_LABEL,
      })
    }
    const complaint = /^\/complaints\/([0-9a-f-]{36})(?:\/(reply|resolve))?$/.exec(path)
    if (complaint) {
      if (!can('admin.support.view')) return forbidden
      const found = this.complaints.find((row) => row.uuid === complaint[1])
      if (!found) return fail(404, 'Shikoyat topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && !complaint[2]) return ok(this.complaintCard(found, can))
      if (!can('admin.support.manage')) return forbidden
      if (req.method === 'PATCH' && !complaint[2]) {
        if (typeof body.support_status === 'string') {
          if (found.supportStatus === 'resolved') {
            found.resolution = null
            found.flow = 'in_review'
          }
          found.supportStatus = body.support_status as FakeComplaint['supportStatus']
          if (found.supportStatus === 'in_progress' && !found.assignee && !('assigned_to' in body)) found.assignee = actor
        }
        if ('assigned_to' in body) found.assignee = body.assigned_to === null ? null : { id: Number(body.assigned_to), name: Number(body.assigned_to) === 3 ? 'Madina Yangi' : 'Aziza Karimova' }
        return ok(this.complaintCard(found, can))
      }
      if (req.method === 'POST' && complaint[2] === 'reply') {
        const text = String(body.text ?? '').trim()
        if (!text) return validation('text', 'Matnni yozing.')
        if (found.supportStatus === 'resolved') return fail(409, 'Shikoyat hal qilingan.', { reason: 'already_resolved' })
        const customer = this.users.find((candidate) => candidate.id === found.customerId)
        if (!customer?.telegram) return fail(422, "Mijozning Telegram akkaunti yo'q: javobni yuborib bo'lmaydi.", { reason: 'no_recipient' })
        found.replies.push({ id: ++this.nextId, body: text, author: actor, created_at: new Date().toISOString() })
        found.supportStatus = 'answered'
        found.assignee ??= actor
        this.sent.push({ userId: customer.id, text })
        return ok(this.complaintCard(found, can))
      }
      if (req.method === 'POST' && complaint[2] === 'resolve') {
        const note = String(body.note ?? '').trim()
        if (note.length < 3) return validation('note', "Izoh kamida 3 ta belgidan iborat bo'lsin.")
        if (found.supportStatus === 'resolved') return fail(409, 'Shikoyat allaqachon hal qilingan.', { reason: 'already_resolved' })
        found.supportStatus = 'resolved'
        found.flow = 'closed'
        found.resolution = { note, by: actor, at: new Date().toISOString() }
        found.assignee ??= actor
        return ok(this.complaintCard(found, can))
      }
    }

    // --- Tayyor javoblar and Shubhali holatlar
    if (path === '/support/canned-replies' || path.startsWith('/support/canned-replies/')) {
      if (!can('admin.support.view')) return forbidden
      if (req.method === 'GET') return ok(this.canned.map((reply, index) => ({ ...reply, sort_order: index, updated_by: null, updated_at: null })))
      if (!can('admin.support.manage')) return forbidden
      const id = Number(path.split('/')[3])
      if (req.method === 'POST') {
        if (!String(body.title ?? '').trim()) return validation('title', 'Nomini yozing.')
        const reply = { id: ++this.nextId, title: String(body.title).trim(), body: String(body.body ?? '').trim() }
        this.canned.push(reply)
        return { status: 201, body: { success: true, message: '', data: { ...reply, sort_order: 0, updated_by: actor, updated_at: new Date().toISOString() } } }
      }
      const reply = this.canned.find((candidate) => candidate.id === id)
      if (!reply) return fail(404, 'Tayyor javob topilmadi.', { reason: 'not_found' })
      if (req.method === 'PATCH') {
        Object.assign(reply, { ...(typeof body.title === 'string' ? { title: body.title } : {}), ...(typeof body.body === 'string' ? { body: body.body } : {}) })
        return ok({ ...reply, sort_order: 0, updated_by: actor, updated_at: new Date().toISOString() })
      }
      if (req.method === 'DELETE') {
        this.canned = this.canned.filter((candidate) => candidate.id !== id)
        return ok({ deleted: true })
      }
    }
    if (req.method === 'GET' && path === '/support/suspicious') {
      if (!can('admin.support.view')) return forbidden
      return ok({
        generated_at: new Date().toISOString(),
        groups: [
          {
            kind: 'cancellations',
            label: "Ko'p bekor qilish",
            description: 'Oxirgi 7 kunda kamida 3 ta buyurtmasi mijoz tufayli bekor qilingan mijozlar.',
            rows: [{ kind: 'cancellations', user: this.brief(7), accounts: [], reason: "7 kunda 4 ta buyurtma bekor qilindi: 3 tasini o'zi bekor qildi, 1 tasini olib ketmadi.", metric: 4, since: iso(120), href: '/users/7' }],
          },
          { kind: 'promo_sharing', label: "Promo-kodni ko'p hisobdan ishlatish", description: "Oxirgi 30 kunda bitta promo-kod bir xil telefon raqami yoki Telegram akkauntiga ega 2 va undan ko'p hisobdan ishlatilgan.", rows: [] },
          { kind: 'refunds', label: "O'rtachadan ko'p qaytarish", description: "Oxirgi 30 kunda qaytarish so'rovlari jami o'rtacha qaytarishdan kamida 3 baravar ko'p bo'lgan mijozlar.", rows: [] },
        ],
      })
    }
    return null
  }

  private waiting(row: FakeComplaint): boolean {
    return (row.supportStatus === 'new' || row.supportStatus === 'in_progress') && row.flow !== 'owner_replied' && row.flow !== 'closed' && Date.now() - Date.parse(row.createdAt) > 4 * 3_600_000
  }

  private brief(id: number) {
    const user = this.users.find((candidate) => candidate.id === id)!
    return { id, name: user.name, role_label: ROLE_LABEL[user.role], blocked: user.blocked !== null }
  }

  private row(user: FakeUser) {
    return {
      id: user.id, name: user.name, role: user.role, role_label: ROLE_LABEL[user.role], phone: mask(user.phone), telegram_linked: user.telegram, store: user.store, branch: null,
      orders_count: user.orders, blocked: user.blocked !== null, blocked_at: user.blocked?.at ?? null, created_at: user.createdAt,
    }
  }

  private card(user: FakeUser, can: (permission: string) => boolean) {
    const manage = can('admin.support.manage')
    const complaints = this.complaints.filter((row) => row.customerId === user.id)
    return {
      profile: {
        id: user.id, uuid: `7f7f7f7f-0000-4000-8000-${String(user.id).padStart(12, '0')}`, name: user.name, role: user.role, role_label: ROLE_LABEL[user.role], phone: mask(user.phone), phone_verified: true,
        telegram: { linked: user.telegram, username: user.telegram ? 'olim' : null }, courier_type: user.role === 'courier' ? { value: 'own', label: "O'z kuryeri" } : null, referred_by: null, referrals: 0, created_at: user.createdAt,
      },
      block: { blocked: user.blocked !== null, at: user.blocked?.at ?? null, reason: user.blocked?.reason ?? null, by: user.blocked?.by ?? null },
      stats: { orders: user.orders, delivered: Math.max(0, user.orders - 2), cancelled: Math.min(2, user.orders), delivered_sum: '850000.00', avg_check: user.orders > 2 ? '85000.00' : null, last_order_at: iso(60 * 5), segment: user.orders >= 10 ? { key: 'vip', label: 'Doimiy mijoz (VIP)' } : { key: 'new', label: 'Yangi mijoz' } },
      orders: can('admin.orders.view') ? [{ uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD', store: { id: 12, name: 'Lazzat Oshxona' }, status: 'delivered', status_label: 'Yetkazildi', total: '85000.00', payment_method: 'cash', created_at: iso(60 * 5), href: '/orders/ab12cd34-1111-4222-8333-444455556666' }] : null,
      refunds: can('admin.orders.view') ? [] : null,
      keshbek: { balance: '15000.00', entries: [{ kind: 'referral', label: "Do'st taklifi uchun bonus (Ali)", amount: '5000.00', order: null, at: iso(60 * 24) }] },
      complaints: can('admin.support.view')
        ? complaints.map((row) => ({ uuid: row.uuid, type: row.type, type_label: TYPE_LABEL[row.type], support_status: row.supportStatus, support_status_label: SUPPORT_LABEL[row.supportStatus], description: row.description, order: row.order, created_at: row.createdAt }))
        : null,
      sessions: {
        active_tokens: user.sessions, refresh_sessions: user.sessions > 0 ? 1 : 0, last_used_at: user.sessions > 0 ? iso(10) : null,
        tokens: Array.from({ length: user.sessions }, (_, index) => ({ id: index + 1, kind: 'mini_app', kind_label: 'Mini App / Telegram', device: null, created_at: iso(60 * 24), last_used_at: iso(10), expires_at: null })),
        devices: [],
      },
      membership: user.store ? { store: { ...user.store, status: 'active' }, branch: null, role: user.role, role_label: ROLE_LABEL[user.role], login_phone: true, login_phone_proof: 'sms', pin_set: false } : null,
      linked_accounts: [],
      audit: can('admin.audit.view') ? [] : null,
      actions: { block: manage && !user.blocked, unblock: manage && user.blocked !== null, close_sessions: manage },
      links: { audit: `/audit?subject_type=User&subject_id=${user.id}` },
    }
  }

  private complaintRow(row: FakeComplaint) {
    const customer = this.users.find((candidate) => candidate.id === row.customerId)
    return {
      uuid: row.uuid, type: row.type, type_label: TYPE_LABEL[row.type], description: row.description, support_status: row.supportStatus, support_status_label: SUPPORT_LABEL[row.supportStatus],
      store_flow: row.flow, store_flow_label: FLOW_LABEL[row.flow], escalated: row.flow === 'in_review', store_replied: row.storeReply !== null, waiting: this.waiting(row),
      customer: customer ? { id: customer.id, name: customer.name } : null, order: row.order, store: row.store, assignee: row.assignee, created_at: row.createdAt,
      answered_at: row.replies.at(-1)?.created_at ?? null, resolved_at: row.resolution?.at ?? null,
    }
  }

  private complaintCard(row: FakeComplaint, can: (permission: string) => boolean) {
    const customer = this.users.find((candidate) => candidate.id === row.customerId)
    const manage = can('admin.support.manage')
    const resolved = row.supportStatus === 'resolved'
    return {
      ...this.complaintRow(row),
      customer: customer ? { id: customer.id, name: customer.name, phone: mask(customer.phone), telegram_linked: customer.telegram, blocked: customer.blocked !== null, href: `/users/${customer.id}` } : null,
      order: { ...row.order, status: 'delivered', status_label: 'Yetkazildi', total: '85000.00', created_at: row.createdAt, href: `/orders/${row.order.uuid}` },
      store_reply: row.storeReply,
      replies: row.replies.map((reply) => ({ ...reply, channel: 'telegram' })),
      resolution: row.resolution,
      history: [],
      actions: { manage, reply: manage && !resolved && (customer?.telegram ?? false), resolve: manage && !resolved, reopen: manage && resolved },
    }
  }
}
