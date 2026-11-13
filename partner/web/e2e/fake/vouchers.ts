// Gift certificates of the fake backend (docs/API.md P6-43 "Sertifikat va vaucher"): two certificates of the store (one
// partly spent, one whole), each with its history, the period report and block / unblock. Enough for the panel's flows;
// the balances, the receipts and the report's sums are the backend's and its tests. Review S1: a reader without
// vouchers.manage gets the codes masked and no `print` block, and finds a certificate by its whole code only.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

const DAY = '2026-11-16'
const KASSIR = { id: 1, name: 'Kassir' }

interface Certificate {
  uuid: string
  code: string
  face_value: string
  balance: string
  status: 'active' | 'used' | 'blocked' | 'expired'
  buyer: { name: string | null; phone: string | null }
  blocked: { at: string | null; reason: string | null } | null
  entries: Array<Record<string, unknown>>
}

const LABELS = { active: 'Faol', used: 'Ishlatilgan', blocked: 'Bloklangan', expired: "Muddati o'tgan" }

export class FakeVouchers {
  vouchers: Certificate[] = [
    {
      uuid: 'dddddddd-0000-4000-8000-000000000001',
      code: 'K7QM-4XPA-9HTR',
      face_value: '200000.00',
      balance: '80000.00',
      status: 'active',
      buyer: { name: 'Aziz', phone: '+99890*****67' },
      blocked: null,
      entries: [
        { uuid: 'e-2', type: 'redemption', type_label: "To'lovda ishlatildi", amount: '120000.00', balance_after: '80000.00', business_date: DAY, bill_uuid: 'bbbbbbbb-0000-4000-8000-000000000001', refund_uuid: null, tender: null, branch_id: 1, shift_id: 1, user: KASSIR, note: null, created_at: `${DAY}T14:00:00+05:00` },
        { uuid: 'e-1', type: 'sale', type_label: 'Sotildi', amount: '200000.00', balance_after: '200000.00', business_date: DAY, bill_uuid: null, refund_uuid: null, tender: 'cash', branch_id: 1, shift_id: 1, user: KASSIR, note: null, created_at: `${DAY}T10:00:00+05:00` },
      ],
    },
    {
      uuid: 'dddddddd-0000-4000-8000-000000000002',
      code: 'MNPQ-3469-ACDE',
      face_value: '50000.00',
      balance: '50000.00',
      status: 'active',
      buyer: { name: null, phone: null },
      blocked: null,
      entries: [{ uuid: 'e-3', type: 'sale', type_label: 'Sotildi', amount: '50000.00', balance_after: '50000.00', business_date: DAY, bill_uuid: null, refund_uuid: null, tender: 'card', branch_id: 1, shift_id: 1, user: KASSIR, note: null, created_at: `${DAY}T11:00:00+05:00` }],
    },
  ]

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private manages(): boolean {
    return this.backend.account.permissions.includes('vouchers.manage')
  }

  private view(voucher: Certificate) {
    const full = this.manages()
    return {
      uuid: voucher.uuid,
      code: full ? voucher.code : `${voucher.code.slice(0, 4)}-****-${voucher.code.slice(-4)}`,
      kind: 'gift',
      face_value: voucher.face_value,
      balance: voucher.balance,
      currency: 'UZS',
      valid_from: DAY,
      valid_until: '2027-11-16',
      status: voucher.status,
      status_label: LABELS[voucher.status],
      branch: { id: 1, name: 'Chilonzor' },
      sale: { tender: voucher.entries.at(-1)?.tender ?? 'cash', card_type: null, transaction_id: null, reference: null, shift_id: 1, sold_at: `${DAY}T10:00:00+05:00`, sold_by: KASSIR },
      buyer: voucher.buyer,
      note: null,
      fiscal: { receipt_uuid: null, status: 'issued' },
      blocked: voucher.blocked,
      print: full ? { store: 'Lazzat Oshxona', code: voucher.code, qr: voucher.code, value: voucher.face_value, valid_from: DAY, valid_until: '2027-11-16' } : null,
      created_at: `${DAY}T10:00:00+05:00`,
    }
  }

  private sum(values: string[]): string {
    return values.reduce((total, value) => total + Number(value), 0).toFixed(2)
  }

  private report(query: Record<string, string>) {
    const live = this.vouchers.filter((voucher) => voucher.status === 'active')
    const blocked = this.vouchers.filter((voucher) => voucher.status === 'blocked')
    return {
      from: query.from ?? DAY,
      to: query.to ?? DAY,
      branch_id: null,
      sold: { count: 2, amount: '250000.00', by_tender: [] },
      redeemed: { count: 1, amount: '120000.00' },
      refunded: { count: 0, amount: '0.00' },
      net_change: '130000.00',
      outstanding: { count: live.length, amount: this.sum(live.map((voucher) => voucher.balance)) },
      blocked: { count: blocked.length, amount: this.sum(blocked.map((voucher) => voucher.balance)) },
      expired: { count: 0, amount: '0.00' },
      as_of: DAY,
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (!req.path.startsWith('/v1/owner/pos/vouchers')) return null
    const path = req.path.slice('/v1/owner/pos/vouchers'.length)

    if (req.method === 'GET' && path === '') {
      const q = (req.query.q ?? '').toUpperCase()
      const rows = this.vouchers.filter((voucher) => (!req.query.status || voucher.status === req.query.status) && (!q || (this.manages() ? voucher.code.includes(q) : voucher.code === q) || (voucher.buyer.name ?? '').toUpperCase().includes(q)))
      return ok({ vouchers: rows.map((voucher) => this.view(voucher)), meta: { current_page: 1, last_page: 1, per_page: 30, total: rows.length } })
    }
    if (req.method === 'GET' && path === '/report') return ok(this.report(req.query))
    if (req.method === 'GET' && path === '/export') {
      return { status: 200, body: null, raw: 'xlsx', headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': `attachment; filename=sertifikatlar-${req.query.from}-${req.query.to}.xlsx` } }
    }

    const match = /^\/([0-9a-f-]{36})(\/block|\/unblock)?$/.exec(path)
    const voucher = match ? this.vouchers.find((row) => row.uuid === match[1]) : undefined
    if (!match || !voucher) return fail(404, 'Sertifikat topilmadi.')
    if (req.method === 'GET' && !match[2]) return ok({ voucher: this.view(voucher), entries: voucher.entries })
    if (req.method === 'POST' && match[2] === '/block') {
      const reason = String(body?.reason ?? '')
      if (reason.trim().length < 3) return fail(422, 'Sababni yozing.', { reason: ['Sababni yozing.'] })
      voucher.status = 'blocked'
      voucher.blocked = { at: `${DAY}T15:00:00+05:00`, reason }
      voucher.entries.unshift({ uuid: `e-b${voucher.entries.length}`, type: 'block', type_label: 'Bloklandi', amount: '0.00', balance_after: voucher.balance, business_date: DAY, bill_uuid: null, refund_uuid: null, tender: null, branch_id: null, shift_id: null, user: { id: 7, name: 'Olim Egamov' }, note: reason, created_at: `${DAY}T15:00:00+05:00` })
      return ok(this.view(voucher))
    }
    if (req.method === 'POST' && match[2] === '/unblock') {
      voucher.status = Number(voucher.balance) > 0 ? 'active' : 'used'
      voucher.blocked = null
      voucher.entries.unshift({ uuid: `e-u${voucher.entries.length}`, type: 'unblock', type_label: 'Blokdan chiqarildi', amount: '0.00', balance_after: voucher.balance, business_date: DAY, bill_uuid: null, refund_uuid: null, tender: null, branch_id: null, shift_id: null, user: { id: 7, name: 'Olim Egamov' }, note: null, created_at: `${DAY}T16:00:00+05:00` })
      return ok(this.view(voucher))
    }
    return null
  }
}
