import { act, cleanup, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { staffKeys } from './keys';
import {
  menuAdminKeys,
  useBranchesBatches,
  useChannelPrices,
  useDeleteModifierGroup,
  useMenuBranches,
  useModifierGroups,
  useSaveBranchVariant,
  useSaveChannelPrices,
  useSaveModifierGroup,
  useSyncProductModifierGroups,
} from './menuAdmin';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

const group = { id: 3, uuid: 'g', name: 'Sous', name_i18n: null, min_select: 0, max_select: null, is_required: false, sort_order: 0, is_active: true, modifiers: [], product_ids: [] };
const batch = (branchId: number) => ({ id: branchId * 10, uuid: 'b', branch_id: branchId, product_id: 1, product_name: 'Osh', product_variant_id: null, variant_name: null, unit: null, label: null, quantity_produced: 30, quantity_left: 12, is_open: true, started_at: null, sold_out_at: null, closed_at: null });

describe('branches and modifier groups', () => {
  it('load under their own keys', async () => {
    mockFetch({ 'GET /api/v1/owner/branches': () => ok([{ id: 1, name: 'A' }]), 'GET /api/v1/owner/modifier-groups': () => ok([group]) });
    const queryClient = makeQueryClient();
    const wrapper = wrapperFor(queryClient);
    const branches = renderHook(() => useMenuBranches(), { wrapper });
    const groups = renderHook(() => useModifierGroups(), { wrapper });
    await waitFor(() => expect(branches.result.current.isSuccess && groups.result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(menuAdminKeys.branches)).toHaveLength(1);
    expect(groups.result.current.data).toEqual([group]);
  });

  it('saving a group refetches the groups', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/modifier-groups': () => ok([group]), 'POST /api/v1/owner/modifier-groups': () => ok(group) });
    const wrapper = wrapperFor(makeQueryClient());
    const list = renderHook(() => useModifierGroups(), { wrapper });
    const save = renderHook(() => useSaveModifierGroup(), { wrapper });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    await act(async () => {
      await save.result.current.mutateAsync({ input: { name: 'Sous' } });
    });
    await waitFor(() => expect(calls.filter((c) => c.method === 'GET')).toHaveLength(2));
  });

  it('deleting a group refetches the groups and the products', async () => {
    const { calls } = mockFetch({ 'DELETE /api/v1/owner/modifier-groups/3': () => ok(null) });
    const queryClient = makeQueryClient();
    queryClient.setQueryData(menuAdminKeys.modifierGroups, [group]);
    queryClient.setQueryData(staffKeys.products, []);
    const { result } = renderHook(() => useDeleteModifierGroup(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync(3);
    });
    expect(calls).toHaveLength(1);
    expect(queryClient.getQueryState(menuAdminKeys.modifierGroups)?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(staffKeys.products)?.isInvalidated).toBe(true);
  });

  it('attaching groups to a product invalidates the groups (their product_ids changed)', async () => {
    const { calls } = mockFetch({ 'PUT /api/v1/owner/products/7/modifier-groups': () => ok({ product_id: 7, modifier_groups: [] }) });
    const queryClient = makeQueryClient();
    queryClient.setQueryData(menuAdminKeys.modifierGroups, [group]);
    const { result } = renderHook(() => useSyncProductModifierGroups(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ productId: 7, groups: [{ modifier_group_id: 3 }] });
    });
    expect(await calls[0]!.json()).toEqual({ groups: [{ modifier_group_id: 3 }] });
    expect(queryClient.getQueryState(menuAdminKeys.modifierGroups)?.isInvalidated).toBe(true);
  });
});

describe('channel prices', () => {
  it('waits for a product id', async () => {
    const { fetchMock } = mockFetch({});
    const { result } = renderHook(() => useChannelPrices(undefined), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {});
    expect(result.current.fetchStatus).toBe('idle');
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('loads a product\'s rows and refetches them after saving', async () => {
    let stored = [{ channel: 'mobile', product_variant_id: 11, price: '27000.00', is_hidden: false }];
    const { calls } = mockFetch({
      'GET /api/v1/owner/products/7/channel-prices': () => ok({ product_id: 7, channels: [], prices: stored }),
      'PUT /api/v1/owner/products/7/channel-prices': () => {
        stored = [];
        return ok({ product_id: 7, prices: stored });
      },
    });
    const wrapper = wrapperFor(makeQueryClient());
    const prices = renderHook(() => useChannelPrices(7), { wrapper });
    const save = renderHook(() => useSaveChannelPrices(), { wrapper });
    await waitFor(() => expect(prices.result.current.data?.prices).toHaveLength(1));
    await act(async () => {
      await save.result.current.mutateAsync({ productId: 7, prices: [] });
    });
    await waitFor(() => expect(prices.result.current.data?.prices).toHaveLength(0));
    expect(calls.map((c) => c.method)).toEqual(['GET', 'PUT', 'GET']);
  });
});

describe('branch overrides', () => {
  it('saving one refetches the stop-list and the availability too', async () => {
    mockFetch({ 'POST /api/v1/owner/branch-variants': () => ok({ id: 1 }) });
    const queryClient = makeQueryClient();
    queryClient.setQueryData(menuAdminKeys.branchVariants(2), []);
    queryClient.setQueryData(staffKeys.stopList(2), []);
    queryClient.setQueryData(staffKeys.menuAvailability(5), []);
    const { result } = renderHook(() => useSaveBranchVariant(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ branch_id: 2, product_id: 7, product_variant_id: 11, is_active: false });
    });
    expect(queryClient.getQueryState(menuAdminKeys.branchVariants(2))?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(staffKeys.stopList(2))?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(staffKeys.menuAvailability(5))?.isInvalidated).toBe(true);
  });
});

describe('the batches of every branch', () => {
  it('reads each branch once and shares the cache with useBatches', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/batches': (request) => {
        const branchId = Number(new URL(request.url).searchParams.get('branch_id'));
        return ok({ branch_id: branchId, batches: [batch(branchId)] });
      },
    });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useBranchesBatches([1, 2]), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.every((entry) => !entry.isPending)).toBe(true));
    expect(result.current.map((entry) => [entry.branchId, entry.batches[0]!.quantity_left])).toEqual([[1, 12], [2, 12]]);
    expect(calls).toHaveLength(2);
    expect(queryClient.getQueryData(staffKeys.batches(2, false))).toHaveLength(1);
  });

  it('marks a branch whose request failed', async () => {
    mockFetch({
      'GET /api/v1/owner/batches': (request) =>
        new URL(request.url).searchParams.get('branch_id') === '2' ? new Response('{"message":"x"}', { status: 500, headers: { 'Content-Type': 'application/json' } }) : ok({ branch_id: 1, batches: [] }),
    });
    const { result } = renderHook(() => useBranchesBatches([1, 2]), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.every((entry) => !entry.isPending)).toBe(true));
    expect(result.current.map((entry) => entry.isError)).toEqual([false, true]);
  });
});
