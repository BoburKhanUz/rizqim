import {
  Ban,
  Bike,
  CheckCircle,
  CheckCircle2,
  ChefHat,
  CircleHelp,
  Clock,
  PackageCheck,
  Undo2,
  UserCheck,
  XCircle,
  type LucideIcon,
} from 'lucide-react';
import type { OrderStatus } from './types';

// How an order status looks, everywhere (components/StatusBadge.tsx draws it). One table, four semantic groups
// and neutral (docs/DESIGN.md, "Holat ranglari"):
//   warning  new                       waiting for the store
//   info     accepted ... on_the_way    in progress; each stage has its own icon
//   success  delivered
//   danger   canceled, rejected
//   neutral  refunded, and any status the app does not know
// Colour alone never tells two statuses apart: the icon and the label (the app's words) do.

export type StatusTone = 'warning' | 'info' | 'success' | 'danger' | 'neutral';

export interface OrderStatusStyle {
  tone: StatusTone;
  icon: LucideIcon;
  badge: string; // background and text classes of the badge (design tokens)
}

// Badge colours of each tone: soft fill + its text colour, >= 4.5:1 in both themes (docs/DESIGN.md)
export const TONE_BADGE: Record<StatusTone, string> = {
  warning: 'bg-warning-soft text-warning-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
  success: 'bg-success-soft text-success-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  neutral: 'bg-surface-2 text-fg-muted',
};

// One entry per App\Enums\OrderStatus value (`Record` makes the compiler ask for an entry when the enum grows).
// on_the_way and delivery are two names for the same stage and look the same.
const STATUS: Record<OrderStatus, { tone: StatusTone; icon: LucideIcon }> = {
  new: { tone: 'warning', icon: Clock },
  accepted: { tone: 'info', icon: CheckCircle2 },
  preparing: { tone: 'info', icon: ChefHat },
  ready: { tone: 'info', icon: PackageCheck },
  courier_assigned: { tone: 'info', icon: UserCheck },
  on_the_way: { tone: 'info', icon: Bike },
  delivery: { tone: 'info', icon: Bike },
  delivered: { tone: 'success', icon: CheckCircle },
  canceled: { tone: 'danger', icon: XCircle },
  rejected: { tone: 'danger', icon: Ban },
  refunded: { tone: 'neutral', icon: Undo2 },
};

export const isOrderStatus = (status: string): status is OrderStatus => Object.hasOwn(STATUS, status);

// How to show `status`. Never another status' look: one the app has no entry for (the backend got a new one)
// is neutral.
export function getOrderStatusLook(status: string): OrderStatusStyle {
  if (!isOrderStatus(status)) return { tone: 'neutral', icon: CircleHelp, badge: TONE_BADGE.neutral };
  const { tone, icon } = STATUS[status];
  return { tone, icon, badge: TONE_BADGE[tone] };
}
