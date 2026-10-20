import { getOrderStatusLook } from '../lib/orderStatus';
import { cx } from './cx';

interface StatusBadgeProps {
  status: string;
  // The status in the user's language. Without it the raw status is shown.
  label?: string;
  className?: string;
}

// The badge of an order status: semantic colour + an icon + a label (lib/orderStatus.ts has the table).
export function StatusBadge({ status, label, className }: StatusBadgeProps) {
  const look = getOrderStatusLook(status);
  const Icon = look.icon;
  return (
    <span
      className={cx(
        'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-caption font-bold',
        look.badge,
        className,
      )}
    >
      <Icon aria-hidden="true" className="size-3.5 shrink-0" />
      <span>{label ?? status}</span>
    </span>
  );
}
