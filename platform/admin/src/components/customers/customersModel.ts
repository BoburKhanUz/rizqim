import type { PillTone } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import type { ComplaintFilter, RoleFilter, SupportStatus } from '@/lib/api/customers';
import { exactMoney } from '../orders/ordersModel';

// Words and tones of the Mijozlar va yordam screens: a state is always said in words, the colour only repeats it.

export { exactMoney as uzs };

export const CUSTOMER_TABS = ['users', 'complaints', 'suspicious'] as const;
export type CustomerTab = (typeof CUSTOMER_TABS)[number];
export const isCustomerTab = (value: string | undefined): value is CustomerTab => (CUSTOMER_TABS as readonly string[]).includes(value ?? '');

export const TAB_LABEL: Record<CustomerTab, MessageKey> = {
  users: 'customers_tab_users',
  complaints: 'customers_tab_complaints',
  suspicious: 'customers_tab_suspicious',
};

// Which permission opens each tab
export const TAB_PERMISSION: Record<CustomerTab, string> = { users: 'users.view', complaints: 'support.view', suspicious: 'support.view' };

export const ROLE_LABEL: Record<RoleFilter, MessageKey> = {
  '': 'customers_role_all',
  customer: 'customers_role_customer',
  courier: 'customers_role_courier',
  staff: 'customers_role_staff',
  owner: 'customers_role_staff',
  manager: 'customers_role_staff',
  cashier: 'customers_role_staff',
  waiter: 'customers_role_staff',
  cook: 'customers_role_staff',
};

export const COMPLAINT_FILTER_LABEL: Record<ComplaintFilter, MessageKey> = {
  open: 'customers_cf_open',
  new: 'customers_cf_new',
  in_progress: 'customers_cf_in_progress',
  answered: 'customers_cf_answered',
  resolved: 'customers_cf_resolved',
  all: 'customers_cf_all',
};

export const SUPPORT_TONE: Record<SupportStatus, PillTone> = { new: 'info', in_progress: 'warning', answered: 'neutral', resolved: 'success' };

export const CARD_TABS = ['overview', 'orders', 'keshbek', 'complaints', 'sessions', 'audit'] as const;
export type UserCardTab = (typeof CARD_TABS)[number];

// "+150 000 so'm" / "−50 000 so'm"
export function signed(value: string): string {
  const negative = value.trim().startsWith('-');
  return `${negative ? '−' : '+'}${exactMoney(negative ? value.trim().slice(1) : value)}`;
}

// A link to the complaint inside the inbox (Bugun's attention rows land here too)
export const complaintHref = (uuid: string) => `/customers?tab=complaints&complaint=${encodeURIComponent(uuid)}`;
