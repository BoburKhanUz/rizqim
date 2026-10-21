import type { OwnerPaymentSettings, PaymentProviderSettings, StoreSettings, StoreSettingsInput } from '@rizqim/staff';

// How the stored payment_settings are read back into a form, and written again. A provider is either a bare boolean (older data)
// or an object; the API adds `available: false` to one the platform cannot process yet (Uzum, Paynet), which can be shown but
// not switched on (the server refuses it with 422 on payment_settings).
type ProviderSetting = boolean | PaymentProviderSettings | null | undefined;

// Cash is on unless the store switched it off
export function cashOn(setting: ProviderSetting): boolean {
  if (setting === undefined || setting === null) return true;
  return typeof setting === 'boolean' ? setting : setting.enabled !== false;
}

// An online provider is on only when the store switched it on
export function onlineOn(setting: ProviderSetting): boolean {
  if (setting === undefined || setting === null) return false;
  return typeof setting === 'boolean' ? setting : setting.enabled === true;
}

export function providerAvailable(setting: ProviderSetting): boolean {
  return typeof setting === 'object' && setting !== null ? setting.available !== false : true;
}

export const PROVIDERS = ['cash', 'click', 'payme', 'uzum', 'paynet'] as const;
export type Provider = (typeof PROVIDERS)[number];

export interface SettingsDraft {
  name: string;
  timezone: string;
  phone: string;
  description: string;
  address: string;
  locationUrl: string;
  isOpen: boolean;
  minOrder: string;
  deliveryFee: string;
  deliveryEnabled: boolean;
  pickupEnabled: boolean;
  openTime: string;
  closeTime: string;
  providers: Record<Provider, boolean>;
}

export function draftFromSettings(settings: StoreSettings): SettingsDraft {
  const payment: OwnerPaymentSettings = settings.payment_settings ?? {};
  const hours = payment.working_hours ?? {};
  return {
    name: settings.name,
    timezone: settings.timezone,
    phone: settings.phone ?? '',
    description: settings.description ?? '',
    address: settings.address ?? '',
    locationUrl: settings.location_url ?? '',
    isOpen: settings.is_open,
    minOrder: String(settings.min_order_amount ?? 0),
    deliveryFee: String(payment.delivery_fee ?? 0),
    deliveryEnabled: payment.delivery_enabled !== false,
    pickupEnabled: payment.pickup_enabled !== false,
    openTime: (hours.open ?? '09:00').slice(0, 5),
    closeTime: (hours.close ?? '22:00').slice(0, 5),
    providers: {
      cash: cashOn(payment.cash),
      click: onlineOn(payment.click),
      payme: onlineOn(payment.payme),
      // Not integrated yet: whatever was stored, they start (and stay) off
      uzum: providerAvailable(payment.uzum) && onlineOn(payment.uzum),
      paynet: providerAvailable(payment.paynet) && onlineOn(payment.paynet),
    },
  };
}

// The request. POST /owner/settings overwrites logo, banner, description, address, location_url, is_open and the minimum
// with what is sent, so the stored logo and banner go back untouched. The numbers are parsed by the screen first.
export function inputFromDraft(settings: StoreSettings, draft: SettingsDraft, numbers: { minOrder: number; deliveryFee: number }): StoreSettingsInput {
  const payment: OwnerPaymentSettings = settings.payment_settings ?? {};
  const unavailable = (provider: Provider) => !providerAvailable(payment[provider]);
  return {
    name: draft.name.trim(),
    timezone: draft.timezone.trim(),
    phone: draft.phone.trim() || null,
    logo: settings.logo,
    banner: settings.banner,
    description: draft.description.trim() || null,
    address: draft.address.trim() || null,
    location_url: draft.locationUrl.trim() || null,
    is_open: draft.isOpen,
    min_order_amount: numbers.minOrder,
    payment_settings: {
      delivery_fee: numbers.deliveryFee,
      delivery_enabled: draft.deliveryEnabled,
      pickup_enabled: draft.pickupEnabled,
      working_hours: { open: draft.openTime, close: draft.closeTime },
      cash: { enabled: draft.providers.cash },
      click: { enabled: draft.providers.click },
      payme: { enabled: draft.providers.payme },
      // Never switched on from here while the provider is not integrated (the server refuses it)
      uzum: { enabled: !unavailable('uzum') && draft.providers.uzum },
      paynet: { enabled: !unavailable('paynet') && draft.providers.paynet },
    },
  };
}
