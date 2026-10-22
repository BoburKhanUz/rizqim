import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  assignStationRoutes,
  createKitchenStation,
  createPrinter,
  deleteKitchenStation,
  deletePrinter,
  deleteStationRoute,
  fetchBranchPosSettings,
  fetchKitchenStations,
  fetchPrinters,
  fetchStationRoutes,
  saveBranchPosSettings,
  updateKitchenStation,
  updatePrinter,
  type BranchPosSettings,
  type BranchPosSettingsPatch,
  type KitchenStation,
  type KitchenStationInput,
  type KitchenStationPatch,
  type KitchenStations,
  type Printer,
  type PrinterInput,
  type PrinterPatch,
  type StationRoute,
  type StationRouteAssignment,
} from '../api/posSetup';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// Kitchen stations, routes, printers and the branch's POS settings (P3-13).
export const posSetupKeys = {
  stations: (branchId?: number) => ['owner', 'kitchen-stations', branchId ?? null] as const,
  allStations: ['owner', 'kitchen-stations'] as const,
  routes: (branchId?: number) => ['owner', 'kitchen-station-routes', branchId ?? null] as const,
  allRoutes: ['owner', 'kitchen-station-routes'] as const,
  printers: (branchId?: number) => ['owner', 'printers', branchId ?? null] as const,
  allPrinters: ['owner', 'printers'] as const,
  branchSettings: (branchId: number) => ['owner', 'branch-pos-settings', branchId] as const,
};

export function useKitchenStations(branchId: number | undefined, options: QueryExtras<KitchenStations> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: posSetupKeys.stations(branchId), queryFn: () => fetchKitchenStations(api, branchId) });
}

// A station change touches the routes (a deleted station takes its routes) and the printers (they lose the station)
function useStationMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      for (const queryKey of [posSetupKeys.allStations, posSetupKeys.allRoutes, posSetupKeys.allPrinters]) void queryClient.invalidateQueries({ queryKey });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateKitchenStation(options: MutationExtras<KitchenStation, KitchenStationInput> = {}) {
  const api = useStaffApi();
  return useStationMutation((input: KitchenStationInput) => createKitchenStation(api, input), options);
}

export function useUpdateKitchenStation(options: MutationExtras<KitchenStation, { id: number; patch: KitchenStationPatch }> = {}) {
  const api = useStaffApi();
  return useStationMutation(({ id, patch }: { id: number; patch: KitchenStationPatch }) => updateKitchenStation(api, id, patch), options);
}

export function useDeleteKitchenStation(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useStationMutation((id: number) => deleteKitchenStation(api, id), options);
}

export function useStationRoutes(branchId: number | undefined, options: QueryExtras<StationRoute[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: posSetupKeys.routes(branchId), queryFn: () => fetchStationRoutes(api, branchId) });
}

// The bulk assignment answers every route of the branch: it replaces the cached list
export function useAssignStationRoutes(options: MutationExtras<StationRoute[], StationRouteAssignment> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: StationRouteAssignment) => assignStationRoutes(api, input),
    onSuccess: (...args) => {
      queryClient.setQueryData(posSetupKeys.routes(args[1].branch_id), args[0]);
      void queryClient.invalidateQueries({ queryKey: posSetupKeys.allRoutes });
      return options.onSuccess?.(...args);
    },
  });
}

export function useDeleteStationRoute(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (id: number) => deleteStationRoute(api, id),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: posSetupKeys.allRoutes });
      return options.onSuccess?.(...args);
    },
  });
}

export function usePrinters(branchId: number | undefined, options: QueryExtras<Printer[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: posSetupKeys.printers(branchId), queryFn: () => fetchPrinters(api, branchId) });
}

function usePrinterMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: posSetupKeys.allPrinters });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreatePrinter(options: MutationExtras<Printer, PrinterInput> = {}) {
  const api = useStaffApi();
  return usePrinterMutation((input: PrinterInput) => createPrinter(api, input), options);
}

export function useUpdatePrinter(options: MutationExtras<Printer, { id: number; patch: PrinterPatch }> = {}) {
  const api = useStaffApi();
  return usePrinterMutation(({ id, patch }: { id: number; patch: PrinterPatch }) => updatePrinter(api, id, patch), options);
}

export function useDeletePrinter(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return usePrinterMutation((id: number) => deletePrinter(api, id), options);
}

export function useBranchPosSettings(branchId: number | null, options: QueryExtras<BranchPosSettings> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: posSetupKeys.branchSettings(branchId ?? 0),
    queryFn: () => fetchBranchPosSettings(api, branchId as number),
  });
}

// The answer is the branch's whole settings: it replaces the cached ones
export function useSaveBranchPosSettings(options: MutationExtras<BranchPosSettings, { branchId: number; patch: BranchPosSettingsPatch }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: { branchId: number; patch: BranchPosSettingsPatch }) => saveBranchPosSettings(api, branchId, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(posSetupKeys.branchSettings(args[1].branchId), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}
