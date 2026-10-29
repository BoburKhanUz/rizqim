import type { MessageKey, Translate } from '@/i18n';

// The paired terminal types (App\Enums\DeviceType) in words, wherever a list or a plan row names a device type.
// P6-14: `display` is the customer display / "ready numbers" screen (free, never counts toward the plan's devices).
// `waiter` is the plan's waiter-phone allowance. A type the backend adds later shows under its key until it gets a line.
const DEVICE_TYPE_LABEL: Record<string, MessageKey> = {
  pos: 'pos_store_dev_pos',
  kds: 'pos_store_dev_kds',
  display: 'pos_store_dev_display',
  waiter: 'pos_store_dev_waiter',
};

export const deviceTypeLabel = (type: string, t: Translate): string => (DEVICE_TYPE_LABEL[type] ? t(DEVICE_TYPE_LABEL[type]) : type);
