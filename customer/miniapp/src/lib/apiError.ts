import { isAxiosError } from 'axios';
import { getApiErrorMessage as getStaffErrorMessage, getApiFieldError as getStaffFieldError, isStaffApiError } from '@rizqim/staff';
import type { ApiErrorBody } from '../types';

// Two clients make requests here: the axios one (customer screens, services/api.ts) and the staff screens' API layer
// (@rizqim/staff, whose failures are StaffApiErrors). Every helper below reads both.

// What is safe to print for a caught error. A raw axios error carries the whole request in `err.config`
// (Authorization header, bodies with phone numbers and OTP codes), so it is never logged as is: only the
// HTTP status and message are kept. Anything else has nothing sensitive and is returned unchanged.
export function sanitizeError(err: unknown): unknown {
  if (isStaffApiError(err)) return `HTTP ${err.status}: ${err.message}`;
  if (!isAxiosError<ApiErrorBody>(err)) return err;
  const status = err.response?.status;
  const message = err.response?.data?.message || err.message;
  return status ? `HTTP ${status}: ${message}` : message;
}

// console.error that is safe to use with errors from API calls.
export function logApiError(context: string, err: unknown): void {
  console.error(context, sanitizeError(err));
}

// The HTTP status of a failed request, if it got an answer.
export function getApiErrorStatus(err: unknown): number | undefined {
  if (isStaffApiError(err)) return err.status;
  return isAxiosError(err) ? err.response?.status : undefined;
}

// The server's `message` of a failed request, if there is one.
export function getApiErrorMessage(err: unknown): string | undefined {
  if (isStaffApiError(err)) return getStaffErrorMessage(err);
  return (isAxiosError<ApiErrorBody>(err) && err.response?.data?.message) || undefined;
}

// First Laravel validation message (422 `errors.<field>`) for a field, if there is one.
export function getApiFieldError(err: unknown, field: string): string | undefined {
  if (isStaffApiError(err)) return getStaffFieldError(err, field);
  if (!isAxiosError<ApiErrorBody>(err) || err.response?.status !== 422) return undefined;
  const messages = err.response.data?.errors?.[field];
  const first = Array.isArray(messages) ? messages[0] : messages;
  return typeof first === 'string' && first.trim() !== '' ? first : undefined;
}

// The text of any thrown Error (e.g. axios' "Network Error"), for screens that show what went wrong.
export function getErrorMessage(err: unknown): string | undefined {
  return err instanceof Error && err.message ? err.message : undefined;
}
