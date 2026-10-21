import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchEmployees,
  fetchMyPermissions,
  inviteEmployee,
  removeEmployee,
  resetStaffPin,
  setStaffPin,
  transferEmployee,
  updateStaffRole,
  type EmployeeInvite,
  type InviteInput,
  type MyPermissions,
  type StaffPinResult,
  type StaffRoleInput,
  type StaffRoleResult,
} from '../api/team';
import type { Employee } from '../api/team';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

export function useEmployees(options: QueryExtras<Employee[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.employees, queryFn: () => fetchEmployees(api) });
}

// Every change of the team refetches the list.
function useTeamMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.employees });
      return options.onSuccess?.(...args);
    },
  });
}

export function useInviteEmployee(options: MutationExtras<EmployeeInvite, InviteInput> = {}) {
  const api = useStaffApi();
  return useTeamMutation((input: InviteInput) => inviteEmployee(api, input), options);
}

export function useTransferEmployee(options: MutationExtras<Employee | null, { employeeId: number; branchId: number }> = {}) {
  const api = useStaffApi();
  return useTeamMutation(({ employeeId, branchId }: { employeeId: number; branchId: number }) => transferEmployee(api, employeeId, branchId), options);
}

export function useRemoveEmployee(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useTeamMutation((employeeId: number) => removeEmployee(api, employeeId), options);
}

export function useSetStaffPin(options: MutationExtras<StaffPinResult, { userId: number; pin: string }> = {}) {
  const api = useStaffApi();
  return useTeamMutation(({ userId, pin }: { userId: number; pin: string }) => setStaffPin(api, userId, pin), options);
}

export function useResetStaffPin(options: MutationExtras<void, number> = {}) {
  const api = useStaffApi();
  return useTeamMutation((userId: number) => resetStaffPin(api, userId), options);
}

export function useUpdateStaffRole(options: MutationExtras<StaffRoleResult, { userId: number; input: StaffRoleInput }> = {}) {
  const api = useStaffApi();
  return useTeamMutation(({ userId, input }: { userId: number; input: StaffRoleInput }) => updateStaffRole(api, userId, input), options);
}

// The role and permission keys of the signed-in person (the backend still checks every request).
export function useMyPermissions(options: QueryExtras<MyPermissions> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.permissions, queryFn: () => fetchMyPermissions(api) });
}
