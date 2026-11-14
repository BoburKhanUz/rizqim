import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  createEvent,
  deleteExtra,
  deletePackage,
  fetchCalendar,
  fetchCatalog,
  fetchEvent,
  fetchExtras,
  fetchHalls,
  fetchPackages,
  fetchSettings,
  freezeEvent,
  newKey,
  saveExtra,
  saveHallRates,
  savePackage,
  saveSettings,
  unfreezeEvent,
  updateEvent,
  type BanquetEvent,
  type EventInput,
  type ExtraCatalogInput,
  type PackageInput,
  type Settings,
  type Slot,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const banquetKeys = {
  all: ['owner', 'banquets'] as const,
  calendar: (query: object) => ['owner', 'banquets', 'calendar', query] as const,
  catalog: (branchId: number) => ['owner', 'banquets', 'catalog', branchId] as const,
  event: (uuid: string) => ['owner', 'banquets', 'event', uuid] as const,
  settings: ['owner', 'banquets', 'setup', 'settings'] as const,
  packages: ['owner', 'banquets', 'setup', 'packages'] as const,
  extras: ['owner', 'banquets', 'setup', 'extras'] as const,
  halls: (branchId: number | null) => ['owner', 'banquets', 'setup', 'halls', branchId] as const,
};

export function useBanquetCalendar(
  query: {
    branch_id: number;
    from: string;
    to: string;
    include_cancelled?: boolean;
  } | null,
) {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.calendar(query ?? {}),
    queryFn: () => fetchCalendar(api, query as NonNullable<typeof query>),
    enabled: query !== null,
    placeholderData: keepPreviousData,
    retry: false,
  });
}

export function useBanquetCatalog(branchId: number | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.catalog(branchId ?? 0),
    queryFn: () => fetchCatalog(api, branchId as number),
    enabled: branchId !== null,
    retry: false,
  });
}

export function useBanquetEvent(uuid: string) {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.event(uuid),
    queryFn: () => fetchEvent(api, uuid),
    retry: false,
  });
}

// Every write refreshes the calendar, the event and anything else under the banquet keys (the payment schedule too)
function useInvalidate() {
  const queryClient = useQueryClient();
  return (event?: BanquetEvent) => {
    if (event) queryClient.setQueryData(banquetKeys.event(event.uuid), event);
    void queryClient.invalidateQueries({ queryKey: banquetKeys.all });
  };
}

export function useSaveEvent({
  onSuccess,
  onError,
}: {
  onSuccess?: (event: BanquetEvent) => void;
  onError?: (error: unknown) => void;
} = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ uuid, input, key }: { uuid: string | null; input: EventInput; key: string }) => (uuid === null ? createEvent(api, input, key) : updateEvent(api, uuid, input, key)),
    onSuccess: (event) => {
      invalidate(event);
      onSuccess?.(event);
    },
    onError,
  });
}

export function useFreezeEvent({
  onSuccess,
  onError,
}: {
  onSuccess?: (event: BanquetEvent) => void;
  onError?: (error: unknown) => void;
} = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ uuid, version, freeze, reason }: { uuid: string; version: number; freeze: boolean; reason?: string }) =>
      freeze ? freezeEvent(api, uuid, version, newKey()) : unfreezeEvent(api, uuid, version, reason ?? '', newKey()),
    onSuccess: (event) => {
      invalidate(event);
      onSuccess?.(event);
    },
    onError,
  });
}

export function useBanquetSettings() {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.settings,
    queryFn: () => fetchSettings(api),
    retry: false,
  });
}

export function useSaveSettings({ onSuccess }: { onSuccess?: (settings: Settings) => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: Partial<Omit<Settings, 'version'>>) => saveSettings(api, input),
    onSuccess: (settings) => {
      invalidate();
      onSuccess?.(settings);
    },
  });
}

export function useBanquetPackages() {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.packages,
    queryFn: () => fetchPackages(api),
    retry: false,
  });
}

export function useSavePackage({ onSuccess }: { onSuccess?: () => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: PackageInput }) => savePackage(api, id, input),
    onSuccess: () => {
      invalidate();
      onSuccess?.();
    },
  });
}

export function useDeletePackage({ onSuccess }: { onSuccess?: (deleted: boolean) => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (id: number) => deletePackage(api, id),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result.deleted);
    },
  });
}

export function useBanquetExtras() {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.extras,
    queryFn: () => fetchExtras(api),
    retry: false,
  });
}

export function useSaveExtra({ onSuccess }: { onSuccess?: () => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ExtraCatalogInput }) => saveExtra(api, id, input),
    onSuccess: () => {
      invalidate();
      onSuccess?.();
    },
  });
}

export function useDeleteExtra({ onSuccess }: { onSuccess?: (deleted: boolean) => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (id: number) => deleteExtra(api, id),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result.deleted);
    },
  });
}

export function useBanquetHalls(branchId: number | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: banquetKeys.halls(branchId),
    queryFn: () => fetchHalls(api, branchId),
    retry: false,
  });
}

export function useSaveHallRates({ onSuccess }: { onSuccess?: () => void } = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: ({ hallId, rates }: { hallId: number; rates: Partial<Record<Slot, string | null>> }) => saveHallRates(api, hallId, rates),
    onSuccess: () => {
      invalidate();
      onSuccess?.();
    },
  });
}
