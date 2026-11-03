import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchLabelSettings, saveLabelSettings, type LabelSettings, type LabelSettingsPatch } from '../api/labels';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The sticker and label settings of a branch (P6-32)
export const labelKeys = {
  settings: (branchId: number) => ['owner', 'label-settings', branchId] as const,
};

export function useLabelSettings(branchId: number | null, options: QueryExtras<LabelSettings> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: labelKeys.settings(branchId ?? 0),
    queryFn: () => fetchLabelSettings(api, branchId as number),
  });
}

export interface LabelSettingsChange {
  branchId: number;
  patch: LabelSettingsPatch;
}

// The answer is the branch's whole settings: it replaces the cached ones
export function useSaveLabelSettings(options: MutationExtras<LabelSettings, LabelSettingsChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: LabelSettingsChange) => saveLabelSettings(api, branchId, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(labelKeys.settings(args[0].branch_id), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}
