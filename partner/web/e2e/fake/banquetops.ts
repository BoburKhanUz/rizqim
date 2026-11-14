// The banquet event's kitchen papers, contract and final settlement of the fake backend (docs/API.md P6-54): one event's
// production sheet, the shopping list, the osh calculator, the contract with its number, the settlement with the
// guests who came, and the owner's operations settings. Enough for the panel's flows; the sums are the backend's and
// its tests.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

export const OPS_EVENT = 'eeeeeeee-0000-4000-8000-000000000054'

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

export class FakeBanquetOps {
  contractNo: string | null = null
  guestsActual: number | null = null
  settings = {
    reserve_percent: '10.00',
    osh: { persons_per_kg: '5.0', product_ids: [] as number[], ingredients: [{ key: 'rice', name: 'Guruch', unit: 'kg', per_kg: '1.000' }, { key: 'meat', name: "Go'sht", unit: 'kg', per_kg: '1.000' }], kazans: [10, 20, 50] },
    portion_weights: [] as { product_id: number; grams: number }[],
    mxik: { package: { mxik: null as string | null, package_code: null }, hall: { mxik: null, package_code: null }, extra: { mxik: null, package_code: null } },
    contract_template: "SHARTNOMA № {{contract_no}}\n{{customer_name}}",
    contract_template_is_default: true,
    contract_placeholders: ['contract_no', 'customer_name', 'total'],
    version: 1,
  }

  constructor(private readonly backend: FakeBackend) {
    void this.backend
  }

  private osh(persons: number) {
    const kg = Math.ceil((persons * 2) / Number(this.settings.osh.persons_per_kg)) / 2
    return {
      persons,
      persons_per_kg: this.settings.osh.persons_per_kg,
      osh_kg: kg.toFixed(1),
      ingredients: this.settings.osh.ingredients.map((ingredient) => ({ ...ingredient, quantity: (kg * Number(ingredient.per_kg)).toFixed(3) })),
      kazans: kg > 0 ? [{ size_kg: 50, count: Math.ceil(kg / 50) }] : [],
      kazan_capacity_kg: (Math.ceil(kg / 50) * 50).toFixed(1),
    }
  }

  private billed(): number {
    return Math.max(this.guestsActual ?? 0, 200, 150)
  }

  private settlement() {
    const guests = this.billed()
    const total = guests * 150000 + 5000000 + 60000
    return {
      event: { uuid: OPS_EVENT, status: 'in_progress', version: 5, order_uuid: 'oooooooo-0000-4000-8000-000000000054' },
      guests: { guaranteed: 200, minimum: 150, actual: this.guestsActual, billed: guests },
      lines: [
        { uuid: 'l-1', kind: 'package', name: 'Standart', unit_price: '150000.00', quantity: '1.000', persons: guests, amount: `${guests * 150000}.00` },
        { uuid: 'l-2', kind: 'hall_rent', name: 'Zal ijarasi: Oq zal', unit_price: '5000000.00', quantity: '1.000', persons: null, amount: '5000000.00' },
      ],
      package_total: `${guests * 150000}.00`,
      hall_total: '5000000.00',
      extras_total: '0.00',
      orders: { lines: '60000.00', discount: '0.00', service_charge: '0.00', other_charges: '0.00', total: '60000.00' },
      total: `${total}.00`,
      advances: { total: '25000000.00', applied: '0.00', rows: [] },
      due: `${total - 25000000}.00`,
      excess: '0.00',
      settlement: null,
      warnings: [],
    }
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.method === 'GET' && req.path === '/v1/owner/pos/banquets/ops-settings') return ok(this.settings)
    if (req.method === 'PUT' && req.path === '/v1/owner/pos/banquets/ops-settings') {
      if (Number(body?.version) !== this.settings.version) return fail(409, "Sozlamalar boshqa joyda o'zgartirilgan: sahifani yangilang.", undefined, { reason: 'version_conflict' })
      const osh = (body?.osh ?? this.settings.osh) as typeof this.settings.osh
      this.settings = { ...this.settings, reserve_percent: Number(body?.reserve_percent ?? 0).toFixed(2), osh, contract_template: String(body?.contract_template ?? ''), contract_template_is_default: false, version: this.settings.version + 1 }
      return ok(this.settings)
    }
    if (req.method === 'GET' && req.path === '/v1/pos/banquets/osh-calculator') return ok(this.osh(Number(req.query.persons ?? 0)))
    if (req.method === 'GET' && req.path === '/v1/pos/banquets/shopping-list') {
      return ok({
        branch_id: 1,
        from: req.query.from,
        to: req.query.to,
        events: [{ uuid: OPS_EVENT, title: "Aziz va Malika to'yi", event_type: 'toy', event_date: '2026-11-20', slot: 'evening', status: 'confirmed', planned_guests: 220 }],
        dishes: [{ product_id: null, name: 'Non', unit: 'piece', quantity_per_person: null, quantity: '220.000', kg: null }],
        osh: { persons: 220, osh_kg: '44.0', ingredients: this.osh(220).ingredients },
      })
    }
    if (req.method === 'GET' && req.path === '/v1/pos/banquets/shopping-list/export') {
      return { status: 200, body: null, raw: 'xlsx', headers: { 'Content-Type': XLSX, 'Content-Disposition': `attachment; filename=bozorlik-${req.query.from}-${req.query.to}.xlsx` } }
    }

    const prefix = `/v1/pos/banquets/${OPS_EVENT}`
    if (!req.path.startsWith(`${prefix}/`)) return null
    const path = req.path.slice(prefix.length)
    if (req.method === 'GET' && path === '/production-sheet') {
      return ok({
        event: { uuid: OPS_EVENT, title: "Aziz va Malika to'yi", event_type: 'toy', event_type_label: "To'y", event_date: '2026-11-20', slot: 'evening', starts_at: null, ends_at: null, local_time: '18:00–23:00', status: 'confirmed', halls: ['Oq zal'] },
        guests: { guaranteed: 200, minimum: 150, actual: this.guestsActual, base: this.billed(), reserve_percent: '10.00', planned: Math.ceil((this.billed() * 110) / 100) },
        stations: [{ station_id: 1, name: 'Osh sexi', dishes: [{ product_id: 9, name: "To'y oshi", unit: 'portion', quantity_per_person: '1.000', quantity: `${Math.ceil((this.billed() * 110) / 100)}.000`, kg: null }] }],
        osh: this.osh(Math.ceil((this.billed() * 110) / 100)),
        warnings: [],
      })
    }
    if (req.method === 'GET' && path === '/production-sheet/export') {
      return { status: 200, body: null, raw: 'xlsx', headers: { 'Content-Type': XLSX, 'Content-Disposition': 'attachment; filename=ishlab-chiqarish-2026-11-20.xlsx' } }
    }
    const contract = () => ({ contract_no: this.contractNo, text: `SHARTNOMA № ${this.contractNo ?? '________'}\n\nKarimovlar oilasi`, lines: [], fields: {}, warnings: [] })
    if (req.method === 'GET' && path === '/contract') return ok(contract())
    if (req.method === 'POST' && path === '/contract/number') {
      this.contractNo ??= '2026-0001'
      return ok(contract())
    }
    if (req.method === 'GET' && path === '/settlement') return ok(this.settlement())
    if (req.method === 'PATCH' && path === '/guests') {
      this.guestsActual = Number(body?.guests_actual)
      return ok({ event: { uuid: OPS_EVENT }, settlement: this.settlement() })
    }
    return null
  }
}
