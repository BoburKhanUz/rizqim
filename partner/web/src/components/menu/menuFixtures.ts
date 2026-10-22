import { json } from '@/test/helpers';

// Answers of the API for the menu screens' tests: {success, message, data} like App\Traits\ApiResponse
export const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: 'ok', data, ...extra });

export const OWNER_PERMISSIONS = ['menu.view', 'menu.manage', 'stoplist.manage', 'batches.manage', 'staff.view'];

export function permissionsAnswer(permissions: string[] = OWNER_PERMISSIONS, over: Record<string, unknown> = {}) {
  return ok({ role: 'owner', store_id: 1, branch_id: null, branch_scoped: false, permissions, device_uuid: null, ...over });
}

export const variant = (over: Record<string, unknown> = {}) => ({
  id: 11,
  uuid: 'v-11',
  product_id: 7,
  name: '1 porsiya',
  sku: null,
  price: '35000.00',
  stock: 999,
  attributes_json: null,
  is_active: true,
  unit: 'piece',
  quantity_step: null,
  min_quantity: null,
  batch_consumption: '1.000',
  ...over,
});

export const product = (over: Record<string, unknown> = {}) => ({
  id: 7,
  uuid: 'p-7',
  store_id: 1,
  category_id: 2,
  name: 'Osh',
  slug: 'osh',
  description: "Toshkent oshi",
  type: 'physical',
  sku: null,
  status: 'active',
  sort_order: 3,
  has_variants: true,
  image: null,
  image_url: null,
  image_thumb_urls: { sm: null, md: null },
  batch_tracked: true,
  quick_add: false,
  is_active: true,
  category: { id: 2, name: 'Issiq taomlar' },
  variants: [variant()],
  ...over,
});

export const category = (over: Record<string, unknown> = {}) => ({ id: 2, uuid: 'c-2', parent_id: null, name: 'Issiq taomlar', slug: 'issiq', sort_order: 0, is_active: true, ...over });

export const group = (over: Record<string, unknown> = {}) => ({
  id: 3,
  uuid: 'g-3',
  name: 'Sous tanlang',
  name_i18n: { ru: 'Выберите соус' },
  min_select: 0,
  max_select: 2,
  is_required: false,
  sort_order: 0,
  is_active: true,
  modifiers: [
    { id: 31, uuid: 'm-31', name: 'Achchiq', name_i18n: null, price_delta: '3000.00', max_quantity: 1, product_variant_id: null, sort_order: 0, is_active: true },
    { id: 32, uuid: 'm-32', name: 'Qatiq', name_i18n: { ru: 'Катык' }, price_delta: '0.00', max_quantity: 1, product_variant_id: null, sort_order: 1, is_active: true },
  ],
  product_ids: [7],
  ...over,
});

export const branch = (over: Record<string, unknown> = {}) => ({ id: 1, name: 'Chilonzor', phone: null, address: null, region_id: null, district_id: null, latitude: null, longitude: null, radius_km: 5, status: 'active', is_active: true, ...over });

export const batch = (over: Record<string, unknown> = {}) => ({
  id: 41,
  uuid: 'b-41',
  branch_id: 1,
  product_id: 7,
  product_name: 'Osh',
  product_variant_id: null,
  variant_name: null,
  unit: null,
  label: 'Tushlik',
  quantity_produced: 30,
  quantity_left: 12,
  is_open: true,
  started_at: '2026-10-21T10:00:00+05:00',
  sold_out_at: null,
  closed_at: null,
  ...over,
});
