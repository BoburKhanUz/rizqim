// The menu side of the fake backend: products with variants (multipart form), categories, modifier groups, channel
// prices, branch availability, the stop-list and the batches ("qozon"). Shaped like docs/MENU.md and the OpenAPI spec.
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeVariant {
  id: number
  uuid: string
  product_id: number
  name: string | null
  sku: string | null
  price: string
  stock: number
  attributes_json: null
  is_active: boolean
  unit: 'piece' | 'kg' | 'l'
  quantity_step: string | null
  min_quantity: string | null
  batch_consumption: string
  unit_label: string | null
}

export interface FakeProduct {
  id: number
  uuid: string
  store_id: number
  category_id: number
  name: string
  slug: string
  description: string | null
  type: 'physical'
  sku: null
  status: 'active' | 'inactive'
  sort_order: number
  has_variants: boolean
  image: null
  image_url: null
  image_thumb_urls: { sm: null; md: null }
  batch_tracked: boolean
  is_active: boolean
  // P3-11: what the item is on a receipt and its codes
  kind: 'dish' | 'service'
  mxik_code: string | null
  package_code: string | null
  vat_rate: string | null
  category: { id: number; name: string } | null
  variants: FakeVariant[]
}

export interface FakeModifier {
  id: number
  uuid: string
  name: string
  name_i18n: Record<string, string> | null
  price_delta: string
  max_quantity: number
  product_variant_id: number | null
  sort_order: number
  is_active: boolean
}

export interface FakeGroup {
  id: number
  uuid: string
  name: string
  name_i18n: Record<string, string> | null
  min_select: number
  max_select: number | null
  is_required: boolean
  sort_order: number
  is_active: boolean
  modifiers: FakeModifier[]
  product_ids: number[]
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

const CATEGORIES = [
  { id: 2, uuid: 'c0000000-0000-4000-8000-000000000002', parent_id: null, name: 'Issiq taomlar', slug: 'issiq', sort_order: 0, is_active: true },
  { id: 3, uuid: 'c0000000-0000-4000-8000-000000000003', parent_id: null, name: 'Ichimliklar', slug: 'ichimliklar', sort_order: 1, is_active: true },
]

const slugify = (name: string) => name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'taom'

export class FakeMenu {
  products: FakeProduct[] = []
  groups: FakeGroup[] = []
  stopList: FakeStopEntry[] = []
  /** ProductChannelPrice rows the owner saved, by product id (the body of PUT /owner/products/{id}/channel-prices). */
  channelPrices = new Map<number, unknown>()
  private counter = 500

  constructor(private readonly backend: FakeBackend) {
    this.addProduct({ name: 'Osh', categoryId: 2, variants: [{ name: '1 porsiya', price: '35000.00' }] })
    this.addProduct({ name: 'Lagmon', categoryId: 2, variants: [{ name: 'Kichik', price: '28000.00' }, { name: 'Katta', price: '38000.00' }] })
  }

  /** Puts a product into the menu directly (test setup). */
  addProduct(input: { name: string; categoryId?: number; variants: Array<{ name: string; price: string; unit?: FakeVariant['unit']; step?: string; min?: string }> }): FakeProduct {
    const id = ++this.counter
    const category = CATEGORIES.find((candidate) => candidate.id === (input.categoryId ?? 2)) ?? null
    const product: FakeProduct = {
      id,
      uuid: `p0000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
      store_id: 1,
      category_id: category?.id ?? 2,
      name: input.name,
      slug: slugify(input.name),
      description: null,
      type: 'physical',
      sku: null,
      status: 'active',
      sort_order: 0,
      has_variants: input.variants.length > 1,
      image: null,
      image_url: null,
      image_thumb_urls: { sm: null, md: null },
      batch_tracked: false,
      is_active: true,
      kind: 'dish',
      mxik_code: null,
      package_code: null,
      vat_rate: null,
      category: category && { id: category.id, name: category.name },
      variants: input.variants.map((variant) => this.variant(id, variant)),
    }
    this.products.push(product)
    return product
  }

  private variant(productId: number, input: { name: string; price: string | number; unit?: string; step?: unknown; min?: unknown; quantity_step?: unknown; min_quantity?: unknown }): FakeVariant {
    const id = ++this.counter
    const decimal = (value: unknown) => (value === null || value === undefined || value === '' ? null : Number(value).toFixed(3))
    return {
      id,
      uuid: `v0000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
      product_id: productId,
      name: input.name,
      sku: null,
      price: Number(input.price).toFixed(2),
      stock: 999,
      attributes_json: null,
      is_active: true,
      unit: (input.unit as FakeVariant['unit']) ?? 'piece',
      quantity_step: decimal(input.quantity_step ?? input.step),
      min_quantity: decimal(input.min_quantity ?? input.min),
      batch_consumption: '1.000',
      unit_label: null,
    }
  }

  // --- Products ----------------------------------------------------------------------------------------------------------

  private saveProduct(form: FormData, existing: FakeProduct | null): Reply {
    const name = String(form.get('name') ?? '').trim()
    const variants = JSON.parse(String(form.get('variants') ?? '[]')) as Array<Record<string, unknown>>
    const errors: Record<string, string[]> = {}
    if (name === '') errors.name = ["Taom nomini kiriting."]
    if (!CATEGORIES.some((category) => category.id === Number(form.get('category_id')))) errors.category_id = ['Kategoriya topilmadi.']
    if (variants.length === 0) errors.variants = ["Kamida bitta variant bo'lishi kerak."]
    variants.forEach((variant, index) => {
      if (!(Number(variant.price) > 0)) errors[`variants.${index}.price`] = ["Narx noldan katta bo'lishi kerak."]
      const step = Number(variant.quantity_step ?? 0)
      const min = Number(variant.min_quantity ?? 0)
      if (variant.unit !== 'piece' && step > 0 && min > 0 && Math.abs(min / step - Math.round(min / step)) > 1e-9) {
        errors[`variants.${index}.min_quantity`] = ["Eng kam miqdor qadamga karrali bo'lishi kerak."]
      }
    })
    // P3-11: a field left out is kept, an empty one is cleared; an MXIK is 17 digits
    const mxik = form.get('mxik_code')
    if (typeof mxik === 'string' && mxik !== '' && !/^\d{17}$/.test(mxik)) errors.mxik_code = ["MXIK kodi 17 ta raqamdan iborat bo'lishi kerak."]
    // (the real server checks a code against the classifier only in its tests; the fake knows one code that is not there)
    else if (mxik === '00000000000000000') errors.mxik_code = ["Bu MXIK tasnifda yo'q."]
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)

    const id = existing?.id ?? ++this.counter
    const category = CATEGORIES.find((candidate) => candidate.id === Number(form.get('category_id')))!
    const kept = new Map((existing?.variants ?? []).map((variant) => [variant.id, variant]))
    const product: FakeProduct = {
      ...(existing ?? this.addBlank(id)),
      name,
      slug: slugify(name),
      description: String(form.get('description') ?? '') || null,
      category_id: category.id,
      category: { id: category.id, name: category.name },
      is_active: form.get('is_active') !== '0',
      status: form.get('is_active') === '0' ? 'inactive' : 'active',
      batch_tracked: form.get('batch_tracked') === '1',
      sort_order: form.has('sort_order') ? Number(form.get('sort_order')) : (existing?.sort_order ?? 0),
      has_variants: variants.length > 1,
      kind: form.has('kind') ? (form.get('kind') === 'service' ? 'service' : 'dish') : (existing?.kind ?? 'dish'),
      mxik_code: form.has('mxik_code') ? String(form.get('mxik_code')) || null : (existing?.mxik_code ?? null),
      package_code: form.has('package_code') ? String(form.get('package_code')) || null : (existing?.package_code ?? null),
      vat_rate: form.has('vat_rate') ? (String(form.get('vat_rate')) === '' ? null : Number(form.get('vat_rate')).toFixed(2)) : (existing?.vat_rate ?? null),
      variants: variants.map((variant) => {
        const previous = typeof variant.id === 'number' ? kept.get(variant.id) : undefined
        return previous ? { ...previous, ...this.variant(id, { name: String(variant.name), price: Number(variant.price), unit: String(variant.unit), quantity_step: variant.quantity_step, min_quantity: variant.min_quantity }), id: previous.id, uuid: previous.uuid } : this.variant(id, { name: String(variant.name), price: Number(variant.price), unit: String(variant.unit), quantity_step: variant.quantity_step, min_quantity: variant.min_quantity })
      }),
    }
    if (existing) this.products[this.products.indexOf(existing)] = product
    else this.products.push(product)
    return existing ? ok(product) : created(product)
  }

  private addBlank(id: number): FakeProduct {
    return {
      id,
      uuid: `p0000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
      store_id: 1,
      category_id: 2,
      name: '',
      slug: '',
      description: null,
      type: 'physical',
      sku: null,
      status: 'active',
      sort_order: 0,
      has_variants: false,
      image: null,
      image_url: null,
      image_thumb_urls: { sm: null, md: null },
      batch_tracked: false,
      is_active: true,
      kind: 'dish',
      mxik_code: null,
      package_code: null,
      vat_rate: null,
      category: null,
      variants: [],
    }
  }

  // --- Modifier groups -------------------------------------------------------------------------------------------------------

  private saveGroup(input: Record<string, unknown>, existing: FakeGroup | null): Reply {
    const name = String(input.name ?? '').trim()
    if (name === '') return fail(422, 'The given data was invalid.', { name: ['Guruh nomini kiriting.'] })
    const options = (input.modifiers as Array<Record<string, unknown>> | undefined) ?? existing?.modifiers ?? []
    const id = existing?.id ?? ++this.counter
    const group: FakeGroup = {
      id,
      uuid: existing?.uuid ?? `g0000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
      name,
      name_i18n: (input.name_i18n as Record<string, string> | null | undefined) ?? existing?.name_i18n ?? null,
      min_select: typeof input.min_select === 'number' ? input.min_select : (existing?.min_select ?? 0),
      max_select: 'max_select' in input ? (input.max_select as number | null) : (existing?.max_select ?? null),
      is_required: typeof input.is_required === 'boolean' ? input.is_required : (existing?.is_required ?? false),
      sort_order: typeof input.sort_order === 'number' ? input.sort_order : (existing?.sort_order ?? 0),
      is_active: typeof input.is_active === 'boolean' ? input.is_active : (existing?.is_active ?? true),
      modifiers: options.map((option, position) => {
        const modifierId = typeof option.id === 'number' ? option.id : ++this.counter
        return {
          id: modifierId,
          uuid: `m0000000-0000-4000-8000-${String(modifierId).padStart(12, '0')}`,
          name: String(option.name),
          name_i18n: (option.name_i18n as Record<string, string> | null | undefined) ?? null,
          price_delta: Number(option.price_delta ?? 0).toFixed(2),
          max_quantity: typeof option.max_quantity === 'number' ? option.max_quantity : 1,
          product_variant_id: null,
          sort_order: position,
          is_active: option.is_active !== false,
        }
      }),
      product_ids: existing?.product_ids ?? [],
    }
    if (existing) this.groups[this.groups.indexOf(existing)] = group
    else this.groups.push(group)
    return existing ? ok(group) : created(group)
  }

  private nameOf(type: string, id: number): string | null {
    if (type === 'product') return this.products.find((product) => product.id === id)?.name ?? null
    if (type === 'variant') return this.products.flatMap((product) => product.variants).find((variant) => variant.id === id)?.name ?? null
    return this.groups.flatMap((group) => group.modifiers).find((modifier) => modifier.id === id)?.name ?? null
  }

  private branchOf(query: Record<string, string>, bodyBranch?: unknown): number | Reply {
    const bound = this.backend.account.branchId
    if (bound !== null) return bound
    const id = Number(query.branch_id ?? bodyBranch)
    return id ? id : fail(422, 'The given data was invalid.', { branch_id: ['Filialni tanlang.'] })
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    const form = req.body instanceof FormData ? req.body : null
    let match: RegExpExecArray | null
    const path = req.path

    if (req.method === 'GET' && path === '/v1/owner/products') return ok(this.products)
    if (req.method === 'GET' && path === '/v1/owner/categories') return ok(CATEGORIES)
    if (req.method === 'POST' && path === '/v1/owner/products' && form) return this.saveProduct(form, null)
    if ((match = /^\/v1\/owner\/products\/(\d+)$/.exec(path))) {
      const product = this.products.find((candidate) => candidate.id === Number(match![1]))
      if (!product) return fail(404, 'Taom topilmadi.')
      if (req.method === 'POST' && form) return this.saveProduct(form, product)
      if (req.method === 'POST' && req.query.toggle_active) {
        product.is_active = !product.is_active
        product.status = product.is_active ? 'active' : 'inactive'
        return ok(product)
      }
      if (req.method === 'DELETE') {
        this.products = this.products.filter((candidate) => candidate !== product)
        return ok(null)
      }
    }

    if (req.method === 'GET' && path === '/v1/owner/modifier-groups') return ok(this.groups)
    if (req.method === 'POST' && path === '/v1/owner/modifier-groups') return this.saveGroup(input, null)
    if ((match = /^\/v1\/owner\/modifier-groups\/(\d+)$/.exec(path))) {
      const group = this.groups.find((candidate) => candidate.id === Number(match![1]))
      if (!group) return fail(404, 'Guruh topilmadi.')
      if (req.method === 'PUT') return this.saveGroup(input, group)
      if (req.method === 'DELETE') {
        this.groups = this.groups.filter((candidate) => candidate !== group)
        return ok(null)
      }
    }
    if ((match = /^\/v1\/owner\/products\/(\d+)\/modifier-groups$/.exec(path)) && req.method === 'PUT') {
      const productId = Number(match[1])
      const chosen = new Set(((input.groups as Array<{ modifier_group_id: number }> | undefined) ?? []).map((group) => group.modifier_group_id))
      for (const group of this.groups) group.product_ids = chosen.has(group.id) ? [...new Set([...group.product_ids, productId])] : group.product_ids.filter((id) => id !== productId)
      return ok(null)
    }

    if ((match = /^\/v1\/owner\/products\/(\d+)\/channel-prices$/.exec(path))) {
      const product = this.products.find((candidate) => candidate.id === Number(match![1]))
      if (!product) return fail(404, 'Taom topilmadi.')
      if (req.method === 'GET') {
        return ok({
          product_id: product.id,
          channels: ['telegram', 'mobile'],
          prices: product.variants.flatMap((variant) => ['telegram', 'mobile'].map((channel) => ({ channel, variant_id: variant.id, price: null, is_hidden: false }))),
          hidden_channels: [],
        })
      }
      if (req.method === 'PUT') {
        this.channelPrices.set(product.id, input)
        return ok([])
      }
    }
    if (req.method === 'GET' && path === '/v1/owner/branch-variants') {
      return ok(
        this.products.map((product, index) => ({
          product_id: product.id,
          name: product.name,
          description: null,
          image_url: null,
          variants: product.variants.map((variant) => ({ variant_id: variant.id, variant_uuid: variant.uuid, name: variant.name, price: variant.price, is_active: true, hidden_reason: null, sort_order: index })),
        })),
      )
    }

    if (req.method === 'GET' && path === '/v1/owner/stop-list') {
      const branch = this.branchOf(req.query)
      return typeof branch === 'number' ? ok({ branch_id: branch, entries: this.stopList.filter((entry) => entry.branch_id === branch) }) : branch
    }
    if (req.method === 'POST' && path === '/v1/owner/stop-list') {
      const branch = this.branchOf({}, input.branch_id)
      if (typeof branch !== 'number') return branch
      const type = String(input.type)
      const id = Number(input.id)
      const name = this.nameOf(type, id)
      if (!['product', 'variant', 'modifier'].includes(type) || name === null) return fail(422, 'The given data was invalid.', { id: ['Tanlangan element topilmadi.'] })
      const existing = this.stopList.find((entry) => entry.branch_id === branch && entry.type === type && entry.subject_id === id)
      if (existing) return created(existing)
      const entry: FakeStopEntry = {
        id: ++this.counter,
        branch_id: branch,
        type,
        subject_id: id,
        subject_uuid: null,
        name,
        reason: typeof input.reason === 'string' ? input.reason : null,
        until: typeof input.until === 'string' ? input.until : null,
        created_by: this.backend.account.id,
        created_at: new Date().toISOString(),
      }
      this.stopList.push(entry)
      return created(entry)
    }
    if ((match = /^\/v1\/owner\/stop-list\/(\d+)$/.exec(path)) && req.method === 'DELETE') {
      this.stopList = this.stopList.filter((entry) => entry.id !== Number(match![1]))
      return ok(null)
    }
    if (req.method === 'GET' && path === '/v1/owner/batches') {
      const branch = this.branchOf(req.query)
      return typeof branch === 'number' ? ok({ branch_id: branch, batches: [] }) : branch
    }
    return null
  }
}
