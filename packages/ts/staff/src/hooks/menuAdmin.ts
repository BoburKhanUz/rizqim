import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchBatches, type Batch } from '../api/menu';
import {
  deleteModifierGroup,
  fetchBranchVariants,
  fetchChannelPrices,
  fetchMenuBranches,
  fetchModifierGroups,
  saveBranchVariant,
  saveChannelPrices,
  saveModifierGroup,
  syncProductModifierGroups,
  type BranchVariantInput,
  type BranchVariantProduct,
  type ChannelPriceRow,
  type ChannelPrices,
  type ChannelPricesInput,
  type MenuBranch,
  type ModifierGroup,
  type ModifierGroupInput,
  type ProductModifierGroups,
} from '../api/menuAdmin';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// The owner's menu beyond products and the stop-list (hooks/menu.ts): modifier groups, channel prices, branch overrides.
// Their keys live here (keys.ts is shared) and sit under ['owner', ...] like the products', so a sign-out clears them all.
export const menuAdminKeys = {
  branches: ['owner', 'menu-branches'] as const,
  modifierGroups: ['owner', 'modifier-groups'] as const,
  channelPrices: (productId: number) => ['owner', 'channel-prices', productId] as const,
  branchVariants: (branchId?: number) => ['owner', 'branch-variants', branchId ?? null] as const,
  allBranchVariants: ['owner', 'branch-variants'] as const,
};

// The branches a menu screen picks from.
export function useMenuBranches(options: QueryExtras<MenuBranch[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: menuAdminKeys.branches, queryFn: () => fetchMenuBranches(api) });
}

// --- Modifier groups ---------------------------------------------------------------------------------------------------

export function useModifierGroups(options: QueryExtras<ModifierGroup[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: menuAdminKeys.modifierGroups, queryFn: () => fetchModifierGroups(api) });
}

// Create (id null) or update a group with its modifiers.
export function useSaveModifierGroup(options: MutationExtras<ModifierGroup, { id?: number | null; input: ModifierGroupInput }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ id, input }: { id?: number | null; input: ModifierGroupInput }) => saveModifierGroup(api, id, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.modifierGroups });
      return options.onSuccess?.(...args);
    },
  });
}

export function useDeleteModifierGroup(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (id: number) => deleteModifierGroup(api, id),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.modifierGroups });
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      return options.onSuccess?.(...args);
    },
  });
}

// Replaces the groups a product offers (the groups' `product_ids` change with it).
export function useSyncProductModifierGroups(options: MutationExtras<void, { productId: number; groups: ProductModifierGroups }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ productId, groups }: { productId: number; groups: ProductModifierGroups }) => syncProductModifierGroups(api, productId, groups),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.modifierGroups });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Channel prices ----------------------------------------------------------------------------------------------------

export function useChannelPrices(productId: number | undefined, options: QueryExtras<ChannelPrices> = {}) {
  const api = useStaffApi();
  return useQuery({
    enabled: productId !== undefined,
    ...options,
    queryKey: menuAdminKeys.channelPrices(productId ?? 0),
    queryFn: () => fetchChannelPrices(api, productId as number),
  });
}

// Replaces every channel-price row of a product.
export function useSaveChannelPrices(options: MutationExtras<ChannelPriceRow[], { productId: number; prices: ChannelPricesInput }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ productId, prices }: { productId: number; prices: ChannelPricesInput }) => saveChannelPrices(api, productId, prices),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.channelPrices(args[1].productId) });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Branch overrides --------------------------------------------------------------------------------------------------

export function useBranchVariants(branchId: number | undefined, options: QueryExtras<BranchVariantProduct[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: menuAdminKeys.branchVariants(branchId), queryFn: () => fetchBranchVariants(api, branchId) });
}

// Switches a variant on or off in a branch; the stop-list shows it too, so that is refetched with it.
export function useSaveBranchVariant(options: MutationExtras<void, BranchVariantInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: BranchVariantInput) => saveBranchVariant(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.allBranchVariants });
      void queryClient.invalidateQueries({ queryKey: staffKeys.allStopList });
      void queryClient.invalidateQueries({ queryKey: ['menu-availability'] });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Batches of every branch ---------------------------------------------------------------------------------------------

export interface BranchBatches {
  branchId: number;
  batches: Batch[];
  isPending: boolean;
  isError: boolean;
}

// The open batches of several branches at once (the "qozon qoldig'i" overview). Each branch is its own cache entry,
// the same one useBatches({ branchId }) reads, so opening a branch afterwards needs no second request.
export function useBranchesBatches(branchIds: readonly number[], options: { refetchInterval?: number } = {}): BranchBatches[] {
  const api = useStaffApi();
  return useQueries({
    queries: branchIds.map((branchId) => ({
      queryKey: staffKeys.batches(branchId, false),
      queryFn: () => fetchBatches(api, { branchId }),
      ...options,
    })),
    combine: (results) =>
      results.map((result, index) => ({
        branchId: branchIds[index] as number,
        batches: result.data ?? [],
        isPending: result.isPending,
        isError: result.isError,
      })),
  });
}
