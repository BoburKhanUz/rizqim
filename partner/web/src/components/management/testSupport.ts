import type { DeliveryZone, ManagedBranch, StaffMember, StoreDevice } from '@rizqim/staff';
import { setSession } from '@/lib/session/client';
import { json } from '@/test/helpers';

// Shared by the tests of the management screens: a signed-in session, the {success, message, data} envelope and fixtures
// shaped like the API's answers.
export const signIn = () => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: { id: 7, name: 'Aziz', role: 'owner' } });

export const ok = (data: unknown, status = 200) => json({ success: true, message: 'ok', data }, status);
export const refusal = (message: string, status = 422, extra: Record<string, unknown> = {}) => json({ success: false, message, ...extra }, status);

export const OWNER_PERMISSIONS = [
  'orders.view', 'staff.view', 'staff.manage', 'staff.pin', 'settings.manage', 'branches.manage', 'zones.manage', 'devices.view', 'devices.manage',
];

export const permissions = (keys: string[] = OWNER_PERMISSIONS, over: Record<string, unknown> = {}) =>
  ok({ role: 'owner', store_id: 1, branch_id: null, branch_scoped: false, permissions: keys, device_uuid: null, ...over });

export const makeMember = (over: Partial<StaffMember> = {}): StaffMember => ({
  id: 1,
  name: 'Ali Valiyev',
  role: 'cashier',
  role_label: 'Kassir',
  branch: { id: 1, name: 'Markaz' },
  courier_type: null,
  phone: '+998901234567',
  login_phone_enabled: true,
  login_phone_proof: 'vouched',
  has_pin: false,
  staff_code: null,
  telegram_linked: false,
  invite_pending: false,
  can_edit: true,
  created_at: '2026-10-01T10:00:00+05:00',
  ...over,
});

export const makeBranch = (over: Partial<ManagedBranch> = {}): ManagedBranch => ({
  id: 1,
  name: 'Markaz',
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
});

export const LOCATIONS = {
  regions: [
    { id: 1, name: 'Toshkent', is_active: true },
    { id: 2, name: 'Samarqand', is_active: true },
  ],
  districts: [
    { id: 10, region_id: 1, name: 'Chilonzor', is_active: true },
    { id: 11, region_id: 1, name: 'Yunusobod', is_active: true },
    { id: 20, region_id: 2, name: 'Registon', is_active: true },
  ],
};

export const makeZone = (over: Partial<DeliveryZone> = {}): DeliveryZone => ({
  id: 7,
  name: 'Markaz',
  branch_id: 1,
  branch: { id: 1, name: 'Markaz' },
  polygon: { type: 'Polygon', coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.3]]] },
  bbox: { min_lat: '41.3', max_lat: '41.4', min_lng: '69.2', max_lng: '69.3' },
  area_km2: '12.50',
  delivery_price: '12000.00',
  min_order_amount: '30000.00',
  free_delivery_from: '150000.00',
  eta_minutes: 40,
  priority: 2,
  active: true,
  created_at: null,
  updated_at: null,
  ...over,
});

export const makeDevice = (over: Partial<StoreDevice> = {}): StoreDevice => ({
  uuid: 'dev-1',
  type: 'pos',
  name: 'Kassa 1',
  platform: 'windows',
  app_version: '1.2.0',
  store_id: 1,
  branch_id: 1,
  user: { id: 5, name: 'Kassir Ali', role: 'cashier' },
  has_push_token: false,
  last_seen_at: '2026-10-21T09:30:00+05:00',
  revoked_at: null,
  created_at: null,
  ...over,
});
