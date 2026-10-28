// The fake of Marketing (P4-31): /admin/marketing/promos, /promo-codes, /categories and /lookup, shaped like the answers of
// AdminMarketing*Controller. The rules that matter to the screens are here: viewing needs marketing.view, every change
// marketing.manage and a reason (3+ characters); deleting also the step-up; a promo code that was used keeps its code, type, value,
// cap and store; an order of banners or categories must name every row (else 409 order_stale); a category lives two levels deep
// and is deleted only when empty. A file arrives as {name, size, type} (server.ts reads the multipart body); the images the screens
// load are a 1x1 PNG.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

const iso = (daysFromNow = 0, hour = 9) => {
  const date = new Date(Date.now() + daysFromNow * 86_400_000)
  date.setUTCHours(hour - 5, 0, 0, 0)
  return date.toISOString()
}
const money = (value: number) => value.toFixed(2)

const PNG = Uint8Array.from(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'))

const BANNER_STATUS: Record<string, string> = { live: "Ko'rinmoqda", scheduled: 'Rejalashtirilgan', ended: 'Tugagan', inactive: "O'chiq" }
const CODE_STATUS: Record<string, string> = { active: 'Faol', inactive: "O'chirilgan", expired: 'Muddati tugagan', exhausted: 'Limit tugagan' }
const CODE_TYPES: Record<string, string> = { percentage: 'Foiz', fixed: 'Belgilangan summa' }

export interface FakeBanner {
  id: number
  title: string
  has_image: boolean
  store_id: number | null
  product_id: number | null
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  sort_order: number
}

export interface FakeCode {
  id: number
  code: string
  type: 'percentage' | 'fixed'
  value: number
  min_order_amount: number
  max_discount_amount: number | null
  usage_limit: number | null
  per_user_limit: number | null
  store_id: number | null
  is_active: boolean
  expires_at: string | null
  stats: { uses: number; unique_customers: number; discount_total: number; gmv: number }
  /** Some order carries the code (even a cancelled one) */
  has_orders: boolean
  orders: Array<{ uuid: string; status: string; discount: string; total: string; created_at: string }>
}

export interface FakeCategory {
  id: number
  name: string
  parent_id: number | null
  has_image: boolean
  sort_order: number
  is_active: boolean
  products_count: number
}

const STORES = [
  { id: 12, name: 'Lazzat Oshxona' },
  { id: 13, name: 'Lazzat Express' },
  { id: 14, name: 'Shirin Qandolat' },
]
const PRODUCTS = [
  { id: 501, name: 'Osh', store_id: 12 },
  { id: 502, name: 'Lag‘mon', store_id: 12 },
  { id: 503, name: 'Napoleon', store_id: 14 },
]

export class FakeMarketing {
  banners: FakeBanner[] = []
  codes: FakeCode[] = []
  categories: FakeCategory[] = []
  private nextBanner = 1
  private nextCode = 1
  private nextCategory = 1

  constructor() {
    this.addBanner({ title: 'Dekabr aksiyasi', store_id: 12 })
    this.addBanner({ title: 'Yangi yil', starts_at: iso(30), ends_at: iso(40) })
    this.addBanner({ title: 'Eski banner', is_active: false })

    this.addCode({
      code: 'YANGI10',
      max_discount_amount: 8000,
      usage_limit: 100,
      min_order_amount: 50_000,
      expires_at: iso(60),
      has_orders: true,
      stats: { uses: 12, unique_customers: 9, discount_total: 84_000, gmv: 1_250_000 },
      orders: [{ uuid: 'ab12cd34-1111-4222-8333-444455556666', status: 'delivered', discount: '8000.00', total: '92000.00', created_at: iso(-1, 13) }],
    })
    this.addCode({ code: 'BEPUL5000', type: 'fixed', value: 5000, max_discount_amount: null, usage_limit: null, per_user_limit: null })
    this.addCode({ code: 'ESKI20', value: 20, max_discount_amount: null, expires_at: iso(-3), is_active: true })

    this.addCategory({ name: 'Taomlar', products_count: 14 })
    this.addCategory({ name: 'Burgerlar', parent_id: 1, products_count: 6 })
    this.addCategory({ name: 'Ichimliklar', is_active: false, products_count: 0 })
  }

  addBanner(input: Partial<FakeBanner> & { title: string }): FakeBanner {
    const banner: FakeBanner = { id: this.nextBanner++, has_image: true, store_id: null, product_id: null, is_active: true, starts_at: null, ends_at: null, sort_order: 0, ...input }
    banner.sort_order = this.banners.length + 1
    this.banners.push(banner)
    return banner
  }

  addCode(input: Partial<FakeCode> & { code: string }): FakeCode {
    const code: FakeCode = {
      id: this.nextCode++,
      type: 'percentage',
      value: 10,
      min_order_amount: 0,
      max_discount_amount: null,
      usage_limit: null,
      per_user_limit: 1,
      store_id: null,
      is_active: true,
      expires_at: null,
      stats: { uses: 0, unique_customers: 0, discount_total: 0, gmv: 0 },
      has_orders: false,
      orders: [],
      ...input,
    }
    this.codes.push(code)
    return code
  }

  addCategory(input: Partial<FakeCategory> & { name: string }): FakeCategory {
    const category: FakeCategory = { id: this.nextCategory++, parent_id: null, has_image: false, sort_order: 0, is_active: true, products_count: 0, ...input }
    category.sort_order = this.categories.length + 1
    this.categories.push(category)
    return category
  }

  // --- Shapes --------------------------------------------------------------------------------------------------------------

  private storeOf = (id: number | null) => STORES.find((store) => store.id === id) ?? null

  private bannerStatus(banner: FakeBanner): keyof typeof BANNER_STATUS {
    const now = Date.now()
    if (!banner.is_active) return 'inactive'
    if (banner.starts_at && Date.parse(banner.starts_at) > now) return 'scheduled'
    if (banner.ends_at && Date.parse(banner.ends_at) <= now) return 'ended'
    return 'live'
  }

  private bannerRow(banner: FakeBanner) {
    const status = this.bannerStatus(banner)
    const product = PRODUCTS.find((item) => item.id === banner.product_id)
    return {
      id: banner.id,
      title: banner.title,
      image_url: banner.has_image ? `http://rizqim.test/storage/promos/${banner.id}.png` : null,
      image_preview: banner.has_image ? `/marketing/promos/${banner.id}/image?v=1` : null,
      store: this.storeOf(banner.store_id),
      product: product ? { id: product.id, name: product.name, store_id: product.store_id } : null,
      is_active: banner.is_active,
      starts_at: banner.starts_at,
      ends_at: banner.ends_at,
      sort_order: banner.sort_order,
      status,
      status_label: BANNER_STATUS[status],
      created_at: iso(-10),
      updated_at: iso(-1),
    }
  }

  private codeStatus(code: FakeCode): keyof typeof CODE_STATUS {
    if (!code.is_active) return 'inactive'
    if (code.expires_at && Date.parse(code.expires_at) <= Date.now()) return 'expired'
    if (code.usage_limit !== null && code.stats.uses >= code.usage_limit) return 'exhausted'
    return 'active'
  }

  private codeRow(code: FakeCode) {
    const status = this.codeStatus(code)
    return {
      id: code.id,
      code: code.code,
      type: code.type,
      type_label: CODE_TYPES[code.type],
      value: money(code.value),
      min_order_amount: money(code.min_order_amount),
      max_discount_amount: code.max_discount_amount === null ? null : money(code.max_discount_amount),
      usage_limit: code.usage_limit,
      per_user_limit: code.per_user_limit,
      store: this.storeOf(code.store_id),
      is_active: code.is_active,
      expires_at: code.expires_at,
      status,
      status_label: CODE_STATUS[status],
      stats: { uses: code.stats.uses, unique_customers: code.stats.unique_customers, discount_total: money(code.stats.discount_total), gmv: money(code.stats.gmv) },
      locked: code.has_orders,
      created_at: iso(-30),
      updated_at: iso(-2),
    }
  }

  private categoryRow(category: FakeCategory) {
    const parent = this.categories.find((item) => item.id === category.parent_id)
    return {
      id: category.id,
      uuid: `c0000000-0000-4000-8000-${String(category.id).padStart(12, '0')}`,
      name: category.name,
      slug: category.name.toLowerCase(),
      parent: parent ? { id: parent.id, name: parent.name } : null,
      image_url: category.has_image ? `http://rizqim.test/storage/categories/${category.id}.webp` : null,
      image_preview: category.has_image ? `/marketing/categories/${category.id}/image?v=1` : null,
      sort_order: category.sort_order,
      is_active: category.is_active,
      products_count: category.products_count,
      children_count: this.categories.filter((item) => item.parent_id === category.id).length,
      updated_at: iso(-1),
    }
  }

  private bannerList() {
    return { data: [...this.banners].sort((a, b) => a.sort_order - b.sort_order).map((banner) => this.bannerRow(banner)), statuses: BANNER_STATUS }
  }

  private categoryList() {
    return { data: [...this.categories].sort((a, b) => a.sort_order - b.sort_order).map((category) => this.categoryRow(category)) }
  }

  // --- Rules ---------------------------------------------------------------------------------------------------------------

  /** The reply for a path below /v1/admin, or null when it is not a marketing path */
  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null): Reply | null {
    if (!path.startsWith('/marketing/')) return null
    const forbidden = fail(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' })
    const write = req.method !== 'GET'
    if (!can('admin.marketing.view') || (write && !can('admin.marketing.manage'))) return forbidden

    const reasonProblem = (): Reply | null => (typeof body.reason === 'string' && body.reason.trim().length >= 3 ? null : validation('reason', 'Sabab kamida 3 ta belgidan iborat bo‘lishi kerak.'))
    const flag = (value: unknown) => value === true || value === '1' || value === 1
    const num = (value: unknown): number | null => (value === null || value === undefined || value === '' ? null : Number(value))
    const idOf = (match: RegExpExecArray | null) => (match ? Number(match[1]) : 0)

    if (req.method === 'GET' && path === '/marketing/lookup') {
      const q = (req.query.q ?? '').toLowerCase()
      const storeId = Number(req.query.store_id) || 0
      return ok({
        stores: STORES.filter((store) => store.name.toLowerCase().includes(q)),
        products: storeId ? PRODUCTS.filter((product) => product.store_id === storeId && product.name.toLowerCase().includes(q)) : [],
      })
    }

    // --- Banners and promotions
    if (path === '/marketing/promos') {
      if (req.method === 'GET') {
        const list = this.bannerList()
        const status = req.query.status
        return ok(status ? { ...list, data: list.data.filter((row) => row.status === status) } : list)
      }
      if (req.method === 'POST') {
        const reason = reasonProblem()
        if (reason) return reason
        const title = String(body.title ?? '').trim()
        if (title.length < 2) return validation('title', 'Sarlavha kamida 2 ta belgi bo‘lsin.')
        if (typeof body.image !== 'object' || body.image === null) return validation('image', 'Rasm majburiy.')
        const starts = typeof body.starts_at === 'string' && body.starts_at !== '' ? body.starts_at : null
        const ends = typeof body.ends_at === 'string' && body.ends_at !== '' ? body.ends_at : null
        if (starts && ends && Date.parse(ends) <= Date.parse(starts)) return fail(422, "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.", { reason: 'invalid_schedule' })
        const banner = this.addBanner({ title, store_id: num(body.store_id), product_id: num(body.product_id), is_active: body.is_active === undefined ? true : flag(body.is_active), starts_at: starts, ends_at: ends })
        return { status: 201, body: { success: true, message: "Banner qo'shildi.", data: this.bannerRow(banner) } }
      }
    }
    if (req.method === 'GET' && path === '/marketing/promos/preview') {
      const live = this.bannerList().data.filter((row) => row.status === 'live')
      return ok({ data: live.map((row) => ({ id: row.id, title: row.title, image_url: row.image_url, image_preview: row.image_preview, store_uuid: null, product_id: row.product?.id ?? null })) })
    }
    if (req.method === 'PUT' && path === '/marketing/promos/order') {
      const reason = reasonProblem()
      if (reason) return reason
      const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []
      if (ids.length !== this.banners.length || this.banners.some((banner) => !ids.includes(banner.id))) return fail(409, "Ro'yxat eskirgan: sahifani yangilang va qayta urining.", { reason: 'order_stale' })
      ids.forEach((id, index) => {
        this.banners.find((banner) => banner.id === id)!.sort_order = index + 1
      })
      return ok(this.bannerList())
    }
    const banner = /^\/marketing\/promos\/(\d+)(\/image)?$/.exec(path)
    if (banner) {
      const row = this.banners.find((item) => item.id === idOf(banner))
      if (!row) return fail(404, 'Banner topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && banner[2]) return row.has_image ? { status: 200, raw: PNG, headers: { 'Content-Type': 'image/png' } } : fail(404, 'Rasm topilmadi.', { reason: 'no_image' })
      if (req.method === 'POST') {
        const reason = reasonProblem()
        if (reason) return reason
        if ('title' in body) row.title = String(body.title).trim()
        if ('is_active' in body) row.is_active = flag(body.is_active)
        if ('store_id' in body) row.store_id = num(body.store_id)
        if ('product_id' in body) row.product_id = num(body.product_id)
        if ('starts_at' in body) row.starts_at = typeof body.starts_at === 'string' && body.starts_at !== '' ? body.starts_at : null
        if ('ends_at' in body) row.ends_at = typeof body.ends_at === 'string' && body.ends_at !== '' ? body.ends_at : null
        if (typeof body.image === 'object' && body.image !== null) row.has_image = true
        if (row.starts_at && row.ends_at && Date.parse(row.ends_at) <= Date.parse(row.starts_at)) return fail(422, "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.", { reason: 'invalid_schedule' })
        return ok(this.bannerRow(row))
      }
      if (req.method === 'DELETE') {
        const recent = needsRecent()
        if (recent) return recent
        const reason = reasonProblem()
        if (reason) return reason
        this.banners = this.banners.filter((item) => item.id !== row.id)
        this.banners.forEach((item, index) => (item.sort_order = index + 1))
        return ok({ id: row.id })
      }
    }

    // --- Promo codes
    if (path === '/marketing/promo-codes') {
      if (req.method === 'GET') {
        const q = (req.query.q ?? '').toUpperCase()
        const status = req.query.status
        const rows = [...this.codes].reverse().filter((code) => (q ? code.code.includes(q) : true) && (status ? this.codeStatus(code) === status : true))
        return ok({ data: rows.map((code) => this.codeRow(code)), current_page: 1, last_page: 1, per_page: 30, total: rows.length, statuses: CODE_STATUS, types: CODE_TYPES })
      }
      if (req.method === 'POST') {
        const reason = reasonProblem()
        if (reason) return reason
        const text = String(body.code ?? '').trim().toUpperCase()
        if (!/^[A-Z0-9_-]{3,50}$/.test(text)) return validation('code', 'Promo-kod noto‘g‘ri.')
        if (this.codes.some((code) => code.code === text)) return validation('code', 'Bunday promo-kod allaqachon bor.')
        const type = body.type === 'fixed' ? 'fixed' : 'percentage'
        const value = Number(body.value)
        if (!(value > 0) || (type === 'percentage' && value > 100) || (type === 'fixed' && !Number.isInteger(value))) return fail(422, "Chegirma qiymati turiga mos emas.", { reason: 'invalid_value' })
        const code = this.addCode({
          code: text,
          type,
          value,
          min_order_amount: num(body.min_order_amount) ?? 0,
          max_discount_amount: type === 'fixed' ? null : num(body.max_discount_amount),
          usage_limit: num(body.usage_limit),
          per_user_limit: num(body.per_user_limit),
          store_id: num(body.store_id),
          is_active: body.is_active === undefined ? true : flag(body.is_active),
          expires_at: typeof body.expires_at === 'string' && body.expires_at !== '' ? body.expires_at : null,
        })
        return { status: 201, body: { success: true, message: 'Promo-kod yaratildi.', data: this.codeCard(code) } }
      }
    }
    const codePath = /^\/marketing\/promo-codes\/(\d+)(?:\/(activate|deactivate))?$/.exec(path)
    if (codePath) {
      const code = this.codes.find((item) => item.id === idOf(codePath))
      if (!code) return fail(404, 'Promo-kod topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET') return ok(this.codeCard(code))
      if (req.method === 'POST' && codePath[2]) {
        const reason = reasonProblem()
        if (reason) return reason
        code.is_active = codePath[2] === 'activate'
        return ok(this.codeCard(code))
      }
      if (req.method === 'PATCH') {
        const reason = reasonProblem()
        if (reason) return reason
        const locked = ['code', 'type', 'value', 'max_discount_amount', 'store_id'].filter((field) => {
          if (!(field in body)) return false
          const now = field === 'code' ? String(body.code).toUpperCase() : field === 'type' ? body.type : num(body[field])
          const was = field === 'code' ? code.code : (code as unknown as Record<string, unknown>)[field]
          return now !== was
        })
        if (code.has_orders && locked.length > 0) return fail(422, "Bu promo-koddan buyurtma berilgan, shuning uchun uning chegirmasini o'zgartirib bo'lmaydi.", { reason: 'promo_code_used', fields: locked })
        if ('code' in body) code.code = String(body.code).toUpperCase()
        if ('type' in body) code.type = body.type === 'fixed' ? 'fixed' : 'percentage'
        if ('value' in body) code.value = Number(body.value)
        if ('min_order_amount' in body) code.min_order_amount = num(body.min_order_amount) ?? 0
        if ('max_discount_amount' in body) code.max_discount_amount = num(body.max_discount_amount)
        if ('usage_limit' in body) code.usage_limit = num(body.usage_limit)
        if ('per_user_limit' in body) code.per_user_limit = num(body.per_user_limit)
        if ('store_id' in body) code.store_id = num(body.store_id)
        if ('is_active' in body) code.is_active = flag(body.is_active)
        if ('expires_at' in body) code.expires_at = typeof body.expires_at === 'string' && body.expires_at !== '' ? body.expires_at : null
        return ok(this.codeCard(code))
      }
      if (req.method === 'DELETE') {
        const recent = needsRecent()
        if (recent) return recent
        const reason = reasonProblem()
        if (reason) return reason
        if (code.has_orders) return fail(409, "Bu promo-koddan buyurtma berilgan, uni o'chirib bo'lmaydi. O'chirib qo'ying (faolsizlantiring).", { reason: 'promo_code_used' })
        this.codes = this.codes.filter((item) => item.id !== code.id)
        return ok({ id: code.id })
      }
    }

    // --- Categories
    if (path === '/marketing/categories') {
      if (req.method === 'GET') return ok(this.categoryList())
      if (req.method === 'POST') {
        const reason = reasonProblem()
        if (reason) return reason
        const name = String(body.name ?? '').trim()
        if (name.length < 2) return validation('name', 'Nomi kamida 2 ta belgi bo‘lsin.')
        const parent = num(body.parent_id)
        if (parent !== null && !this.categories.some((item) => item.id === parent && item.parent_id === null)) return fail(422, "Ota kategoriya asosiy kategoriya bo'lishi kerak.", { reason: 'invalid_parent' })
        const category = this.addCategory({ name, parent_id: parent, is_active: body.is_active === undefined ? true : flag(body.is_active), has_image: typeof body.image === 'object' && body.image !== null })
        return { status: 201, body: { success: true, message: "Kategoriya qo'shildi.", data: this.categoryRow(category) } }
      }
    }
    if (req.method === 'PUT' && path === '/marketing/categories/order') {
      const reason = reasonProblem()
      if (reason) return reason
      const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []
      if (ids.length !== this.categories.length || this.categories.some((category) => !ids.includes(category.id))) return fail(409, "Ro'yxat eskirgan: sahifani yangilang va qayta urining.", { reason: 'order_stale' })
      ids.forEach((id, index) => {
        this.categories.find((category) => category.id === id)!.sort_order = index + 1
      })
      return ok(this.categoryList())
    }
    const categoryPath = /^\/marketing\/categories\/(\d+)(\/image)?$/.exec(path)
    if (categoryPath) {
      const category = this.categories.find((item) => item.id === idOf(categoryPath))
      if (!category) return fail(404, 'Kategoriya topilmadi.', { reason: 'not_found' })
      if (req.method === 'GET' && categoryPath[2]) return category.has_image ? { status: 200, raw: PNG, headers: { 'Content-Type': 'image/png' } } : fail(404, 'Rasm topilmadi.', { reason: 'no_image' })
      if (req.method === 'POST') {
        const reason = reasonProblem()
        if (reason) return reason
        if ('name' in body) category.name = String(body.name).trim()
        if ('is_active' in body) category.is_active = flag(body.is_active)
        if ('parent_id' in body) {
          const parent = num(body.parent_id)
          if (parent !== null && (parent === category.id || !this.categories.some((item) => item.id === parent && item.parent_id === null) || this.categories.some((item) => item.parent_id === category.id))) {
            return fail(422, "Ota kategoriya asosiy kategoriya bo'lishi kerak.", { reason: 'invalid_parent' })
          }
          category.parent_id = parent
        }
        if (typeof body.image === 'object' && body.image !== null) category.has_image = true
        else if (flag(body.remove_image)) category.has_image = false
        return ok(this.categoryRow(category))
      }
      if (req.method === 'DELETE') {
        const recent = needsRecent()
        if (recent) return recent
        const reason = reasonProblem()
        if (reason) return reason
        const children = this.categories.filter((item) => item.parent_id === category.id).length
        if (category.products_count > 0 || children > 0) return fail(409, "Kategoriyada mahsulotlar yoki ichki kategoriyalar bor, uni o'chirib bo'lmaydi. Uni yashiring.", { reason: 'category_in_use', products: category.products_count, children })
        this.categories = this.categories.filter((item) => item.id !== category.id)
        this.categories.forEach((item, index) => (item.sort_order = index + 1))
        return ok({ id: category.id })
      }
    }

    return null
  }

  private codeCard(code: FakeCode) {
    return { ...this.codeRow(code), recent_orders: code.orders }
  }
}
