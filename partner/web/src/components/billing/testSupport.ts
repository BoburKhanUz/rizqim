import type { Entitlements } from '@rizqim/staff';
import { json } from '@/test/helpers';

// Shared by the tests of the plan screens: the entitlements the way GET /owner/billing/entitlements answers them.
export const ok = (data: unknown) => json({ success: true, message: 'ok', data });

const DEVICES: Entitlements['devices'] = {
  pos: { included: 1, active: 1, extra: 0, extra_price: '59000.00', can_add: true },
  kds: { included: 0, active: 0, extra: 0, extra_price: '29000.00', can_add: false },
  waiter: { included: null, unlimited: true, extra_price: '0.00', can_add: true },
};

// A store on the Start plan: no bookings, deposits, nasiya, KDS or service charge
export const startPlan = (over: Partial<Entitlements> = {}): Entitlements => ({
  plan: { code: 'start', name: 'Start', tier: 'start', version: 2 },
  legacy: false,
  period: 'monthly',
  status: 'active',
  trial_ends_at: null,
  current_period: { start: '2026-10-01T00:00:00+05:00', end: '2026-11-01T00:00:00+05:00' },
  term_ends_at: null,
  locked_since: null,
  lock_at: null,
  settings_locked: false,
  features: { hall: true, room_fee: true, guest_items: true, shifts: true, rizqim_orders: true, bookings: false, deposits: false, customer_accounts: false, kds: false, service_charge: false, multi_branch_reports: false, aggregator_orders: false, banquet: false },
  required_plans: { bookings: 'asosiy', deposits: 'asosiy', customer_accounts: 'asosiy', kds: 'asosiy', service_charge: 'asosiy', multi_branch_reports: 'pro', aggregator_orders: 'pro', banquet: 'pro' },
  devices: DEVICES,
  notice: null,
  ...over,
});

// A store on the Asosiy plan: everything but the Pro features
export const asosiyPlan = (over: Partial<Entitlements> = {}): Entitlements =>
  startPlan({
    plan: { code: 'asosiy', name: 'Asosiy', tier: 'asosiy', version: 2 },
    features: { hall: true, room_fee: true, guest_items: true, shifts: true, rizqim_orders: true, bookings: true, deposits: true, customer_accounts: true, kds: true, service_charge: true, multi_branch_reports: false, aggregator_orders: false, banquet: false },
    devices: { ...DEVICES, kds: { included: 1, active: 1, extra: 0, extra_price: '29000.00', can_add: true } },
    ...over,
  });

export const reminder = (over: Partial<NonNullable<Entitlements['notice']>> = {}): NonNullable<Entitlements['notice']> => ({
  level: 'reminder',
  message: "OB-202610-1 hisob-faktura (249 000 so'm) muddati 3 kun o'tdi. 19.11.2026 dan yangi sozlamalar yopiladi. Kassa ishlashda davom etadi.",
  days_overdue: 3,
  lock_at: new Date(Date.now() + 4.2 * 86_400_000).toISOString(),
  invoice: { uuid: 'inv-1', number: 'OB-202610-1', amount: '249000.00', outstanding: '249000.00', due_date: '2026-11-11' },
  ...over,
});

export const locked = (over: Partial<NonNullable<Entitlements['notice']>> = {}): NonNullable<Entitlements['notice']> =>
  reminder({ level: 'locked', days_overdue: 9, lock_at: '2026-11-19T00:00:00+05:00', message: "OB-202610-1 hisob-faktura to'lanmagan.", ...over });

export const refusalBody = (reason: string, data: Record<string, unknown>, message: string) =>
  json({ success: false, message, code: reason, data: { reason, ...data }, ...(data.feature ? { feature: data.feature, required_plan: data.required_plan } : {}) }, 403);
