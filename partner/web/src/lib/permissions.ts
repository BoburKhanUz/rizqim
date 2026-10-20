import {
  ClipboardList,
  LayoutDashboard,
  PackageX,
  Settings,
  Store,
  UtensilsCrossed,
  Users,
  Wallet,
  ChartColumn,
  type LucideIcon,
} from 'lucide-react';
import type { MessageKey } from '@/i18n';

// The sections of the panel and the backend permission (docs/AUTH.md, GET /me/permissions) that opens each one. The
// backend decides what a person may do; this only hides links to sections the backend would refuse anyway.
// "Filiallar" has no permission of its own in the backend yet: it follows settings.manage (owner).
export interface NavItem {
  key: 'dashboard' | 'orders' | 'menu' | 'stoplist' | 'branches' | 'staff' | 'reports' | 'finance' | 'settings';
  href: string;
  label: MessageKey;
  permission: string;
  icon: LucideIcon;
}

export const NAV_ITEMS: readonly NavItem[] = [
  { key: 'dashboard', href: '/dashboard', label: 'nav_dashboard', permission: 'reports.view', icon: LayoutDashboard },
  { key: 'orders', href: '/orders', label: 'nav_orders', permission: 'orders.view', icon: ClipboardList },
  { key: 'menu', href: '/menu', label: 'nav_menu', permission: 'menu.view', icon: UtensilsCrossed },
  { key: 'stoplist', href: '/stoplist', label: 'nav_stoplist', permission: 'stoplist.manage', icon: PackageX },
  { key: 'branches', href: '/branches', label: 'nav_branches', permission: 'settings.manage', icon: Store },
  { key: 'staff', href: '/staff', label: 'nav_staff', permission: 'staff.view', icon: Users },
  { key: 'reports', href: '/reports', label: 'nav_reports', permission: 'reports.view', icon: ChartColumn },
  { key: 'finance', href: '/finance', label: 'nav_finance', permission: 'finance.view', icon: Wallet },
  { key: 'settings', href: '/settings', label: 'nav_settings', permission: 'settings.manage', icon: Settings },
];

// The sections `permissions` opens, in menu order
export const visibleNav = (permissions: readonly string[]): NavItem[] => NAV_ITEMS.filter((item) => permissions.includes(item.permission));

// Where to land after signing in: the first section the person may open
export const homePath = (permissions: readonly string[]): string | null => visibleNav(permissions)[0]?.href ?? null;
