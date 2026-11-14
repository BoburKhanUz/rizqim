import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  downloadProductionSheet,
  downloadShoppingList,
  fetchContract,
  fetchOpsSettings,
  fetchOshCalculation,
  fetchProductionSheet,
  fetchSettlement,
  fetchShoppingList,
  numberContract,
  saveGuestsActual,
  saveOpsSettings,
  type ContractDocument,
  type DateRange,
  type OpsSettings,
  type OpsSettingsInput,
  type Settlement,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const banquetOpsKeys = {
  all: ['owner', 'banquetops'] as const,
  sheet: (uuid: string) => ['owner', 'banquetops', 'sheet', uuid] as const,
  shopping: (range: DateRange) => ['owner', 'banquetops', 'shopping', range] as const,
  osh: (persons: number) => ['owner', 'banquetops', 'osh', persons] as const,
  settings: ['owner', 'banquetops', 'settings'] as const,
  contract: (uuid: string) => ['owner', 'banquetops', 'contract', uuid] as const,
  settlement: (uuid: string) => ['owner', 'banquetops', 'settlement', uuid] as const,
};

export function useProductionSheet(uuid: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.sheet(uuid), queryFn: () => fetchProductionSheet(api, uuid), retry: false });
}

export function useShoppingList(range: DateRange, enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.shopping(range), queryFn: () => fetchShoppingList(api, range), enabled, placeholderData: keepPreviousData, retry: false });
}

export function useOshCalculation(persons: number, enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.osh(persons), queryFn: () => fetchOshCalculation(api, persons), enabled: enabled && persons > 0, placeholderData: keepPreviousData, retry: false });
}

export function useOpsSettings() {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.settings, queryFn: () => fetchOpsSettings(api), retry: false });
}

export function useSaveOpsSettings({ onSuccess }: { onSuccess?: (settings: OpsSettings) => void } = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ input, key }: { input: OpsSettingsInput; key: string }) => saveOpsSettings(api, input, key),
    onSuccess: (settings) => {
      queryClient.setQueryData(banquetOpsKeys.settings, settings);
      void queryClient.invalidateQueries({ queryKey: banquetOpsKeys.all });
      onSuccess?.(settings);
    },
  });
}

export function useContract(uuid: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.contract(uuid), queryFn: () => fetchContract(api, uuid), retry: false });
}

export function useNumberContract(uuid: string, { onSuccess }: { onSuccess?: (document: ContractDocument) => void } = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (key: string) => numberContract(api, uuid, key),
    onSuccess: (document) => {
      queryClient.setQueryData(banquetOpsKeys.contract(uuid), document);
      // The event's number shows in the events screens too
      void queryClient.invalidateQueries({ queryKey: ['owner', 'banquets'] });
      onSuccess?.(document);
    },
  });
}

export function useSettlement(uuid: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetOpsKeys.settlement(uuid), queryFn: () => fetchSettlement(api, uuid), retry: false });
}

export function useSaveGuestsActual(uuid: string, { onSuccess }: { onSuccess?: (settlement: Settlement) => void } = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ guests, key }: { guests: number; key: string }) => saveGuestsActual(api, uuid, guests, key),
    onSuccess: (result) => {
      queryClient.setQueryData(banquetOpsKeys.settlement(uuid), result.settlement);
      void queryClient.invalidateQueries({ queryKey: banquetOpsKeys.sheet(uuid) });
      void queryClient.invalidateQueries({ queryKey: ['owner', 'banquets'] });
      onSuccess?.(result.settlement);
    },
  });
}

export function useDownloadProductionSheet({ onSuccess, onError }: { onSuccess?: (file: { blob: Blob; filename: string }) => void; onError?: (error: unknown) => void } = {}) {
  const api = useStaffApi();
  return useMutation({ mutationFn: ({ uuid, eventDate }: { uuid: string; eventDate?: string }) => downloadProductionSheet(api, uuid, eventDate), onSuccess, onError });
}

export function useDownloadShoppingList({ onSuccess, onError }: { onSuccess?: (file: { blob: Blob; filename: string }) => void; onError?: (error: unknown) => void } = {}) {
  const api = useStaffApi();
  return useMutation({ mutationFn: (range: DateRange) => downloadShoppingList(api, range), onSuccess, onError });
}
