import { isMessageKey, type Translate } from '@/i18n';

// The words of a backend value, in the language of the page; a value the page has no words for is shown as it is.
function label(t: Translate, prefix: string, value: string | null | undefined): string {
  if (!value) return '—';
  const key = `${prefix}_${value}`;
  return isMessageKey(key) ? t(key) : value;
}

export const statusLabel = (t: Translate, status: string) => label(t, 'status', status);
export const orderTypeLabel = (t: Translate, type: string | null | undefined) => label(t, 'type', type);
export const paymentLabel = (t: Translate, method: string | null | undefined) => label(t, 'payment', method);
export const roleLabel = (t: Translate, role: string) => label(t, 'role', role);
