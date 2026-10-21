'use client';

import { useState } from 'react';
import { Store } from 'lucide-react';
import { Field, fieldClass } from '@rizqim/ui';
import { useMenuBranches } from '@rizqim/staff';
import { useT } from '../I18nProvider';
import { useBranchScope } from '../shell/BranchScope';

// The branch a stop-list / batches / branch-variants screen works on: the branch chosen in the top bar
// (useBranchScope), so a stop never lands on another branch than the one the panel shows. Only when the top bar says
// "all branches" does the page offer its own picker (starting at the first branch). The list is the backend's (a
// branch-bound caller gets their own branch only).
export function useBranchChoice() {
  const query = useMenuBranches();
  const scope = useBranchScope();
  const [chosen, setChosen] = useState<number | null>(null);
  const all = query.data ?? NO_BRANCHES;
  const scoped = scope.branchId === null ? NO_BRANCHES : all.filter((branch) => branch.id === scope.branchId);
  const followsScope = scoped.length > 0;
  const branches = followsScope ? scoped : all;
  const branchId = followsScope ? scope.branchId : branches.some((branch) => branch.id === chosen) ? chosen : (branches[0]?.id ?? null);
  return {
    branches,
    branchId,
    // true: the top bar's branch (the page shows its name, no picker)
    followsScope,
    choose: setChosen,
    isPending: query.isPending,
    isError: query.isError,
    refetch: () => void query.refetch(),
    isFetching: query.isFetching,
  };
}

const NO_BRANCHES: Array<{ id: number; name: string }> = [];

// A select of the branches; one branch only: its name, no choice.
export function BranchSelect({ branches, value, onChange, className }: { branches: Array<{ id: number; name: string }>; value: number | null; onChange: (id: number) => void; className?: string }) {
  const t = useT();
  if (branches.length === 1) {
    return (
      <p className="flex min-h-11 items-center gap-2 text-body font-bold text-fg">
        <Store aria-hidden="true" className="size-5 text-fg-muted" />
        <span>{t('menu_branch')}: {branches[0]?.name}</span>
      </p>
    );
  }
  return (
    <Field label={t('menu_branch')} className={className}>
      {(control) => (
        <select {...control} className={fieldClass} value={value ?? ''} onChange={(event) => onChange(Number(event.target.value))}>
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>
      )}
    </Field>
  );
}
