import type { TranslationKey } from '../store/languageStore';

// Translation key of a payment method the API sends in Order.payment_method ("cash", "CASH", "click" ...), or null
// for one this app has no name for (show it as sent).
const KNOWN = ['cash', 'card', 'click', 'payme', 'uzum', 'paynet', 'keshbek'] as const;
const ALIASES: Record<string, (typeof KNOWN)[number]> = { naqd: 'cash', terminal: 'card' };

export function getPaymentMethodLabelKey(method: string | null | undefined): TranslationKey | null {
  const value = method?.trim().toLowerCase();
  if (!value) return null;
  const known = (KNOWN as readonly string[]).includes(value) ? (value as (typeof KNOWN)[number]) : ALIASES[value];
  return known ? `paymentMethod_${known}` : null;
}

// The name of `method` in the user's language (`t` from useLanguageStore).
export function getPaymentMethodLabel(method: string | null | undefined, t: (key: TranslationKey) => string): string {
  const key = getPaymentMethodLabelKey(method);
  return key ? t(key) : (method ?? '');
}
