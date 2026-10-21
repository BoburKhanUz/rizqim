import { orderTypeLabel as staffOrderTypeLabel, paymentMethodLabel, statusLabel as staffStatusLabel } from '@rizqim/staff';
import { isMessageKey, type Language, type Translate } from '@/i18n';

// The words of an order's status, type and payment method are @rizqim/staff's (shared with the Mini App's staff screens);
// a value they have no words for is shown as the backend sent it. A role's words are this app's.
export const statusLabel = (language: Language, status: string) => staffStatusLabel(status, language);
export const orderTypeLabel = (language: Language, type: string | null | undefined) => staffOrderTypeLabel(type, language) || '—';
export const paymentLabel = (language: Language, method: string | null | undefined) => paymentMethodLabel(method, language) || '—';

export function roleLabel(t: Translate, role: string): string {
  const key = `role_${role}`;
  return isMessageKey(key) ? t(key) : role;
}
