import { useState } from 'react';
import { useBranchList, type BranchListItem } from '@rizqim/staff';
import { useAuthStore } from '../store/authStore';

const NO_BRANCHES: BranchListItem[] = [];

export interface WorkBranch {
  branches: BranchListItem[];
  // The branch the screen works with: the pick of an owner or of store-wide staff (the first branch until they pick),
  // undefined for branch-bound staff (the backend works on their own branch whatever is asked)
  branchId: number | undefined;
  setBranchId: (id: number) => void;
  // There is more than one branch to choose from
  canPick: boolean;
  // A screen that needs the branch id (the stop-list and batches of store-wide staff) waits for this
  ready: boolean;
  isLoading: boolean;
  isError: boolean;
  refetch: () => void;
}

// Which branch a staff screen works with (stop-list, batches, working hours). The backend's rule
// (PermissionService::isBranchScoped): staff with a branch_id, other than the owner, are bound to that branch and send
// none; the owner and store-wide staff (a manager without a branch) must name one (branch_id, else 422), so they pick.
export function useWorkBranch(): WorkBranch {
  const user = useAuthStore((state) => state.user);
  const storeWide = user !== null && (user.role === 'owner' || user.branch_id === null);
  const { data, isLoading, isError, refetch } = useBranchList({ enabled: storeWide });
  const branches = data ?? NO_BRANCHES;
  const [chosen, setChosen] = useState<number | null>(null);

  const picked = branches.find((branch) => branch.id === chosen) ?? branches[0];
  return {
    branches,
    branchId: storeWide ? picked?.id : undefined,
    setBranchId: setChosen,
    canPick: storeWide && branches.length > 1,
    ready: !storeWide || picked !== undefined,
    isLoading: storeWide && isLoading,
    isError: storeWide && isError,
    refetch: () => void refetch(),
  };
}
