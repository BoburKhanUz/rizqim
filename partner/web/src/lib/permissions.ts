import {
  Armchair,
  BadgeCheck,
  CalendarCheck,
  CalendarDays,
  ChartColumn,
  CookingPot,
  ClipboardList,
  Coins,
  HandCoins,
  History,
  Hourglass,
  LayoutDashboard,
  Map,
  PackageX,
  PiggyBank,
  Printer,
  ReceiptText,
  Sandwich,
  Scale,
  Settings,
  ShieldCheck,
  Store,
  Timer,
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
//   dailymenu  "Bugungi menyu" and kompleks (menu.manage, or stoplist.manage for the manager who runs the day; P6-15)
//   yield      the yield report and the write-offs (reports.view; P6-15)
//   staffpay   wage rules, work time and pay statements, "Ish haqi" (staff_pay.view / staff_pay.manage; P6-23)
//   branches   edit a branch (branches.manage, a branch-bound manager their own) or create/delete (settings.manage)
//   zones      draw delivery zones (zones.manage, owner only)
//   floor      halls, places, the floor map, room tariffs, per-guest items (tables.manage; tariffs and guest items inside need
//              settings.manage, changing halls the owner or manager role)
//   bookings   the booking calendar (bookings.manage)
//   accounts   customer accounts, nasiya (customer_accounts.manage)
//   shifts     shifts with X and Z reports (pos.close_shift or reports.view)
//   tips       tips (choy puli): report, pool split, tip settings, service charge rules (reports.view / tips.manage /
//              settings.manage; P6-22)
//   possetup   kitchen stations, routes, printers, branch POS settings (settings.manage)
//   kitchentimes the prep time report of the kitchen display, per station, dish and hour (reports.view; P6-33)
//   permissions what each role may do alone, the "Huquqlar" page (permissions.manage, the owner)
//   audit      the store's harakatlar jurnali (audit.view, the owner by default; P4-32, the old store panel's log)
//   billing    the plan, devices, invoices and how to pay, "Tarif va to'lov" (finance.view)
//   fiscal     MXIK readiness (menu.view / settings.manage), fiscal settings (settings.manage), receipts (finance.view / reports.view);
//              the section opens for menu.manage (not every menu.view: cooks and waiters have it), settings.manage, finance.view, reports.view
export type NavKey =
  | 'dashboard'
  | 'orders'
  | 'bookings'
  | 'floor'
  | 'menu'
  | 'stoplist'
  | 'batches'
  | 'combos'
  | 'dailymenu'
  | 'yield'
  | 'kitchentimes'
  | 'staffpay'
  | 'shifts'
  | 'tips'
  | 'accounts'
  | 'staff'
  | 'branches'
  | 'zones'
  | 'settings'
  | 'possetup'
  | 'permissions'
  | 'audit'
  | 'fiscal'
  | 'reports'
  | 'finance'
  | 'billing';

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
  { key: 'bookings', href: '/bookings', label: 'nav_bookings', anyOf: ['bookings.manage'], icon: CalendarDays },
  { key: 'floor', href: '/floor', label: 'nav_floor', anyOf: ['tables.manage'], icon: Armchair },
  { key: 'menu', href: '/menu', label: 'nav_menu', anyOf: ['menu.view', 'menu.manage'], icon: UtensilsCrossed },
  { key: 'stoplist', href: '/stoplist', label: 'nav_stoplist', anyOf: ['stoplist.manage'], icon: PackageX },
  { key: 'batches', href: '/stoplist/batches', label: 'nav_batches', anyOf: ['batches.manage'], icon: CookingPot },
  // P6-31: the combo constructor (module combo)
  { key: 'combos', href: '/menu/combos', label: 'nav_combos', anyOf: ['menu.view', 'menu.manage'], icon: Sandwich },
  { key: 'dailymenu', href: '/daily-menu', label: 'nav_dailymenu', anyOf: ['menu.manage', 'stoplist.manage'], icon: CalendarCheck },
  { key: 'yield', href: '/yield', label: 'nav_yield', anyOf: ['reports.view'], icon: Scale },
  // P6-33: the kitchen's prep times (KDS v2)
  { key: 'kitchentimes', href: '/kitchen-times', label: 'kds_nav', anyOf: ['reports.view'], icon: Hourglass },
  { key: 'staffpay', href: '/staff-pay', label: 'nav_staffpay', anyOf: ['staff_pay.view', 'staff_pay.manage'], icon: Coins },
  { key: 'shifts', href: '/shifts', label: 'nav_shifts', anyOf: ['pos.close_shift', 'reports.view'], icon: Timer },
  { key: 'tips', href: '/tips', label: 'nav_tips', anyOf: ['reports.view', 'tips.manage', 'settings.manage'], icon: PiggyBank },
  { key: 'accounts', href: '/accounts', label: 'nav_accounts', anyOf: ['customer_accounts.manage'], icon: HandCoins },
  { key: 'staff', href: '/staff', label: 'nav_staff', anyOf: ['staff.view'], icon: Users },
  { key: 'branches', href: '/branches', label: 'nav_branches', anyOf: ['branches.manage', 'settings.manage'], icon: Store },
  { key: 'zones', href: '/zones', label: 'nav_zones', anyOf: ['zones.manage'], icon: Map },
  { key: 'settings', href: '/settings', label: 'nav_settings', anyOf: ['settings.manage'], icon: Settings },
  { key: 'possetup', href: '/pos-setup', label: 'nav_possetup', anyOf: ['settings.manage'], icon: Printer },
  { key: 'permissions', href: '/permissions', label: 'nav_permissions', anyOf: ['permissions.manage'], icon: ShieldCheck },
  { key: 'audit', href: '/audit', label: 'nav_audit', anyOf: ['audit.view'], icon: History },
  { key: 'fiscal', href: '/fiscal', label: 'nav_fiscal', anyOf: ['settings.manage', 'menu.manage', 'finance.view', 'reports.view'], icon: ReceiptText },
  { key: 'reports', href: '/reports', label: 'nav_reports', anyOf: ['reports.view'], icon: ChartColumn },
  { key: 'finance', href: '/finance', label: 'nav_finance', anyOf: ['finance.view'], icon: Wallet },
  { key: 'billing', href: '/billing', label: 'nav_billing', anyOf: ['finance.view'], icon: BadgeCheck },
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
