// Uzbek numbers go to the backend as "+998" followed by 9 digits (the format its SMS endpoints accept).
export const PHONE_PREFIX = '+998';

// The 9 national digits of what was typed or pasted ("90 123-45-67", "+998 90 123 45 67", "998901234567"), or null
// when it is not a complete Uzbek number.
export function nationalDigits(input: string): string | null {
  const digits = input.replace(/\D/g, '');
  if (digits.length === 9) return digits;
  if (digits.length === 12 && digits.startsWith('998')) return digits.slice(3);
  return null;
}

// "90 123 45 67" while typing: keeps at most 9 digits (a pasted +998 prefix is dropped)
export function formatNationalInput(input: string): string {
  let digits = input.replace(/\D/g, '');
  if (digits.startsWith('998') && digits.length > 9) digits = digits.slice(3);
  digits = digits.slice(0, 9);
  const parts = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)].filter(Boolean);
  return parts.join(' ');
}

export const toE164 = (nine: string) => `${PHONE_PREFIX}${nine}`;
