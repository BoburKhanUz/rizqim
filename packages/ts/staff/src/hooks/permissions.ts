import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchRolePermissions, saveRolePermission, type RolePermissionChange, type RolePermissionSaved, type RolePermissionTable } from '../api/permissions';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The role permission table of the partner web's "Huquqlar" page (P3-37). Not to be confused with staffKeys.permissions,
// which is what the signed-in person may do (GET /me/permissions).
export const rolePermissionKeys = {
  table: ['owner', 'role-permissions'] as const,
};

export function useRolePermissions(options: QueryExtras<RolePermissionTable> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: rolePermissionKeys.table, queryFn: () => fetchRolePermissions(api) });
}

// A change answers the one cell it touched: the cached table takes it at once (the effective matrix), and the table is
// read again so the owner sees what the server really holds (the defaults too, in case they moved).
export function useSaveRolePermission(options: MutationExtras<RolePermissionSaved, RolePermissionChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (change: RolePermissionChange) => saveRolePermission(api, change),
    onSuccess: (...args) => {
      const [saved] = args;
      queryClient.setQueryData<RolePermissionTable>(rolePermissionKeys.table, (table) =>
        table ? { ...table, matrix: { ...table.matrix, [saved.role]: { ...table.matrix[saved.role], [saved.permission]: saved.allowed } } } : table,
      );
      void queryClient.invalidateQueries({ queryKey: rolePermissionKeys.table });
      return options.onSuccess?.(...args);
    },
  });
}
