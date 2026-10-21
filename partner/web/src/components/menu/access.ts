'use client';

import { useMyPermissions } from '@rizqim/staff';

// What the signed-in person may do on the menu screens. The backend checks every request anyway: this only hides the
// buttons it would refuse.
export function useMenuAccess() {
  const query = useMyPermissions();
  const permissions = query.data?.permissions ?? [];
  return {
    isPending: query.isPending,
    can: (permission: string) => permissions.includes(permission),
    // A branch-bound person (a manager) acts on their own branch only
    branchScoped: query.data?.branch_scoped ?? false,
    branchId: query.data?.branch_id ?? null,
  };
}
