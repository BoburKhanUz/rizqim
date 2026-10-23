// The fiscal side of the fake backend (docs/API.md P3-11, P3-14): the store's fiscal settings, the readiness list and the
// fiscal receipts. The products live in menu.ts (their MXIK is read from there).
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const CHARGES = ['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'deposit', 'booking_fee', 'credit', 'delivery'] as const
type ChargeKey = (typeof CHARGES)[number]

interface Charge {
  name: string
  mxik: string | null
  package_code: string | null
  unit: string
}

const DEFAULT_CHARGES: Record<ChargeKey, Charge> = {
  room_fee: { name: 'Xona puli', mxik: null, package_code: null, unit: 'xizmat' },
  place_fee: { name: 'Joy puli', mxik: null, package_code: null, unit: 'xizmat' },
  min_spend_topup: { name: "Minimal summaga qo'shimcha", mxik: null, package_code: null, unit: 'xizmat' },
  service_charge: { name: 'Xizmat haqi', mxik: null, package_code: null, unit: 'xizmat' },
  deposit: { name: 'Bron avansi', mxik: '10202002004000001', package_code: null, unit: 'xizmat' },
  booking_fee: { name: 'Bron xizmati', mxik: '10202002003000002', package_code: null, unit: 'xizmat' },
  credit: { name: "Qarz to'lovi", mxik: '10202002004000001', package_code: null, unit: 'xizmat' },
  delivery: { name: 'Yetkazib berish', mxik: null, package_code: null, unit: 'xizmat' },
}

export interface FakeReceipt {
  uuid: string
  type: 'sale' | 'refund' | 'advance' | 'credit'
  status: 'pending' | 'issued' | 'failed'
  payment_form: 'cash' | 'card' | 'qr' | 'mixed'
  driver: string
  provider: string | null
  non_fiscal: boolean
  tax_regime: string | null
  total: string
  vat_total: string
  fiscal_sign: string | null
  receipt_number: string | null
  qr_url: string | null
  terminal_id: string | null
  error: string | null
  attempts: number
  next_attempt_at: string | null
  issued_at: string | null
  created_at: string
  branch_id: number | null
  device_id: number | null
  subject_type: string | null
  subject_id: number | null
  original_receipt_uuid: string | null
  lines: unknown[]
  payments: unknown[]
}

const MXIK = /^\d{17}$/

export class FakeFiscal {
  tin: string | null = '123456789'
  taxRegime: string | null = 'vat'
  enabled = true
  driver = 'sandbox'
  charges: Record<ChargeKey, Charge> = structuredClone(DEFAULT_CHARGES)
  depositRefundReceipt: boolean | null = null
  retainedDepositReceipt: boolean | null = null
  receipts: FakeReceipt[] = []

  constructor(private readonly backend: FakeBackend) {}

  private settingsView() {
    return {
      tin: this.tin,
      tax_regime: this.taxRegime,
      fiscal_settings: {
        enabled: this.enabled,
        driver: this.driver,
        active_driver: this.driver,
        charges: this.charges,
        sandbox: { latency_ms: 0, fail_attempts: 0, failure_rate: 0, reject: false },
        deposit_refund_receipt: this.depositRefundReceipt,
        retained_deposit_receipt: this.retainedDepositReceipt,
      },
      drivers: ['sandbox', 'null'],
      tax_regimes: [
        { value: 'vat', label: "QQS to'lovchi", vat_rate: '12' },
        { value: 'vat_simplified', label: 'Soddalashtirilgan QQS', vat_rate: '6' },
        { value: 'turnover', label: "Aylanma solig'i", vat_rate: '0' },
      ],
    }
  }

  private updateSettings(input: Record<string, unknown>): Reply {
    const errors: Record<string, string[]> = {}
    if ('tin' in input && input.tin !== null && !/^\d{9}$/.test(String(input.tin))) errors.tin = ["STIR 9 ta raqamdan iborat bo'lishi kerak."]
    const settings = (input.fiscal_settings as Record<string, unknown> | undefined) ?? {}
    const charges = (settings.charges as Record<string, Record<string, unknown>> | undefined) ?? {}
    for (const [key, change] of Object.entries(charges)) {
      if (typeof change.mxik === 'string' && !MXIK.test(change.mxik)) errors[`fiscal_settings.charges.${key}.mxik`] = ["MXIK kodi 17 ta raqamdan iborat bo'lishi kerak."]
    }
    if (Object.keys(errors).length > 0) return fail(422, 'The given data was invalid.', errors)
    if ('tin' in input) this.tin = (input.tin as string | null) ?? null
    if ('tax_regime' in input) this.taxRegime = (input.tax_regime as string | null) ?? null
    if (settings.enabled === true && (!this.tin || !this.taxRegime)) return fail(422, 'The given data was invalid.', { 'fiscal_settings.enabled': ['Fiskal rejimni yoqish uchun STIR va soliq rejimi kerak.'] })
    if (typeof settings.enabled === 'boolean') this.enabled = settings.enabled
    if (typeof settings.driver === 'string') this.driver = settings.driver
    for (const [key, change] of Object.entries(charges) as Array<[ChargeKey, Record<string, unknown>]>) {
      const next = { ...this.charges[key] }
      for (const [field, value] of Object.entries(change)) {
        // null: the default comes back
        const fallback = DEFAULT_CHARGES[key][field as keyof Charge]
        ;(next as Record<string, unknown>)[field] = value === null ? fallback : value
      }
      this.charges[key] = next
    }
    if ('deposit_refund_receipt' in settings) this.depositRefundReceipt = settings.deposit_refund_receipt as boolean | null
    if ('retained_deposit_receipt' in settings) this.retainedDepositReceipt = settings.retained_deposit_receipt as boolean | null
    return ok(this.settingsView())
  }

  /** A receipt for tests that look at the list. */
  addReceipt(over: Partial<FakeReceipt> = {}): FakeReceipt {
    const index = this.receipts.length + 1
    const receipt: FakeReceipt = {
      uuid: `r0000000-0000-4000-8000-${String(index).padStart(12, '0')}`,
      type: 'sale',
      status: 'issued',
      payment_form: 'card',
      driver: this.driver,
      provider: this.driver,
      non_fiscal: true,
      tax_regime: this.taxRegime,
      total: '70000.00',
      vat_total: '7500.00',
      fiscal_sign: null,
      receipt_number: null,
      qr_url: null,
      terminal_id: null,
      error: null,
      attempts: 1,
      next_attempt_at: null,
      issued_at: new Date().toISOString(),
      created_at: new Date().toISOString(),
      branch_id: 1,
      device_id: null,
      subject_type: 'order_bill',
      subject_id: index,
      original_receipt_uuid: null,
      lines: [{ name: 'Osh', mxik_code: '10202001001000000', package_code: null, unit: 'dona', quantity: '2.000', unit_price: '35000.00', amount: '70000.00', vat_rate: '12', vat_amount: '7500.00', no_vat: false, commissioner_tin: null, marking_code: null }],
      payments: [{ form: 'card', amount: '70000.00', prepayment: false, credit: false, card_type: 'uzcard', transaction_id: null }],
      ...over,
    }
    this.receipts.unshift(receipt)
    return receipt
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path
    let match: RegExpExecArray | null
    if (req.method === 'GET' && path === '/v1/owner/fiscal/readiness') {
      return ok({
        enabled: this.enabled,
        tin_set: Boolean(this.tin),
        tax_regime: this.taxRegime,
        products_missing_mxik: this.backend.menu.products
          .filter((product) => !MXIK.test(product.mxik_code ?? ''))
          .map((product) => ({ id: product.id, uuid: product.uuid, name: product.name, kind: product.kind, status: product.status, category_id: product.category_id })),
        charges_missing_mxik: CHARGES.filter((key) => !this.charges[key].mxik),
      })
    }
    if (req.method === 'GET' && path === '/v1/owner/fiscal/settings') return ok(this.settingsView())
    if (req.method === 'PUT' && path === '/v1/owner/fiscal/settings') return this.updateSettings(body ?? {})
    if (req.method === 'GET' && path === '/v1/owner/fiscal/receipts') {
      const rows = this.receipts.filter((receipt) => (!req.query.status || receipt.status === req.query.status) && (!req.query.type || receipt.type === req.query.type))
      return ok({ data: rows, current_page: 1, last_page: 1, per_page: 25, total: rows.length, from: rows.length ? 1 : null, to: rows.length || null })
    }
    if ((match = /^\/v1\/owner\/fiscal\/receipts\/([\w-]+)(\/retry)?$/.exec(path))) {
      const receipt = this.receipts.find((candidate) => candidate.uuid === match![1])
      if (!receipt) return fail(404, 'Chek topilmadi.')
      if (req.method === 'GET' && !match[2]) return ok(receipt)
      if (req.method === 'POST' && match[2]) {
        if (receipt.status === 'issued') return fail(422, 'Chek allaqachon yuborilgan.', undefined, { reason: 'already_issued' })
        receipt.status = 'issued'
        receipt.error = null
        receipt.attempts += 1
        return ok(receipt)
      }
    }
    return null
  }
}
