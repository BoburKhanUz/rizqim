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

// Wave P: the store's own Paylov account (POST /owner/settings payment_settings.paylov, PaylovAccount::KEYS): merchant_id
// names the payment link; the other six are secrets (Store::isSecretKey: the login names too), write-only like Click's
// secret_key: the API says only whether one is stored (has_consumer_key...), an empty field keeps it. Until all seven are
// stored, Paylov orders are paid to the platform. The webhook password must be at least 16 characters (422 otherwise).
export const PAYLOV_SECRETS = ['consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'] as const;
export type PaylovSecret = (typeof PAYLOV_SECRETS)[number];
export const PAYLOV_CALLBACK_PASSWORD_MIN = 16;
// docs/PAYMENTS_GO_LIVE.md §11.3: one address for both the Checkout callback and the cancel webhook
export const PAYLOV_WEBHOOK_URL = 'https://api.rizqim.uz/api/v1/payments/paylov/webhook';

export interface PaylovDraft {
  merchant_id: string;
  secrets: Record<PaylovSecret, string>;
  stored: Record<PaylovSecret, boolean>;
}

function paylovDraft(setting: unknown): PaylovDraft {
  const section = (typeof setting === 'object' && setting !== null ? setting : {}) as Record<string, unknown>;
  const secrets = {} as Record<PaylovSecret, string>;
  const stored = {} as Record<PaylovSecret, boolean>;
  for (const key of PAYLOV_SECRETS) {
    secrets[key] = '';
    stored[key] = section[`has_${key}`] === true;
  }
  return { merchant_id: typeof section.merchant_id === 'string' ? section.merchant_id : '', secrets, stored };
}

// A typed webhook password that is too short (an empty field keeps the stored one)
export function paylovCallbackPasswordTooShort(draft: PaylovDraft): boolean {
  const typed = draft.secrets.callback_password.trim();
  return typed !== '' && typed.length < PAYLOV_CALLBACK_PASSWORD_MIN;
}

// The Paylov keys sent (own account only): merchant_id as typed (empty: none), each secret only when a new one was typed
function paylovInput(draft: PaylovDraft): Record<string, string | null> {
  const input: Record<string, string | null> = { merchant_id: draft.merchant_id.trim() || null };
  for (const key of PAYLOV_SECRETS) if (draft.secrets[key].trim() !== '') input[key] = draft.secrets[key].trim();
  return input;
}

export const PROVIDERS = ['cash', 'click', 'payme', 'paylov', 'uzum', 'paynet'] as const;
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
  paylov: PaylovDraft;
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
      paylov: providerAvailable(payment.paylov) && onlineOn(payment.paylov),
      // Not integrated yet: whatever was stored, they start (and stay) off
      uzum: providerAvailable(payment.uzum) && onlineOn(payment.uzum),
      paynet: providerAvailable(payment.paynet) && onlineOn(payment.paynet),
    },
    merchant: { click: merchantDraft(payment.click), payme: merchantDraft(payment.payme) },
    paylov: paylovDraft(payment.paylov),
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
      // Switched on only while RIZQIM offers it (the server refuses it otherwise)
      paylov: { enabled: !unavailable('paylov') && draft.providers.paylov, ...(own && !unavailable('paylov') ? paylovInput(draft.paylov) : {}) },
      // Never switched on from here while the provider is not integrated (the server refuses it)
      uzum: { enabled: !unavailable('uzum') && draft.providers.uzum },
      paynet: { enabled: !unavailable('paynet') && draft.providers.paynet },
    },
  };
}
