import { Check } from 'lucide-react';
import type { StaffOrderItem } from '@rizqim/staff';
import { useLanguageStore } from '../../store/languageStore';
import { isLineReady, lineModifiers, lineQuantity } from '../../lib/staffOrders';
import { useStaffText } from '../../hooks/useStaffText';
import { Pill } from './Pill';
import { ORDER_TEXT } from './orderText';
import { cx } from '../ui';

// The lines of a staff order, the way the kitchen reads them: name and quantity (a weight as "1,5 kg", pieces as "x2"), the
// chosen modifiers under it, and the "Tayyor" mark of a line the cook has finished. `prices` adds each line's total (the
// detail screen; the list does not need it).
export function OrderLines({ items, prices = false, formatPrice }: { items: readonly StaffOrderItem[]; prices?: boolean; formatPrice?: (value: string) => string }) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(ORDER_TEXT);

  return (
    <ul className="flex flex-col gap-2 text-body text-fg">
      {items.map((item) => {
        const modifiers = lineModifiers(item);
        const ready = isLineReady(item);
        return (
          <li key={item.id} className="flex items-start gap-2" data-kitchen-status={item.kitchen_status}>
            <div className="min-w-0 flex-1">
              <p className={cx(ready && 'text-fg-muted')}>
                <span className="font-medium">
                  {item.product_name} {lineQuantity(item, language)}
                </span>
                {item.variant_name && <span className="ml-1.5 text-footnote text-fg-muted">{item.variant_name}</span>}
              </p>
              {modifiers && <p className="text-footnote text-fg-muted">{modifiers}</p>}
            </div>
            {prices && formatPrice && item.total_price !== undefined && <span className="shrink-0 text-footnote font-bold text-fg">{formatPrice(item.total_price)}</span>}
            {ready && (
              <Pill tone="success" icon={Check} className="shrink-0">
                {tx('lineReady')}
              </Pill>
            )}
          </li>
        );
      })}
    </ul>
  );
}
