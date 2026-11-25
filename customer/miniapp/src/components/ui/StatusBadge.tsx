import { StatusBadge as BaseStatusBadge } from '@rizqim/ui';
import { getOrderStatusLook } from '../../lib/orderStatus';
import { useLanguageStore, type TranslationKey } from '../../store/languageStore';

interface StatusBadgeProps {
  status: string;
  // customer: "Kutilmoqda"; staff: "Yangi" (the staff's words for the same status)
  audience?: 'customer' | 'staff';
  // Words that say more than the status (owner decision 4: a refund the restaurant still has to make)
  labelKey?: TranslationKey;
  className?: string;
}

// The badge of an order status: semantic colour + an icon + a label (lib/orderStatus.ts has the table). The look
// comes from @rizqim/ui; the words from the Mini App's language store.
export function StatusBadge({ status, audience = 'customer', labelKey, className }: StatusBadgeProps) {
  const t = useLanguageStore((state) => state.t);
  // Subscribing to the language re-renders the badge when it changes (t itself never changes)
  useLanguageStore((state) => state.language);
  const look = getOrderStatusLook(status);
  const key = labelKey ?? (audience === 'staff' ? look.staffLabelKey : look.labelKey);
  return <BaseStatusBadge status={status} label={key ? t(key) : status} className={className} />;
}
