import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchQrPaymentSettings, saveQrPaymentSettings, type QrPaymentSettingsPatch, type QrPaymentSettingsView } from '../api/qrPayments';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The QR payments of a branch at the till (P6-41/P6-42)
export const qrPaymentKeys = {
  settings: (branchId: number) => ['owner', 'qr-payments', branchId] as const,
};

export function useQrPaymentSettings(branchId: number | null, options: QueryExtras<QrPaymentSettingsView> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: qrPaymentKeys.settings(branchId ?? 0),
    queryFn: () => fetchQrPaymentSettings(api, branchId as number),
  });
}

export interface QrPaymentSettingsChange {
  branchId: number;
  patch: QrPaymentSettingsPatch;
}

// The answer is the branch's whole view: it replaces the cached one
export function useSaveQrPaymentSettings(options: MutationExtras<QrPaymentSettingsView, QrPaymentSettingsChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: QrPaymentSettingsChange) => saveQrPaymentSettings(api, branchId, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(qrPaymentKeys.settings(args[0].settings.branch_id), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}
