import { formatMoney } from '@rizqim/ui';
import type { PillTone } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import type { DeviceState, SubscriptionStatus } from '@/lib/api/pos';

// Words and tones of the POS va obunalar screens: a state is always said in words, the colour only repeats it.

export const money = (value: string | number | null | undefined) => formatMoney(value ?? 0, 'uz');

export const TABS = ['subscriptions', 'plans', 'invoices', 'devices', 'shifts', 'fiscal', 'sync'] as const;
export type PosTab = (typeof TABS)[number];
export const isTab = (value: string | undefined): value is PosTab => TABS.includes(value as PosTab);

// The permissions that open each tab (the backend checks the same): the money side or the tills side
export const TAB_PERMISSIONS: Record<PosTab, readonly string[]> = {
  subscriptions: ['subscriptions.manage', 'finance.view'],
  plans: ['subscriptions.manage', 'finance.view'],
  invoices: ['subscriptions.manage', 'finance.view'],
  devices: ['devices.view'],
  shifts: ['devices.view'],
  fiscal: ['devices.view'],
  sync: ['devices.view'],
};

export const TAB_LABEL: Record<PosTab, MessageKey> = {
  subscriptions: 'pos_tab_subscriptions',
  plans: 'pos_tab_plans',
  invoices: 'pos_tab_invoices',
  devices: 'pos_tab_devices',
  shifts: 'pos_tab_shifts',
  fiscal: 'pos_tab_fiscal',
  sync: 'pos_tab_sync',
};

export const SUBSCRIPTION_TONE: Record<SubscriptionStatus, PillTone> = { trialing: 'info', active: 'success', past_due: 'warning', locked: 'danger', cancelled: 'neutral' };
export const SUBSCRIPTION_LABEL: Record<SubscriptionStatus, MessageKey> = {
  trialing: 'pos_sub_trialing',
  active: 'pos_sub_active',
  past_due: 'pos_sub_past_due',
  locked: 'pos_sub_locked',
  cancelled: 'pos_sub_cancelled',
};

export const INVOICE_TONE: Record<string, PillTone> = { issued: 'warning', paid: 'success', void: 'neutral' };
export const INVOICE_LABEL: Record<'issued' | 'paid' | 'void' | 'overdue', MessageKey> = { issued: 'pos_inv_issued', paid: 'pos_inv_paid', void: 'pos_inv_void', overdue: 'pos_inv_overdue' };
export const DUNNING_LABEL: Record<'reminder_1' | 'reminder_3' | 'reminder_7' | 'locked' | 'unlocked', MessageKey> = {
  reminder_1: 'pos_dun_reminder_1',
  reminder_3: 'pos_dun_reminder_3',
  reminder_7: 'pos_dun_reminder_7',
  locked: 'pos_dun_locked',
  unlocked: 'pos_dun_unlocked',
};

export const DEVICE_TONE: Record<DeviceState, PillTone> = { online: 'success', offline: 'danger', never: 'neutral', revoked: 'neutral' };
export const DEVICE_LABEL: Record<DeviceState, MessageKey> = { online: 'pos_dev_online', offline: 'pos_dev_offline', never: 'pos_dev_never', revoked: 'pos_dev_revoked' };

export const FISCAL_TONE: Record<string, PillTone> = { failed: 'danger', pending: 'warning', issued: 'success' };
export const FISCAL_LABEL: Record<'failed' | 'pending' | 'issued' | 'open' | 'all', MessageKey> = { failed: 'pos_fis_failed', pending: 'pos_fis_pending', issued: 'pos_fis_issued', open: 'pos_fis_open', all: 'pos_fis_all' };

export const CHANNEL_LABEL: Record<string, MessageKey> = {
  rizqim_courier: 'pos_channel_rizqim_courier',
  own_delivery: 'pos_channel_own_delivery',
  pickup: 'pos_channel_pickup',
  pos: 'pos_channel_pos',
  aggregator: 'pos_channel_aggregator',
};

export const SYNC_TONE: Record<string, PillTone> = { rejected: 'danger', conflict: 'warning' };

// "2026-11-10" → "10.11.2026"
export function day(value: string | null | undefined): string {
  if (!value) return '—';
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  return match ? `${match[3]}.${match[2]}.${match[1]}` : value;
}

// A percent from the API ("15.00") without trailing zeros ("15", "2.5")
export const percentText = (value: string | null | undefined) => (value === null || value === undefined ? '—' : `${Number(value)}%`);

// Whole days from `now` to an ISO moment (negative: past); null for nothing
export function daysUntil(iso: string | null | undefined, now = Date.now()): number | null {
  if (!iso) return null;
  const at = Date.parse(iso);
  return Number.isFinite(at) ? Math.ceil((at - now) / 86_400_000) : null;
}

// The words of a moment last seen: "3 daqiqa oldin" is the screen's job, this gives the minutes
export function minutesSince(iso: string | null | undefined, now = Date.now()): number | null {
  if (!iso) return null;
  const at = Date.parse(iso);
  return Number.isFinite(at) ? Math.max(0, Math.floor((now - at) / 60_000)) : null;
}

// A status or stage the backend may add later falls back to a neutral word instead of failing
export const invoiceLabel = (status: string): MessageKey => (status in INVOICE_LABEL ? INVOICE_LABEL[status as keyof typeof INVOICE_LABEL] : 'pos_inv_issued');
export const dunningLabel = (stage: string): MessageKey => (stage in DUNNING_LABEL ? DUNNING_LABEL[stage as keyof typeof DUNNING_LABEL] : 'pos_dun_reminder_1');
export const fiscalLabel = (status: string): MessageKey => (status in FISCAL_LABEL ? FISCAL_LABEL[status as keyof typeof FISCAL_LABEL] : 'pos_fis_pending');
