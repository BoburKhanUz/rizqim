import { Bike, CheckCircle2, Clock, MessageSquare, PackageCheck, ShieldAlert, Sparkles, TriangleAlert, UserCheck, XCircle, type LucideIcon } from 'lucide-react';

// What a notification type looks like (pages/Notifications.tsx): an icon on a soft tile
// of one tone. The icon is never the only cue: the notification's title says what it is.

export type NotificationTone = 'success' | 'danger' | 'info' | 'brand' | 'warning' | 'neutral';

const LOOK: Record<string, { icon: LucideIcon; tone: NotificationTone }> = {
  delivered: { icon: CheckCircle2, tone: 'success' },
  accepted: { icon: CheckCircle2, tone: 'success' },
  store_payment_required: { icon: ShieldAlert, tone: 'danger' },
  complaint_reply: { icon: MessageSquare, tone: 'info' },
  new_order: { icon: Sparkles, tone: 'brand' },
  on_the_way: { icon: Bike, tone: 'info' },
  canceled: { icon: XCircle, tone: 'danger' },
  courier_assigned: { icon: UserCheck, tone: 'info' },
  courier_ready: { icon: PackageCheck, tone: 'info' },
  courier_delay_warning: { icon: TriangleAlert, tone: 'warning' },
};

// The API sends "telegram_customer_accepted", "telegram_manager_new_order", "complaint_reply" ...: the audience prefix says
// who it was for, the rest what happened
const AUDIENCE_PREFIX = /^telegram_(customer|manager|courier|branch)_/;

export const getNotificationLook = (type: string): { icon: LucideIcon; tone: NotificationTone } => {
  const kinds = [type, type.replace(AUDIENCE_PREFIX, ''), type.replace(/^telegram_/, '')];
  const known = kinds.find((kind) => Object.hasOwn(LOOK, kind));
  return known ? LOOK[known] : { icon: Clock, tone: 'neutral' };
};

// Background and icon colour of the tile (design tokens: readable in both themes)
export const NOTIFICATION_TILE: Record<NotificationTone, string> = {
  success: 'bg-success-soft text-success-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  brand: 'bg-brand-soft text-brand-soft-fg',
  neutral: 'bg-surface-2 text-fg-muted',
};
