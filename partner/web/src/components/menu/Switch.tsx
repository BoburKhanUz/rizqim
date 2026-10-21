import { cx } from '@rizqim/ui';

// An on/off switch with a 44px touch target. The state is also written out by `label` for a screen reader, and the
// knob's position (not only the colour) shows it.
export function Switch({ checked, onChange, label, disabled, className }: { checked: boolean; onChange: (checked: boolean) => void; label: string; disabled?: boolean; className?: string }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className={cx(
        'inline-flex min-h-11 min-w-14 shrink-0 items-center justify-center rounded-control disabled:cursor-not-allowed disabled:opacity-60',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
        className,
      )}
    >
      <span className={cx('flex h-7 w-12 items-center rounded-full border px-0.5 transition-colors', checked ? 'justify-end border-success bg-success' : 'justify-start border-fg-muted bg-surface-2')}>
        <span className={cx('size-5 rounded-full shadow-sm', checked ? 'bg-on-success' : 'bg-fg-muted')} />
      </span>
    </button>
  );
}
