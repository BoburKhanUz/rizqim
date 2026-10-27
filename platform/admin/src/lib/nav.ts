import {
  Activity,
  ClipboardList,
  Cpu,
  HandCoins,
  History,
  LayoutDashboard,
  Megaphone,
  MonitorSmartphone,
  Plug,
  ShieldCheck,
  Store,
  Users,
  UsersRound,
  type LucideIcon,
} from 'lucide-react';
import type { MessageKey } from '@/i18n';

// The sections of the control center, in the order of the sidebar (docs/PHASE4_PLAN.md, section 4), and the admin
// permissions (without the "admin." prefix, see `hasPermission`) that open each one: a section shows when the member has ANY
// of them. An item without permissions is open to every signed-in member. The backend decides what a member may do; this only
// hides links the backend would refuse anyway.
//
// Stage B (P4-13 Bugun, P4-14 Buyurtmalar, P4-15 Monitoring) replaces the placeholder in app/(app)/<section>/page.tsx; the
// sidebar entries, permissions and routes are already here.
export type NavKey =
  | 'today'
  | 'orders'
  | 'stores'
  | 'pos'
  | 'finance'
  | 'customers'
  | 'marketing'
  | 'monitoring'
  | 'integrations'
  | 'team'
  | 'roles'
  | 'audit'
  | 'sessions';

export interface NavItem {
  key: NavKey;
  href: string;
  label: MessageKey;
  anyOf: readonly string[];
  icon: LucideIcon;
  // Other paths that belong to the section (search results point at /users/12 inside "Mijozlar va yordam")
  alsoPaths?: readonly string[];
  // Built in this stage (false: the page is a calm placeholder)
  built: boolean;
}

export const NAV_ITEMS: readonly NavItem[] = [
  { key: 'today', href: '/today', label: 'nav_today', anyOf: ['today.view'], icon: LayoutDashboard, built: false },
  { key: 'orders', href: '/orders', label: 'nav_orders', anyOf: ['orders.view'], icon: ClipboardList, built: false },
  { key: 'stores', href: '/stores', label: 'nav_stores', anyOf: ['stores.view'], icon: Store, built: false },
  { key: 'pos', href: '/pos', label: 'nav_pos', anyOf: ['subscriptions.manage', 'devices.view'], icon: MonitorSmartphone, built: false },
  { key: 'finance', href: '/finance', label: 'nav_finance', anyOf: ['finance.view'], icon: HandCoins, built: false },
  { key: 'customers', href: '/customers', label: 'nav_customers', anyOf: ['users.view', 'support.view'], icon: UsersRound, alsoPaths: ['/users'], built: false },
  { key: 'marketing', href: '/marketing', label: 'nav_marketing', anyOf: ['marketing.manage'], icon: Megaphone, built: false },
  { key: 'monitoring', href: '/monitoring', label: 'nav_monitoring', anyOf: ['monitoring.view'], icon: Activity, built: false },
  { key: 'integrations', href: '/integrations', label: 'nav_integrations', anyOf: ['integrations.view'], icon: Plug, built: false },
  // Tizim
  { key: 'team', href: '/team', label: 'nav_team', anyOf: ['team.manage'], icon: Users, built: true },
  { key: 'roles', href: '/roles', label: 'nav_roles', anyOf: ['team.manage'], icon: ShieldCheck, built: true },
  { key: 'audit', href: '/audit', label: 'nav_audit', anyOf: ['audit.view'], icon: History, built: true },
  { key: 'sessions', href: '/sessions', label: 'nav_sessions', anyOf: [], icon: Cpu, built: true },
];

// The last four items are the "Tizim" group
export const SYSTEM_KEYS: readonly NavKey[] = ['team', 'roles', 'audit', 'sessions'];

// Where a signed-in member always can go
export const FALLBACK_HOME = '/sessions';

// Permissions are named "admin.<name>" by the backend; both spellings are accepted
const bare = (permission: string) => (permission.startsWith('admin.') ? permission.slice('admin.'.length) : permission);

export function hasPermission(granted: readonly string[], required: string): boolean {
  const wanted = bare(required);
  return granted.some((permission) => bare(permission) === wanted);
}

export const hasAny = (granted: readonly string[], required: readonly string[]): boolean => required.length === 0 || required.some((permission) => hasPermission(granted, permission));

export const canOpen = (item: NavItem, granted: readonly string[]): boolean => hasAny(granted, item.anyOf);

export const visibleNav = (granted: readonly string[]): NavItem[] => NAV_ITEMS.filter((item) => canOpen(item, granted));

const inPath = (pathname: string, base: string) => pathname === base || pathname.startsWith(`${base}/`);

// The section a path belongs to (`/orders/abc` → orders)
export const sectionOf = (pathname: string): NavItem | undefined => NAV_ITEMS.find((item) => inPath(pathname, item.href) || item.alsoPaths?.some((path) => inPath(pathname, path)));

// Where to land after signing in: the first section the member may open
export const homePath = (granted: readonly string[]): string => visibleNav(granted)[0]?.href ?? FALLBACK_HOME;

