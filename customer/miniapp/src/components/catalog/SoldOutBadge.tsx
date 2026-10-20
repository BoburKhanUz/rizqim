import { cx } from '../ui';

// "Tugadi": the dish (or one choice of it) cannot be ordered now. A word, not only a colour (docs/DESIGN.md).
export function SoldOutBadge({ className }: { className?: string }) {
  return (
    <span className={cx('inline-flex shrink-0 items-center rounded-control bg-danger-soft px-2 py-0.5 text-caption font-bold text-danger-soft-fg', className)}>
      Tugadi
    </span>
  );
}
