import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createPackagingRule,
  deletePackagingRule,
  fetchBusinessDays,
  fetchPackagingRules,
  fetchPickupSettings,
  savePickupSettings,
  updatePackagingRule,
  type BusinessDayQuery,
  type BusinessDayReport,
  type PackagingRule,
  type PackagingRuleInput,
  type PackagingRulePatch,
  type PickupSettings,
  type PickupSettingsPatch,
} from '../api/takeaway';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// P6-34: packaging rules, the pickup-time rules and the business-day report of a branch
export const takeawayKeys = {
  rules: (branchId: number) => ['owner', 'packaging-rules', branchId] as const,
  pickup: (branchId: number) => ['owner', 'pickup-settings', branchId] as const,
  businessDays: (query: BusinessDayQuery) => ['owner', 'business-days', query] as const,
};

export function usePackagingRules(branchId: number | null, options: QueryExtras<PackagingRule[]> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: takeawayKeys.rules(branchId ?? 0),
    queryFn: () => fetchPackagingRules(api, branchId as number),
  });
}

export type PackagingRuleChange =
  | { action: 'create'; branchId: number; input: PackagingRuleInput }
  | { action: 'update'; branchId: number; ruleId: number; patch: PackagingRulePatch }
  | { action: 'delete'; branchId: number; ruleId: number };

// One mutation for the three changes; the branch's list is fetched again after each
export function useChangePackagingRule(options: MutationExtras<PackagingRule | null, PackagingRuleChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: async (change: PackagingRuleChange): Promise<PackagingRule | null> => {
      if (change.action === 'create') return createPackagingRule(api, change.branchId, change.input);
      if (change.action === 'update') return updatePackagingRule(api, change.branchId, change.ruleId, change.patch);
      await deletePackagingRule(api, change.branchId, change.ruleId);
      return null;
    },
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: takeawayKeys.rules(args[1].branchId) });
      return options.onSuccess?.(...args);
    },
  });
}

export function usePickupSettings(branchId: number | null, options: QueryExtras<PickupSettings> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: takeawayKeys.pickup(branchId ?? 0),
    queryFn: () => fetchPickupSettings(api, branchId as number),
  });
}

export interface PickupSettingsChange {
  branchId: number;
  patch: PickupSettingsPatch;
}

export function useSavePickupSettings(options: MutationExtras<PickupSettings, PickupSettingsChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: PickupSettingsChange) => savePickupSettings(api, branchId, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(takeawayKeys.pickup(args[0].branch_id), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}

export function useBusinessDays(query: BusinessDayQuery | null, options: QueryExtras<BusinessDayReport> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: query !== null && options.enabled !== false,
    queryKey: takeawayKeys.businessDays(query ?? { branchId: 0 }),
    queryFn: () => fetchBusinessDays(api, query as BusinessDayQuery),
  });
}
