// The branch format of the fake backend (docs/API.md P6-11 "Filial formati"): the profile, the till's mode, the module switches
// over the profile's defaults with the plan's and the law's locks, and the sample stations and menu. The store's service fee
// rows (GET /owner/service-fees) live here too: the Format page reads them to warn that a stolovaya takes no service charge.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

interface Profile {
  key: string
  label: string
  description: string
  mode: string
  first_screen: string
  modules: string[]
  locks_service_charge: boolean
  recommended_plan: string
  stations: Array<{ name: string; kind: string; kind_label: string }>
  dishes: Array<{ category: string; name: string; variants: string[] }>
}

const PROFILES: Profile[] = [
  {
    key: 'choyxona',
    label: 'Choyxona',
    description: "Zal, xona va tapchanlar, ofitsiant telefoni: hozirgi kassa o'zgarishsiz.",
    mode: 'hybrid',
    first_screen: 'hall_map',
    modules: ['rooms', 'room_fee', 'guest_items', 'bookings', 'customer_accounts', 'service_charge', 'kds'],
    locks_service_charge: false,
    recommended_plan: 'asosiy',
    stations: [{ name: 'Osh va qozon', kind: 'osh', kind_label: 'Osh va qozon' }],
    dishes: [{ category: 'Milliy taomlar', name: 'Osh', variants: ['1 kg'] }],
  },
  {
    key: 'osh_markazi',
    label: 'Osh markazi',
    description: 'Tez kassa, buyurtma raqami va tayyor raqamlar ekrani: osh tushligi.',
    mode: 'counter',
    first_screen: 'quick_counter',
    modules: ['quick_counter', 'queue_numbers', 'queue_calling', 'customer_display', 'batches', 'batch_writeoff', 'daily_menu'],
    locks_service_charge: false,
    recommended_plan: 'start',
    stations: [
      { name: 'Osh va qozon', kind: 'osh', kind_label: 'Osh va qozon' },
      { name: 'Mangal', kind: 'mangal', kind_label: 'Mangal' },
    ],
    dishes: [{ category: 'Milliy taomlar', name: 'Osh', variants: ['porsiya'] }],
  },
  {
    key: 'stolovaya',
    label: 'Stolovaya (oshxona-liniya)',
    description: "Kunlik menyu, kompleks va partiya hisobi; xizmat haqi qonun bo'yicha olinmaydi.",
    mode: 'counter',
    first_screen: 'quick_counter',
    modules: ['quick_counter', 'queue_numbers', 'daily_menu', 'combo_meal', 'batches', 'batch_writeoff', 'terminal_card_flow'],
    locks_service_charge: true,
    recommended_plan: 'start',
    stations: [{ name: 'Issiq taomlar', kind: 'kitchen', kind_label: 'Issiq oshxona' }],
    dishes: [{ category: 'Birinchi taomlar', name: 'Mastava', variants: ['porsiya'] }],
  },
]

const MODULES: Array<{ key: string; label: string; description: string; plan?: string }> = [
  { key: 'quick_counter', label: 'Tez kassa', description: "Menyu tugmalari bilan bir necha bosishda buyurtma." },
  { key: 'queue_numbers', label: 'Buyurtma raqami', description: "Har buyurtmaga talon va raqam." },
  { key: 'queue_calling', label: 'Raqamni chaqirish', description: "Tayyor raqam ekranda va ovozda e'lon qilinadi." },
  { key: 'customer_display', label: 'Mijoz ekrani', description: "Savat va tayyor raqamlar mijozga ko'rinadi." },
  { key: 'daily_menu', label: 'Bugungi menyu', description: 'Kunlik taomlar ro‘yxati.' },
  { key: 'combo_meal', label: 'Kompleks', description: "Bir narxdagi to'plam." },
  { key: 'batches', label: 'Partiyalar (qozon)', description: 'Qozon va tandir partiyalari.' },
  { key: 'batch_writeoff', label: 'Hisobdan chiqarish', description: "Sotilmagan porsiyalar hisobdan chiqariladi." },
  { key: 'terminal_card_flow', label: 'Karta terminali', description: "Bank terminalidagi kartalar va kun yakuni." },
  { key: 'rooms', label: 'Xonalar va tapchanlar', description: "Xona va tapchanlar bandligi." },
  { key: 'service_charge', label: 'Xizmat haqi', description: "Hisobga foiz qo'shiladi." },
  { key: 'kds', label: 'Oshxona ekrani (KDS)', description: 'Oshxona uchun buyurtmalar ekrani.', plan: 'asosiy' },
]

export interface FakeServiceFeeRow {
  branch_id: number | null
  hall_id: number | null
  service_charge_percent: string
  service_charge_channels: string[] | null
  place_fee_amount: string
  place_fee_mode: string
  place_fee_channels: string[] | null
}

export class FakeFormat {
  profile = 'choyxona'
  mode = 'hybrid'
  /** {module: true|false}: the owner's switches over the profile's defaults. */
  overrides: Record<string, boolean> = {}
  /** The plan lacks these modules (a plan lock): they stay off and cannot be switched on. */
  planLocked = new Set<string>(['kds'])
  /** Sample stations and dishes already on the branch. */
  existingStations = new Set<string>(['Osh va qozon'])
  existingDishes = new Set<string>()
  serviceFees: FakeServiceFeeRow[] = []

  constructor(readonly backend: FakeBackend) {}

  private get current(): Profile {
    return PROFILES.find((profile) => profile.key === this.profile) ?? PROFILES[0]
  }

  private enabled(key: string): boolean {
    if (this.current.locks_service_charge && key === 'service_charge') return false
    if (this.planLocked.has(key)) return false
    return key in this.overrides ? this.overrides[key] : this.current.modules.includes(key)
  }

  private view(branchId: number) {
    const profile = this.current
    return {
      branch_id: branchId,
      profile: profile.key,
      profile_label: profile.label,
      mode: this.mode,
      first_screen: profile.first_screen,
      modules: MODULES.filter((module) => this.enabled(module.key)).map((module) => module.key),
      locks: { service_charge_zero: profile.locks_service_charge },
      module_rows: MODULES.map((module) => {
        const locked = profile.locks_service_charge && module.key === 'service_charge' ? 'law' : this.planLocked.has(module.key) ? 'plan' : null
        return {
          key: module.key,
          label: module.label,
          description: module.description,
          default: profile.modules.includes(module.key),
          override: module.key in this.overrides ? this.overrides[module.key] : null,
          configured: true,
          plan_allows: !this.planLocked.has(module.key),
          required_plan: this.planLocked.has(module.key) ? (module.plan ?? 'asosiy') : null,
          required_plan_name: this.planLocked.has(module.key) ? 'Asosiy' : null,
          locked,
          enabled: this.enabled(module.key),
        }
      }),
      profiles: PROFILES.map((option) => ({
        key: option.key,
        label: option.label,
        description: option.description,
        mode: option.mode,
        first_screen: option.first_screen,
        modules: option.modules,
        locks_service_charge: option.locks_service_charge,
        recommended_plan: option.recommended_plan,
        stations: option.stations.map((station) => station.name),
        dishes: option.dishes.length,
      })),
      template: {
        profile: profile.key,
        stations: profile.stations.map((station) => ({ ...station, exists: this.existingStations.has(station.name) })),
        dishes: profile.dishes.map((dish) => ({ ...dish, exists: this.existingDishes.has(dish.name) })),
      },
    }
  }

  private update(body: Record<string, unknown>): Reply {
    const branchId = Number(body.branch_id ?? 1)
    if (typeof body.profile === 'string') {
      const next = PROFILES.find((profile) => profile.key === body.profile)
      if (!next) return fail(422, 'The given data was invalid.', { profile: ['Bunday format yo‘q.'] })
      // A new profile starts from its own mode and modules: the switches set by hand are dropped
      this.profile = next.key
      this.mode = next.mode
      this.overrides = {}
    }
    if (typeof body.mode === 'string') this.mode = body.mode
    const modules = (body.modules as Record<string, boolean | null> | undefined) ?? {}
    for (const [key, value] of Object.entries(modules)) {
      if (value === true && this.current.locks_service_charge && key === 'service_charge') {
        return fail(422, "Stolovayada xizmat haqi qonun bo'yicha olinmaydi (0%), uni yoqib bo'lmaydi.", undefined, { reason: 'service_charge_locked', module: key })
      }
      if (value === true && this.planLocked.has(key)) return fail(403, 'Bu modul tarifingizda yo‘q.', undefined, { reason: 'plan_feature_missing', module: key })
      if (value === null) delete this.overrides[key]
      else this.overrides[key] = value
    }
    return ok(this.view(branchId))
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const path = req.path
    if (req.method === 'GET' && path === '/v1/owner/pos/format') return ok(this.view(Number(req.query.branch_id ?? 1)))
    if (req.method === 'PUT' && path === '/v1/owner/pos/format') return this.update(body ?? {})
    if (req.method === 'POST' && path === '/v1/owner/pos/format/template') {
      const profile = this.current
      const stations = profile.stations.filter((station) => !this.existingStations.has(station.name))
      const dishes = profile.dishes.filter((dish) => !this.existingDishes.has(dish.name))
      const result = {
        stations_created: stations.map((station) => station.name),
        stations_skipped: profile.stations.filter((station) => this.existingStations.has(station.name)).map((station) => station.name),
        categories_created: [] as string[],
        dishes_created: dishes.map((dish) => dish.name),
        dishes_skipped: profile.dishes.filter((dish) => this.existingDishes.has(dish.name)).map((dish) => dish.name),
      }
      for (const station of stations) this.existingStations.add(station.name)
      for (const dish of dishes) this.existingDishes.add(dish.name)
      return ok({ ...result, format: this.view(Number(body?.branch_id ?? 1)) })
    }
    if (req.method === 'GET' && path === '/v1/owner/service-fees') return ok({ channels: ['pos_hall', 'pos_takeaway'], settings: this.serviceFees })
    return null
  }
}
