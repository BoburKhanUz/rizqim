import { ApiError } from './api/client';
import type { Translate } from '@/i18n';

// A failed call in words for the screen: the backend's own sentence (it writes in Uzbek), else one for the kind of failure.
export function errorText(error: unknown, t: Translate, fallback?: string): string {
  if (!(error instanceof ApiError)) return fallback ?? t('unknownError');
  if (error.unreachable) return t('networkError');
  if (error.status === 429) return error.message || (error.retryAfter ? t('rateLimitedIn', { minutes: Math.max(1, Math.ceil(error.retryAfter / 60)) }) : t('rateLimited'));
  if (error.message) return error.message;
  if (error.status === 403) return t('forbidden');
  return fallback ?? t('unknownError');
}

// The sign-in steps: a wrong credential is said in the screen's own words (the backend answers the same for every cause)
export function authErrorText(error: unknown, t: Translate, wrongCredential: string): string {
  if (error instanceof ApiError && !error.unreachable && error.status !== 429 && error.status !== 403 && error.status < 500) return wrongCredential;
  return errorText(error, t);
}

// The first message of a field error (422), when the backend sent one
export const fieldError = (error: unknown, field: string): string | undefined => (error instanceof ApiError ? error.fields[field]?.[0] : undefined);
