// The fake of Ovqatxonalar (P4-22): /admin/stores (list, card, lookup, actions), /store-applications (the pipeline),
// /store-tasks, /store-broadcasts, shaped like AdminStore*Controller answer. The rules that matter to the screens are
// here: viewing needs stores.view (the picker also orders.view), changing stores.manage; actions on a store, converting,
// rejecting, the move to `active` and broadcasts a step-up and a reason (3+ characters); a stage move forward needs the
// checklist of the stages passed and `menu` or later a store; a broadcast sends to the count of its preview only.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const STAGES = ['application', 'review', 'menu', 'devices', 'training', 'trial', 'active'] as const
const STAGE_LABEL: Record<string, string> = {
  application: 'Ariza', review: 'Tekshiruv', menu: 'Menyu', devices: 'Qurilmalar', training: "O'qitish", trial: 'Sinov', active: 'Faol', rejected: 'Rad etildi',
}
const CHECKLIST: Record<string, Array<{ key: string; label: string; required: boolean }>> = {
  application: [{ key: 'contacted', label: "Egasi bilan bog'lanildi", required: true }],
  review: [{ key: 'tin_checked', label: 'STIR tekshirildi', required: true }, { key: 'documents', label: 'Hujjatlar olindi', required: false }],
  menu: [{ key: 'menu_entered', label: 'Menyu kiritildi', required: true }],
  devices: [{ key: 'pos_paired', label: 'Kassa ulandi', required: true }],
  training: [{ key: 'staff_trained', label: "Xodimlar o'qitildi", required: true }],
  trial: [{ key: 'first_order', label: 'Birinchi buyurtma', required: true }],
}
const STATUS_LABEL: Record<string, string> = { active: 'Faol', suspended: "To'xtatilgan", pending: 'Ulanmoqda', rejected: 'Rad etilgan', payment_required: 'Qarz limitidan oshgan' }
const REGIONS = [{ id: 1, name: 'Toshkent shahri' }, { id: 2, name: 'Samarqand viloyati' }]
const DISTRICTS: Record<number, Array<{ id: number; name: string }>> = { 1: [{ id: 11, name: 'Chilonzor tumani' }, { id: 12, name: 'Yunusobod tumani' }], 2: [{ id: 21, name: 'Kattaqo\'rg\'on tumani' }] }

export interface FakeStore {
  id: number
  name: string
  status: string
  is_active: boolean
  is_premium: boolean
  is_featured: boolean
  region: { id: number; name: string } | null
  debt: string
  credit_limit: string
  health: { level: 'good' | 'attention' | 'churn_risk'; score: number; reasons: Array<{ key: string; level: 'attention' | 'churn_risk'; points: number; label: string }> }
  owner: { name: string; phone: string; telegram: boolean } | null
  history: Array<{ from: string | null; to: string; by: string | null; comment: string; at: string }>
  next_contact_on: string | null
  notes: Array<{ id: number; body: string; author: string; at: string }>
}

export interface FakeApplication {
  id: number
  uuid: string
  name: string
  format: string
  owner_name: string
  phone: string
  region: { id: number; name: string } | null
  stage: string
  ticked: string[]
  store_id: number | null
  assignee: { id: number; name: string } | null
  due_on: string | null
  rejected_reason: string | null
  created_at: string
  notes: Array<{ id: number; body: string; author: string; at: string }>
}

const iso = (minutesAgo = 0) => new Date(Date.now() - minutesAgo * 60_000).toISOString()
const tashkentDay = (days = 0) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tashkent' }).format(new Date(Date.now() + days * 86_400_000))
const over = (store: FakeStore) => Number(store.debt) > Number(store.credit_limit)
const LEVEL_LABEL = { good: 'Yaxshi', attention: "E'tibor kerak", churn_risk: 'Ketish xavfi' } as const

export function defaultStores(): FakeStore[] {
  return [
    {
      id: 12, name: 'Lazzat Oshxona', status: 'active', is_active: true, is_premium: false, is_featured: false, region: REGIONS[0], debt: '0.00', credit_limit: '100000.00',
      health: { level: 'good', score: 100, reasons: [] }, owner: { name: 'Aziz Karimov', phone: '+998901234567', telegram: true },
      history: [{ from: 'pending', to: 'active', by: 'Aziza Karimova', comment: 'Ulash jarayoni yakunlandi', at: iso(60 * 24 * 30) }], next_contact_on: null, notes: [],
    },
    {
      id: 13, name: 'Lazzat Express', status: 'active', is_active: true, is_premium: true, is_featured: false, region: REGIONS[0], debt: '20000.00', credit_limit: '100000.00',
      health: { level: 'attention', score: 85, reasons: [{ key: 'complaints', level: 'attention', points: 10, label: 'Oxirgi 30 kunda 3 ta shikoyat' }] }, owner: { name: 'Jamshid', phone: '+998935550011', telegram: true },
      history: [], next_contact_on: null, notes: [],
    },
    {
      id: 15, name: 'Qarzdor kafe', status: 'active', is_active: true, is_premium: false, is_featured: false, region: REGIONS[1], debt: '450000.00', credit_limit: '100000.00',
      health: { level: 'churn_risk', score: 45, reasons: [{ key: 'debt_over_limit', level: 'churn_risk', points: 25, label: "Qarz kredit limitidan oshgan: 450 000 so'm (limit 100 000 so'm)" }, { key: 'devices_silent', level: 'churn_risk', points: 25, label: 'Kassa 3 kundan beri aloqa qilmagan' }] },
      owner: { name: 'Dilshod', phone: '+998935554433', telegram: false }, history: [], next_contact_on: null, notes: [],
    },
  ]
}

export function defaultApplications(): FakeApplication[] {
  return [
    { id: 7, uuid: '7a7a7a7a-0000-4000-8000-000000000007', name: "Choyxona Navro'z", format: 'teahouse', owner_name: 'Bobur', phone: '+998907654321', region: REGIONS[0], stage: 'application', ticked: [], store_id: null, assignee: null, due_on: tashkentDay(1), rejected_reason: null, created_at: iso(30), notes: [] },
    { id: 8, uuid: '8b8b8b8b-0000-4000-8000-000000000008', name: 'Burger Joy', format: 'fast_food', owner_name: 'Sardor', phone: '+998911112233', region: REGIONS[1], stage: 'review', ticked: ['contacted'], store_id: null, assignee: { id: 2, name: 'Sardor Operator' }, due_on: tashkentDay(-2), rejected_reason: null, created_at: iso(60 * 24 * 3), notes: [] },
  ]
}

export class FakeStores {
  stores: FakeStore[] = defaultStores()
  applications: FakeApplication[] = defaultApplications()
  tasks: Array<{ id: number; title: string; due_on: string; store_id: number | null; application: string | null; assignee: { id: number; name: string }; done: boolean }> = []
  broadcasts: Array<{ uuid: string; audience: string; text: string; reason: string; stores: number; recipients: number; status: string; by: { id: number; name: string }; at: string }> = []
  messages: Array<{ store: number; text: string; reason: string }> = []
  private nextId = 100

  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null, actor: { id: number; name: string }): Reply | null {
    if (!(path === '/stores' || path.startsWith('/stores/') || path.startsWith('/store-'))) return null
    const forbidden = fail(403, "Ruxsat yo'q.", { reason: 'forbidden' })
    const reason = () => (typeof body.reason === 'string' && body.reason.trim().length >= 3 ? null : validation('reason', 'Sababni yozing (kamida 3 belgi).'))
    const act = (): Reply | null => {
      if (!can('admin.stores.manage')) return forbidden
      return needsRecent() ?? reason()
    }

    if (req.method === 'GET' && path === '/stores/lookup') {
      if (!can('admin.stores.view') && !can('admin.orders.view')) return forbidden
      const q = (req.query.q ?? '').toLowerCase()
      const id = Number(req.query['ids[]'] ?? 0)
      const stores = this.stores.filter((store) => (id ? store.id === id : !q || store.name.toLowerCase().includes(q))).map((store) => ({ id: store.id, name: store.name, status: store.status, region: store.region?.name ?? null }))
      const districtsOf = Number(req.query.districts_of ?? 0)
      return ok({ stores, regions: REGIONS.filter((region) => id || !q || region.name.toLowerCase().includes(q)), districts: DISTRICTS[districtsOf] ?? [] })
    }
    if (!can('admin.stores.view')) return forbidden

    // --- Stores
    if (req.method === 'GET' && path === '/stores') return ok(this.list(req.query))
    const one = /^\/stores\/(\d+)(?:\/([a-z-]+))?$/.exec(path)
    if (one) {
      const store = this.stores.find((row) => row.id === Number(one[1]))
      if (!store) return fail(404, 'Ovqatxona topilmadi.', { reason: 'not_found' })
      const sub = one[2]
      if (req.method === 'GET' && !sub) return ok(this.card(store, can))
      if (req.method === 'POST' && sub === 'notes') {
        if (!can('admin.stores.manage')) return forbidden
        store.notes.unshift({ id: ++this.nextId, body: String(body.body ?? ''), author: actor.name, at: iso() })
        return { status: 201, body: { success: true, message: '', data: { id: this.nextId } } }
      }
      if (req.method === 'PUT' && sub === 'next-contact') {
        if (!can('admin.stores.manage')) return forbidden
        store.next_contact_on = typeof body.next_contact_on === 'string' ? body.next_contact_on : null
        return ok({ next_contact_on: store.next_contact_on })
      }
      const refused = act()
      if (refused) return refused
      const text = String(body.reason).trim()
      if (req.method === 'POST' && sub === 'suspend') {
        if (store.status === 'suspended') return fail(409, "Ovqatxona allaqachon to'xtatilgan.", { reason: 'already_suspended' })
        store.history.unshift({ from: store.status, to: 'suspended', by: actor.name, comment: text, at: iso() })
        store.status = 'suspended'
        store.is_active = false
        return ok(this.card(store, can))
      }
      if (req.method === 'POST' && sub === 'activate') {
        store.history.unshift({ from: store.status, to: 'active', by: actor.name, comment: text, at: iso() })
        store.status = 'active'
        store.is_active = true
        return ok(this.card(store, can))
      }
      if (req.method === 'PUT' && sub === 'credit-limit') {
        store.credit_limit = Number(body.credit_limit).toFixed(2)
        return ok(this.card(store, can))
      }
      if (req.method === 'PUT' && sub === 'display') {
        if (typeof body.is_premium === 'boolean') store.is_premium = body.is_premium
        if (typeof body.is_featured === 'boolean') store.is_featured = body.is_featured
        return ok(this.card(store, can))
      }
      if (req.method === 'POST' && sub === 'telegram-link') return ok({ invite_link: `https://t.me/rizqim_test_bot?start=invite_e2e-${++this.nextId}`, owner_linked: store.owner?.telegram ?? false })
      if (req.method === 'POST' && sub === 'message') {
        if (!store.owner?.telegram) return fail(422, "Ovqatxona egasining Telegram akkaunti ulanmagan. Telegram havolasini yuboring.", { reason: 'no_recipient' })
        this.messages.push({ store: store.id, text: String(body.text), reason: text })
        return ok({ recipients: 1 })
      }
      return null
    }

    // --- Pipeline
    if (req.method === 'GET' && path === '/store-applications') return ok(this.applicationList(req.query, actor))
    if (req.method === 'POST' && path === '/store-applications') {
      if (!can('admin.stores.manage')) return forbidden
      const application: FakeApplication = {
        id: ++this.nextId, uuid: `9c9c9c9c-0000-4000-8000-${String(this.nextId).padStart(12, '0')}`, name: String(body.name), format: String(body.format), owner_name: String(body.owner_name), phone: String(body.phone),
        region: REGIONS.find((region) => region.id === body.region_id) ?? null, stage: 'application', ticked: [], store_id: null, assignee: null, due_on: tashkentDay(1), rejected_reason: null, created_at: iso(), notes: [],
      }
      this.applications.push(application)
      return { status: 201, body: { success: true, message: '', data: this.applicationCard(application, can) } }
    }
    const app = /^\/store-applications\/([0-9a-f-]{36})(?:\/([a-z]+))?$/.exec(path)
    if (app) {
      const application = this.applications.find((row) => row.uuid === app[1])
      if (!application) return fail(404, 'Ariza topilmadi.', { reason: 'not_found' })
      const sub = app[2]
      if (req.method === 'GET' && !sub) return ok(this.applicationCard(application, can))
      if (!can('admin.stores.manage')) return forbidden
      if (req.method === 'PATCH' && !sub) {
        if ('assignee_id' in body) application.assignee = body.assignee_id === actor.id ? actor : null
        if ('due_on' in body) application.due_on = (body.due_on as string | null) ?? null
        return ok(this.applicationCard(application, can))
      }
      if (req.method === 'POST' && sub === 'checklist') {
        const item = String(body.item)
        application.ticked = body.done ? [...new Set([...application.ticked, item])] : application.ticked.filter((key) => key !== item)
        return ok(this.applicationCard(application, can))
      }
      if (req.method === 'POST' && sub === 'notes') {
        application.notes.unshift({ id: ++this.nextId, body: String(body.body ?? ''), author: actor.name, at: iso() })
        return { status: 201, body: { success: true, message: '', data: { id: this.nextId } } }
      }
      if (req.method === 'POST' && sub === 'stage') {
        const to = String(body.stage)
        if (to === 'active') {
          const refused = needsRecent() ?? reason()
          if (refused) return refused
        }
        const from = STAGES.indexOf(application.stage as (typeof STAGES)[number])
        const target = STAGES.indexOf(to as (typeof STAGES)[number])
        if (application.stage === 'rejected') {
          application.stage = to
          application.rejected_reason = null
          return ok(this.applicationCard(application, can))
        }
        if (target > from) {
          for (const stage of STAGES.slice(from, target)) {
            const missing = (CHECKLIST[stage] ?? []).filter((item) => item.required && !application.ticked.includes(item.key))
            if (missing.length > 0) return fail(422, `«${STAGE_LABEL[stage]}» bosqichining ro'yxati tugallanmagan: ${missing.map((item) => item.label).join(', ')}.`, { reason: 'checklist_incomplete', stage, missing: missing.map((item) => item.key) })
          }
          if (target >= 2 && application.store_id === null) return fail(422, `«${STAGE_LABEL[to]}» bosqichi uchun avval ovqatxonani yarating (arizadan ovqatxona yaratish).`, { reason: 'store_required' })
        }
        application.stage = to
        application.due_on = to === 'active' ? null : tashkentDay(2)
        if (to === 'active') {
          const store = this.stores.find((row) => row.id === application.store_id)
          if (store) {
            store.history.unshift({ from: store.status, to: 'active', by: actor.name, comment: `Ulash jarayoni yakunlandi: ${String(body.reason)}`, at: iso() })
            store.status = 'active'
            store.is_active = true
          }
        }
        return ok(this.applicationCard(application, can))
      }
      const refused = needsRecent() ?? reason()
      if (refused) return refused
      if (req.method === 'POST' && sub === 'convert') {
        if (application.store_id !== null) return fail(409, 'Bu arizadan ovqatxona allaqachon yaratilgan.', { reason: 'already_converted' })
        if (!body.branch_district_id || !body.branch_address) return validation('branch_address', 'Manzilni yozing.')
        const store: FakeStore = {
          id: ++this.nextId, name: application.name, status: 'pending', is_active: false, is_premium: false, is_featured: false, region: application.region, debt: '0.00', credit_limit: '100000.00',
          health: { level: 'good', score: 100, reasons: [] }, owner: { name: application.owner_name, phone: application.phone, telegram: false }, history: [], next_contact_on: null, notes: [],
        }
        this.stores.push(store)
        application.store_id = store.id
        return { status: 201, body: { success: true, message: '', data: { application: this.applicationCard(application, can), store_id: store.id, invite_link: `https://t.me/rizqim_test_bot?start=invite_new-${store.id}` } } }
      }
      if (req.method === 'POST' && sub === 'reject') {
        application.stage = 'rejected'
        application.rejected_reason = String(body.reason).trim()
        application.due_on = null
        return ok(this.applicationCard(application, can))
      }
      return null
    }

    // --- Tasks
    if (req.method === 'GET' && path === '/store-tasks') {
      const open = this.tasks.filter((task) => !task.done && (req.query.assignee_id !== 'me' || task.assignee.id === actor.id))
      return ok({ tasks: open.map((task) => this.task(task)), counts: { overdue: open.filter((task) => task.due_on < tashkentDay()).length, today: open.filter((task) => task.due_on === tashkentDay()).length, upcoming: open.filter((task) => task.due_on > tashkentDay()).length } })
    }
    if (req.method === 'POST' && path === '/store-tasks') {
      if (!can('admin.stores.manage')) return forbidden
      const task = { id: ++this.nextId, title: String(body.title), due_on: String(body.due_on), store_id: typeof body.store_id === 'number' ? body.store_id : null, application: typeof body.application_uuid === 'string' ? body.application_uuid : null, assignee: actor, done: false }
      this.tasks.push(task)
      return { status: 201, body: { success: true, message: '', data: this.task(task) } }
    }
    const task = /^\/store-tasks\/(\d+)$/.exec(path)
    if (req.method === 'PATCH' && task) {
      if (!can('admin.stores.manage')) return forbidden
      const row = this.tasks.find((item) => item.id === Number(task[1]))
      if (!row) return fail(404, 'Vazifa topilmadi.', { reason: 'not_found' })
      if (typeof body.done === 'boolean') row.done = body.done
      return ok(this.task(row))
    }

    // --- Broadcasts
    if (req.method === 'GET' && path === '/store-broadcasts') {
      return ok({ broadcasts: this.broadcasts.map((row) => ({ uuid: row.uuid, audience: row.audience, text: row.text, reason: row.reason, stores: row.stores, recipients: row.recipients, queued: row.recipients, status: row.status, created_by: row.by, created_at: row.at, finished_at: row.at })), limit_per_day: 10, sent_today: this.broadcasts.length })
    }
    if (req.method === 'POST' && path === '/store-broadcasts/preview') {
      if (!can('admin.stores.manage')) return forbidden
      return ok(this.preview(body))
    }
    if (req.method === 'POST' && path === '/store-broadcasts') {
      const refused = act()
      if (refused) return refused
      const preview = this.preview(body)
      if (preview.recipients !== body.recipients) return fail(409, "Qabul qiluvchilar soni o'zgardi: qayta ko'rib chiqing.", { reason: 'count_changed', preview })
      const row = { uuid: `bc-${++this.nextId}`, audience: String(body.audience), text: String(body.text), reason: String(body.reason), stores: preview.stores, recipients: preview.recipients, status: 'sent', by: actor, at: iso() }
      this.broadcasts.unshift(row)
      return { status: 201, body: { success: true, message: '', data: { uuid: row.uuid, recipients: row.recipients, stores: row.stores, status: 'queued' } } }
    }
    return null
  }

  private audience(body: Record<string, unknown>): FakeStore[] {
    if (body.audience === 'selected') return this.stores.filter((store) => Array.isArray(body.store_ids) && body.store_ids.includes(store.id))
    const statuses = Array.isArray(body.statuses) ? (body.statuses as string[]) : []
    return this.stores.filter((store) => statuses.length === 0 || statuses.includes(store.status))
  }

  private preview(body: Record<string, unknown>) {
    const stores = this.audience(body)
    const reached = stores.filter((store) => store.owner?.telegram)
    return { stores: stores.length, recipients: reached.length, without_telegram: stores.length - reached.length, sample: stores.slice(0, 5).map((store) => store.name) }
  }

  private health(store: FakeStore) {
    return { level: store.health.level, label: LEVEL_LABEL[store.health.level], score: store.health.score, reasons: store.health.reasons.length, top_reason: store.health.reasons[0]?.label ?? null, computed_at: iso(5) }
  }

  private list(query: Record<string, string>) {
    let rows = this.stores
    if (query.status) rows = rows.filter((store) => store.status === query.status)
    if (query.region_id) rows = rows.filter((store) => String(store.region?.id) === query.region_id)
    if (query.debt === 'over_limit') rows = rows.filter(over)
    if (query.q) rows = rows.filter((store) => store.name.toLowerCase().includes(query.q.toLowerCase()))
    const counted = rows
    if (query.health) rows = rows.filter((store) => store.health.level === query.health)
    return {
      data: rows.map((store) => ({
        id: store.id, uuid: `0000000${store.id}-0000-4000-8000-000000000000`.slice(-36), name: store.name, status: store.status, status_label: STATUS_LABEL[store.status] ?? store.status, is_active: store.is_active, is_premium: store.is_premium, is_featured: store.is_featured,
        region: store.region, branches_count: 1, owner: store.owner ? { id: store.id + 1000, name: store.owner.name, telegram_linked: store.owner.telegram } : null, plan: null,
        debt_balance: store.debt, subscription_debt: '0.00', commission_debt: store.debt, credit_limit: store.credit_limit, over_limit: over(store), health: this.health(store), next_contact_on: store.next_contact_on, created_at: iso(60 * 24 * 60),
      })),
      current_page: 1, last_page: 1, per_page: 30, total: rows.length,
      health_counts: { all: counted.length, good: counted.filter((s) => s.health.level === 'good').length, attention: counted.filter((s) => s.health.level === 'attention').length, churn_risk: counted.filter((s) => s.health.level === 'churn_risk').length, none: 0 },
      statuses: STATUS_LABEL,
    }
  }

  private card(store: FakeStore, can: (permission: string) => boolean) {
    const manage = can('admin.stores.manage')
    const pii = can('admin.pii.view')
    const mask = (phone: string) => (pii ? phone : `${phone.slice(0, 4)} ${phone.slice(4, 6)} *** ** ${phone.slice(-2)}`)
    const application = this.applications.find((row) => row.store_id === store.id) ?? null
    return {
      profile: {
        id: store.id, uuid: `uuid-${store.id}`, name: store.name, status: store.status, status_label: STATUS_LABEL[store.status] ?? store.status, is_active: store.is_active, is_open: true, is_premium: store.is_premium, is_featured: store.is_featured,
        is_order_locked: over(store), phone: null, address: 'Chilonzor 1', description: null, logo_url: null, tin: '305000111', legal_name: null, tax_regime: null, payment_account_type: 'platform_account',
        commission_rate: '10', rating: '4.50', review_count: 12, created_at: iso(60 * 24 * 60), telegram: { store_chat_linked: true, owner_linked: store.owner?.telegram ?? false, owner_invite_pending: !store.owner?.telegram },
      },
      application: application ? this.applicationRow(application) : null,
      contacts: store.owner ? [{ kind: 'staff', user_id: store.id + 1000, name: store.owner.name, role: 'owner', role_label: 'Ega', phone: mask(store.owner.phone), telegram_linked: store.owner.telegram, branch_id: null }] : [],
      branches: [{ id: store.id + 500, name: 'Asosiy Filial', address: 'Chilonzor 1', phone: null, region: store.region, district: null, is_active: true, open_time: '09:00:00', close_time: '23:00:00' }],
      status_history: store.history.map((row) => ({ from_status: row.from, from_label: row.from ? (STATUS_LABEL[row.from] ?? row.from) : null, to_status: row.to, to_label: STATUS_LABEL[row.to] ?? row.to, by: row.by ? { id: 1, name: row.by } : null, comment: row.comment, created_at: row.at })),
      plan: {
        source: 'billing_v2',
        subscription: null,
        entitlements: { plan: null, status: null, settings_locked: false, locked_since: null, features: {}, devices: { pos: { included: null, active: 1, extra: 0 } } },
        debt: { debt_balance: store.debt, subscription_debt: '0.00', commission_debt: store.debt, credit_limit: store.credit_limit, over_credit_limit: over(store) },
        commission: {
          rates: { rizqim_courier: { rate: '15.00', source: 'default', default: '15.00' }, own_delivery: { rate: '10.00', source: 'default', default: '10.00' }, pickup: { rate: '5.00', source: 'default', default: '5.00' }, pos: { rate: '0.00', source: 'default', default: '0.00' } },
          overrides: {}, flat_rate: null, delivery_courier: 'own',
        },
        history: [],
        invoices: [],
      },
      devices: { terminals: 1, terminals_online: store.health.reasons.some((r) => r.key === 'devices_silent') ? 0 : 1, by_type: { pos: 1, kds: 0 }, items: [{ uuid: `d-${store.id}`, name: 'Kassa 1', type: 'pos', type_label: 'Kassa', terminal: true, branch: null, platform: 'android', app_version: '1.4.0', last_seen_at: iso(store.health.reasons.some((r) => r.key === 'devices_silent') ? 60 * 24 * 4 : 1), online: !store.health.reasons.some((r) => r.key === 'devices_silent'), paired_at: null }] },
      fiscal: { enabled: false, driver: null, tax_regime: null, failed_7d: 0, pending: 0, missing_mxik: 0, last_failed: [] },
      finance: { debt_balance: store.debt, subscription_debt: '0.00', commission_debt: store.debt, wallet_balance: '0.00', credit_limit: store.credit_limit, over_limit: over(store), min_payout_reserve: '100000.00', available_for_payout: '0.00', payouts: [], ledger: [] },
      orders: { quality: null, last_30_days: [], recent: [] },
      complaints: { open: 0, last_30_days: 0, items: [] },
      health: { level: store.health.level, label: LEVEL_LABEL[store.health.level], score: store.health.score, reasons: store.health.reasons, metrics: {}, computed_at: iso() },
      crm: {
        next_contact_on: store.next_contact_on,
        notes: store.notes.map((note) => ({ id: note.id, body: note.body, author: { id: 1, name: note.author }, store_id: store.id, application_id: null, created_at: note.at })),
        tasks: this.tasks.filter((task) => task.store_id === store.id).map((task) => this.task(task)),
      },
      audit: [],
      actions: {
        manage, suspend: manage && store.status !== 'suspended', activate: manage && store.status !== 'active' && application === null, credit_limit: manage, display: manage,
        telegram_link: manage && store.owner !== null, message_owner: manage && Boolean(store.owner?.telegram), commission_override: can('admin.subscriptions.manage'),
      },
      links: { orders: `/orders?store_id=${store.id}`, audit: `/audit?store_id=${store.id}`, subscription: `/pos?store=${store.id}` },
    }
  }

  private applicationRow(application: FakeApplication) {
    const store = this.stores.find((row) => row.id === application.store_id) ?? null
    return {
      id: application.id, uuid: application.uuid, name: application.name, format: application.format, format_label: application.format === 'teahouse' ? 'Choyxona' : 'Fast food', region: application.region, city: null,
      owner_name: application.owner_name, source: 'website', stage: application.stage, stage_label: STAGE_LABEL[application.stage], stage_changed_at: application.created_at, assignee: application.assignee,
      due_on: application.due_on, overdue: application.due_on !== null && application.due_on < tashkentDay() && !['active', 'rejected'].includes(application.stage),
      store: store ? { id: store.id, name: store.name, status: store.status } : null, created_at: application.created_at,
    }
  }

  private applicationCard(application: FakeApplication, can: (permission: string) => boolean) {
    const at = STAGES.indexOf(application.stage as (typeof STAGES)[number])
    return {
      ...this.applicationRow(application),
      phone: can('admin.pii.view') ? application.phone : `${application.phone.slice(0, 4)} ${application.phone.slice(4, 6)} *** ** ${application.phone.slice(-2)}`,
      tin: null, comment: null, rejected_reason: application.rejected_reason, rejected_at: null, converted_at: null, activated_at: null,
      checklist: Object.entries(CHECKLIST).map(([stage, items]) => ({
        stage, label: STAGE_LABEL[stage], current: stage === application.stage, passed: at > STAGES.indexOf(stage as (typeof STAGES)[number]),
        items: items.map((item) => ({ ...item, detect: false, done: application.ticked.includes(item.key), ticked: application.ticked.includes(item.key), detected: false, done_at: null, done_by: null })),
      })),
      crm: {
        next_contact_on: null,
        notes: application.notes.map((note) => ({ id: note.id, body: note.body, author: { id: 1, name: note.author }, store_id: null, application_id: application.id, created_at: note.at })),
        tasks: this.tasks.filter((task) => task.application === application.uuid).map((task) => this.task(task)),
      },
      audit: [],
      actions: { manage: can('admin.stores.manage'), convert: application.store_id === null && !['active', 'rejected'].includes(application.stage), reject: !['active', 'rejected'].includes(application.stage) },
    }
  }

  private applicationList(query: Record<string, string>, actor: { id: number }) {
    let rows = this.applications
    const open = (row: FakeApplication) => !['active', 'rejected'].includes(row.stage)
    if (query.assignee_id === 'me') rows = rows.filter((row) => row.assignee?.id === actor.id)
    const counted = rows
    if (query.stage === 'open') rows = rows.filter(open)
    else if (query.stage === 'overdue') rows = rows.filter((row) => open(row) && row.due_on !== null && row.due_on < tashkentDay())
    else if (query.stage) rows = rows.filter((row) => row.stage === query.stage)
    const stage_counts: Record<string, number> = Object.fromEntries([...STAGES, 'rejected'].map((stage) => [stage, counted.filter((row) => row.stage === stage).length]))
    stage_counts.overdue = counted.filter((row) => open(row) && row.due_on !== null && row.due_on < tashkentDay()).length
    return {
      data: rows.map((row) => this.applicationRow(row)), current_page: 1, last_page: 1, per_page: 200, total: rows.length, stage_counts,
      stages: [...STAGES, 'rejected'].map((key) => ({ key, label: STAGE_LABEL[key] })),
      formats: [{ key: 'cafe', label: 'Kafe' }, { key: 'teahouse', label: 'Choyxona' }, { key: 'fast_food', label: 'Fast food' }],
    }
  }

  private task(task: FakeStores['tasks'][number]) {
    const today = tashkentDay()
    const store = this.stores.find((row) => row.id === task.store_id)
    return {
      id: task.id, title: task.title, due_on: task.due_on, state: task.done ? 'done' : task.due_on < today ? 'overdue' : task.due_on === today ? 'today' : 'upcoming',
      assignee: task.assignee, created_by: task.assignee, store: store ? { id: store.id, name: store.name } : null, application: null, done_at: task.done ? iso() : null, created_at: iso(),
    }
  }
}
