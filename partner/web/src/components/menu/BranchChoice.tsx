'use client';

import { useState } from 'react';
import { Store } from 'lucide-react';
import { Field, fieldClass } from '@rizqim/ui';
import { useMenuBranches } from '@rizqim/staff';
import { useT } from '../I18nProvider';

// The branch a stop-list / batches screen works on: the first one until the person picks another. The list is the
// backend's (a branch-bound caller gets their own branch only).
export function useBranchChoice() {
  const query = useMenuBranches();
  const [chosen, setChosen] = useState<number | null>(null);
  const branches = query.data ?? [];
  const branchId = branches.some((branch) => branch.id === chosen) ? chosen : (branches[0]?.id ?? null);
  return { branches, branchId, choose: setChosen, isPending: query.isPending, isError: query.isError, refetch: () => void query.refetch(), isFetching: query.isFetching };
}

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
