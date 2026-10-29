import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  closeDistribution,
  computeDistribution,
  correctTip,
  fetchDistributions,
  fetchServiceChargeRules,
  fetchTips,
  fetchTipSettings,
  fetchTipsReport,
  saveServiceChargeRules,
  saveTipSettings,
  type CorrectionInput,
  type Range,
  type ServiceChargeInput,
  type TipSettingsInput,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const tipKeys = {
  all: ['owner', 'tips'] as const,
  settings: (branchId: number) => ['owner', 'tips', 'settings', branchId] as const,
  report: (range: Range) => ['owner', 'tips', 'report', range.branchId, range.from, range.to] as const,
  entries: (range: Range, staffId: number | null) => ['owner', 'tips', 'entries', range.branchId, range.from, range.to, staffId] as const,
  distributions: (range: Range) => ['owner', 'tips', 'distributions', range.branchId, range.from, range.to] as const,
  serviceCharge: (branchId: number) => ['owner', 'tips', 'service-charge', branchId] as const,
};

interface Done<T = unknown> {
  onSuccess?: (result: T) => void;
}

function useInvalidate() {
  const queryClient = useQueryClient();
  return () => void queryClient.invalidateQueries({ queryKey: tipKeys.all });
}

export function useTipSettings(branchId: number) {
  const api = useStaffApi();
  return useQuery({ queryKey: tipKeys.settings(branchId), queryFn: () => fetchTipSettings(api, branchId), retry: false });
}

export function useSaveTipSettings(branchId: number, { onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: TipSettingsInput) => saveTipSettings(api, branchId, input),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useTipsReport(range: Range | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: range ? tipKeys.report(range) : ['owner', 'tips', 'report', 'none'],
    queryFn: () => fetchTipsReport(api, range as Range),
    enabled: range !== null,
    retry: false,
  });
}

export function useTips(range: Range | null, staffId: number | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: range ? tipKeys.entries(range, staffId) : ['owner', 'tips', 'entries', 'none'],
    queryFn: () => fetchTips(api, { ...(range as Range), staffId }),
    enabled: range !== null,
    retry: false,
  });
}

export function useCorrectTip({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: CorrectionInput) => correctTip(api, input),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useDistributions(range: Range | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: range ? tipKeys.distributions(range) : ['owner', 'tips', 'distributions', 'none'],
    queryFn: () => fetchDistributions(api, range as Range),
    enabled: range !== null,
    retry: false,
  });
}

export function useComputeDistribution({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: { branchId: number; businessDate: string; shiftUuid?: string | null }) => computeDistribution(api, input),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useCloseDistribution({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (uuid: string) => closeDistribution(api, uuid),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useServiceChargeRules(branchId: number) {
  const api = useStaffApi();
  return useQuery({ queryKey: tipKeys.serviceCharge(branchId), queryFn: () => fetchServiceChargeRules(api, branchId), retry: false });
}

export function useSaveServiceChargeRules(branchId: number, { onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: ServiceChargeInput) => saveServiceChargeRules(api, branchId, input),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}
