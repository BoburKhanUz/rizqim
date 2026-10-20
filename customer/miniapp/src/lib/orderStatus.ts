import type { LucideIcon } from 'lucide-react';
import { getOrderStatusLook as getStyle, isOrderStatus, TONE_BADGE, type StatusTone } from '@rizqim/ui';
import type { OrderStatus } from '../types';

// How an order status looks, everywhere (components/ui/StatusBadge.tsx draws it). The table of tones, icons and badge
// colours is @rizqim/ui's (packages/ts/ui/src/lib/orderStatus.ts, shared with the partner web); this adds the
// translation keys of the Mini App's labels. Four semantic groups and neutral (docs/DESIGN.md, "Holat ranglari"):
//   warning  new                       waiting for the store
//   info     accepted ... on_the_way    in progress; each stage has its own icon and label
//   success  delivered
//   danger   canceled, rejected
//   neutral  refunded, and any status this app does not know
// Colour alone never tells two statuses apart: the icon and the label do.

export { isOrderStatus, TONE_BADGE, type StatusTone };

// Translation keys of a status label (store/languageStore.ts has both sets in every language)
export type OrderStatusLabelKey = `status_${OrderStatus}`;
export type StaffOrderStatusLabelKey = `staffStatus_${OrderStatus}`;

export interface OrderStatusLook {
  labelKey: OrderStatusLabelKey | null; // what the customer reads; null: a status this app does not know
  staffLabelKey: StaffOrderStatusLabelKey | null; // what the staff reads ("Yangi" rather than "Kutilmoqda")
  tone: StatusTone;
  icon: LucideIcon;
  badge: string; // background and text classes of the badge (design tokens)
}

// How to show `status`. Never another status' look: one the app has no entry for (the backend got a new one)
// is neutral and keeps its own name.
export function getOrderStatusLook(status: string): OrderStatusLook {
  const style = getStyle(status);
  if (!isOrderStatus(status)) return { labelKey: null, staffLabelKey: null, ...style };
  return { labelKey: `status_${status}`, staffLabelKey: `staffStatus_${status}`, ...style };
}
