import type { BranchListItem } from '@rizqim/staff';
import { selectionChanged } from '../../lib/twa';
import { cx, fieldClass } from '../ui';

// The owner's branch picker: a plain select (the native picker of the phone), named for screen readers.
export function BranchSelect({ branches, value, onChange, label }: { branches: readonly BranchListItem[]; value: number | undefined; onChange: (id: number) => void; label: string }) {
  return (
    <select
      aria-label={label}
      value={value ?? ''}
      onChange={(e) => {
        selectionChanged();
        onChange(Number(e.target.value));
      }}
      className={cx(fieldClass, 'cursor-pointer bg-surface')}
    >
      {branches.map((branch) => (
        <option key={branch.id} value={branch.id}>
          {branch.name}
        </option>
      ))}
    </select>
  );
}
