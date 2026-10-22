// Shifts with their X and Z reports of the fake backend (docs/API.md P3-18). The figures are fixed so a test can read them.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

export const shiftReport = (uuid: string, expected = '1730000.00') => ({
  shift_uuid: uuid,
  generated_at: '2026-10-21T19:00:00+00:00',
  period: { from: '2026-10-21T04:00:00+00:00', to: '2026-10-21T19:00:00+00:00' },
  sales: { bills: 14, received: '3200000.00', refunded: '50000.00', net: '3150000.00', bills_total: '3200000.00', subtotal: '2900000.00', discount: '100000.00', room_fee: '300000.00', min_spend_topup: '0.00', service_charge: '290000.00', place_fee: '0.00', average_bill: '228571.00' },
  by_tender: [
    { tender: 'cash', count: 8, amount: '1500000.00' },
    { tender: 'card', count: 5, amount: '1200000.00' },
    { tender: 'qr', count: 0, amount: '0.00' },
    { tender: 'prepayment', count: 1, amount: '200000.00' },
    { tender: 'account', count: 1, amount: '300000.00' },
  ],
  cards: [{ card_type: 'uzcard', count: 3, amount: '700000.00' }],
  by_channel: [{ channel: 'pos_hall', orders: 12, amount: '3000000.00' }],
  by_waiter: [{ waiter_id: 9, name: 'Ofitsiant Vali', orders: 12, bills: 12, amount: '3000000.00', service_charge: '270000.00' }],
  discounts: { count: 2, amount: '100000.00' },
  voids: { count: 1, amount: '35000.00' },
  refunds: { count: 1, amount: '50000.00', by_method: [{ method: 'cash', count: 1, amount: '50000.00' }] },
  deposits: { taken: { count: 2, amount: '400000.00', cash: '150000.00' }, applied: { count: 1, amount: '200000.00' }, refunded: { count: 1, amount: '100000.00', cash: '100000.00' } },
  credit: { sales: { count: 1, amount: '300000.00' }, repayments: { count: 1, amount: '80000.00', cash: '80000.00' } },
  cash: { opening: '500000.00', sales: '1500000.00', refunds: '50000.00', cash_in: '100000.00', cash_out: '200000.00', wages: '150000.00', purchases: '300000.00', deposits: '150000.00', deposit_refunds: '100000.00', repayments: '80000.00', expected },
  movements: [{ uuid: 'm1', type: 'purchase', category: 'bazaar', amount: '300000.00', note: "Go'sht", employee: null, user: { id: 5, name: 'Kassir Ali' }, approved_by: null, created_at: '2026-10-21T08:00:00+00:00' }],
})

export interface FakeShift {
  uuid: string
  status: 'open' | 'closed'
  branch_id: number
  device: { uuid: string; name: string } | null
  business_date: string
  opened_at: string
  opened_by: { id: number; name: string } | null
  opening_cash: string
  closed_at: string | null
  closed_by: { id: number; name: string } | null
  expected_cash: string | null
  counted_cash: string | null
  counted_breakdown: Record<string, number> | null
  difference: string | null
  z_number: number | null
  force_closed: boolean
  close_note: string | null
}

export class FakeShifts {
  shifts: FakeShift[] = [
    {
      uuid: 's0000000-0000-4000-8000-000000000012',
      status: 'closed',
      branch_id: 1,
      device: { uuid: 'dev-1', name: 'Kassa 1' },
      business_date: '2026-10-21',
      opened_at: '2026-10-21T04:00:00+00:00',
      opened_by: { id: 5, name: 'Kassir Ali' },
      opening_cash: '500000.00',
      closed_at: '2026-10-21T19:00:00+00:00',
      closed_by: { id: 5, name: 'Kassir Ali' },
      expected_cash: '1730000.00',
      counted_cash: '1720000.00',
      counted_breakdown: { '100000': 17, '10000': 2 },
      difference: '-10000.00',
      z_number: 12,
      force_closed: false,
      close_note: null,
    },
  ]

  constructor(private readonly backend: FakeBackend) {}

  handle(req: FakeRequest): Reply | null {
    void this.backend
    const path = req.path
    let match: RegExpExecArray | null
    if (req.method === 'GET' && path === '/v1/pos/shifts') {
      const rows = this.shifts.filter((shift) => (!req.query.status || shift.status === req.query.status) && (!req.query.branch_id || shift.branch_id === Number(req.query.branch_id)))
      return ok(rows)
    }
    if ((match = /^\/v1\/pos\/shifts\/([\w-]+)(\/x-report)?$/.exec(path)) && req.method === 'GET') {
      const shift = this.shifts.find((candidate) => candidate.uuid === match![1])
      if (!shift) return fail(404, 'Smena topilmadi.')
      const report = shiftReport(shift.uuid, shift.expected_cash ?? '900000.00')
      if (match[2]) return ok({ shift, report })
      return ok({ ...shift, summary: shift.status === 'closed' ? report : null })
    }
    return null
  }
}
