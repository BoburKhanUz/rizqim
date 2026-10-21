import { NavLink } from 'react-router-dom';
import { ChevronRight, ShoppingBag } from 'lucide-react';
import { useCartStore } from '../../store/cartStore';
import { useLanguageStore } from '../../store/languageStore';
import { formatMoney } from '../../lib/format';
import { hapticFeedback } from '../../lib/telegram';

// The "view cart" bar of the shop screens, in the bottom dock right above the navigation.
export function FloatingCartBar() {
  const { t, language } = useLanguageStore();
  // Select the computed values, not the getter functions: a getter's reference never changes, so the bar would not
  // re-render when items are added
  const totalItems = useCartStore((state) => state.getTotalItems());
  const totalAmount = useCartStore((state) => state.getSubtotal());

  if (totalItems === 0) return null;

  return (
    <div className="px-4 pb-3 animate-scale-up">
      <NavLink
        to="/cart"
        onClick={() => hapticFeedback('medium')}
        className="flex min-h-13 w-full items-center justify-between gap-3 rounded-card bg-brand px-4 text-on-brand shadow-lg transition-transform motion-safe:active:scale-[0.98]"
      >
        <span className="flex min-w-0 items-center gap-2.5">
          <span className="flex items-center gap-1 rounded-control border border-on-brand/50 px-2 py-0.5 text-footnote font-bold">
            <ShoppingBag aria-hidden="true" className="size-4" />
            {totalItems}
          </span>
          <span className="truncate text-body font-bold">{t('viewCart')}</span>
        </span>
        <span className="flex shrink-0 items-center gap-1 whitespace-nowrap text-body font-bold">
          {formatMoney(totalAmount, language)}
          <ChevronRight aria-hidden="true" className="size-5" />
        </span>
      </NavLink>
    </div>
  );
}
