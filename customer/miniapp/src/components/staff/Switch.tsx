import { selectionChanged } from '../../lib/twa';
import { cx } from '../ui/cx';

interface SwitchProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  // The accessible name (what the switch turns on)
  label: string;
  disabled?: boolean;
}

// An on/off switch: role="switch", a 44x44 hit area around the 48x28 track.
export function Switch({ checked, onChange, label, disabled = false }: SwitchProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => {
        selectionChanged();
        onChange(!checked);
      }}
      className="group inline-flex min-h-11 min-w-12 shrink-0 cursor-pointer items-center justify-center disabled:cursor-not-allowed disabled:opacity-60"
    >
      <span
        className={cx(
          'flex h-7 w-12 items-center rounded-full p-0.5 transition-colors group-focus-visible:outline-2 group-focus-visible:outline-offset-2 group-focus-visible:outline-brand',
          checked ? 'bg-brand' : 'bg-fg-muted/80',
        )}
      >
        <span className={cx('size-6 rounded-full bg-surface shadow transition-transform', checked ? 'translate-x-5' : 'translate-x-0')} />
      </span>
    </button>
  );
}
