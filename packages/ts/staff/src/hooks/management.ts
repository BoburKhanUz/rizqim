import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createBranch,
  createDeliveryZone,
  createManagedStaff,
  createPairingCode,
  deleteBranch,
  deleteDeliveryZone,
  fetchDeliveryZones,
  fetchManagedStaff,
  fetchRegionsAndDistricts,
  fetchServiceFees,
  fetchStoreDevices,
  fetchStoreSettings,
  renameStoreDevice,
  revokeStoreDevice,
  saveServiceFee,
  saveStoreSettings,
  updateBranch,
  updateDeliveryZone,
  updateManagedStaff,
  type BranchCreateInput,
  type BranchPatchInput,
  type CreatedStaff,
  type DeliveryZone,
  type LocationsList,
  type ManagedBranch,
  type PairingCode,
  type PairingCodeInput,
  type ServiceFeeInput,
  type ServiceFeeRow,
  type ServiceFees,
  type StaffCreateInput,
  type StaffMember,
  type StaffPatchInput,
  type StoreDevice,
  type StoreSettings,
  type StoreSettingsInput,
  type ZoneInput,
} from '../api/management';
import { removeEmployee, resetStaffPin, setStaffPin, updateStaffRole, type StaffPinResult, type StaffRoleInput, type StaffRoleResult } from '../api/team';
import { menuAdminKeys } from './menuAdmin';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// Hooks of the management screens of the partner web (P2-23): staff, branches, delivery zones, store settings, fees and
// terminals. Same shape as the other hooks: the data comes from the functions of api/management.ts, a change refetches the
// lists it touches, polling and `enabled` come in through `options`.

export const managementKeys = {
  staff: ['owner', 'staff'] as const,
  zones: ['owner', 'delivery-zones'] as const,
  regions: ['locations'] as const,
  serviceFees: ['owner', 'service-fees'] as const,
  devices: (includeRevoked = false) => ['owner', 'devices', includeRevoked] as const,
  allDevices: ['owner', 'devices'] as const,
};

// --- Staff -------------------------------------------------------------------------------------------------------------

export function useManagedStaff(options: QueryExtras<StaffMember[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: managementKeys.staff, queryFn: () => fetchManagedStaff(api) });
}

// Every change of a member refetches the staff list (and the owner's older employees list).
function useStaffMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: managementKeys.staff });
      void queryClient.invalidateQueries({ queryKey: staffKeys.employees });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateManagedStaff(options: MutationExtras<CreatedStaff, StaffCreateInput> = {}) {
  const api = useStaffApi();
  return useStaffMutation((input: StaffCreateInput) => createManagedStaff(api, input), options);
}

export function useUpdateManagedStaff(options: MutationExtras<StaffMember, { id: number; input: StaffPatchInput }> = {}) {
  const api = useStaffApi();
  return useStaffMutation(({ id, input }: { id: number; input: StaffPatchInput }) => updateManagedStaff(api, id, input), options);
}

export function useChangeStaffRole(options: MutationExtras<StaffRoleResult, { id: number; input: StaffRoleInput }> = {}) {
  const api = useStaffApi();
  return useStaffMutation(({ id, input }: { id: number; input: StaffRoleInput }) => updateStaffRole(api, id, input), options);
}

export function useSetManagedStaffPin(options: MutationExtras<StaffPinResult, { id: number; pin: string }> = {}) {
  const api = useStaffApi();
  return useStaffMutation(({ id, pin }: { id: number; pin: string }) => setStaffPin(api, id, pin), options);
}

export function useClearManagedStaffPin(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useStaffMutation((id: number) => resetStaffPin(api, id), options);
}

// DELETE /owner/employees/{id}: the owner removes a member from the store.
export function useRemoveManagedStaff(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useStaffMutation((id: number) => removeEmployee(api, id), options);
}

// --- Branches ----------------------------------------------------------------------------------------------------------

// The branch list is useMenuBranches (hooks/menuAdmin.ts, P2-22); the changes below refetch it.

// A branch change also moves what hangs on it: zones show their branch's name, the staff list its branch.
function useBranchMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: menuAdminKeys.branches });
      void queryClient.invalidateQueries({ queryKey: managementKeys.zones });
      void queryClient.invalidateQueries({ queryKey: managementKeys.staff });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateBranch(options: MutationExtras<ManagedBranch, BranchCreateInput> = {}) {
  const api = useStaffApi();
  return useBranchMutation((input: BranchCreateInput) => createBranch(api, input), options);
}

export function useUpdateBranch(options: MutationExtras<ManagedBranch, { id: number; input: BranchPatchInput }> = {}) {
  const api = useStaffApi();
  return useBranchMutation(({ id, input }: { id: number; input: BranchPatchInput }) => updateBranch(api, id, input), options);
}

export function useDeleteBranch(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useBranchMutation((id: number) => deleteBranch(api, id), options);
}

// Regions and districts change once in a blue moon: never stale.
export function useRegionsAndDistricts(options: QueryExtras<LocationsList> = {}) {
  const api = useStaffApi();
  return useQuery({ staleTime: Infinity, ...options, queryKey: managementKeys.regions, queryFn: () => fetchRegionsAndDistricts(api) });
}

// --- Delivery zones ----------------------------------------------------------------------------------------------------

export function useDeliveryZones(options: QueryExtras<DeliveryZone[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: managementKeys.zones, queryFn: () => fetchDeliveryZones(api) });
}

function useZoneMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: managementKeys.zones });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateDeliveryZone(options: MutationExtras<DeliveryZone, ZoneInput> = {}) {
  const api = useStaffApi();
  return useZoneMutation((input: ZoneInput) => createDeliveryZone(api, input), options);
}

export function useUpdateDeliveryZone(options: MutationExtras<DeliveryZone, { id: number; input: Partial<ZoneInput> }> = {}) {
  const api = useStaffApi();
  return useZoneMutation(({ id, input }: { id: number; input: Partial<ZoneInput> }) => updateDeliveryZone(api, id, input), options);
}

export function useDeleteDeliveryZone(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useZoneMutation((id: number) => deleteDeliveryZone(api, id), options);
}

// --- Store settings and fees -------------------------------------------------------------------------------------------

// The same GET as useOwnerSettings (one cache entry), typed with the store's timezone.
export function useStoreSettings(options: QueryExtras<StoreSettings | null> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.settings, queryFn: () => fetchStoreSettings(api) });
}

export function useSaveStoreSettings(options: MutationExtras<void, StoreSettingsInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: StoreSettingsInput) => saveStoreSettings(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.settings });
      return options.onSuccess?.(...args);
    },
  });
}

export function useServiceFees(options: QueryExtras<ServiceFees> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: managementKeys.serviceFees, queryFn: () => fetchServiceFees(api) });
}

export function useSaveServiceFee(options: MutationExtras<ServiceFeeRow, ServiceFeeInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: ServiceFeeInput) => saveServiceFee(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: managementKeys.serviceFees });
      // A hall's own charge is shown with the hall (floorKeys.allHalls; the floor hooks import this file, so the key is spelt here)
      if (args[1].hall_id !== undefined && args[1].hall_id !== null) void queryClient.invalidateQueries({ queryKey: ['owner', 'halls'] });
      return options.onSuccess?.(...args);
    },
  });
}

// --- Terminals ---------------------------------------------------------------------------------------------------------

export function useStoreDevices(includeRevoked = false, options: QueryExtras<StoreDevice[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: managementKeys.devices(includeRevoked), queryFn: () => fetchStoreDevices(api, includeRevoked) });
}

function useDeviceMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: managementKeys.allDevices });
      return options.onSuccess?.(...args);
    },
  });
}

export function useRenameStoreDevice(options: MutationExtras<StoreDevice, { uuid: string; name: string }> = {}) {
  const api = useStaffApi();
  return useDeviceMutation(({ uuid, name }: { uuid: string; name: string }) => renameStoreDevice(api, uuid, name), options);
}

export function useRevokeStoreDevice(options: MutationExtras<StoreDevice, string> = {}) {
  const api = useStaffApi();
  return useDeviceMutation((uuid: string) => revokeStoreDevice(api, uuid), options);
}

// A new code does not change the device list (the terminal appears once it has paired).
export function useCreatePairingCode(options: MutationExtras<PairingCode, PairingCodeInput> = {}) {
  const api = useStaffApi();
  return useMutation({ ...options, mutationFn: (input: PairingCodeInput) => createPairingCode(api, input) });
}
