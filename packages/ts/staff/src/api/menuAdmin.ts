import type { StaffApi } from './client';
import { unwrap } from './errors';
import { productFormData, type ProductInput } from './menu';
import type { JsonRequest, OkData } from './types';

// The owner's menu beyond products, categories, the stop-list and the batches (api/menu.ts): modifier groups, a product's
// channel prices, branch overrides of a variant, the list of branches the menu screens choose from (P2-22, docs/MENU.md).

// --- Branches (for the pickers of the menu screens) --------------------------------------------------------------------

export type MenuBranch = OkData<'branch.index'>[number];

// GET /owner/branches: the store's branches, oldest first; a branch-bound caller gets only their own.
export async function fetchMenuBranches(api: StaffApi): Promise<MenuBranch[]> {
  const body = unwrap(await api.client.GET('/v1/owner/branches'));
  return body.data ?? [];
}

// --- Modifier groups ("Sous tanlang", "Qo'shimchalar") -------------------------------------------------------------------

export type ModifierGroup = OkData<'modifierGroup.index'>[number];
export type ModifierOption = ModifierGroup['modifiers'][number];

// The body of POST /owner/modifier-groups and PUT /owner/modifier-groups/{id} (ModifierGroupRequest). `max_select` null:
// any number. `modifiers` present replaces the list (left out on an update: the modifiers stay).
export type ModifierGroupInput = JsonRequest<'modifierGroup.store'>;
// One modifier of it: with `id` it updates that modifier; without, it creates one; one left out of the list is deleted
// (order history keeps its snapshot). `price_delta`: so'm, per piece (once per line for items sold by weight).
export type ModifierOptionInput = NonNullable<ModifierGroupInput['modifiers']>[number];

// GET /owner/modifier-groups: every group with its modifiers (inactive ones too) and the ids of the products offering it.
export async function fetchModifierGroups(api: StaffApi): Promise<ModifierGroup[]> {
  const body = unwrap(await api.client.GET('/v1/owner/modifier-groups'));
  return body.data ?? [];
}

// POST /owner/modifier-groups (no id) or PUT /owner/modifier-groups/{id}
export async function saveModifierGroup(api: StaffApi, id: number | null | undefined, input: ModifierGroupInput): Promise<ModifierGroup> {
  const options = { body: input };
  if (id === null || id === undefined) return unwrap(await api.client.POST('/v1/owner/modifier-groups', options)).data;
  return unwrap(await api.client.PUT('/v1/owner/modifier-groups/{id}', { params: { path: { id } }, ...options })).data;
}

// DELETE /owner/modifier-groups/{id}: products stop offering it; past orders keep their snapshot.
export async function deleteModifierGroup(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/modifier-groups/{id}', { params: { path: { id } } }));
}

export type ProductModifierGroups = JsonRequest<'modifierGroup.syncProduct'>['groups'];

// PUT /owner/products/{id}/modifier-groups: REPLACES the groups a product offers. Per product `min_select`, `max_select`
// and `is_required` override the group's own (null: the group's; left out of a row: the product's saved rule stays);
// `sort_order` orders them. GET /owner/products returns them as `modifier_group_links`.
export async function syncProductModifierGroups(api: StaffApi, productId: number, groups: ProductModifierGroups): Promise<void> {
  unwrap(await api.client.PUT('/v1/owner/products/{id}/modifier-groups', { params: { path: { id: productId } }, body: { groups } }));
}

// --- Channel prices -------------------------------------------------------------------------------------------------------

export type ChannelPrices = OkData<'channelPrice.index'>;
export type ChannelPriceRow = ChannelPrices['prices'][number];
export type ChannelPricesInput = JsonRequest<'channelPrice.update'>['prices'];

// GET /owner/products/{id}/channel-prices: one row per channel and variant that differs from the default (the
// variant's own price, visible). `product_variant_id` null: the whole product (hidden only).
export async function fetchChannelPrices(api: StaffApi, productId: number): Promise<ChannelPrices> {
  return unwrap(await api.client.GET('/v1/owner/products/{id}/channel-prices', { params: { path: { id: productId } } })).data;
}

// PUT /owner/products/{id}/channel-prices: REPLACES every row of the product, so the rows of channels the screen does
// not show must be sent back as they were read.
export async function saveChannelPrices(api: StaffApi, productId: number, prices: ChannelPricesInput): Promise<ChannelPriceRow[]> {
  const body = unwrap(await api.client.PUT('/v1/owner/products/{id}/channel-prices', { params: { path: { id: productId } }, body: { prices } }));
  return body.data.prices;
}

// --- Branch overrides of a variant ----------------------------------------------------------------------------------------

export type BranchVariantProduct = OkData<'branchProductVariant.index'>[number];
export type BranchVariantInput = JsonRequest<'branchProductVariant.update'>;

// GET /owner/branch-variants: every product and variant with whether the branch sells it. An owner sends `branchId`;
// a manager always gets their own branch.
export async function fetchBranchVariants(api: StaffApi, branchId?: number): Promise<BranchVariantProduct[]> {
  const body = unwrap(await api.client.GET('/v1/owner/branch-variants', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

// POST /owner/branch-variants: switches a variant on or off in one branch (an older switch, shown as a stop-list entry too).
export async function saveBranchVariant(api: StaffApi, input: BranchVariantInput): Promise<void> {
  unwrap(await api.client.POST('/v1/owner/branch-variants', { body: input }));
}

// --- Product form ---------------------------------------------------------------------------------------------------------

export type ProductEditorInput = ProductInput & { sort_order?: number | null };

// The multipart body of the product editor: productFormData plus `sort_order` (0..100000), which that helper does not
// carry. Pass the result to saveProduct / useSaveProduct.
export function productEditorFormData(input: ProductEditorInput): FormData {
  const form = productFormData(input);
  if (input.sort_order !== undefined && input.sort_order !== null) form.append('sort_order', String(input.sort_order));
  return form;
}
