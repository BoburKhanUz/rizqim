import { Bike, Check, ChefHat, PackageCheck, type LucideIcon } from 'lucide-react';
import type { OrderAction } from '@rizqim/staff';

// How each action of @rizqim/staff's getOrderActions() is drawn on the order screens. Which actions an order has, and their
// words, are the package's; the icons, colours and sizes are the screens'.
export const ACTION_LOOK: Record<OrderAction['id'], { icon?: LucideIcon; variant?: 'danger' | 'success'; basis: string }> = {
  accept: { icon: Check, basis: 'basis-36' },
  reject: { variant: 'danger', basis: 'basis-28' },
  start_preparing: { icon: ChefHat, basis: 'basis-40' },
  mark_ready: { icon: PackageCheck, variant: 'success', basis: 'basis-40' },
  assign_courier: { icon: Bike, basis: 'basis-40' },
  hand_over: { icon: Check, variant: 'success', basis: 'basis-40' },
  pick_up: { icon: Bike, basis: 'basis-40' },
  confirm_delivery: { icon: Check, variant: 'success', basis: 'basis-40' },
  cancel: { variant: 'danger', basis: 'basis-28' },
};
