// Branches, staff (members, PINs, roles), locations and the store's terminals of the fake backend (docs/API.md section 12).
import type { FakeBackend } from './backend'
import { ROLE_LABELS } from './backend'
import { created, fail, ok, type FakeRequest, type Reply } from './types'

export interface FakeBranch {
  id: number
  name: string
  phone: string | null
  address: string | null
  region_id: number | null
  district_id: number | null
  latitude: number | null
  longitude: number | null
  radius_km: number
  status: 'active' | 'inactive' | 'temporarily_closed'
  is_active: boolean
  working_hours: { open: string | null; close: string | null; all_day: boolean; overnight: boolean; timezone: string }
  is_open_now: boolean
  created_at: string | null
  updated_at: string | null
}

export interface FakeMember {
  id: number
  name: string
  role: string
  role_label: string
  branch: { id: number; name: string } | null
  courier_type: string | null
  phone: string | null
  login_phone_enabled: boolean
  has_pin: boolean
  staff_code: string | null
  telegram_linked: boolean
  invite_pending: boolean
  can_edit: boolean
  created_at: string | null
}

export const makeBranch = (over: Partial<FakeBranch> & Pick<FakeBranch, 'id' | 'name'>): FakeBranch => ({
  phone: '+998712000000',
  address: 'Amir Temur 1',
  region_id: 1,
  district_id: 10,
  latitude: 41.3111,
  longitude: 69.2797,
  radius_km: 8,
  status: 'active',
  is_active: true,
  working_hours: { open: '09:00', close: '22:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' },
  is_open_now: true,
  created_at: null,
  updated_at: null,
  ...over,
})

export const makeMember = (over: Partial<FakeMember> & Pick<FakeMember, 'id' | 'name' | 'role'>): FakeMember => ({
  role_label: ROLE_LABELS[over.role] ?? over.role,
  branch: { id: 1, name: 'Chilonzor' },
  courier_type: over.role === 'courier' ? 'own' : null,
  phone: null,
  login_phone_enabled: false,
  has_pin: false,
  staff_code: null,
  telegram_linked: false,
  invite_pending: false,
  can_edit: true,
  created_at: '2026-10-01T10:00:00+05:00',
  ...over,
})

// Codes the backend refuses as too easy (StaffPinService::weakness)
const WEAK_PINS = ['0000', '1111', '1234', '2580', '4321']

const LOCATIONS = {
  regions: [{ id: 1, name: 'Toshkent', is_active: true }],
  districts: [
    { id: 10, region_id: 1, name: 'Chilonzor', is_active: true },
    { id: 11, region_id: 1, name: 'Yunusobod', is_active: true },
  ],
}

export class FakeTeam {
  branches: FakeBranch[] = [makeBranch({ id: 1, name: 'Chilonzor' }), makeBranch({ id: 2, name: 'Yunusobod', latitude: 41.365, longitude: 69.287 })]
  members: FakeMember[] = [
    makeMember({ id: 21, name: 'Malika Menejer', role: 'manager', phone: '+998901112233', login_phone_enabled: true, has_pin: true, staff_code: 'M-21', telegram_linked: true }),
    makeMember({ id: 22, name: 'Sanjar Oshpaz', role: 'cook', phone: '+998907776655', login_phone_enabled: true }),
  ]
  private counter = 100

  constructor(private readonly backend: FakeBackend) {}

  private bound(): number | null {
    return this.backend.account.branchId
  }

  private createMember(body: Record<string, unknown>): Reply {
    const name = String(body.name ?? '').trim()
    const phone = typeof body.phone === 'string' && body.phone !== '' ? body.phone : null
    if (name === '') return fail(422, 'The given data was invalid.', { name: ['Ismini kiriting.'] })
    if (phone && this.members.some((member) => member.phone === phone)) {
      return fail(422, 'The given data was invalid.', { phone: ["Bu raqam do'koningizning boshqa xodimiga biriktirilgan."] })
    }
    const branch = this.branches.find((candidate) => candidate.id === Number(body.branch_id))
    if (body.role !== 'owner' && !branch && this.bound() === null && body.branch_id !== null && body.branch_id !== undefined) {
      return fail(422, 'The given data was invalid.', { branch_id: ['Filial topilmadi.'] })
    }
    const member = makeMember({
      id: ++this.counter,
      name,
      role: String(body.role),
      branch: branch ? { id: branch.id, name: branch.name } : null,
      phone,
      login_phone_enabled: phone !== null,
      invite_pending: true,
    })
    this.members.push(member)
    return created({ staff: member, invite_link: `https://t.me/e2e_bot?start=invite_${member.id}` })
  }

  private setPin(id: number, body: Record<string, unknown>): Reply {
    const member = this.members.find((candidate) => candidate.id === id)
    if (!member) return fail(404, 'Xodim topilmadi.')
    const pin = String(body.pin ?? '')
    if (!/^\d{4,6}$/.test(pin)) return fail(422, "PIN 4 tadan 6 tagacha raqamdan iborat bo'lishi kerak.", { pin: ["PIN 4 tadan 6 tagacha raqamdan iborat bo'lishi kerak."] })
    if (WEAK_PINS.includes(pin)) return fail(422, "PIN juda oddiy.", { pin: ["PIN juda oddiy: boshqasini tanlang."] })
    member.has_pin = true
    member.staff_code = member.staff_code ?? `K-${member.id}`
    this.pins.set(member.id, pin)
    return ok({ user_id: id, staff_code: member.staff_code, pin_set_at: new Date().toISOString() })
  }

  /** The PIN each member was given (what a terminal would check). */
  readonly pins = new Map<number, string>()

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    const input = body ?? {}
    let match: RegExpExecArray | null
    const path = req.path

    if (req.method === 'GET' && path === '/v1/owner/branches') {
      const bound = this.bound()
      return ok(this.branches.filter((branch) => bound === null || branch.id === bound))
    }
    if (req.method === 'GET' && path === '/v1/locations') return ok(LOCATIONS)

    if (req.method === 'GET' && path === '/v1/owner/staff') return ok(this.members)
    if (req.method === 'POST' && path === '/v1/owner/staff') return this.createMember(input)
    if ((match = /^\/v1\/owner\/staff\/(\d+)$/.exec(path)) && req.method === 'PATCH') {
      const member = this.members.find((candidate) => candidate.id === Number(match![1]))
      if (!member) return fail(404, 'Xodim topilmadi.')
      if (typeof input.name === 'string') member.name = input.name
      if ('phone' in input) {
        member.phone = (input.phone as string | null) ?? null
        member.login_phone_enabled = member.phone !== null
      }
      return ok(member)
    }
    if ((match = /^\/v1\/owner\/staff\/(\d+)\/pin$/.exec(path))) {
      if (req.method === 'PUT') return this.setPin(Number(match[1]), input)
      if (req.method === 'DELETE') {
        const member = this.members.find((candidate) => candidate.id === Number(match![1]))
        if (member) member.has_pin = false
        this.pins.delete(Number(match[1]))
        return ok(null)
      }
    }
    if ((match = /^\/v1\/owner\/staff\/(\d+)\/role$/.exec(path)) && req.method === 'PUT') {
      const member = this.members.find((candidate) => candidate.id === Number(match![1]))
      if (!member) return fail(404, 'Xodim topilmadi.')
      member.role = String(input.role)
      member.role_label = ROLE_LABELS[member.role] ?? member.role
      return ok({ user_id: member.id, role: member.role })
    }
    if ((match = /^\/v1\/owner\/employees\/(\d+)$/.exec(path)) && req.method === 'DELETE') {
      this.members = this.members.filter((member) => member.id !== Number(match![1]))
      return ok(null)
    }

    // The owner's staff list as the older employees endpoint sends it (the stop-list shows who stopped what)
    if (req.method === 'GET' && path === '/v1/owner/employees') return ok(this.members.map((member) => ({ id: member.id, name: member.name, role: member.role, phone: member.phone, branch: member.branch })))
    if (req.method === 'GET' && path === '/v1/owner/devices') return ok([])
    return null
  }
}
