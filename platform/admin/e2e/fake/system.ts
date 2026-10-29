// The fake of Tizim (P4-31): /admin/system/settings, /payment-providers, /regions, /districts, /feature-flags, shaped like the
// controllers' answers (App\Http\Controllers\Api\V1\Admin\AdminSystem*). The rules that matter to the screens are here: everything
// needs system.manage, every change a step-up and a reason; secrets answer configured + last 4 only; a region or district in use
// is not deleted; an order is the full list. `secrets` keeps the values only to check the page never shows them.
import { fail, ok, validation, type FakeRequest, type Reply } from './types'

interface Item {
  key: string
  label: string
  description: string
  type: 'select' | 'email' | 'text' | 'secret'
  editable: boolean
  secret: boolean
  value: string | null
  default: string | null
  options: Array<{ value: string; label: string }> | null
  group: 'sms' | 'telegram' | 'general'
  source: 'settings' | 'env' | 'default' | null
  updated_at: string | null
}

interface Credential {
  key: string
  label: string
  known: boolean
  value: string | null
  source: 'settings' | 'env' | null
}

interface Provider {
  id: number
  slug: string
  name: string
  is_active: boolean
  mode: 'test' | 'production'
  wired: boolean
  credentials: Credential[]
  updated_at: string | null
}

interface District {
  id: number
  region_id: number
  name: string
  is_active: boolean
  sort_order: number
  usage: { branches: number; addresses: number }
}

interface Region {
  id: number
  name: string
  is_active: boolean
  sort_order: number
  usage: { branches: number; addresses: number; applications: number }
}

const mask = (value: string | null) => {
  const text = (value ?? '').trim()
  return text === '' ? { configured: false, last4: null } : { configured: true, last4: text.length >= 8 ? text.slice(-4) : null }
}

const reasonOk = (body: Record<string, unknown>) => typeof body.reason === 'string' && body.reason.trim().length >= 3

export class FakeSystem {
  items: Item[] = [
    { key: 'sms_driver', label: 'SMS yuborish usuli', description: '«Log» — SMS yuborilmaydi. «Eskiz» — haqiqiy SMS.', type: 'select', editable: true, secret: false, value: 'log', default: 'log', options: [{ value: 'log', label: 'Log (SMS yuborilmaydi)' }, { value: 'eskiz', label: 'Eskiz (haqiqiy SMS)' }], group: 'sms', source: 'settings', updated_at: null },
    { key: 'eskiz_email', label: 'Eskiz email', description: 'Eskiz kabinetiga kiradigan email.', type: 'email', editable: true, secret: false, value: 'sms@rizqim.uz', default: null, options: null, group: 'sms', source: 'settings', updated_at: null },
    { key: 'eskiz_from', label: "Jo'natuvchi nomi", description: "SMS kimdan kelgani. Bo'sh bo'lsa 4546.", type: 'text', editable: true, secret: false, value: '4546', default: '4546', options: null, group: 'sms', source: 'default', updated_at: null },
    { key: 'eskiz_password', label: 'Eskiz paroli', description: 'Eskiz API paroli.', type: 'secret', editable: true, secret: true, value: 'eskiz-password-9876', default: null, options: null, group: 'sms', source: 'settings', updated_at: null },
    { key: 'telegram_bot_token', label: 'Bot tokeni', description: 'TELEGRAM_BOT_TOKEN.', type: 'secret', editable: false, secret: true, value: '123456:AAEexampleTokenWXYZ', default: null, options: null, group: 'telegram', source: 'env', updated_at: null },
    { key: 'telegram_bot_username', label: 'Bot nomi', description: 'TELEGRAM_BOT_USERNAME.', type: 'text', editable: false, secret: false, value: 'rizqim_bot', default: null, options: null, group: 'telegram', source: 'env', updated_at: null },
    { key: 'app_env', label: 'Muhit', description: 'APP_ENV.', type: 'text', editable: false, secret: false, value: 'production', default: null, options: null, group: 'general', source: 'env', updated_at: null },
  ]

  providers: Provider[] = [
    {
      id: 1, slug: 'payme', name: 'Payme', is_active: true, mode: 'test', wired: true, updated_at: null,
      credentials: [
        { key: 'merchant_id', label: 'Merchant ID', known: true, value: '65eb2b988f01b97b0a7c4912', source: 'settings' },
        { key: 'secret_key', label: 'Maxfiy kalit', known: true, value: null, source: null },
      ],
    },
    {
      id: 2, slug: 'click', name: 'Click', is_active: true, mode: 'production', wired: true, updated_at: null,
      credentials: [
        { key: 'service_id', label: 'Service ID', known: true, value: '32104', source: 'env' },
        { key: 'secret_key', label: 'Maxfiy kalit', known: true, value: 'click-env-secret-7777', source: 'env' },
      ],
    },
  ]

  regions: Region[] = [
    { id: 1, name: 'Andijon viloyati', is_active: true, sort_order: 1, usage: { branches: 0, addresses: 0, applications: 0 } },
    { id: 2, name: 'Toshkent shahri', is_active: true, sort_order: 2, usage: { branches: 3, addresses: 5, applications: 1 } },
    { id: 3, name: 'Xorazm viloyati', is_active: false, sort_order: 3, usage: { branches: 0, addresses: 0, applications: 0 } },
  ]

  districts: District[] = [
    { id: 11, region_id: 1, name: 'Asaka tumani', is_active: true, sort_order: 1, usage: { branches: 0, addresses: 0 } },
    { id: 21, region_id: 2, name: 'Chilonzor tumani', is_active: true, sort_order: 1, usage: { branches: 2, addresses: 3 } },
    { id: 22, region_id: 2, name: 'Yunusobod tumani', is_active: true, sort_order: 2, usage: { branches: 1, addresses: 2 } },
  ]

  // Review S#7: the fake is a production server (app_env above) without PAYMENTS_ALLOW_TEST_MODE
  allowTestMode = false

  flagDefault = false
  // store id → its own value of new-checkout-copy
  flagValues = new Map<number, boolean>([[12, true]])
  stores = [
    { id: 12, name: 'Lazzat Oshxona', status: 'active' },
    { id: 13, name: 'Lazzat Express', status: 'active' },
    { id: 14, name: 'Shirin Qandolat', status: 'active' },
  ]

  private nextId = 100

  private groups() {
    const labels = { sms: 'SMS', telegram: 'Telegram', general: 'Umumiy' } as const
    return {
      groups: (['sms', 'telegram', 'general'] as const).map((key) => ({
        key,
        label: labels[key],
        items: this.items
          .filter((item) => item.group === key)
          .map((item) => ({
            key: item.key, label: item.label, description: item.description, type: item.type, editable: item.editable, secret: item.secret,
            value: item.secret ? null : item.value, ...(item.secret ? mask(item.value) : { configured: item.value !== null && item.value !== '', last4: null }),
            source: item.source, default: item.default, options: item.options, updated_at: item.updated_at,
          })),
      })),
    }
  }

  private providerRow(provider: Provider) {
    return { ...provider, credentials: provider.credentials.map((c) => ({ key: c.key, label: c.label, known: c.known, ...mask(c.value), source: c.source })) }
  }

  private districtRow(district: District) {
    return { ...district, deletable: district.usage.branches + district.usage.addresses === 0 }
  }

  private regionRow(region: Region) {
    const districts = this.districts.filter((d) => d.region_id === region.id).sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name)).map((d) => this.districtRow(d))
    const used = region.usage.branches + region.usage.addresses + region.usage.applications
    return { ...region, districts_count: districts.length, active_districts: districts.filter((d) => d.is_active).length, deletable: districts.length === 0 && used === 0, districts }
  }

  private regionList() {
    return [...this.regions].sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name)).map((r) => this.regionRow(r))
  }

  private flags() {
    const on = this.stores.filter((s) => this.flagValues.get(s.id) ?? this.flagDefault).length
    return { flags: [{ name: 'new-checkout-copy', label: 'Yangi buyurtma matni', description: "Buyurtmani rasmiylashtirish sahifasidagi yangi matnlar (namunaviy flag, xatti-harakatni o'zgartirmaydi).", default: this.flagDefault, stores_on: on, stores_off: this.stores.length - on, stores_total: this.stores.length }] }
  }

  private flagRow(store: { id: number; name: string; status: string }) {
    const stored = this.flagValues.has(store.id)
    return { store, flags: { 'new-checkout-copy': { active: stored ? this.flagValues.get(store.id)! : this.flagDefault, stored } } }
  }

  /** The reply for a path below /v1/admin, or null when it is not a Tizim path */
  route(req: FakeRequest, path: string, body: Record<string, unknown>, can: (permission: string) => boolean, needsRecent: () => Reply | null): Reply | null {
    if (!path.startsWith('/system/')) return null
    if (!can('admin.system.manage')) return fail(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' })

    if (req.method === 'GET') {
      if (path === '/system/settings') return ok(this.groups())
      if (path === '/system/payment-providers') return ok(this.providers.map((p) => this.providerRow(p)))
      if (path === '/system/regions') return ok(this.regionList())
      if (path === '/system/feature-flags') return ok(this.flags())
      if (path === '/system/feature-flags/stores') {
        const q = (req.query.q ?? '').toLowerCase()
        const rows = this.stores.filter((s) => (q ? s.name.toLowerCase().includes(q) : true)).sort((a, b) => a.name.localeCompare(b.name)).map((s) => this.flagRow(s))
        return ok({ data: rows, current_page: 1, last_page: 1, per_page: 30, total: rows.length })
      }
      return null
    }

    const recent = needsRecent()
    if (recent) return recent
    if (!reasonOk(body)) return validation('reason', 'Sabab majburiy.')
    const now = new Date().toISOString()

    if (req.method === 'PATCH' && path === '/system/settings') {
      const values = (body.values ?? {}) as Record<string, unknown>
      for (const [key, value] of Object.entries(values)) {
        const item = this.items.find((i) => i.key === key && i.editable)
        if (!item) return fail(422, `Bunday sozlama yo'q: ${key}.`, { reason: 'unknown_setting' })
        if (item.secret) return fail(422, "Maxfiy kalit bu yerda o'zgartirilmaydi: uni alohida almashtiring.", { reason: 'secret_separate' })
        const text = typeof value === 'string' ? value.trim() : ''
        item.value = text === '' ? item.default : text
        item.source = text === '' ? (item.default ? 'default' : null) : 'settings'
        item.updated_at = now
      }
      return ok(this.groups(), { message: 'Sozlamalar saqlandi.' })
    }

    const secret = /^\/system\/settings\/secrets\/([a-z0-9_]+)$/.exec(path)
    if (secret) {
      const item = this.items.find((i) => i.key === secret[1] && i.editable && i.secret)
      if (!item) return fail(404, "Bunday maxfiy sozlama yo'q.", { reason: 'not_found' })
      if (req.method === 'PUT') {
        if (typeof body.value !== 'string' || body.value.trim() === '') return validation('value', 'Qiymat majburiy.')
        item.value = body.value.trim()
        item.source = 'settings'
      } else {
        item.value = null
        item.source = null
      }
      item.updated_at = now
      return ok(this.groups())
    }

    const provider = /^\/system\/payment-providers\/([a-z0-9_-]+)(?:\/credentials\/([a-z0-9_]+))?$/.exec(path)
    if (provider) {
      const row = this.providers.find((p) => p.slug === provider[1])
      if (!row) return fail(404, "To'lov tizimi topilmadi.", { reason: 'not_found' })
      if (!provider[2] && req.method === 'PATCH') {
        if (body.mode === 'test' && row.mode !== 'test' && !this.allowTestMode) {
          return fail(422, "Productionda to'lov tizimini test rejimiga o'tkazib bo'lmaydi: mijozlar to'lovi sinov (sandbox) hisobiga ketadi. Ataylab sinash kerak bo'lsa, serverda PAYMENTS_ALLOW_TEST_MODE=true qiling.", { reason: 'test_mode_not_allowed' })
        }
        if (typeof body.is_active === 'boolean') row.is_active = body.is_active
        if (body.mode === 'test' || body.mode === 'production') row.mode = body.mode
        row.updated_at = now
        return ok(this.providerRow(row))
      }
      const credential = row.credentials.find((c) => c.key === provider[2])
      if (!credential) return fail(422, "Bu to'lov tizimida bunday kalit yo'q.", { reason: 'unknown_key' })
      if (req.method === 'PUT') {
        if (typeof body.value !== 'string' || body.value.trim() === '') return validation('value', 'Qiymat majburiy.')
        credential.value = body.value.trim()
        credential.source = 'settings'
      } else {
        credential.value = null
        credential.source = null
      }
      row.updated_at = now
      return ok(this.providerRow(row))
    }

    if (req.method === 'POST' && path === '/system/regions') {
      const name = String(body.name ?? '').trim()
      if (name.length < 2) return validation('name', 'Nomi kamida 2 belgi.')
      if (this.regions.some((r) => r.name.toLowerCase() === name.toLowerCase())) return fail(422, 'Bunday nomli viloyat bor.', { reason: 'name_taken', field: 'name' })
      const region: Region = { id: this.nextId++, name, is_active: body.is_active === true, sort_order: Math.max(0, ...this.regions.map((r) => r.sort_order)) + 1, usage: { branches: 0, addresses: 0, applications: 0 } }
      this.regions.push(region)
      return { status: 201, body: { success: true, message: "Viloyat qo'shildi.", data: this.regionRow(region) } }
    }

    if (req.method === 'PUT' && path === '/system/regions/order') {
      const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []
      if ([...ids].sort().join() !== this.regions.map((r) => r.id).sort().join()) return fail(422, "Tartib to'liq ro'yxat bo'lishi kerak: har biri bir marta.", { reason: 'order_mismatch' })
      ids.forEach((id, index) => (this.regions.find((r) => r.id === id)!.sort_order = index + 1))
      return ok(this.regionList())
    }

    const region = /^\/system\/regions\/(\d+)(\/districts(\/order)?)?$/.exec(path)
    if (region) {
      const row = this.regions.find((r) => r.id === Number(region[1]))
      if (!row) return fail(404, 'Viloyat topilmadi.', { reason: 'not_found' })
      if (req.method === 'PATCH' && !region[2]) {
        if (typeof body.name === 'string') row.name = body.name.trim()
        if (typeof body.is_active === 'boolean') row.is_active = body.is_active
        return ok(this.regionRow(row))
      }
      if (req.method === 'DELETE' && !region[2]) {
        const districts = this.districts.filter((d) => d.region_id === row.id).length
        if (districts > 0) return fail(422, `Viloyatda ${districts} ta tuman bor: avval ularni o'chiring yoki viloyatni o'chirib qo'ying (faol emas).`, { reason: 'has_districts', districts })
        if (row.usage.branches + row.usage.addresses + row.usage.applications > 0) return fail(422, "Viloyat ishlatilmoqda: o'chirib bo'lmaydi, faol emas qilib qo'ying.", { reason: 'in_use', usage: row.usage })
        this.regions = this.regions.filter((r) => r.id !== row.id)
        return ok({ deleted: true })
      }
      if (req.method === 'POST' && region[2] && !region[3]) {
        const name = String(body.name ?? '').trim()
        if (name.length < 2) return validation('name', 'Nomi kamida 2 belgi.')
        const own = this.districts.filter((d) => d.region_id === row.id)
        const district: District = { id: this.nextId++, region_id: row.id, name, is_active: body.is_active === true, sort_order: Math.max(0, ...own.map((d) => d.sort_order)) + 1, usage: { branches: 0, addresses: 0 } }
        this.districts.push(district)
        return { status: 201, body: { success: true, message: "Tuman qo'shildi.", data: this.districtRow(district) } }
      }
      if (req.method === 'PUT' && region[3]) {
        const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []
        const own = this.districts.filter((d) => d.region_id === row.id)
        if ([...ids].sort().join() !== own.map((d) => d.id).sort().join()) return fail(422, "Tartib to'liq ro'yxat bo'lishi kerak: har biri bir marta.", { reason: 'order_mismatch' })
        ids.forEach((id, index) => (own.find((d) => d.id === id)!.sort_order = index + 1))
        return ok(this.regionRow(row))
      }
    }

    const district = /^\/system\/districts\/(\d+)$/.exec(path)
    if (district) {
      const row = this.districts.find((d) => d.id === Number(district[1]))
      if (!row) return fail(404, 'Tuman topilmadi.', { reason: 'not_found' })
      if (req.method === 'PATCH') {
        if (typeof body.name === 'string') row.name = body.name.trim()
        if (typeof body.is_active === 'boolean') row.is_active = body.is_active
        return ok(this.districtRow(row))
      }
      if (req.method === 'DELETE') {
        if (row.usage.branches + row.usage.addresses > 0) return fail(422, "Tuman ishlatilmoqda: o'chirib bo'lmaydi, faol emas qilib qo'ying.", { reason: 'in_use', usage: row.usage })
        this.districts = this.districts.filter((d) => d.id !== row.id)
        return ok({ deleted: true })
      }
    }

    const flag = /^\/system\/feature-flags\/(?:([a-z0-9_.-]+)\/)?stores\/(\d+)$/.exec(path)
    if (flag) {
      const store = this.stores.find((s) => s.id === Number(flag[2]))
      if (!store) return fail(404, 'Ovqatxona topilmadi.', { reason: 'not_found' })
      if (req.method === 'PUT' && flag[1]) {
        if (flag[1] !== 'new-checkout-copy') return fail(404, "Bunday funksiya yo'q.", { reason: 'not_found' })
        if (typeof body.active !== 'boolean') return validation('active', 'Qiymat majburiy.')
        this.flagValues.set(store.id, body.active)
        return ok(this.flagRow(store))
      }
      if (req.method === 'DELETE' && !flag[1]) {
        this.flagValues.delete(store.id)
        return ok(this.flagRow(store))
      }
    }

    return null
  }
}
