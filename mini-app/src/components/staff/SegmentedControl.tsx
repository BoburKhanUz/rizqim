import { selectionChanged } from '../../lib/twa';
import { cx } from '../ui/cx';

export interface Segment<T extends string> {
  value: T;
  label: string;
}

interface SegmentedControlProps<T extends string> {
  segments: readonly Segment<T>[];
  value: T;
  onChange: (value: T) => void;
  // What the group is (screen readers): "Davr", "Holat"
  label: string;
  className?: string;
}

// Tabs / period filter: one choice of a few. Each segment is a 44px-tall toggle button (aria-pressed), the chosen one is
// the brand colour; a tap ticks Telegram's haptics (selectionChanged).
export function SegmentedControl<T extends string>({ segments, value, onChange, label, className }: SegmentedControlProps<T>) {
  return (
    <div role="group" aria-label={label} className={cx('flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1 scrollbar-hide', className)}>
      {segments.map((segment) => {
        const selected = segment.value === value;
        return (
          <button
            key={segment.value}
            type="button"
            aria-pressed={selected}
            onClick={() => {
              if (selected) return;
              selectionChanged();
              onChange(segment.value);
            }}
            className={cx(
              'min-h-11 min-w-0 flex-1 cursor-pointer whitespace-nowrap rounded-control px-2 text-body font-bold transition-colors',
              'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
              selected ? 'bg-brand text-on-brand' : 'text-fg-muted hover:bg-surface-2',
            )}
          >
            {segment.label}
          </button>
        );
      })}
    </div>
  );
}
