import { useMyPermissions } from '@rizqim/staff';

// What the signed-in person may do, from GET /me/permissions (@rizqim/staff, cached: the shell reads the same answer).
// The screens only hide buttons the backend would refuse; every request is checked again there.
export function useCan() {
  const query = useMyPermissions();
  const permissions = query.data?.permissions ?? [];
  return {
    ready: query.isSuccess,
    role: query.data?.role ?? null,
    branchScoped: query.data?.branch_scoped ?? false,
    branchId: query.data?.branch_id ?? null,
    can: (permission: string) => permissions.includes(permission),
  };
}
