import { isStaffApiError } from '@rizqim/staff';
import type { Translate } from '@/i18n';

// What a failed request shows on the menu screens. The backend answers in Uzbek where the message is its own
// ("Eng kam miqdor (0,3) qadamga (0,5) karrali bo'lishi kerak ..."), but a field rule of Laravel's (required, numeric,
// max ...) comes in English while the backend's locale is "en". The screens show the Uzbek messages as they are and
// put an Uzbek sentence in place of an English one.

export interface ErrorView {
  // One line for the banner above the form
  message: string;
  // The first message of each invalid field, keyed as the server keys them ("name", "variants.0.min_quantity")
  fields: Record<string, string>;
}

// Laravel's default English wording: these words do not occur in the backend's own Uzbek messages
const ENGLISH = /\b(the|must|field|fields|required|invalid|greater|selected|taken|not|been|characters|please|failed|unauthorized|server|error)\b/i;

export const isEnglish = (message: string): boolean => ENGLISH.test(message);

// An Uzbek sentence for a field rule that came in English
export function localizeFieldMessage(message: string, t: Translate): string {
  if (!isEnglish(message)) return message;
  if (/already been taken/i.test(message)) return t('menu_err_taken');
  if (/\b(image|jpeg|jpg|png|webp|mimes|kilobytes)\b/i.test(message)) return t('menu_err_image');
  if (/required|must be present|is missing/i.test(message)) return t('menu_err_required');
  if (/not be greater|less than|too long|characters|maximum|may not/i.test(message)) return t('menu_err_too_big');
  if (/greater than|at least|must be a positive|minimum/i.test(message)) return t('menu_err_too_small');
  if (/number|numeric|integer|decimal|digits/i.test(message)) return t('menu_err_number');
  return t('menu_err_invalid');
}

function firstMessage(value: unknown): string | undefined {
  const first = Array.isArray(value) ? value[0] : value;
  return typeof first === 'string' && first.trim() !== '' ? first : undefined;
}

// The banner line and the per-field messages of a failed request, in Uzbek.
export function describeError(error: unknown, t: Translate): ErrorView {
  if (!isStaffApiError(error)) return { message: t('networkError'), fields: {} };
  const fields: Record<string, string> = {};
  if (error.status === 422) {
    for (const [field, value] of Object.entries(error.errors ?? {})) {
      const message = firstMessage(value);
      if (message) fields[field] = localizeFieldMessage(message, t);
    }
  }
  const own = error.hasServerMessage && !isEnglish(error.message) ? error.message : undefined;
  switch (error.status) {
    case 403:
      return { message: own ?? t('menu_forbidden'), fields };
    case 404:
      return { message: own ?? t('menu_not_found'), fields };
    case 409:
      return { message: own ?? t('menu_conflict'), fields };
    case 422:
      // Laravel's own summary ("The name field is required. (and 2 more errors)") is English: the field messages say it
      return { message: own ?? (Object.keys(fields).length > 0 ? t('menu_check_fields') : t('menu_err_invalid')), fields };
    case 429:
      return { message: t('menu_too_many'), fields };
    default:
      return { message: own && error.status < 500 ? own : t('unknownError'), fields };
  }
}

// The field messages no input of the form shows: `shown` matches the keys that have an input of their own.
export function unplacedMessages(fields: Record<string, string>, shown: RegExp): string[] {
  return Array.from(new Set(Object.entries(fields).filter(([key]) => !shown.test(key)).map(([, message]) => message)));
}
