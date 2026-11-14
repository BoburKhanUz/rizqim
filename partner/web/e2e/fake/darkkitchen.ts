// The dark kitchen pages of the fake backend (docs/API.md P6-55): two brands with their channel rules and branch
// switches, the dishes' brand, one ingredient ("Tovuq") linked to the menu's dishes with its quick stop, the channel net
// revenue report and the channel commissions. Enough for the panel's flows; the prices, the availability and the
// report's sums are the backend's and its tests.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

interface Brand {
  id: number
  uuid: string
  name: string
  short_code: string
  color: string
  logo_url: string | null
  is_active: boolean
  sort_order: number
  channels: Array<{ channel: string; is_hidden: boolean; price_percent: string | null }>
  off_branch_ids: number[]
}

interface Ingredient {
  id: number
  uuid: string
  name: string
  unit: string | null
  links: Array<{ product_id: number; is_critical: boolean }>
  stopped: Array<{ branch_id: number; entry_id: number; reason: string | null; until: string | null }>
}

const figures = (orders: number, gross: number, discounts: number, refunds: number, commission: number) => ({
  orders,
  gross: gross.toFixed(2),
  discounts: discounts.toFixed(2),
  refunds: refunds.toFixed(2),
  commission: commission.toFixed(2),
  net: (gross - discounts - refunds - commission).toFixed(2),
})

export class FakeDarkKitchen {
  brands: Brand[] = [
    { id: 1, uuid: 'b0000000-0000-4000-8000-000000000001', name: 'Burger Bro', short_code: 'BB', color: '#DC2626', logo_url: null, is_active: true, sort_order: 0, channels: [], off_branch_ids: [] },
    { id: 2, uuid: 'b0000000-0000-4000-8000-000000000002', name: 'Lavash Lab', short_code: 'LL', color: '#16A34A', logo_url: null, is_active: true, sort_order: 1, channels: [{ channel: 'aggregator', is_hidden: false, price_percent: '15.00' }], off_branch_ids: [] },
  ]
  productBrands = new Map<number, number>()
  ingredients: Ingredient[] = [{ id: 1, uuid: 'i0000000-0000-4000-8000-000000000001', name: 'Tovuq', unit: 'kg', links: [{ product_id: 501, is_critical: true }], stopped: [] }]
  rates: Array<{ channel: string; percent: string; note: string | null }> = [{ channel: 'aggregator', percent: '25.00', note: 'Yandex' }]
  private next = 3

  constructor(private readonly backend: FakeBackend) {}

  private productName(id: number): string {
    return this.backend.menu.products.find((product) => product.id === id)?.name ?? `#${id}`
  }

  private brandView(brand: Brand) {
    return { ...brand, products_count: [...this.productBrands.values()].filter((id) => id === brand.id).length }
  }

  private ingredientView(ingredient: Ingredient, branchId: number | null) {
    return {
      ...ingredient,
      links: ingredient.links.map((link) => ({ ...link, name: this.productName(link.product_id) })),
      stopped_here: branchId !== null && ingredient.stopped.some((stop) => stop.branch_id === branchId),
    }
  }

  private report() {
    const rows = [
      { channel: 'aggregator', channel_label: 'Agregator', commission_percent: '25.00', commission_source: 'owner', brand_id: 2, brand_name: 'Lavash Lab', brand_code: 'LL', brand_color: '#16A34A', ...figures(1, 50000, 0, 0, 12500) },
      { channel: 'telegram', channel_label: 'Telegram', commission_percent: null, commission_source: 'billing', brand_id: 1, brand_name: 'Burger Bro', brand_code: 'BB', brand_color: '#DC2626', ...figures(1, 60000, 6000, 0, 5400) },
    ]
    return {
      period: { from: '2026-11-15', to: '2026-11-21', timezone: 'Asia/Tashkent' },
      branch_id: null,
      currency: 'UZS',
      rows,
      by_channel: [
        { channel: 'aggregator', channel_label: 'Agregator', commission_percent: '25.00', commission_source: 'owner', ...figures(1, 50000, 0, 0, 12500) },
        { channel: 'telegram', channel_label: 'Telegram', commission_percent: null, commission_source: 'billing', ...figures(1, 60000, 6000, 0, 5400) },
      ],
      by_brand: [
        { brand_id: 1, brand_name: 'Burger Bro', brand_code: 'BB', brand_color: '#DC2626', ...figures(1, 60000, 6000, 0, 5400) },
        { brand_id: 2, brand_name: 'Lavash Lab', brand_code: 'LL', brand_color: '#16A34A', ...figures(1, 50000, 0, 0, 12500) },
      ],
      totals: figures(2, 110000, 6000, 0, 17900),
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path

    // Brands
    if (req.method === 'GET' && path === '/v1/owner/brands') return ok({ brands: this.brands.map((brand) => this.brandView(brand)), channels_available: ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'] })
    if (req.method === 'POST' && path === '/v1/owner/brands') {
      const code = String(body?.short_code ?? '').toUpperCase()
      if (this.brands.some((brand) => brand.short_code === code)) return fail(422, 'Bu qisqa kod boshqa brendda bor.', { short_code: ['Bu qisqa kod boshqa brendda bor.'] })
      const brand: Brand = { id: this.next++, uuid: `b0000000-0000-4000-8000-00000000000${this.next}`, name: String(body?.name ?? ''), short_code: code, color: String(body?.color ?? '#64748B'), logo_url: null, is_active: true, sort_order: this.brands.length, channels: [], off_branch_ids: [] }
      this.brands.push(brand)
      return { status: 201, body: { success: true, message: 'Brend yaratildi.', data: this.brandView(brand) } }
    }
    const brandMatch = /^\/v1\/owner\/brands\/(\d+)(\/channels|\/branches)?$/.exec(path)
    if (brandMatch) {
      const brand = this.brands.find((row) => row.id === Number(brandMatch[1]))
      if (!brand) return fail(404, 'Brend topilmadi.')
      if (req.method === 'PATCH' && !brandMatch[2]) {
        Object.assign(brand, body ?? {})
        return ok(this.brandView(brand))
      }
      if (req.method === 'DELETE' && !brandMatch[2]) {
        this.brands = this.brands.filter((row) => row !== brand)
        return ok(null)
      }
      if (req.method === 'PUT' && brandMatch[2] === '/channels') {
        const rows = (body?.channels as Brand['channels'] | undefined) ?? []
        brand.channels = rows.filter((row) => row.is_hidden || (row.price_percent !== null && Number(row.price_percent) !== 0)).map((row) => ({ channel: row.channel, is_hidden: row.is_hidden, price_percent: row.is_hidden || row.price_percent === null ? null : Number(row.price_percent).toFixed(2) }))
        return ok(this.brandView(brand))
      }
      if (req.method === 'PUT' && brandMatch[2] === '/branches') {
        const rows = (body?.branches as Array<{ branch_id: number; is_active: boolean }> | undefined) ?? []
        brand.off_branch_ids = rows.filter((row) => !row.is_active).map((row) => row.branch_id)
        return ok(this.brandView(brand))
      }
    }
    if (req.method === 'PUT' && path === '/v1/owner/product-brands') {
      const ids = (body?.product_ids as number[] | undefined) ?? []
      const brandId = body?.brand_id as number | null
      for (const id of ids) {
        if (brandId === null) this.productBrands.delete(id)
        else this.productBrands.set(id, brandId)
      }
      for (const product of this.backend.menu.products) (product as unknown as Record<string, unknown>).brand_id = this.productBrands.get(product.id) ?? null
      return ok({ changed: ids.length, brand_id: brandId })
    }

    // Ingredients
    if (req.method === 'GET' && path === '/v1/owner/ingredients') {
      const branchId = req.query.branch_id ? Number(req.query.branch_id) : null
      return ok({ branch_id: branchId, ingredients: this.ingredients.map((ingredient) => this.ingredientView(ingredient, branchId)) })
    }
    if (req.method === 'POST' && path === '/v1/owner/ingredients') {
      const ingredient: Ingredient = { id: this.next++, uuid: `i0000000-0000-4000-8000-00000000000${this.next}`, name: String(body?.name ?? ''), unit: (body?.unit as string | null) ?? null, links: [], stopped: [] }
      this.ingredients.push(ingredient)
      return { status: 201, body: { success: true, message: "Ingredient qo'shildi.", data: this.ingredientView(ingredient, null) } }
    }
    const ingredientMatch = /^\/v1\/owner\/ingredients\/(\d+)(\/stop|\/products|\/link-categories)?$/.exec(path)
    if (ingredientMatch) {
      const ingredient = this.ingredients.find((row) => row.id === Number(ingredientMatch[1]))
      if (!ingredient) return fail(404, 'Ingredient topilmadi.')
      const action = ingredientMatch[2]
      if (req.method === 'POST' && action === '/stop') {
        const branchId = Number(body?.branch_id)
        ingredient.stopped = [...ingredient.stopped.filter((stop) => stop.branch_id !== branchId), { branch_id: branchId, entry_id: 50, reason: (body?.reason as string | undefined) ?? null, until: (body?.until as string | undefined) ?? null }]
        const closed = ingredient.links.filter((link) => link.is_critical).map((link) => link.product_id)
        return { status: 201, body: { success: true, message: "Ingredient stop-listga qo'shildi.", data: { entry_id: 50, branch_id: branchId, ingredient_id: ingredient.id, reason: body?.reason ?? null, until: body?.until ?? null, closed_product_ids: closed } } }
      }
      if (req.method === 'DELETE' && action === '/stop') {
        const branchId = Number(req.query.branch_id)
        if (!ingredient.stopped.some((stop) => stop.branch_id === branchId)) return fail(404, 'Bu ingredient filialda stop-listda emas.')
        ingredient.stopped = ingredient.stopped.filter((stop) => stop.branch_id !== branchId)
        return ok({ branch_id: branchId, ingredient_id: ingredient.id })
      }
      if (req.method === 'PUT' && action === '/products') {
        ingredient.links = ((body?.links as Ingredient['links'] | undefined) ?? []).map((link) => ({ product_id: link.product_id, is_critical: link.is_critical ?? true }))
        return ok(this.ingredientView(ingredient, null))
      }
      if (req.method === 'POST' && action === '/link-categories') {
        const added = this.backend.menu.products.filter((product) => !ingredient.links.some((link) => link.product_id === product.id))
        ingredient.links.push(...added.map((product) => ({ product_id: product.id, is_critical: true })))
        return ok({ ...this.ingredientView(ingredient, null), added: added.length })
      }
      if (req.method === 'PATCH' && !action) {
        Object.assign(ingredient, body ?? {})
        return ok(this.ingredientView(ingredient, null))
      }
      if (req.method === 'DELETE' && !action) {
        this.ingredients = this.ingredients.filter((row) => row !== ingredient)
        return ok(null)
      }
    }

    // The channel net revenue report and the commissions
    if (req.method === 'GET' && path === '/v1/owner/reports/channel-revenue') return ok(this.report())
    if (req.method === 'GET' && path === '/v1/owner/reports/channel-revenue/export') {
      return { status: 200, body: null, raw: 'xlsx', headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': `attachment; filename=kanal-tushum-${req.query.from}-${req.query.to}.xlsx` } }
    }
    if (req.method === 'GET' && path === '/v1/owner/channel-commissions') return ok({ rates: this.rates, channels: ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'] })
    if (req.method === 'PUT' && path === '/v1/owner/channel-commissions') {
      this.rates = ((body?.rates as typeof this.rates | undefined) ?? []).map((rate) => ({ channel: rate.channel, percent: Number(rate.percent).toFixed(2), note: rate.note ?? null }))
      return ok({ rates: this.rates, channels: [] })
    }
    return null
  }
}
