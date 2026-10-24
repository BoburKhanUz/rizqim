// Role permissions of the fake backend (GET/PUT /owner/permissions; docs/AUTH.md section 6). The defaults are
// App\Services\Auth\RolePermissions after P3-37 (the cashier reprints bills and moves cash alone); the matrix is the defaults
// with the store's overrides, and a PUT sets or (allowed null) removes one override, as PermissionService::setOverride does.
import type { FakeBackend } from './backend'
import { fail, ok, type FakeRequest, type Reply } from './types'

// The owner keeps these: refused when granted (Permission::isGrantable)
const OWNER_ONLY = ['orders.refund', 'finance.manage', 'settings.manage', 'zones.manage', 'staff.manage', 'audit.view', 'permissions.manage']

const LABELS: Record<string, string> = {
  'orders.view': "Buyurtmalarni ko'rish",
  'orders.manage': "Buyurtma yaratish va holatini o'zgartirish",
  'orders.cancel': 'Buyurtmani bekor qilish yoki rad etish',
  'orders.discount': 'Chegirma berish',
  'orders.void_item': "Taomni chekdan o'chirish",
  'orders.assign_courier': 'Kuryer biriktirish',
  'orders.refund': 'Pulni qaytarish',
  'kitchen.manage': 'Oshxona ekrani: "tayyor" belgilash',
  'deliveries.manage': 'Yetkazib berish (kuryer)',
  'tables.manage': 'Stollarni boshqarish',
  'menu.view': "Menyuni ko'rish",
  'menu.manage': 'Menyu va narxlarni tahrirlash',
  'stoplist.manage': 'Stop-list (tugadi belgisi)',
  'batches.manage': "Qozon va partiya qoldig'i",
  'staff.view': "Xodimlarni ko'rish",
  'staff.manage': 'Xodimlar va rollarni boshqarish',
  'staff.pin': "Xodim PIN kodini o'rnatish",
  'devices.view': "Qurilmalarni ko'rish",
  'devices.manage': "Qurilmalarni ulash va o'chirish",
  'reports.view': 'Hisobotlar',
  'finance.view': "Moliyani ko'rish",
  'finance.manage': "Pul yechish va to'ldirish",
  'settings.manage': "Do'kon sozlamalari",
  'zones.manage': 'Yetkazib berish hududlari va narxlari',
  'audit.view': 'Harakatlar jurnali',
  'permissions.manage': "Ruxsatlarni o'zgartirish",
  'pos.open_shift': 'Smenani ochish',
  'pos.close_shift': 'Smenani yopish',
  'pos.cash_in_out': 'Kassaga pul kiritish va chiqarish',
  'pos.take_payment': "To'lov qabul qilish",
  'pos.reprint_bill': 'Schyotni qayta chiqarish',
  'branches.manage': 'Filiallar va ish vaqti',
  'bookings.manage': 'Bron, avans va oldindan buyurtma',
  'customer_accounts.manage': 'Nasiya hisoblari',
  'pos.refund': 'Smena ichida pulni qaytarish',
}

const KEYS = Object.keys(LABELS)

const DEFAULTS: Record<string, string[]> = {
  owner: KEYS.filter((key) => key !== 'orders.refund'),
  manager: [
    'orders.view', 'orders.manage', 'orders.cancel', 'orders.discount', 'orders.void_item', 'orders.assign_courier', 'kitchen.manage', 'tables.manage',
    'menu.view', 'stoplist.manage', 'staff.view', 'staff.pin', 'devices.view', 'devices.manage', 'reports.view', 'pos.open_shift', 'pos.close_shift',
    'pos.cash_in_out', 'pos.take_payment', 'pos.reprint_bill', 'batches.manage', 'branches.manage', 'bookings.manage', 'customer_accounts.manage', 'pos.refund',
  ],
  cashier: ['orders.view', 'orders.manage', 'tables.manage', 'menu.view', 'pos.open_shift', 'pos.close_shift', 'pos.take_payment', 'bookings.manage', 'pos.reprint_bill', 'pos.cash_in_out'],
  waiter: ['orders.view', 'orders.manage', 'tables.manage', 'menu.view'],
  cook: ['orders.view', 'kitchen.manage', 'menu.view', 'batches.manage'],
  courier: ['deliveries.manage'],
}

const ROLE_LABELS: Record<string, string> = { owner: 'Ega', manager: 'Menejer', cashier: 'Kassir', waiter: 'Ofitsiant', cook: 'Oshpaz', courier: 'Kuryer' }

const matrixOf = (granted: (role: string, key: string) => boolean) =>
  Object.fromEntries(Object.keys(DEFAULTS).map((role) => [role, Object.fromEntries(KEYS.map((key) => [key, granted(role, key)]))]))

export class FakePermissions {
  /** The store's overrides: role -> permission -> allowed (what PUT sets) */
  readonly overrides = new Map<string, boolean>()

  constructor(private readonly backend: FakeBackend) {}

  private effective(role: string, key: string): boolean {
    return this.overrides.get(`${role}:${key}`) ?? DEFAULTS[role].includes(key)
  }

  handle(req: FakeRequest, body: Record<string, unknown> | null): Reply | null {
    if (req.path !== '/v1/owner/permissions') return null
    if (!this.backend.account.permissions.includes('permissions.manage')) return fail(403, 'Bu amalga ruxsat yo‘q.')

    if (req.method === 'GET') {
      return ok({
        roles: Object.keys(DEFAULTS).map((role) => ({ role, label: ROLE_LABELS[role], editable: role !== 'owner' })),
        permissions: KEYS.map((key) => ({ key, label: LABELS[key], grantable: !OWNER_ONLY.includes(key) })),
        matrix: matrixOf((role, key) => this.effective(role, key)),
        defaults: matrixOf((role, key) => DEFAULTS[role].includes(key)),
      })
    }

    if (req.method === 'PUT') {
      const role = String(body?.role)
      const permission = String(body?.permission)
      const allowed = body?.allowed
      if (!DEFAULTS[role] || role === 'owner') return fail(422, 'The given data was invalid.', { role: ['Tanlangan qiymat noto‘g‘ri.'] })
      if (!KEYS.includes(permission)) return fail(422, 'The given data was invalid.', { permission: ['Tanlangan qiymat noto‘g‘ri.'] })
      if (allowed !== null && typeof allowed !== 'boolean') return fail(422, 'The given data was invalid.', { allowed: ['allowed maydoni noto‘g‘ri.'] })
      if (allowed === true && OWNER_ONLY.includes(permission)) return fail(422, "Bu ruxsatni faqat do'kon egasi bajaradi.")
      if (allowed === null) this.overrides.delete(`${role}:${permission}`)
      else this.overrides.set(`${role}:${permission}`, allowed)
      return ok({ role, permission, allowed: this.effective(role, permission) }, { message: 'Ruxsat saqlandi.' })
    }
    return null
  }
}
