// The combo constructor of the fake backend (P6-31, docs/API.md "Kombo"): combos built from the fake menu's products, the
// receipt preview and the store's combo fiscal mode. Answers like the server: the product must exist and not be a combo
// yet, every slot needs a name and options, min ≤ max, one default per slot, a delta of 0 or more (422 otherwise).
import type { FakeBackend } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

interface FakeOption {
  id: number
  uuid: string
  variant_id: number
  quantity: number
  price_delta: string
  is_default: boolean
  label: string | null
}

interface FakeSlot {
  id: number
  uuid: string
  name: string
  min_picks: number
  max_picks: number
  allow_modifiers: boolean
  options: FakeOption[]
}

export interface FakeCombo {
  id: number
  uuid: string
  product_id: number
  is_active: boolean
  slots: FakeSlot[]
}

const uuid = (id: number) => `c0000000-0000-4000-8000-${String(id).padStart(12, '0')}`

export class FakeCombos {
  combos: FakeCombo[] = []
  fiscalMode: 'single' | 'split' = 'single'
  private counter = 900

  constructor(readonly backend: FakeBackend) {}

  private dish(variantId: number) {
    for (const product of this.backend.menu.products) {
      const variant = product.variants.find((candidate) => candidate.id === variantId)
      if (variant) return { product, variant }
    }
    return null
  }

  private presentOption(slot: FakeSlot, option: FakeOption) {
    const dish = this.dish(option.variant_id)
    const name = dish?.product.name ?? ''
    const variantName = dish && dish.variant.name !== name ? dish.variant.name : null
    return {
      ...option,
      variant_uuid: dish?.variant.uuid ?? '',
      product_id: dish?.product.id ?? null,
      name,
      variant_name: variantName,
      modifiers: [],
      receipt_label: option.label ?? `${slot.name} — ${name}${variantName ? ` (${variantName})` : ''}`,
    }
  }

  private present(combo: FakeCombo) {
    const product = this.backend.menu.products.find((candidate) => candidate.id === combo.product_id)
    return {
      ...combo,
      product_name: product?.name ?? null,
      prices: product?.variants.map((variant) => variant.price) ?? [],
      slots: combo.slots.map((slot) => ({ ...slot, is_required: slot.min_picks > 0, options: slot.options.map((option) => this.presentOption(slot, option)) })),
    }
  }

  private slotsFrom(raw: unknown): { slots?: FakeSlot[]; errors?: Record<string, string[]> } {
    const slots = Array.isArray(raw) ? (raw as Array<Record<string, unknown>>) : []
    if (slots.length === 0) return { errors: { slots: ["Komboda 1 tadan 8 tagacha slot bo'lishi kerak."] } }
    const errors: Record<string, string[]> = {}
    const result: FakeSlot[] = slots.map((slot, s) => {
      const name = String(slot.name ?? '').trim()
      const min = Number(slot.min_picks ?? 1)
      const max = Number(slot.max_picks ?? Math.max(1, min))
      if (name === '') errors[`slots.${s}.name`] = ['Slot nomini yozing (masalan "Ichimlik").']
      if (min > max) errors[`slots.${s}.max_picks`] = ["Tanlovlar soni noto'g'ri."]
      const options = Array.isArray(slot.options) ? (slot.options as Array<Record<string, unknown>>) : []
      if (options.filter((option) => option.is_default === true).length > 1) errors[`slots.${s}.options`] = ["Slotda bitta standart tanlov bo'ladi."]
      return {
        id: ++this.counter,
        uuid: uuid(this.counter),
        name,
        min_picks: min,
        max_picks: max,
        allow_modifiers: slot.allow_modifiers === true,
        options: options.map((option, o) => {
          if (!this.dish(Number(option.variant_id))) errors[`slots.${s}.options.${o}.variant_id`] = ['Taom topilmadi yoki faol emas.']
          if (Number(option.price_delta ?? 0) < 0) errors[`slots.${s}.options.${o}.price_delta`] = ["Narx farqi manfiy bo'lmaydi (0 yoki qo'shimcha)."]
          return {
            id: ++this.counter,
            uuid: uuid(this.counter),
            variant_id: Number(option.variant_id),
            quantity: Number(option.quantity ?? 1),
            price_delta: Number(option.price_delta ?? 0).toFixed(2),
            is_default: option.is_default === true,
            label: typeof option.label === 'string' && option.label.trim() !== '' ? option.label.trim() : null,
          }
        }),
      }
    })
    return Object.keys(errors).length > 0 ? { errors } : { slots: result }
  }

  private preview(combo: FakeCombo, picks: Array<{ option_id: string }>): Reply {
    const product = this.backend.menu.products.find((candidate) => candidate.id === combo.product_id)
    const base = Number(product?.variants[0]?.price ?? 0)
    const chosen = new Set(picks.map((pick) => pick.option_id))
    const parts: Array<{ slot: FakeSlot; option: FakeOption }> = []
    for (const slot of combo.slots) {
      const picked = slot.options.filter((option) => chosen.has(option.uuid))
      const taken = picked.length > 0 ? picked : slot.min_picks > 0 ? slot.options.filter((option) => option.is_default) : []
      for (const option of taken) parts.push({ slot, option })
    }
    const delta = parts.reduce((sum, part) => sum + Number(part.option.price_delta), 0)
    const price = (base + delta).toFixed(2)
    const list = parts.map((part) => Number(this.dish(part.option.variant_id)?.variant.price ?? 0))
    const weight = list.reduce((sum, value) => sum + value, 0) || 1
    let given = 0
    const shares = list.map((value, index) => {
      const share = index === list.length - 1 ? Number(price) - given : Math.floor(((Number(price) * value) / weight) * 100) / 100
      given += share
      return share.toFixed(2)
    })
    return ok({
      name: product?.name ?? '',
      quantity: 1,
      base_price: base.toFixed(2),
      delta: delta.toFixed(2),
      price,
      parts: parts.map((part) => {
        const shown = this.presentOption(part.slot, part.option)
        return { slot_name: part.slot.name, name: shown.name, variant_name: shown.variant_name, quantity: part.option.quantity, label: shown.receipt_label }
      }),
      fiscal_mode: this.fiscalMode,
      fiscal: {
        single: [{ name: product?.name ?? '', mxik_code: product?.mxik_code ?? null, quantity: 1, amount: price }],
        split: parts.map((part, index) => ({ name: this.presentOption(part.slot, part.option).name, mxik_code: null, quantity: part.option.quantity, amount: shares[index] })),
      },
    })
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path
    if (req.method === 'GET' && path === '/v1/owner/combos') return ok({ combos: this.combos.map((combo) => this.present(combo)), fiscal_mode: this.fiscalMode })
    if (req.method === 'POST' && path === '/v1/owner/combos') {
      const productId = Number(body?.product_id)
      if (!this.backend.menu.products.some((product) => product.id === productId)) return fail(404, 'Mahsulot topilmadi.')
      if (this.combos.some((combo) => combo.product_id === productId)) return fail(422, 'Bu mahsulot allaqachon kombo.', { product_id: ['Bu mahsulot allaqachon kombo.'] })
      const { slots, errors } = this.slotsFrom(body?.slots)
      if (errors) return fail(422, Object.values(errors)[0]![0]!, errors)
      const combo: FakeCombo = { id: ++this.counter, uuid: uuid(this.counter), product_id: productId, is_active: body?.is_active !== false, slots: slots! }
      this.combos.push(combo)
      return created(this.present(combo))
    }
    const one = /^\/v1\/owner\/combos\/(\d+)(\/preview)?$/.exec(path)
    if (one) {
      const combo = this.combos.find((candidate) => candidate.id === Number(one[1]))
      if (!combo) return fail(404, 'Kombo topilmadi.')
      if (req.method === 'POST' && one[2]) return this.preview(combo, Array.isArray(body?.picks) ? (body.picks as Array<{ option_id: string }>) : [])
      if (req.method === 'PUT' && !one[2]) {
        if (body?.slots !== undefined) {
          const { slots, errors } = this.slotsFrom(body.slots)
          if (errors) return fail(422, Object.values(errors)[0]![0]!, errors)
          combo.slots = slots!
        }
        if (typeof body?.is_active === 'boolean') combo.is_active = body.is_active
        return ok(this.present(combo))
      }
      if (req.method === 'DELETE' && !one[2]) {
        this.combos = this.combos.filter((candidate) => candidate !== combo)
        return ok(null)
      }
    }
    // The combo fiscal mode is one field of the store's fiscal settings (the rest is the fiscal area's)
    if (req.method === 'PUT' && path === '/v1/owner/fiscal/settings' && body && typeof body.fiscal_settings === 'object' && body.fiscal_settings !== null) {
      const settings = body.fiscal_settings as Record<string, unknown>
      if (Object.keys(settings).length === 1 && 'combo_fiscal_mode' in settings) {
        if (settings.combo_fiscal_mode !== 'single' && settings.combo_fiscal_mode !== 'split') return fail(422, "Kombo fiskal chekda: noto'g'ri qiymat.", { 'fiscal_settings.combo_fiscal_mode': ["Noto'g'ri qiymat."] })
        this.fiscalMode = settings.combo_fiscal_mode
        return ok({ fiscal_settings: { combo_fiscal_mode: this.fiscalMode } })
      }
    }
    return null
  }
}
