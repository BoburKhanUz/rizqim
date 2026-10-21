'use client';

import { createContext, useCallback, useContext, useId, useMemo, useState, type ReactNode } from 'react';
import { Store } from 'lucide-react';
import { fieldClass } from '@rizqim/ui';
import { useBranchList, type BranchListItem } from '@rizqim/staff';
import { hasAny } from '@/lib/permissions';
import { readStored, writeStored } from '@/lib/storage';
import { useAccess } from '../AuthProvider';
import { useT } from '../I18nProvider';

// The branch the panel works on, chosen once in the top bar and read by every page that filters by branch
// (useBranchScope().branchId: null = all branches). The owner and store-wide staff choose "all" or one branch; a
// branch-bound person always works on their own branch. The choice is remembered per person in this browser.

export interface BranchScopeValue {
  // The branch chosen, null for all branches
  branchId: number | null;
  setBranchId: (branchId: number | null) => void;
  // The branches the person can see (GET /owner/branches; a branch-bound person gets only their own)
  branches: BranchListItem[];
  // A branch-bound person: the branch is fixed
  locked: boolean;
  branchName: (id: number | null | undefined) => string | null;
}

const NO_BRANCHES: BranchListItem[] = [];

const BranchScopeContext = createContext<BranchScopeValue | null>(null);

// Who may list the branches (routes/api_management.php, GET /owner/branches)
const BRANCH_LIST_PERMISSIONS = ['branches.manage', 'settings.manage', 'orders.view', 'menu.view', 'staff.view', 'reports.view'];

export const branchStorageKey = (storeId: number | null, userId: number | null) => `rz_branch:${storeId ?? 0}:${userId ?? 0}`;

const parseStored = (value: string | null): number | null => {
  if (value === null || value === 'all') return null;
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
};

export function BranchScopeProvider({ children }: { children: ReactNode }) {
  const access = useAccess();
  const canList = access !== null && hasAny(access.permissions, BRANCH_LIST_PERMISSIONS);
  const list = useBranchList({ enabled: canList });
  const branches = list.data ?? NO_BRANCHES;
  const locked = access?.branchScoped === true && access.branchId !== null;
  const key = branchStorageKey(access?.storeId ?? null, access?.userId ?? null);

  // The choice of this session; until one is made, the one saved for this person
  const [chosen, setChosen] = useState<{ key: string; branchId: number | null } | null>(null);
  const saved = chosen?.key === key ? chosen.branchId : parseStored(readStored(key));

  let branchId: number | null;
  if (locked) branchId = access.branchId;
  // A saved branch that is gone (deleted, or the person moved): all branches
  else if (saved !== null && list.isSuccess && !branches.some((branch) => branch.id === saved)) branchId = null;
  else branchId = saved;

  const setBranchId = useCallback(
    (next: number | null) => {
      setChosen({ key, branchId: next });
      writeStored(key, next === null ? 'all' : String(next));
    },
    [key, setChosen],
  );

  const value = useMemo<BranchScopeValue>(
    () => ({
      branchId,
      setBranchId,
      branches,
      locked,
      branchName: (id) => (id == null ? null : (branches.find((branch) => branch.id === id)?.name ?? null)),
    }),
    [branchId, setBranchId, branches, locked],
  );
  return <BranchScopeContext.Provider value={value}>{children}</BranchScopeContext.Provider>;
}

const OUTSIDE: BranchScopeValue = { branchId: null, setBranchId: () => {}, branches: NO_BRANCHES, locked: false, branchName: () => null };

// The branch the panel works on. Outside the shell (a test of one page): all branches, no picker.
export function useBranchScope(): BranchScopeValue {
  return useContext(BranchScopeContext) ?? OUTSIDE;
}

// The picker of the top bar: "All branches" or one; a branch-bound person sees their branch, not a picker.
export function BranchSwitcher({ className }: { className?: string }) {
  const t = useT();
  const id = useId();
  const { branchId, setBranchId, branches, locked, branchName } = useBranchScope();

  if (locked) {
    const name = branchName(branchId) ?? t('branchUnknown', { id: branchId ?? '' });
    return (
      <p className={`inline-flex min-h-11 items-center gap-2 text-body text-fg ${className ?? ''}`}>
        <Store aria-hidden="true" className="size-5 text-fg-muted" />
        {t('branchOnly', { name })}
      </p>
    );
  }
  if (branches.length === 0) return null;

  return (
    <div className={`flex items-center gap-2 ${className ?? ''}`}>
      <label htmlFor={id} className="inline-flex items-center gap-2 text-footnote font-medium text-fg-muted">
        <Store aria-hidden="true" className="size-5" />
        {t('branchLabel')}
      </label>
      <select
        id={id}
        className={`${fieldClass} min-w-48 text-body`}
        value={branchId === null ? 'all' : String(branchId)}
        onChange={(event) => setBranchId(parseStored(event.target.value))}
      >
        <option value="all">{t('branchAll')}</option>
        {branches.map((branch) => (
          <option key={branch.id} value={branch.id}>
            {branch.name}
          </option>
        ))}
      </select>
    </div>
  );
}
