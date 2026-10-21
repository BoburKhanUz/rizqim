import {
  ChartColumn,
  CookingPot,
  ClipboardList,
  LayoutDashboard,
  Map,
  PackageX,
  Settings,
  Store,
  UtensilsCrossed,
  Users,
  Wallet,
  type LucideIcon,
} from 'lucide-react';
import type { MessageKey } from '@/i18n';

// The sections of the panel and the backend permissions (docs/AUTH.md, GET /me/permissions) that open each one: a
// section shows when the person has ANY of its permissions. The backend decides what a person may do; this only hides
// links to sections the backend would refuse anyway (and each page still shows the server's 403 when it comes).
//   dashboard  today's board summary (orders.view) and the analytics (reports.view)
//   stoplist   the stop-list of a branch (stoplist.manage)
//   batches    the batches (kazan) of a branch, /stoplist/batches (batches.manage: cooks have it without the stop-list)
//   branches   edit a branch (branches.manage, a branch-bound manager their own) or create/delete (settings.manage)
//   zones      draw delivery zones (zones.manage, owner only)
export type NavKey = 'dashboard' | 'orders' | 'menu' | 'stoplist' | 'batches' | 'staff' | 'branches' | 'zones' | 'settings' | 'reports' | 'finance';

export interface NavItem {
  key: NavKey;
  href: string;
  label: MessageKey;
  anyOf: readonly string[];
  icon: LucideIcon;
}

export const NAV_ITEMS: readonly NavItem[] = [
  { key: 'dashboard', href: '/dashboard', label: 'nav_dashboard', anyOf: ['orders.view', 'reports.view'], icon: LayoutDashboard },
  { key: 'orders', href: '/orders', label: 'nav_orders', anyOf: ['orders.view'], icon: ClipboardList },
  { key: 'menu', href: '/menu', label: 'nav_menu', anyOf: ['menu.view', 'menu.manage'], icon: UtensilsCrossed },
  { key: 'stoplist', href: '/stoplist', label: 'nav_stoplist', anyOf: ['stoplist.manage'], icon: PackageX },
  { key: 'batches', href: '/stoplist/batches', label: 'nav_batches', anyOf: ['batches.manage'], icon: CookingPot },
  { key: 'staff', href: '/staff', label: 'nav_staff', anyOf: ['staff.view'], icon: Users },
  { key: 'branches', href: '/branches', label: 'nav_branches', anyOf: ['branches.manage', 'settings.manage'], icon: Store },
  { key: 'zones', href: '/zones', label: 'nav_zones', anyOf: ['zones.manage'], icon: Map },
  { key: 'settings', href: '/settings', label: 'nav_settings', anyOf: ['settings.manage'], icon: Settings },
  { key: 'reports', href: '/reports', label: 'nav_reports', anyOf: ['reports.view'], icon: ChartColumn },
  { key: 'finance', href: '/finance', label: 'nav_finance', anyOf: ['finance.view'], icon: Wallet },
];

// The person has at least one of `required`
export const hasAny = (permissions: readonly string[], required: readonly string[]): boolean => required.some((p) => permissions.includes(p));

export const canOpen = (item: NavItem, permissions: readonly string[]): boolean => hasAny(permissions, item.anyOf);

// The sections `permissions` opens, in menu order
export const visibleNav = (permissions: readonly string[]): NavItem[] => NAV_ITEMS.filter((item) => canOpen(item, permissions));

const inSection = (pathname: string, item: NavItem) => pathname === item.href || pathname.startsWith(`${item.href}/`);

// The section a path belongs to, the most specific one (`/orders/abc` → orders, `/stoplist/batches` → batches)
export const sectionOf = (pathname: string): NavItem | undefined =>
  NAV_ITEMS.filter((item) => inSection(pathname, item)).sort((a, b) => b.href.length - a.href.length)[0];

// Where to land after signing in: the first section the person may open
export const homePath = (permissions: readonly string[]): string | null => visibleNav(permissions)[0]?.href ?? null;
