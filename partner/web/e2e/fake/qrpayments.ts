// A branch's QR payment settings of the fake backend (docs/API.md "QR to'lov kassada", P6-41/P6-42). Answers like the server:
// the whole view (settings, the store's merchant status per provider, the options), only what is sent changes, the default
// must be an offered provider (422), the expiry 1–30, the qr_payments module gates the change (403 module_disabled).
// Reached through FakeFormat (it owns the module switches).
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const PROVIDERS = ['click', 'payme', 'uzqr'] as const
const LABELS: Record<string, string> = { click: 'Click', payme: 'Payme', uzqr: 'UzQR' }

export class FakeQrPayments {
  providers: string[] = [...PROVIDERS]
  defaultProvider: string | null = 'click'
  expiry = 5
  updatedAt: string | null = null
  /** The store's own merchant accounts (Click, Payme) and the UzQR gateway that are set up. */
  configured = new Set<string>(['click'])

  constructor(
    readonly backend: FakeBackend,
    private readonly moduleOn: () => boolean,
  ) {}

  private view(branchId: number) {
    return {
      settings: {
        branch_id: branchId,
        enabled: this.moduleOn(),
        providers: PROVIDERS.filter((key) => this.providers.includes(key)),
        default_provider: this.defaultProvider !== null && this.providers.includes(this.defaultProvider) ? this.defaultProvider : (this.providers[0] ?? null),
        expiry_minutes: this.expiry,
        updated_at: this.updatedAt,
      },
      merchants: PROVIDERS.map((key) => ({ provider: key, label: LABELS[key], configured: this.configured.has(key), driver: key === 'uzqr' ? (this.configured.has(key) ? 'uzqr:sandbox' : 'uzqr:none') : key })),
      options: { providers: PROVIDERS.map((key) => ({ key, label: LABELS[key] })), expiry_min: 1, expiry_max: 30 },
    }
  }

  private update(branchId: number, body: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    const providers = Array.isArray(body.providers) ? (body.providers as string[]) : this.providers
    if (providers.some((key) => !(key in LABELS))) errors.providers = ["Provayder noto'g'ri."]
    if ('expiry_minutes' in body && (typeof body.expiry_minutes !== 'number' || body.expiry_minutes < 1 || body.expiry_minutes > 30)) errors.expiry_minutes = ["QR amal qilish vaqti 1 dan 30 gacha bo'lishi kerak."]
    if (typeof body.default_provider === 'string' && !providers.includes(body.default_provider)) errors.default_provider = ["Asosiy provayder yoqilganlar orasida bo'lishi kerak."]
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)

    this.providers = providers
    if ('default_provider' in body) this.defaultProvider = (body.default_provider as string | null) ?? null
    if (typeof body.expiry_minutes === 'number') this.expiry = body.expiry_minutes
    this.updatedAt = '2026-11-20T10:00:00Z'
    return ok(this.view(branchId))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path !== '/v1/owner/pos/qr-payments') return null
    if (req.method === 'GET') return ok(this.view(Number(req.query.branch_id ?? 1)))
    if (req.method === 'PUT') {
      if (!this.moduleOn()) return fail(403, "«Click, Payme va UzQR kassada» moduli bu filialda o'chiq.", undefined, { reason: 'module_disabled', module: 'qr_payments' })
      return this.update(Number(body?.branch_id ?? 1), body ?? {})
    }
    return null
  }
}
