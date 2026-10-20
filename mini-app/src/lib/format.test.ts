import { afterEach, describe, expect, it } from 'vitest';
import { formatDate, formatDateTime, formatMoney, formatNumber, formatOrderCode, formatTime, NBSP } from './format';
import { useLanguageStore } from '../store/languageStore';

// The tests read values with plain spaces; the app writes non-breaking ones
const plain = (text: string) => text.replace(new RegExp(NBSP, 'g'), ' ');

afterEach(() => useLanguageStore.setState({ language: 'uz' }));

describe('formatMoney', () => {
  it("groups thousands and adds the currency of each language", () => {
    expect(plain(formatMoney(25000, 'uz'))).toBe("25 000 so'm");
    expect(plain(formatMoney(25000, 'ru'))).toBe('25 000 сум');
    expect(plain(formatMoney(25000, 'en'))).toBe('25,000 UZS');
    expect(plain(formatMoney(1234567, 'uz'))).toBe("1 234 567 so'm");
    expect(plain(formatMoney(0, 'uz'))).toBe("0 so'm");
    expect(plain(formatMoney(999, 'uz'))).toBe("999 so'm");
  });

  it('never breaks a value across lines', () => {
    expect(formatMoney(25000, 'uz')).toBe(`25${NBSP}000${NBSP}so'm`);
    expect(formatMoney(25000, 'en')).toBe(`25,000${NBSP}UZS`);
  });

  it('reads the decimal strings of the API and rounds to whole so\'m', () => {
    expect(plain(formatMoney('25000.00', 'uz'))).toBe("25 000 so'm");
    expect(plain(formatMoney('12000.5', 'uz'))).toBe("12 001 so'm");
    expect(plain(formatMoney(99.4, 'uz'))).toBe("99 so'm");
  });

  it('shows a missing or non-numeric amount as zero, never NaN', () => {
    for (const value of [undefined, null, '', 'abc', Number.NaN, Number.POSITIVE_INFINITY]) {
      expect(plain(formatMoney(value, 'uz'))).toBe("0 so'm");
    }
  });

  it('keeps the sign of a negative amount', () => {
    expect(plain(formatMoney(-100000, 'uz'))).toBe("-100 000 so'm");
  });

  it("uses the user's language when none is given", () => {
    useLanguageStore.setState({ language: 'ru' });
    expect(plain(formatMoney(5000))).toBe('5 000 сум');
  });
});

describe('formatNumber', () => {
  it('groups digits like the money it goes with', () => {
    expect(plain(formatNumber(5000, 'uz'))).toBe('5 000');
    expect(formatNumber(5000, 'en')).toBe('5,000');
  });
});

describe('dates and times', () => {
  // 19 October 2026, 20:12 local time
  const evening = new Date(2026, 9, 19, 20, 12);
  const now = new Date(2026, 9, 20, 9, 0);

  it('writes Uzbek months itself (Intl prints "M10")', () => {
    expect(formatDate(evening, 'uz', now)).toBe('19-okt');
    expect(plain(formatDateTime(evening, 'uz', now))).toBe('19-okt, 20:12');
    expect(formatDate(new Date(2026, 0, 3), 'uz', now)).toBe('3-yan');
    expect(formatDate(new Date(2026, 4, 9), 'uz', now)).toBe('9-may');
  });

  it('uses Intl for Russian and English', () => {
    expect(plain(formatDateTime(evening, 'ru', now))).toBe('19 окт., 20:12');
    expect(plain(formatDateTime(evening, 'en', now))).toBe('Oct 19, 20:12');
  });

  it('adds the year to a date of another year', () => {
    expect(plain(formatDate(new Date(2025, 11, 31), 'uz', now))).toBe('31-dek 2025');
    expect(plain(formatDate(new Date(2025, 11, 31), 'en', now))).toBe('Dec 31, 2025');
  });

  it('is 24-hour with leading zeros', () => {
    expect(formatTime(new Date(2026, 9, 19, 7, 5))).toBe('07:05');
    expect(formatTime(evening)).toBe('20:12');
  });

  it('reads ISO strings and shows a dash for nothing or garbage', () => {
    expect(formatTime(evening.toISOString())).toBe('20:12');
    expect(formatDate(null)).toBe('—');
    expect(formatDateTime('not a date')).toBe('—');
    expect(formatTime(undefined)).toBe('—');
  });
});

describe('formatOrderCode', () => {
  it('prefers the short code and falls back to the uuid', () => {
    expect(formatOrderCode({ short_code: 'E101', uuid: 'abcdef12-0000' })).toBe('#E101');
    expect(formatOrderCode({ short_code: null, uuid: 'abcdef12-0000' })).toBe('#ABCDEF12');
    expect(formatOrderCode({ short_code: ' ', uuid: null })).toBe('');
  });
});
