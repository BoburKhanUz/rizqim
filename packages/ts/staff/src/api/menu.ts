import type { Category, Product } from '../types';
import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// --- Products (the owner's menu) ------------------------------------------------------------------------------------

// A product of GET /owner/products: with its variants, category and `modifier_group_links` (the modifier groups it
// offers, in order, with this product's own rules: null is the group's).
export type OwnerProduct = OkData<'ownerCatalog.getProducts'>[number];
export type ModifierGroupLink = OwnerProduct['modifier_group_links'][number];

// GET /owner/products (menu.view): every product of the store.
export async function fetchOwnerProducts(api: StaffApi): Promise<OwnerProduct[]> {
  const body = unwrap(await api.client.GET('/v1/owner/products'));
  return body.data ?? [];
}

// GET /owner/categories: the options of the product form.
export async function fetchOwnerCategories(api: StaffApi): Promise<Category[]> {
  const body = unwrap(await api.client.GET('/v1/owner/categories'));
  return body.data ?? [];
}

// The fiscal fields of a product (P3-11): what it is on a receipt (dish or service), its MXIK and package code, its own VAT
// rate. Left out (undefined): the server keeps the stored value. null or "": clears it (a cleared `kind` is a dish).
export type ProductKind = 'dish' | 'service';

export interface ProductFiscalInput {
  kind?: ProductKind | null;
  mxik_code?: string | null;
  package_code?: string | null;
  vat_rate?: number | string | null;
}

// A product as the form sends it (multipart, so the image can go along). `variants` is a JSON array:
// [{"id": 1, "name": "...", "price": 25000}, ...]: one without an id is created, an existing one left out is deleted;
// "unit" (piece|kg|l), "quantity_step", "min_quantity" and "batch_consumption" are optional (docs/MENU.md).
export interface ProductInput extends ProductFiscalInput {
  name: string;
  description?: string | null;
  category_id: number | string;
  is_active?: boolean;
  image?: File | null;
  variants: ReadonlyArray<object>;
  batch_tracked?: boolean;
  // P3-26: offered on the POS quick-add strip (bootstrap `quick_items`). Left out: the stored value stays.
  quick_add?: boolean;
}

// The multipart body of ProductInput. Booleans go as "1"/"0" (Laravel's boolean rule takes both).
export function productFormData(input: ProductInput): FormData {
  const form = new FormData();
  form.append('name', input.name);
  form.append('description', input.description ?? '');
  form.append('category_id', String(input.category_id));
  if (input.is_active !== undefined) form.append('is_active', input.is_active ? '1' : '0');
  if (input.batch_tracked !== undefined) form.append('batch_tracked', input.batch_tracked ? '1' : '0');
  if (input.quick_add !== undefined) form.append('quick_add', input.quick_add ? '1' : '0');
  if (input.image) form.append('image', input.image);
  form.append('variants', JSON.stringify(input.variants));
  for (const field of ['kind', 'mxik_code', 'package_code', 'vat_rate'] as const) {
    const value = input[field];
    if (value !== undefined) form.append(field, value === null ? '' : String(value));
  }
  return form;
}

// POST /owner/products (create) or POST /owner/products/{id} (update; POST, not PUT, because PHP does not read a
// multipart PUT body). `form` is a ready FormData (productFormData) so an app can add fields of its own.
export async function saveProduct(api: StaffApi, id: number | string | null | undefined, form: FormData): Promise<Product | null> {
  // The generated body type is the JSON shape of the fields; a FormData is what goes over the wire
  const multipart = { body: form as never, bodySerializer: (value: unknown) => value as FormData };
  const result =
    id === null || id === undefined || id === ''
      ? unwrap(await api.client.POST('/v1/owner/products', multipart))
      : unwrap(await api.client.POST('/v1/owner/products/{id}', { params: { path: { id: String(id) } }, ...multipart }));
  return result.data ?? null;
}

// POST /owner/products/{id}?toggle_active=1: switches the product on or off (nothing else is sent).
export async function toggleProductActive(api: StaffApi, id: number | string): Promise<void> {
  const result = await api.client.POST('/v1/owner/products/{id}', {
    params: { path: { id: String(id) }, query: { toggle_active: 1 } },
    body: undefined as never,
  });
  unwrap(result);
}

// DELETE /owner/products/{id}/image: removes the product's picture (and its thumbnails).
export async function removeProductImage(api: StaffApi, id: number | string): Promise<Product> {
  return unwrap(await api.client.DELETE('/v1/owner/products/{id}/image', { params: { path: { id: String(id) } } })).data;
}

// DELETE /owner/products/{id}
export async function deleteProduct(api: StaffApi, id: number | string): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/products/{id}', { params: { path: { id: String(id) } } }));
}

// --- Stop-list ("tugadi") --------------------------------------------------------------------------------------------

export type StopListEntry = OkData<'stopList.index'>['entries'][number];
export type StopListInput = JsonRequest<'stopList.store'>;

// GET /owner/stop-list: what one branch cannot sell right now. `branchId` is required unless the token is a branch's.
export async function fetchStopList(api: StaffApi, branchId?: number): Promise<StopListEntry[]> {
  const body = unwrap(await api.client.GET('/v1/owner/stop-list', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data?.entries ?? [];
}

// POST /owner/stop-list: stops a product, a variant or a modifier (optionally until a time).
export async function addStopListEntry(api: StaffApi, input: StopListInput): Promise<StopListEntry> {
  const body = unwrap(await api.client.POST('/v1/owner/stop-list', { body: input }));
  return body.data;
}

// DELETE /owner/stop-list/{id}: puts it back on sale.
export async function removeStopListEntry(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/stop-list/{id}', { params: { path: { id } } }));
}

// --- Batches ("qozon") -----------------------------------------------------------------------------------------------

export type Batch = OkData<'batch.index'>['batches'][number];
export type BatchInput = JsonRequest<'batch.store'>;
export type BatchAdjustment = NonNullable<JsonRequest<'batch.adjust'>>;

// GET /owner/batches: the open batches of a branch (and the last 50 closed ones with `includeClosed`).
export async function fetchBatches(api: StaffApi, options: { branchId?: number; includeClosed?: boolean } = {}): Promise<Batch[]> {
  const query = {
    ...(options.branchId === undefined ? {} : { branch_id: options.branchId }),
    ...(options.includeClosed ? { include_closed: true } : {}),
  };
  const body = unwrap(await api.client.GET('/v1/owner/batches', { params: { query } }));
  return body.data?.batches ?? [];
}

// POST /owner/batches: opens a batch of a product (a pot of plov).
export async function openBatch(api: StaffApi, input: BatchInput): Promise<Batch> {
  const body = unwrap(await api.client.POST('/v1/owner/batches', { body: input }));
  return body.data;
}

// POST /owner/batches/{id}/adjust: adds to the batch or sets what is left.
export async function adjustBatch(api: StaffApi, id: number, adjustment: BatchAdjustment): Promise<Batch> {
  const body = unwrap(await api.client.POST('/v1/owner/batches/{id}/adjust', { params: { path: { id } }, body: adjustment }));
  return body.data;
}

// POST /owner/batches/{id}/close
export async function closeBatch(api: StaffApi, id: number): Promise<Batch> {
  const body = unwrap(await api.client.POST('/v1/owner/batches/{id}/close', { params: { path: { id } } }));
  return body.data;
}

// --- What a store's menu cannot offer right now --------------------------------------------------------------------------

export type BranchAvailability = OkData<'menuAvailability.index'>['branches'][number];

// GET /catalog/availability: stop-list and sold-out batches per branch (public; used to grey out the menu).
export async function fetchMenuAvailability(api: StaffApi, storeId: number | string, branchId?: number): Promise<BranchAvailability[]> {
  const body = unwrap(await api.client.GET('/v1/catalog/availability', { params: { query: { store_id: String(storeId), ...(branchId === undefined ? {} : { branch_id: branchId }) } } }));
  // (openapi-fetch drops the `id: null` / `until: null` members of the spec's union here: the same type, cast)
  return (body.data?.branches ?? []) as BranchAvailability[];
}
