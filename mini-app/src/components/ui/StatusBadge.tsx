import { getOrderStatusLook } from '../../lib/orderStatus';
import { useLanguageStore } from '../../store/languageStore';
import { cx } from './cx';

interface StatusBadgeProps {
  status: string;
  // customer: "Kutilmoqda"; staff: "Yangi" (the staff's words for the same status)
  audience?: 'customer' | 'staff';
  className?: string;
}

// The badge of an order status: semantic colour + an icon + a label (lib/orderStatus.ts has the table).
export function StatusBadge({ status, audience = 'customer', className }: StatusBadgeProps) {
  const t = useLanguageStore((state) => state.t);
  // Subscribing to the language re-renders the badge when it changes (t itself never changes)
  useLanguageStore((state) => state.language);
  const look = getOrderStatusLook(status);
  const key = audience === 'staff' ? look.staffLabelKey : look.labelKey;
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
      <span>{key ? t(key) : status}</span>
    </span>
  );
}
