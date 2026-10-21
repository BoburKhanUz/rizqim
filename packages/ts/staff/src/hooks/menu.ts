import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  addStopListEntry,
  adjustBatch,
  closeBatch,
  deleteProduct,
  fetchBatches,
  fetchMenuAvailability,
  fetchOwnerCategories,
  fetchOwnerProducts,
  fetchStopList,
  openBatch,
  removeStopListEntry,
  saveProduct,
  toggleProductActive,
  removeProductImage,
  type Batch,
  type BatchAdjustment,
  type BatchInput,
  type BranchAvailability,
  type StopListEntry,
  type StopListInput,
} from '../api/menu';
import type { Category, Product } from '../types';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// The owner's menu: products, categories, the stop-list and the batches ("qozon").

export function useOwnerProducts(options: QueryExtras<Product[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.products, queryFn: () => fetchOwnerProducts(api) });
}

export function useOwnerCategories(options: QueryExtras<Category[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.categories, queryFn: () => fetchOwnerCategories(api) });
}

// Create (id null) or update a product from a FormData (productFormData).
export function useSaveProduct(options: MutationExtras<Product | null, { id?: number | string | null; form: FormData }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ id, form }: { id?: number | string | null; form: FormData }) => saveProduct(api, id, form),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      return options.onSuccess?.(...args);
    },
  });
}

export function useToggleProductActive(options: MutationExtras<void, number | string> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (id: number | string) => toggleProductActive(api, id),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      return options.onSuccess?.(...args);
    },
  });
}

// P2-30: DELETE /owner/products/{id}/image
export function useRemoveProductImage(options: MutationExtras<Product, number | string> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (id: number | string) => removeProductImage(api, id),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      return options.onSuccess?.(...args);
    },
  });
}

export function useDeleteProduct(options: MutationExtras<void, number | string> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (id: number | string) => deleteProduct(api, id),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Stop-list -------------------------------------------------------------------------------------------------------

export function useStopList(branchId?: number, options: QueryExtras<StopListEntry[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.stopList(branchId), queryFn: () => fetchStopList(api, branchId) });
}

// The stop-list changes what the menu can sell: the availability a store's menu shows is refetched with it.
function useStopListMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<unknown>, options: MutationExtras<unknown, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.allStopList });
      void queryClient.invalidateQueries({ queryKey: ['menu-availability'] });
      return options.onSuccess?.(...args);
    },
  });
}

export function useAddStopListEntry(options: MutationExtras<unknown, StopListInput> = {}) {
  const api = useStaffApi();
  return useStopListMutation((input: StopListInput) => addStopListEntry(api, input), options);
}

export function useRemoveStopListEntry(options: MutationExtras<unknown, number> = {}) {
  const api = useStaffApi();
  return useStopListMutation((id: number) => removeStopListEntry(api, id), options);
}

// --- Batches ---------------------------------------------------------------------------------------------------------

export function useBatches(options: { branchId?: number; includeClosed?: boolean } & QueryExtras<Batch[]> = {}) {
  const api = useStaffApi();
  const { branchId, includeClosed, ...queryOptions } = options;
  return useQuery({
    ...queryOptions,
    queryKey: staffKeys.batches(branchId, includeClosed ?? false),
    queryFn: () => fetchBatches(api, { branchId, includeClosed }),
  });
}

// A batch opening, a top-up or a close changes what the menu can sell, like the stop-list does.
function useBatchMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<Batch>, options: MutationExtras<Batch, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.allBatches });
      void queryClient.invalidateQueries({ queryKey: ['menu-availability'] });
      return options.onSuccess?.(...args);
    },
  });
}

export function useOpenBatch(options: MutationExtras<Batch, BatchInput> = {}) {
  const api = useStaffApi();
  return useBatchMutation((input: BatchInput) => openBatch(api, input), options);
}

export function useAdjustBatch(options: MutationExtras<Batch, { id: number; adjustment: BatchAdjustment }> = {}) {
  const api = useStaffApi();
  return useBatchMutation(({ id, adjustment }: { id: number; adjustment: BatchAdjustment }) => adjustBatch(api, id, adjustment), options);
}

export function useCloseBatch(options: MutationExtras<Batch, number> = {}) {
  const api = useStaffApi();
  return useBatchMutation((id: number) => closeBatch(api, id), options);
}

// What a store's menu cannot offer right now, per branch.
export function useMenuAvailability(storeId: number | string | undefined, options: QueryExtras<BranchAvailability[]> = {}) {
  const api = useStaffApi();
  const enabled = storeId !== undefined && storeId !== '';
  return useQuery({
    enabled,
    ...options,
    queryKey: staffKeys.menuAvailability(storeId ?? ''),
    queryFn: () => fetchMenuAvailability(api, storeId as number | string),
  });
}
