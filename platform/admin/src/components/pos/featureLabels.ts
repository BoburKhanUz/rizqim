import type { MessageKey, Translate } from '@/i18n';

// The plan features (App\Enums\PlanFeature) in words. A feature the backend adds later shows under its key until it gets a line here.
const FEATURE_LABEL: Record<string, MessageKey> = {
  hall: 'pos_feature_hall',
  room_fee: 'pos_feature_room_fee',
  guest_items: 'pos_feature_guest_items',
  shifts: 'pos_feature_shifts',
  rizqim_orders: 'pos_feature_rizqim_orders',
  bookings: 'pos_feature_bookings',
  deposits: 'pos_feature_deposits',
  customer_accounts: 'pos_feature_customer_accounts',
  kds: 'pos_feature_kds',
  service_charge: 'pos_feature_service_charge',
  multi_branch_reports: 'pos_feature_multi_branch_reports',
  aggregator_orders: 'pos_feature_aggregator_orders',
  banquet: 'pos_feature_banquet',
};

export const featureLabel = (key: string, t: Translate): string => (FEATURE_LABEL[key] ? t(FEATURE_LABEL[key]) : key);
