import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  assignBrand,
  createBrand,
  createIngredient,
  deleteBrand,
  deleteIngredient,
  downloadChannelRevenue,
  fetchBrands,
  fetchChannelRevenue,
  fetchCommissions,
  fetchIngredients,
  linkIngredientCategories,
  reopenIngredient,
  saveBrandBranches,
  saveBrandChannels,
  saveCommissions,
  saveIngredientLinks,
  stopIngredient,
  updateBrand,
  updateIngredient,
  type BrandInput,
  type Channel,
  type RevenueFilter,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all. A brand or link change
// also refreshes the menu (products carry their brand).
export const darkKitchenKeys = {
  brands: ['owner', 'brands'] as const,
  ingredients: (branchId: number | null) => ['owner', 'ingredients', branchId] as const,
  ingredientsAll: ['owner', 'ingredients'] as const,
  revenue: (filter: RevenueFilter) => ['owner', 'channel-revenue', filter] as const,
  commissions: ['owner', 'channel-commissions'] as const,
};

export function useBrands() {
  const api = useStaffApi();
  return useQuery({ queryKey: darkKitchenKeys.brands, queryFn: () => fetchBrands(api), retry: false });
}

function useOwnerMutation<V, R>(run: (variables: V) => Promise<R>, keys: readonly (readonly unknown[])[], onSuccess?: (result: R) => void) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: run,
    onSuccess: (result) => {
      for (const key of keys) void queryClient.invalidateQueries({ queryKey: key });
      onSuccess?.(result);
    },
  });
}

const PRODUCTS = ['owner', 'products'] as const;
const STOP_LIST = ['owner', 'stop-list'] as const;

export function useSaveBrand(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(({ id, input }: { id: number | null; input: BrandInput }) => (id === null ? createBrand(api, input) : updateBrand(api, id, input)), [darkKitchenKeys.brands], onSuccess);
}

export function useToggleBrand() {
  const api = useStaffApi();
  return useOwnerMutation(({ id, isActive }: { id: number; isActive: boolean }) => updateBrand(api, id, { is_active: isActive }), [darkKitchenKeys.brands, PRODUCTS]);
}

export function useDeleteBrand(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation((id: number) => deleteBrand(api, id), [darkKitchenKeys.brands, PRODUCTS], onSuccess);
}

export function useSaveBrandChannels(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(
    ({ id, channels }: { id: number; channels: { channel: Channel; is_hidden: boolean; price_percent: string | null }[] }) => saveBrandChannels(api, id, channels),
    [darkKitchenKeys.brands],
    onSuccess,
  );
}

export function useSaveBrandBranches(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(({ id, branches }: { id: number; branches: { branch_id: number; is_active: boolean }[] }) => saveBrandBranches(api, id, branches), [darkKitchenKeys.brands], onSuccess);
}

export function useAssignBrand(onSuccess?: (changed: number) => void) {
  const api = useStaffApi();
  return useOwnerMutation(({ brandId, productIds }: { brandId: number | null; productIds: number[] }) => assignBrand(api, brandId, productIds), [darkKitchenKeys.brands, PRODUCTS], (result) =>
    onSuccess?.(result.changed),
  );
}

export function useIngredients(branchId: number | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: darkKitchenKeys.ingredients(branchId), queryFn: () => fetchIngredients(api, branchId), retry: false, refetchInterval: 30_000 });
}

export function useSaveIngredient(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(
    ({ id, name, unit }: { id: number | null; name: string; unit: string | null }) => (id === null ? createIngredient(api, { name, unit }) : updateIngredient(api, id, { name, unit })),
    [darkKitchenKeys.ingredientsAll],
    onSuccess,
  );
}

export function useDeleteIngredient(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation((id: number) => deleteIngredient(api, id), [darkKitchenKeys.ingredientsAll], onSuccess);
}

export function useSaveIngredientLinks(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(({ id, links }: { id: number; links: { product_id: number; is_critical: boolean }[] }) => saveIngredientLinks(api, id, links), [darkKitchenKeys.ingredientsAll], onSuccess);
}

export function useLinkCategories(onSuccess?: (added: number) => void) {
  const api = useStaffApi();
  return useOwnerMutation(
    ({ id, categoryIds, isCritical }: { id: number; categoryIds: number[]; isCritical: boolean }) => linkIngredientCategories(api, id, categoryIds, isCritical),
    [darkKitchenKeys.ingredientsAll],
    (result) => onSuccess?.(result.added),
  );
}

export function useStopIngredient(onSuccess?: (closed: number) => void) {
  const api = useStaffApi();
  return useOwnerMutation(
    ({ id, branchId, reason, until }: { id: number; branchId: number; reason: string | null; until: string | null }) => stopIngredient(api, id, branchId, { reason, until }),
    [darkKitchenKeys.ingredientsAll, STOP_LIST],
    (result) => onSuccess?.(result.closed_product_ids.length),
  );
}

export function useReopenIngredient(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(({ id, branchId }: { id: number; branchId: number }) => reopenIngredient(api, id, branchId), [darkKitchenKeys.ingredientsAll, STOP_LIST], onSuccess);
}

export function useChannelRevenue(filter: RevenueFilter, enabled: boolean) {
  const api = useStaffApi();
  return useQuery({ queryKey: darkKitchenKeys.revenue(filter), queryFn: () => fetchChannelRevenue(api, filter), enabled, retry: false });
}

export function useCommissions(enabled: boolean) {
  const api = useStaffApi();
  return useQuery({ queryKey: darkKitchenKeys.commissions, queryFn: () => fetchCommissions(api), enabled, retry: false });
}

export function useSaveCommissions(onSuccess?: () => void) {
  const api = useStaffApi();
  return useOwnerMutation(
    (rates: { channel: Channel; percent: string; note: string | null }[]) => saveCommissions(api, rates),
    [darkKitchenKeys.commissions, ['owner', 'channel-revenue']],
    onSuccess,
  );
}

export function useDownloadChannelRevenue({ onSuccess, onError }: { onSuccess?: (file: { blob: Blob; filename: string }) => void; onError?: (error: unknown) => void } = {}) {
  const api = useStaffApi();
  return useMutation({ mutationFn: (filter: RevenueFilter) => downloadChannelRevenue(api, filter), onSuccess, onError });
}
