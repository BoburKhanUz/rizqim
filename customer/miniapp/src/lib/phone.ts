// Uzbek numbers go to the SMS endpoints as "+998" followed by 9 digits.
export const UZ_PHONE_PREFIX = '+998';

// The 9 national digits of an Uzbek number typed in any common format, or null if it isn't one.
export function getUzPhoneDigits(input: string | null | undefined): string | null {
  const digits = (input ?? '').replace(/\D/g, '');
  if (digits.length === 9) return digits;
  if (digits.length === 12 && digits.startsWith('998')) return digits.slice(3);
  return null;
}

export type PhoneChange =
  | { kind: 'unchanged' }
  | { kind: 'cleared' } // the user emptied a phone that was set
  | { kind: 'invalid' } // not an Uzbek (+998) number
  | { kind: 'changed'; fullPhone: string };

// Compares what the user typed with the phone currently saved on the account.
export function classifyPhoneChange(typed: string, current: string | null | undefined): PhoneChange {
  const input = typed.trim();
  const saved = (current ?? '').trim();
  if (input === saved) return { kind: 'unchanged' };
  if (input === '') return { kind: 'cleared' };
  const digits = getUzPhoneDigits(input);
  if (digits === null) return { kind: 'invalid' };
  if (digits === getUzPhoneDigits(saved)) return { kind: 'unchanged' }; // only the formatting differs
  return { kind: 'changed', fullPhone: `${UZ_PHONE_PREFIX}${digits}` };
}
