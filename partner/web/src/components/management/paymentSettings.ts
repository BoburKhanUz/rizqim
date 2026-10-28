import type { OwnerPaymentSettings, PaymentProviderSettings, StoreSettings, StoreSettingsInput } from '@rizqim/staff';

// How the stored payment_settings are read back into a form, and written again. A provider is either a bare boolean (older data)
// or an object; the API adds `available: false` to one the platform cannot process now (Uzum, Paynet not integrated yet; P4-32: a
// provider RIZQIM switched off, `unavailable_reason: switched_off`), which can be shown but not switched on (the server refuses it
// with 422 on payment_settings and keeps what the store saved for it).
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

// P4-32: why a provider cannot be switched on: not integrated yet (Uzum, Paynet) or switched off by RIZQIM for a while
export function unavailableReason(setting: ProviderSetting): 'not_integrated' | 'switched_off' | null {
  if (providerAvailable(setting)) return null;
  return typeof setting === 'object' && setting !== null && setting.unavailable_reason === 'switched_off' ? 'switched_off' : 'not_integrated';
}

// P4-32 (the old store panel's merchant fields): a store collecting into its own Click / Payme cashbox enters the keys the
// payment link and the webhooks use. The secret is write-only: the API says only whether one is stored (has_secret_key); an
// empty field keeps it.
export const MERCHANT_PROVIDERS = ['click', 'payme'] as const;
export type MerchantProvider = (typeof MERCHANT_PROVIDERS)[number];
export const MERCHANT_FIELDS: Record<MerchantProvider, readonly ('merchant_id' | 'service_id')[]> = { click: ['service_id', 'merchant_id'], payme: ['merchant_id'] };

export interface MerchantDraft {
  merchant_id: string;
  service_id: string;
  secret_key: string;
  hasSecret: boolean;
}

function merchantDraft(setting: ProviderSetting): MerchantDraft {
  const section = typeof setting === 'object' && setting !== null ? setting : {};
  return { merchant_id: section.merchant_id ?? '', service_id: section.service_id ?? '', secret_key: '', hasSecret: section.has_secret_key === true };
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
  merchant: Record<MerchantProvider, MerchantDraft>;
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
    merchant: { click: merchantDraft(payment.click), payme: merchantDraft(payment.payme) },
  };
}

// The merchant keys sent for a provider (own account only): ids as typed (empty: none), the secret only when a new one was typed
function merchantInput(provider: MerchantProvider, draft: MerchantDraft): Record<string, string | null> {
  const input: Record<string, string | null> = {};
  for (const field of MERCHANT_FIELDS[provider]) input[field] = draft[field].trim() || null;
  if (draft.secret_key.trim() !== '') input.secret_key = draft.secret_key.trim();
  return input;
}

// The request. POST /owner/settings overwrites logo, banner, description, address, location_url, is_open and the minimum
// with what is sent, so the stored logo and banner go back untouched. The numbers are parsed by the screen first.
export function inputFromDraft(settings: StoreSettings, draft: SettingsDraft, numbers: { minOrder: number; deliveryFee: number }): StoreSettingsInput {
  const payment: OwnerPaymentSettings = settings.payment_settings ?? {};
  const unavailable = (provider: Provider) => !providerAvailable(payment[provider]);
  const own = settings.payment_account_type === 'own_account';
  const merchant = (provider: MerchantProvider) => (own && !unavailable(provider) ? merchantInput(provider, draft.merchant[provider]) : {});
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
      click: { enabled: draft.providers.click, ...merchant('click') },
      payme: { enabled: draft.providers.payme, ...merchant('payme') },
      // Never switched on from here while the provider is not integrated (the server refuses it)
      uzum: { enabled: !unavailable('uzum') && draft.providers.uzum },
      paynet: { enabled: !unavailable('paynet') && draft.providers.paynet },
    },
  };
}
