import { NavLink } from 'react-router-dom';
import { useCartStore } from '../../store/cartStore';
import { useLanguageStore } from '../../store/languageStore';
import { selectionChanged } from '../../lib/twa';
import { cx } from '@rizqim/ui';
import type { NavItem } from './navItems';

// The tab bar at the bottom of the dock, for every role (the items come from navItems.ts). It pads itself by the
// bottom safe area, so its background reaches the edge of the screen.
export function BottomNav({ items }: { items: NavItem[] }) {
  const { t } = useLanguageStore();
  const totalItems = useCartStore((state) => state.getTotalItems());

  return (
    <nav
      aria-label="Asosiy navigatsiya"
      className="flex items-stretch justify-around border-t border-line bg-surface/95 pb-[var(--rz-inset-bottom)] backdrop-blur-md"
    >
      {items.map((item) => {
        const Icon = item.icon;
        const badge = item.cartBadge && totalItems > 0 ? totalItems : null;
        return (
          <NavLink
            key={item.to}
            to={item.to}
            end
            onClick={() => selectionChanged()}
            className={({ isActive }) =>
              cx(
                'flex min-h-14 flex-1 flex-col items-center justify-center gap-1 py-2 text-caption transition-colors',
                'focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand',
                isActive ? 'font-bold text-brand' : 'font-medium text-fg-muted',
              )
            }
          >
            <span className="relative">
              <Icon aria-hidden="true" className="size-6" />
              {badge !== null && (
                <span className="absolute -right-2.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-caption font-bold text-on-danger ring-2 ring-surface">
                  {badge}
                </span>
              )}
            </span>
            <span>{t(item.labelKey)}</span>
          </NavLink>
        );
      })}
    </nav>
  );
}
