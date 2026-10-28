import { ApiError } from './api/client';
import type { MessageKey, Translate } from '@/i18n';

// Every error code of the admin API (docs/API.md "Xato formati") in one plain Uzbek sentence. The backend's own `message` is
// Uzbek too, but the sentence here is the one the app controls (it also says what to do next).
const CODE_TEXT: Record<string, MessageKey> = {
  invalid_credentials: 'err_invalid_credentials',
  member_disabled: 'err_member_disabled',
  locked: 'err_locked',
  challenge_invalid: 'err_challenge_invalid',
  invalid_code: 'err_invalid_code',
  invalid_recovery_code: 'err_invalid_recovery_code',
  setup_required: 'err_setup_required',
  setup_not_started: 'err_setup_not_started',
  two_factor_already_enabled: 'err_two_factor_already_enabled',
  setup_not_allowed: 'err_setup_not_allowed',
  invite_invalid: 'err_invite_invalid',
  invite_already_accepted: 'err_invite_already_accepted',
  email_taken: 'err_email_taken',
  reauth_required: 'err_reauth_required',
  forbidden: 'err_forbidden',
  superadmin_only: 'err_superadmin_only',
  cannot_change_self: 'err_cannot_change_self',
  last_superadmin: 'err_last_superadmin',
  role_not_editable: 'err_role_not_editable',
  not_found: 'err_not_found',
};

export const KNOWN_ERROR_CODES: readonly string[] = Object.keys(CODE_TEXT);

// A failed call in words for the screen: the sentence of its error code, else the backend's own, else one for the kind of failure.
export function errorText(error: unknown, t: Translate, fallback?: string): string {
  if (!(error instanceof ApiError)) return fallback ?? t('unknownError');
  if (error.unreachable) return t('networkError');
  const key = CODE_TEXT[error.code];
  if (key) return t(key, { minutes: Math.max(1, Math.ceil((error.retryAfter ?? 60) / 60)) });
  if (error.status === 429) return error.message || (error.retryAfter ? t('rateLimitedIn', { minutes: Math.max(1, Math.ceil(error.retryAfter / 60)) }) : t('rateLimited'));
  if (error.message) return error.message;
  if (error.status === 403) return t('forbidden');
  return fallback ?? t('unknownError');
}

// The sign-in steps: a refusal that has no code of its own is said in the screen's own words
export function authErrorText(error: unknown, t: Translate, wrongCredential: string): string {
  if (error instanceof ApiError && !error.unreachable && !CODE_TEXT[error.code] && error.status !== 429 && error.status !== 403 && error.status < 500) return wrongCredential;
  return errorText(error, t);
}

// The first message of a field error (422), when the backend sent one
export const fieldError = (error: unknown, field: string): string | undefined => (error instanceof ApiError ? error.fields[field]?.[0] : undefined);
