import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { posSetupKeys, useStaffApi } from '@rizqim/staff';
import { fetchKdsTargets, fetchPrepTimes, saveProductTargets, type PrepQuery, type ProductTargetInput } from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const kdsKeys = {
  targets: (branchId: number) => ['owner', 'kds', 'targets', branchId] as const,
  report: (query: PrepQuery) => ['owner', 'kds', 'report', query.branchId, query.from, query.to, query.stationId] as const,
};

export function useKdsTargets(branchId: number, enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: kdsKeys.targets(branchId), queryFn: () => fetchKdsTargets(api, branchId), enabled, retry: false });
}

export function useSaveProductTargets(branchId: number) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (items: ProductTargetInput[]) => saveProductTargets(api, branchId, items),
    onSuccess: (page) => {
      queryClient.setQueryData(kdsKeys.targets(branchId), page);
      void queryClient.invalidateQueries({ queryKey: ['owner', 'kds', 'report'] });
    },
  });
}

// A station's own target changed (PATCH /owner/kitchen-stations/{id}): the targets page reads it again
export function useRefreshKdsTargets() {
  const queryClient = useQueryClient();
  return () => {
    void queryClient.invalidateQueries({ queryKey: ['owner', 'kds'] });
    void queryClient.invalidateQueries({ queryKey: posSetupKeys.allStations });
  };
}

export function usePrepTimes(query: PrepQuery | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: query ? kdsKeys.report(query) : ['owner', 'kds', 'report', 'none'], queryFn: () => fetchPrepTimes(api, query as PrepQuery), enabled: query !== null, retry: false });
}
