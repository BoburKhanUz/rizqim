import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchUzQrSettings, saveUzQrTillKey, type UzQrSettingsView, type UzQrTill, type UzQrTillPatch } from '../api/uzqr';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The UzQR credentials of the store's tills (Wave P)
export const uzqrKeys = {
  settings: (branchId: number) => ['owner', 'uzqr', branchId] as const,
};

export function useUzQrSettings(branchId: number | null, options: QueryExtras<UzQrSettingsView> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: uzqrKeys.settings(branchId ?? 0),
    queryFn: () => fetchUzQrSettings(api, branchId as number),
  });
}

export interface UzQrTillKeyChange {
  uuid: string;
  patch: UzQrTillPatch;
}

// The answer is the till's new view: it replaces the till in every cached list
export function useSaveUzQrTillKey(options: MutationExtras<UzQrTill, UzQrTillKeyChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ uuid, patch }: UzQrTillKeyChange) => saveUzQrTillKey(api, uuid, patch),
    onSuccess: (...args) => {
      const till = args[0];
      queryClient.setQueriesData<UzQrSettingsView>({ queryKey: ['owner', 'uzqr'] }, (view) =>
        view === undefined ? view : { ...view, tills: view.tills.map((row) => (row.uuid === till.uuid ? till : row)) },
      );
      return options.onSuccess?.(...args);
    },
  });
}
