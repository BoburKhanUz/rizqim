import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  applyFormatTemplate,
  fetchPosFormat,
  savePosFormat,
  type FormatTemplateInput,
  type FormatTemplateResult,
  type PosFormat,
  type PosFormatPatch,
} from '../api/posFormat';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';
import { posSetupKeys } from './posSetup';

// The branch's format profile (P6-11)
export const posFormatKeys = {
  format: (branchId: number) => ['owner', 'pos-format', branchId] as const,
  all: ['owner', 'pos-format'] as const,
};

export function usePosFormat(branchId: number | null, options: QueryExtras<PosFormat> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: posFormatKeys.format(branchId ?? 0),
    queryFn: () => fetchPosFormat(api, branchId as number),
  });
}

// The answer is the branch's whole format: it replaces the cached one. A profile sets the till's mode, so the POS settings
// are read again.
export function useSavePosFormat(options: MutationExtras<PosFormat, PosFormatPatch> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (patch: PosFormatPatch) => savePosFormat(api, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(posFormatKeys.format(args[0].branch_id), args[0]);
      void queryClient.invalidateQueries({ queryKey: posSetupKeys.allBranchSettings });
      return options.onSuccess?.(...args);
    },
  });
}

// Applying a template adds stations (and dishes): the stations, routes and the menu are read again
export function useApplyFormatTemplate(options: MutationExtras<FormatTemplateResult, FormatTemplateInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: FormatTemplateInput) => applyFormatTemplate(api, input),
    onSuccess: (...args) => {
      queryClient.setQueryData(posFormatKeys.format(args[0].format.branch_id), args[0].format);
      for (const queryKey of [posSetupKeys.allStations, posSetupKeys.allRoutes, staffKeys.products, staffKeys.categories]) void queryClient.invalidateQueries({ queryKey });
      return options.onSuccess?.(...args);
    },
  });
}
