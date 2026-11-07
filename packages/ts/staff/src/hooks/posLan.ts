import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchBranchLan, rotateBranchLanKey, saveBranchLan, type BranchLan, type BranchLanPatch } from '../api/posLan';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// P6 local bridge of a branch: the hub, the port, the key version.
export const posLanKeys = {
  branch: (branchId: number) => ['owner', 'pos-lan', branchId] as const,
  all: ['owner', 'pos-lan'] as const,
};

export function useBranchLan(branchId: number | null, options: QueryExtras<BranchLan> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: posLanKeys.branch(branchId ?? 0),
    queryFn: () => fetchBranchLan(api, branchId as number),
  });
}

// Both answer the branch's whole view: it replaces the cached one
function useBranchLanMutation<TVariables extends { branchId: number }>(mutationFn: (variables: TVariables) => Promise<BranchLan>, options: MutationExtras<BranchLan, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      queryClient.setQueryData(posLanKeys.branch(args[1].branchId), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}

export function useSaveBranchLan(options: MutationExtras<BranchLan, { branchId: number; patch: BranchLanPatch }> = {}) {
  const api = useStaffApi();
  return useBranchLanMutation(({ branchId, patch }: { branchId: number; patch: BranchLanPatch }) => saveBranchLan(api, branchId, patch), options);
}

export function useRotateBranchLanKey(options: MutationExtras<BranchLan, { branchId: number }> = {}) {
  const api = useStaffApi();
  return useBranchLanMutation(({ branchId }: { branchId: number }) => rotateBranchLanKey(api, branchId), options);
}
