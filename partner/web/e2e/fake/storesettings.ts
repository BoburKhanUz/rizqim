// The store tab of Sozlamalar in the fake backend (GET/POST /owner/settings, OwnerSettingsController): the store's own
// values and payment_settings. Wave P: the store's own Paylov account, with the server's rules that matter to the screen:
// the secrets (Store::isSecretKey) are never sent back, only has_*; a blank or masked secret keeps the stored one; the
// webhook (callback) password is at least 16 characters (422 on payment_settings.paylov.callback_password); a provider
// RIZQIM switched off cannot be switched on (422 on payment_settings).
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const PAYLOV_KEYS = ['merchant_id', 'consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'] as const
const OWN_FIELDS = ['merchant_id', 'service_id', 'secret_key'] as const
const PROVIDERS = ['cash', 'click', 'payme', 'uzum', 'paynet', 'paylov'] as const
const isSecret = (key: string) => /secret|password|passwd|token|private|(^|_)key$|(^|_)(username|login)$/i.test(key)
const masked = (value: unknown) => typeof value === 'string' && /^[\s*•]*$/u.test(value)

export class FakeStoreSettings {
  store: Record<string, unknown> = {
    id: 1, name: 'Osh markazi', timezone: 'Asia/Tashkent', logo: null, logo_url: null, banner: null, banner_url: null, description: 'Eng yaxshi osh',
    phone: '+998712000000', address: 'Amir Temur 1', location_url: null, is_open: true, min_order_amount: 30000, status: 'active', is_order_locked: false,
    tin: null, tax_regime: null, telegram: { group_link: null, group_linked: false },
    fiscal_settings: { enabled: false, driver: 'none', active_driver: 'none', charges: {}, sandbox: { latency_ms: 0, fail_attempts: 0, failure_rate: 0, reject: false }, deposit_refund_receipt: null, retained_deposit_receipt: null },
  }
  accountType: 'own_account' | 'platform_account' = 'own_account'
  /** What payment_settings holds in the database: secrets in the clear, as the fake never shows them */
  payment: Record<string, unknown> = {
    delivery_fee: 12000, delivery_enabled: true, pickup_enabled: true, working_hours: { open: '09:00', close: '23:00' },
    cash: { enabled: true },
    click: { enabled: true, service_id: '111', merchant_id: '222', secret_key: 'click-secret' },
    payme: { enabled: true, merchant_id: 'pm-1', secret_key: 'payme-secret' },
    paylov: { enabled: true, merchant_id: '4f9c2a7e-1b3d-4e8f-9a6b-0c2d4e6f8a1b', consumer_key: 'ck-1', consumer_secret: 'cs-1', username: 'osh-markazi' },
  }
  /** Methods RIZQIM cannot process now: not integrated (uzum, paynet) or switched off (a test adds paylov) */
  unavailable: Record<string, 'not_integrated' | 'switched_off'> = { uzum: 'not_integrated', paynet: 'not_integrated' }

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path !== '/v1/owner/settings') return null
    if (req.method === 'GET') return ok(this.present())
    if (req.method !== 'POST') return null

    const input = (body?.payment_settings ?? {}) as Record<string, Record<string, unknown> | unknown>
    const paylov = (input.paylov ?? {}) as Record<string, unknown>
    const callback = paylov.callback_password
    if (typeof callback === 'string' && !masked(callback) && callback.trim().length < 16) {
      return fail(422, "Paylov webhook paroli kamida 16 belgidan iborat bo'lishi kerak.", { 'payment_settings.paylov.callback_password': ["Paylov webhook paroli kamida 16 belgidan iborat bo'lishi kerak."] })
    }
    for (const [method, reason] of Object.entries(this.unavailable)) {
      const requested = input[method] as Record<string, unknown> | undefined
      if (requested?.enabled === true) {
        const name = method.charAt(0).toUpperCase() + method.slice(1)
        return fail(422, 'Xato', { payment_settings: [reason === 'switched_off' ? `${name} orqali to'lovni RIZQIM vaqtincha o'chirgan, uni yoqib bo'lmaydi.` : `${name} orqali to'lov hozircha mavjud emas, uni yoqib bo'lmaydi.`] })
      }
    }

    for (const key of ['delivery_fee', 'working_hours', 'delivery_enabled', 'pickup_enabled'] as const) if (input[key] !== undefined) this.payment[key] = input[key]
    for (const provider of PROVIDERS) {
      const requested = input[provider] as Record<string, unknown> | undefined
      if (!requested || this.unavailable[provider]) continue
      const current = { ...((this.payment[provider] as Record<string, unknown> | undefined) ?? {}) }
      current.enabled = requested.enabled === true
      if (this.accountType === 'own_account') {
        for (const field of provider === 'paylov' ? PAYLOV_KEYS : OWN_FIELDS) {
          if (!(field in requested)) continue
          const value = requested[field]
          // A blank or masked secret keeps the stored one
          if (isSecret(field) && (value === null || value === '' || masked(value))) continue
          current[field] = value
        }
      }
      this.payment[provider] = current
    }
    for (const key of ['name', 'timezone', 'phone', 'description', 'address', 'location_url', 'is_open', 'min_order_amount', 'logo', 'banner'] as const) if (body && key in body) this.store[key] = body[key]
    return ok(null, { message: "Do'kon sozlamalari muvaffaqiyatli yangilandi." })
  }

  /** The stored Paylov keys (for the assertions: the fake keeps them in the clear) */
  paylov(): Record<string, unknown> {
    return { ...((this.payment.paylov as Record<string, unknown> | undefined) ?? {}) }
  }

  private present() {
    const forOwner = (value: Record<string, unknown>): Record<string, unknown> =>
      Object.fromEntries(Object.entries(value).map(([key, inner]) => (isSecret(key) ? [`has_${key}`, typeof inner === 'string' && inner !== ''] : [key, inner])))
    const payment: Record<string, unknown> = {}
    for (const [key, value] of Object.entries(this.payment)) payment[key] = value && typeof value === 'object' && !Array.isArray(value) && key !== 'working_hours' ? forOwner(value as Record<string, unknown>) : value
    for (const [method, reason] of Object.entries(this.unavailable)) payment[method] = { enabled: false, available: false, unavailable_reason: reason }
    return {
      ...this.store,
      payment_settings: payment,
      payment_account_type: this.accountType,
      allowed_methods: PROVIDERS.filter((method) => !this.unavailable[method]),
    }
  }
}
