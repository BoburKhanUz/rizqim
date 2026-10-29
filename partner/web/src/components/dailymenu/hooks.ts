import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  cancelBatchPlan,
  copyDailyMenu,
  createBatchPlan,
  createMealSet,
  deleteMealSet,
  fetchBatchPlans,
  fetchDailyMenu,
  fetchMealSets,
  fetchWriteOffs,
  fetchYield,
  saveDailyMenu,
  saveRotation,
  updateMealSet,
  writeOffBatch,
  type BatchPlanInput,
  type MealSetInput,
  type PeriodQuery,
  type WriteOffInput,
} from './api';

// Keys sit under ['owner', ...] like the products', so a sign-out clears them all
export const dailyKeys = {
  menu: (branchId: number, date: string) => ['owner', 'daily-menu', branchId, date] as const,
  menus: ['owner', 'daily-menu'] as const,
  sets: ['owner', 'meal-sets'] as const,
  plans: (branchId: number) => ['owner', 'batch-plans', branchId] as const,
  writeOffs: (query: PeriodQuery) => ['owner', 'batch-writeoffs', query.branchId, query.from, query.to] as const,
  yield: (query: PeriodQuery) => ['owner', 'batch-yield', query.branchId, query.from, query.to] as const,
};

interface Done {
  onSuccess?: () => void;
}

export function useDailyMenu(branchId: number | null, date: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: dailyKeys.menu(branchId ?? 0, date), queryFn: () => fetchDailyMenu(api, branchId as number, date === '' ? undefined : date), enabled: branchId !== null, retry: false });
}

export function useSaveDailyMenu({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { branchId: number; date: string; productIds: number[]; rotation?: number[] }) =>
      // The rotation first (which dishes the menu rules), then the day's dishes
      (input.rotation ? saveRotation(api, input.rotation) : Promise.resolve(null)).then(() => saveDailyMenu(api, input)),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: dailyKeys.menus });
      onSuccess?.();
    },
  });
}

export function useCopyDailyMenu({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { branchId: number; date: string; fromDate?: string }) => copyDailyMenu(api, input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: dailyKeys.menus });
      onSuccess?.();
    },
  });
}

export function useMealSets() {
  const api = useStaffApi();
  return useQuery({ queryKey: dailyKeys.sets, queryFn: () => fetchMealSets(api), retry: false });
}

export function useSaveMealSet({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number | null } & MealSetInput) =>
      input.id === null ? createMealSet(api, { productId: input.productId as number, parts: input.parts ?? [] }) : updateMealSet(api, input.id, { parts: input.parts, isActive: input.isActive }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: dailyKeys.sets });
      void queryClient.invalidateQueries({ queryKey: dailyKeys.menus });
      onSuccess?.();
    },
  });
}

export function useDeleteMealSet({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteMealSet(api, id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: dailyKeys.sets });
      onSuccess?.();
    },
  });
}

export function useBatchPlans(branchId: number | null, options: { refetchInterval?: number } = {}) {
  const api = useStaffApi();
  return useQuery({ queryKey: dailyKeys.plans(branchId ?? 0), queryFn: () => fetchBatchPlans(api, branchId as number), enabled: branchId !== null, retry: false, ...options });
}

export function useCreateBatchPlan({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: BatchPlanInput) => createBatchPlan(api, input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['owner', 'batch-plans'] });
      onSuccess?.();
    },
  });
}

export function useCancelBatchPlan() {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => cancelBatchPlan(api, id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['owner', 'batch-plans'] }),
  });
}

export function useWriteOffBatch({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number; key: string } & WriteOffInput) => writeOffBatch(api, input.id, input, input.key),
    onSuccess: () => {
      // The batch list and the reports read the new count
      void queryClient.invalidateQueries({ queryKey: ['owner', 'batches'] });
      void queryClient.invalidateQueries({ queryKey: ['owner', 'batch-writeoffs'] });
      void queryClient.invalidateQueries({ queryKey: ['owner', 'batch-yield'] });
      onSuccess?.();
    },
  });
}

export function useWriteOffs(query: PeriodQuery | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: query ? dailyKeys.writeOffs(query) : ['owner', 'batch-writeoffs', 'none'], queryFn: () => fetchWriteOffs(api, query as PeriodQuery), enabled: query !== null, retry: false });
}

export function useYieldReport(query: PeriodQuery | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: query ? dailyKeys.yield(query) : ['owner', 'batch-yield', 'none'], queryFn: () => fetchYield(api, query as PeriodQuery), enabled: query !== null, retry: false });
}
