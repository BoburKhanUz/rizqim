import { getApiErrorMessage, getApiErrorStatus, isStaffApiError, StaffApiError } from '@rizqim/staff';
import type { Translate } from '@/i18n';
import { billingRefusalText } from '../billing/refusal';

// The first validation message of any field of a 422 (Laravel: errors.<field>[0]), in the server's own words (Uzbek).
export function firstFieldError(err: unknown): string | undefined {
  if (!isStaffApiError(err) || err.status !== 422 || !err.errors) return undefined;
  for (const messages of Object.values(err.errors)) {
    const first = Array.isArray(messages) ? messages[0] : messages;
    if (typeof first === 'string' && first.trim() !== '') return first;
  }
  return undefined;
}

// What to tell the person about a failed request. The backend answers 403/404/422 with a `message` in Uzbek, which is the
// best explanation there is ("Filialda buyurtmalar bor ..."): it is shown as it comes. Without one: a line per status;
// a request that never got an answer is the network.
export function errorText(err: unknown, t: Translate): string {
  // P4-21: the plan or the unpaid-invoice lock said no: the same plain words everywhere, in the panel's language
  const refusal = billingRefusalText(err, t);
  if (refusal) return refusal;
  const field = firstFieldError(err);
  if (field) return field;
  const message = getApiErrorMessage(err);
  if (message) return message;
  const status = getApiErrorStatus(err);
  if (status === 403) return t('mgmt_forbidden');
  if (status === 404) return t('mgmt_notFound');
  if (status === 429) return t('rateLimited');
  return isStaffApiError(err) ? t('unknownError') : t('networkError');
}

export const reasonOf = (err: unknown): string | undefined => (err instanceof StaffApiError ? err.reason : undefined);
