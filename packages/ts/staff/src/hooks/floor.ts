import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createAutoGuestItem,
  createDiningTable,
  createHall,
  createRoomTariff,
  deleteAutoGuestItem,
  deleteDiningTable,
  deleteHall,
  deleteHallServiceFee,
  deleteRoomTariff,
  fetchAutoGuestItems,
  fetchHalls,
  fetchRoomTariffs,
  saveFloorLayout,
  saveTariffCalendar,
  updateAutoGuestItem,
  updateDiningTable,
  updateHall,
  updateRoomTariff,
  type AutoGuestItem,
  type AutoGuestItemInput,
  type AutoGuestItemPatch,
  type DiningTable,
  type DiningTableInput,
  type DiningTablePatch,
  type FloorLayoutRow,
  type Hall,
  type HallInput,
  type HallPatch,
  type RoomTariff,
  type RoomTariffInput,
  type RoomTariffPatch,
  type RoomTariffs,
  type TariffCalendar,
} from '../api/floor';
import { managementKeys } from './management';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// Halls, places, the floor layout, room tariffs and per-guest items of one branch (P3-12). A change refetches what it touches.
export const floorKeys = {
  halls: (branchId?: number) => ['owner', 'halls', branchId ?? null] as const,
  allHalls: ['owner', 'halls'] as const,
  tariffs: (branchId?: number) => ['owner', 'room-tariffs', branchId ?? null] as const,
  allTariffs: ['owner', 'room-tariffs'] as const,
  guestItems: (branchId?: number) => ['owner', 'auto-guest-items', branchId ?? null] as const,
  allGuestItems: ['owner', 'auto-guest-items'] as const,
};

export function useHalls(branchId: number | undefined, options: QueryExtras<Hall[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: floorKeys.halls(branchId), queryFn: () => fetchHalls(api, branchId) });
}

// A mutation that changes the halls or the places refetches the halls (they carry their places)
function useHallsMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: floorKeys.allHalls });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateHall(options: MutationExtras<Hall, HallInput> = {}) {
  const api = useStaffApi();
  return useHallsMutation((input: HallInput) => createHall(api, input), options);
}

export function useUpdateHall(options: MutationExtras<Hall, { id: number; patch: HallPatch }> = {}) {
  const api = useStaffApi();
  return useHallsMutation(({ id, patch }: { id: number; patch: HallPatch }) => updateHall(api, id, patch), options);
}

export function useDeleteHall(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useHallsMutation((id: number) => deleteHall(api, id), options);
}

export function useCreateDiningTable(options: MutationExtras<DiningTable, DiningTableInput> = {}) {
  const api = useStaffApi();
  return useHallsMutation((input: DiningTableInput) => createDiningTable(api, input), options);
}

export function useUpdateDiningTable(options: MutationExtras<DiningTable, { id: number; patch: DiningTablePatch }> = {}) {
  const api = useStaffApi();
  return useHallsMutation(({ id, patch }: { id: number; patch: DiningTablePatch }) => updateDiningTable(api, id, patch), options);
}

export function useDeleteDiningTable(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useHallsMutation((id: number) => deleteDiningTable(api, id), options);
}

// "Save" of the map editor: the whole branch at once
export function useSaveFloorLayout(options: MutationExtras<DiningTable[], { branchId: number; tables: FloorLayoutRow[] }> = {}) {
  const api = useStaffApi();
  return useHallsMutation(({ branchId, tables }: { branchId: number; tables: FloorLayoutRow[] }) => saveFloorLayout(api, branchId, tables), options);
}

// A hall's own service charge goes through the service-fees endpoint (useSaveServiceFee with `hall_id`); removing it is here.
export function useDeleteHallServiceFee(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (hallId: number) => deleteHallServiceFee(api, hallId),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: floorKeys.allHalls });
      void queryClient.invalidateQueries({ queryKey: managementKeys.serviceFees });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Room tariffs and the calendar -----------------------------------------------------------------------------------------

export function useRoomTariffs(branchId: number | undefined, options: QueryExtras<RoomTariffs> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: floorKeys.tariffs(branchId), queryFn: () => fetchRoomTariffs(api, branchId) });
}

function useTariffMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: floorKeys.allTariffs });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateRoomTariff(options: MutationExtras<RoomTariff, RoomTariffInput> = {}) {
  const api = useStaffApi();
  return useTariffMutation((input: RoomTariffInput) => createRoomTariff(api, input), options);
}

export function useUpdateRoomTariff(options: MutationExtras<RoomTariff, { id: number; patch: RoomTariffPatch }> = {}) {
  const api = useStaffApi();
  return useTariffMutation(({ id, patch }: { id: number; patch: RoomTariffPatch }) => updateRoomTariff(api, id, patch), options);
}

export function useDeleteRoomTariff(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useTariffMutation((id: number) => deleteRoomTariff(api, id), options);
}

export function useSaveTariffCalendar(options: MutationExtras<TariffCalendar, { branchId: number; weekend_days: number[]; holidays: string[] }> = {}) {
  const api = useStaffApi();
  return useTariffMutation(({ branchId, ...calendar }: { branchId: number; weekend_days: number[]; holidays: string[] }) => saveTariffCalendar(api, branchId, calendar), options);
}

// --- Per-guest items ("choy-non") ----------------------------------------------------------------------------------------------

export function useAutoGuestItems(branchId: number | undefined, options: QueryExtras<AutoGuestItem[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: floorKeys.guestItems(branchId), queryFn: () => fetchAutoGuestItems(api, branchId) });
}

function useGuestItemMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: floorKeys.allGuestItems });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateAutoGuestItem(options: MutationExtras<AutoGuestItem, AutoGuestItemInput> = {}) {
  const api = useStaffApi();
  return useGuestItemMutation((input: AutoGuestItemInput) => createAutoGuestItem(api, input), options);
}

export function useUpdateAutoGuestItem(options: MutationExtras<AutoGuestItem, { id: number; patch: AutoGuestItemPatch }> = {}) {
  const api = useStaffApi();
  return useGuestItemMutation(({ id, patch }: { id: number; patch: AutoGuestItemPatch }) => updateAutoGuestItem(api, id, patch), options);
}

export function useDeleteAutoGuestItem(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useGuestItemMutation((id: number) => deleteAutoGuestItem(api, id), options);
}
