import { cx } from './cx';

// A grey placeholder block while data loads (inside the page: the header and the navigation stay usable).
export function Skeleton({ className }: { className?: string }) {
  return <span aria-hidden="true" className={cx('block animate-pulse rounded-control bg-surface-2 motion-reduce:animate-none', className)} />;
}

// A list of card-shaped placeholders: `count` cards of `lines` text lines each.
export function CardSkeletons({ count = 3, lines = 3, label }: { count?: number; lines?: number; label?: string }) {
  return (
    <div role="status" aria-label={label} className="flex flex-col gap-3">
      {Array.from({ length: count }, (_, index) => (
        <div key={index} className="flex flex-col gap-2.5 rounded-card border border-line bg-surface p-3">
          <div className="flex items-center justify-between gap-3">
            <Skeleton className="h-5 w-24" />
            <Skeleton className="h-6 w-20 rounded-full" />
          </div>
          {Array.from({ length: lines }, (_, line) => (
            <Skeleton key={line} className={cx('h-4', line === 0 ? 'w-3/4' : 'w-1/2')} />
          ))}
        </div>
      ))}
    </div>
  );
}
