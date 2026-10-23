import { getApiFieldError } from '@rizqim/staff';
import type { Translate } from '@/i18n';
import { errorText } from '../management/errors';

export interface FormErrors {
  // Messages under the field they name (the server's, Uzbek)
  fields: Record<string, string>;
  // A refusal no field of the form shows (a 403, a 409, a domain reason)
  form: string | null;
}

export const NO_ERRORS: FormErrors = { fields: {}, form: null };

// What the server refused, sorted into the fields the form shows and the one line for the rest. `aliases` maps a field the
// server names to the one the form has ("mxik" -> "mxik_code").
export function sortServerErrors(err: unknown, fields: readonly string[], t: Translate, aliases: Record<string, string> = {}): FormErrors {
  const found: Record<string, string> = {};
  for (const field of fields) {
    const message = getApiFieldError(err, field);
    if (message) found[aliases[field] ?? field] = message;
  }
  return { fields: found, form: Object.keys(found).length === 0 ? errorText(err, t) : null };
}
