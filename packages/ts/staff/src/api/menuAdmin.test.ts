import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockFetch, ok, testApi, json } from '../test/helpers';
import { StaffApiError } from './errors';
import {
  deleteModifierGroup,
  fetchBranchVariants,
  fetchChannelPrices,
  fetchMenuBranches,
  fetchModifierGroups,
  productEditorFormData,
  saveBranchVariant,
  saveChannelPrices,
  saveModifierGroup,
  syncProductModifierGroups,
} from './menuAdmin';

afterEach(() => vi.unstubAllGlobals());

const group = { id: 3, uuid: 'g', name: 'Sous', name_i18n: { ru: 'Соус' }, min_select: 0, max_select: 2, is_required: false, sort_order: 0, is_active: true, modifiers: [], product_ids: [7] };

describe('branches', () => {
  it('lists the branches of the store', async () => {
    mockFetch({ 'GET /api/v1/owner/branches': () => ok([{ id: 1, name: 'Chilonzor' }]) });
    expect((await fetchMenuBranches(testApi()))[0]!.name).toBe('Chilonzor');
  });
});

describe('modifier groups', () => {
  it('reads the groups', async () => {
    mockFetch({ 'GET /api/v1/owner/modifier-groups': () => ok([group]) });
    expect(await fetchModifierGroups(testApi())).toEqual([group]);
  });

  it('creates a group with POST and sends the body as JSON', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/modifier-groups': () => json({ success: true, message: 'ok', data: group }, 201) });
    const input = { name: 'Sous', name_i18n: { ru: 'Соус' }, max_select: 2, modifiers: [{ name: 'Achchiq', price_delta: 3000, max_quantity: 1 }] };
    expect((await saveModifierGroup(testApi(), null, input)).id).toBe(3);
    expect(await calls[0]!.json()).toEqual(input);
  });

  it('updates a group with PUT on its id', async () => {
    const { calls } = mockFetch({ 'PUT /api/v1/owner/modifier-groups/3': () => ok(group) });
    await saveModifierGroup(testApi(), 3, { name: 'Sous', modifiers: [{ id: 9, name: 'Achchiq' }] });
    expect(calls[0]!.method).toBe('PUT');
    expect(((await calls[0]!.json()) as { modifiers: Array<{ id: number }> }).modifiers[0]!.id).toBe(9);
  });

  it('throws the server\'s validation message', async () => {
    mockFetch({ 'POST /api/v1/owner/modifier-groups': () => json({ message: "Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak.", errors: { max_select: ["Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak."] } }, 422) });
    const error = await saveModifierGroup(testApi(), null, { name: 'x' }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(StaffApiError);
    expect((error as StaffApiError).fieldError('max_select')).toMatch(/Eng ko'p/);
  });

  it('deletes a group', async () => {
    const { calls } = mockFetch({ 'DELETE /api/v1/owner/modifier-groups/3': () => ok(null) });
    await deleteModifierGroup(testApi(), 3);
    expect(calls).toHaveLength(1);
  });

  it('replaces the groups of a product', async () => {
    const { calls } = mockFetch({ 'PUT /api/v1/owner/products/7/modifier-groups': () => ok({ product_id: 7, modifier_groups: [] }) });
    await syncProductModifierGroups(testApi(), 7, [{ modifier_group_id: 3, sort_order: 0 }]);
    expect(await calls[0]!.json()).toEqual({ groups: [{ modifier_group_id: 3, sort_order: 0 }] });
  });
});

describe('channel prices', () => {
  it('reads the rows of a product', async () => {
    mockFetch({ 'GET /api/v1/owner/products/7/channel-prices': () => ok({ product_id: 7, channels: ['telegram', 'mobile'], prices: [{ channel: 'mobile', product_variant_id: 11, price: '27000.00', is_hidden: false }] }) });
    const result = await fetchChannelPrices(testApi(), 7);
    expect(result.prices[0]!.price).toBe('27000.00');
  });

  it('replaces the rows and returns what the server stored', async () => {
    const { calls } = mockFetch({
      'PUT /api/v1/owner/products/7/channel-prices': () => ok({ product_id: 7, prices: [{ channel: 'mobile', product_variant_id: 11, price: '27000.00', is_hidden: false }] }),
    });
    const stored = await saveChannelPrices(testApi(), 7, [{ channel: 'mobile', product_variant_id: 11, price: 27000, is_hidden: false }]);
    expect(stored).toHaveLength(1);
    expect(await calls[0]!.json()).toEqual({ prices: [{ channel: 'mobile', product_variant_id: 11, price: 27000, is_hidden: false }] });
  });
});

describe('branch overrides', () => {
  it('asks for one branch', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/branch-variants': () => ok([]) });
    await fetchBranchVariants(testApi(), 2);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('2');
  });

  it('asks for the caller\'s own branch without an id', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/branch-variants': () => ok([]) });
    await fetchBranchVariants(testApi());
    expect(new URL(calls[0]!.url).searchParams.has('branch_id')).toBe(false);
  });

  it('switches a variant off in a branch', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/branch-variants': () => ok({ id: 1 }) });
    await saveBranchVariant(testApi(), { branch_id: 2, product_id: 7, product_variant_id: 11, is_active: false, hidden_reason: 'Tugadi' });
    expect(await calls[0]!.json()).toMatchObject({ branch_id: 2, is_active: false, hidden_reason: 'Tugadi' });
  });
});

describe('productEditorFormData', () => {
  it('adds the sort order to the product form', () => {
    const form = productEditorFormData({ name: 'Osh', category_id: 4, variants: [{ name: '1 porsiya', price: 35000 }], sort_order: 12, batch_tracked: true });
    expect(form.get('name')).toBe('Osh');
    expect(form.get('sort_order')).toBe('12');
    expect(form.get('batch_tracked')).toBe('1');
    expect(JSON.parse(String(form.get('variants')))).toEqual([{ name: '1 porsiya', price: 35000 }]);
  });

  it('leaves the sort order out when there is none', () => {
    expect(productEditorFormData({ name: 'Osh', category_id: 4, variants: [] }).has('sort_order')).toBe(false);
    expect(productEditorFormData({ name: 'Osh', category_id: 4, variants: [], sort_order: null }).has('sort_order')).toBe(false);
  });
});
