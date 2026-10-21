import { normalizePaymentMethod } from '@rizqim/staff';
import type { TranslationKey } from '../store/languageStore';

// Translation key of a payment method the API sends in Order.payment_method ("cash", "CASH", "click" ...), or null
// for one this app has no name for (show it as sent). The names and the spellings are @rizqim/staff's.
export function getPaymentMethodLabelKey(method: string | null | undefined): TranslationKey | null {
  const known = normalizePaymentMethod(method);
  return known ? `paymentMethod_${known}` : null;
}

// The name of `method` in the user's language (`t` from useLanguageStore).
export function getPaymentMethodLabel(method: string | null | undefined, t: (key: TranslationKey) => string): string {
  const key = getPaymentMethodLabelKey(method);
  return key ? t(key) : (method ?? '');
}
