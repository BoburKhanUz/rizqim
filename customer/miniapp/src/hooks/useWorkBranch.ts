import { useState } from 'react';
import { useBranchList, type BranchListItem } from '@rizqim/staff';
import { useAuthStore } from '../store/authStore';

const NO_BRANCHES: BranchListItem[] = [];

export interface WorkBranch {
  branches: BranchListItem[];
  // The branch the screen works with: an owner's pick (the first branch until they pick), undefined for a manager (the
  // backend works on their own branch whatever is asked)
  branchId: number | undefined;
  setBranchId: (id: number) => void;
  // The owner has more than one branch to choose from
  canPick: boolean;
  // A screen that needs the branch id (the owner's stop-list and batches) waits for this
  ready: boolean;
  isLoading: boolean;
  isError: boolean;
  refetch: () => void;
}

// Which branch a staff screen works with (stop-list, batches, working hours). Owners pick one of their branches; a manager's
// requests are bound to their branch by the backend, so they send none.
export function useWorkBranch(): WorkBranch {
  const role = useAuthStore((state) => state.user?.role);
  const isOwner = role === 'owner';
  const { data, isLoading, isError, refetch } = useBranchList({ enabled: isOwner });
  const branches = data ?? NO_BRANCHES;
  const [chosen, setChosen] = useState<number | null>(null);

  const picked = branches.find((branch) => branch.id === chosen) ?? branches[0];
  return {
    branches,
    branchId: isOwner ? picked?.id : undefined,
    setBranchId: setChosen,
    canPick: isOwner && branches.length > 1,
    ready: !isOwner || picked !== undefined,
    isLoading: isOwner && isLoading,
    isError: isOwner && isError,
    refetch: () => void refetch(),
  };
}
