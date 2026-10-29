import { cx } from '../ui';

// "Tugadi": the dish (or one choice of it) cannot be ordered now. A word, not only a colour (docs/DESIGN.md). With
// `nextBatch` ("12:30", the branch's planned next batch, P6-15) it also says when it will be back.
export function SoldOutBadge({ className, nextBatch }: { className?: string; nextBatch?: string | null }) {
  const badge = (
    <span className={cx('inline-flex shrink-0 items-center rounded-control bg-danger-soft px-2 py-0.5 text-caption font-bold text-danger-soft-fg', !nextBatch && className)}>
      Tugadi
    </span>
  );
  if (!nextBatch) return badge;
  return (
    <span className={cx('inline-flex shrink-0 flex-col items-end gap-0.5', className)}>
      {badge}
      <span className="text-caption font-medium text-fg-muted">Keyingi partiya {nextBatch}</span>
    </span>
  );
}
