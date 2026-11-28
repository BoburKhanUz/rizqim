import { describe, expect, it } from 'vitest';
import { normalizeUzPhone } from './SmsTest';

// The same rules as the backend's OnlinegroupSmsService::normalizePhone
describe('normalizeUzPhone', () => {
  it('accepts what the gateway accepts, in any spacing', () => {
    expect(normalizeUzPhone('+998901234567')).toBe('+998901234567');
    expect(normalizeUzPhone('+998 (90) 123-45-67')).toBe('+998901234567');
    expect(normalizeUzPhone('998901234567')).toBe('+998901234567');
    expect(normalizeUzPhone('90 123 45 67')).toBe('+998901234567');
    expect(normalizeUzPhone('8 998 90 123 45 67')).toBe('+998901234567');
    expect(normalizeUzPhone('  901234567 ')).toBe('+998901234567');
  });

  it('refuses anything else', () => {
    for (const wrong of ['', 'telefon', '90 123 45', '+1 202 555 0123', '+901234567', '+8998901234567', '9989012345678', '7998901234567']) {
      expect(normalizeUzPhone(wrong)).toBeNull();
    }
  });
});
