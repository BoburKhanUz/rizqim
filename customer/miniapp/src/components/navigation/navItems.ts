import {
  BarChart3,
  ClipboardList,
  Home as HomeIcon,
  LayoutGrid,
  List,
  PackageX,
  ShoppingBag,
  User as UserIcon,
  Users,
  type LucideIcon,
} from 'lucide-react';
import type { TranslationKey } from '../../store/languageStore';
import type { UserRole } from '../../types';

export interface NavItem {
  to: string;
  icon: LucideIcon;
  labelKey: TranslationKey;
  // Shows the number of items in the cart
  cartBadge?: boolean;
}

const PROFILE: NavItem = { to: '/profile', icon: UserIcon, labelKey: 'nav_profile' };

const CUSTOMER: NavItem[] = [
  { to: '/', icon: HomeIcon, labelKey: 'catalog' },
  { to: '/cart', icon: ShoppingBag, labelKey: 'cart', cartBadge: true },
  { to: '/orders', icon: List, labelKey: 'myOrders' },
  PROFILE,
];

const STAFF: Partial<Record<UserRole, NavItem[]>> = {
  owner: [
    { to: '/admin/analytics', icon: BarChart3, labelKey: 'nav_analytics' },
    { to: '/admin/catalog', icon: LayoutGrid, labelKey: 'nav_menu' },
    { to: '/manager', icon: ClipboardList, labelKey: 'nav_orders' },
    { to: '/admin/employees', icon: Users, labelKey: 'nav_employees' },
    PROFILE,
  ],
  manager: [
    { to: '/manager', icon: ClipboardList, labelKey: 'nav_orders' },
    { to: '/admin/stoplist', icon: PackageX, labelKey: 'nav_stoplist' },
    PROFILE,
  ],
  courier: [{ to: '/courier', icon: ClipboardList, labelKey: 'nav_deliveries' }, PROFILE],
};

// The tabs of the bottom navigation of `role` (customers and anyone without a staff role get the shop's).
export function getNavItems(role: UserRole | null | undefined): NavItem[] {
  return (role && STAFF[role]) || CUSTOMER;
}

// Screens without a way "back": every tab of every role, and the order confirmation. Everywhere else Telegram's
// BackButton is shown (hooks/useTelegramBackButton.ts).
export const ROOT_PATHS: ReadonlySet<string> = new Set([
  ...CUSTOMER.map((item) => item.to),
  ...Object.values(STAFF).flatMap((items) => items.map((item) => item.to)),
  '/success',
]);

// The same for one role: a screen that is a tab of the manager's (the stop-list) is a page with a way back for the owner,
// who opens it from the menu.
export function getRootPaths(role: UserRole | null | undefined): ReadonlySet<string> {
  return new Set([...getNavItems(role).map((item) => item.to), '/success']);
}
