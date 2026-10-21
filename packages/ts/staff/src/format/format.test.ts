import { describe, expect, it } from 'vitest';
import {
  formatDate,
  formatDateTime,
  formatLineQuantity,
  formatMoney,
  formatNumber,
  formatOrderCode,
  formatQuantity,
  formatQuantityNumber,
  formatTime,
  isMeasuredUnit,
  NBSP,
  roundQuantity,
  sumAmounts,
  toAmount,
  unitLabel,
} from '../index';

// The strings are what the Mini App prints today (its own format.test.ts / quantity.test.ts say the same): the web
// panel must show the same amounts, dates and weights. Plain spaces here; the apps write non-breaking ones.
const plain = (text: string) => text.replace(new RegExp(NBSP, 'g'), ' ');

describe('money', () => {
  it('groups thousands and adds the currency of each language', () => {
    expect(plain(formatMoney(25000, 'uz'))).toBe("25 000 so'm");
    expect(plain(formatMoney(25000, 'ru'))).toBe('25 000 сум');
    expect(plain(formatMoney(25000, 'en'))).toBe('25,000 UZS');
    expect(plain(formatMoney(1234567, 'uz'))).toBe("1 234 567 so'm");
  });

  it('never breaks a value across lines', () => {
    expect(formatMoney(25000, 'uz')).toBe(`25${NBSP}000${NBSP}so'm`);
  });

  it("reads the API's decimal strings and rounds to whole so'm", () => {
    expect(plain(formatMoney('25000.00', 'uz'))).toBe("25 000 so'm");
    expect(plain(formatMoney('12000.5', 'uz'))).toBe("12 001 so'm");
  });

  it('shows a missing or non-numeric amount as zero, never NaN', () => {
    for (const value of [undefined, null, '', 'abc', Number.NaN, Number.POSITIVE_INFINITY]) {
      expect(plain(formatMoney(value, 'uz'))).toBe("0 so'm");
    }
  });

  it('does arithmetic on amounts exactly', () => {
    expect(toAmount('15000.00')).toBe(15000);
    expect(toAmount(null)).toBe(0);
    expect(sumAmounts(['0.10', '0.20', null, 5])).toBe(5.3);
    expect(plain(formatNumber(5000, 'uz'))).toBe('5 000');
  });
});

describe('dates and times', () => {
  const evening = new Date(2026, 9, 19, 20, 12);
  const now = new Date(2026, 9, 20, 9, 0);

  it('writes Uzbek months itself', () => {
    expect(formatDate(evening, 'uz', now)).toBe('19-okt');
    expect(plain(formatDateTime(evening, 'uz', now))).toBe('19-okt, 20:12');
  });

  it('writes Russian and English with Intl', () => {
    expect(plain(formatDateTime(evening, 'ru', now))).toBe('19 окт., 20:12');
    expect(plain(formatDateTime(evening, 'en', now))).toBe('Oct 19, 20:12');
  });

  it('adds the year to a date of another year', () => {
    expect(plain(formatDate(new Date(2025, 11, 31), 'uz', now))).toBe('31-dek 2025');
  });

  it('is 24-hour with leading zeros, and a dash for nothing', () => {
    expect(formatTime(new Date(2026, 9, 19, 7, 5))).toBe('07:05');
    expect(formatTime(null)).toBe('—');
    expect(formatDateTime('not a date', 'uz')).toBe('—');
  });

  it('knows an order by its short code, else the start of its uuid', () => {
    expect(formatOrderCode({ short_code: 'E101', uuid: 'abcdef12-0000' })).toBe('#E101');
    expect(formatOrderCode({ short_code: null, uuid: 'abcdef12-0000' })).toBe('#ABCDEF12');
  });
});

describe('quantities', () => {
  it('writes 1,5 kg in Uzbek and Russian, 1.5 kg in English', () => {
    expect(formatQuantity(1.5, 'kg', 'uz')).toBe(`1,5${NBSP}kg`);
    expect(formatQuantity(1.5, 'kg', 'ru')).toBe(`1,5${NBSP}кг`);
    expect(formatQuantity(1.5, 'kg', 'en')).toBe(`1.5${NBSP}kg`);
    expect(formatQuantity(0.25, 'l', 'uz')).toBe(`0,25${NBSP}l`);
  });

  it('writes pieces as a bare number and an order line as x2', () => {
    expect(formatQuantity(2, 'piece', 'uz')).toBe('2');
    expect(formatQuantity(2, undefined, 'uz')).toBe('2');
    expect(formatLineQuantity(2, 'piece', 'uz')).toBe('x2');
    expect(formatLineQuantity(1.5, 'kg', 'uz')).toBe(`1,5${NBSP}kg`);
  });

  it('shows no trailing zeros or float noise', () => {
    expect(formatQuantity(0.1 + 0.2, 'kg', 'uz')).toBe(`0,3${NBSP}kg`);
    expect(formatQuantityNumber(2, 'uz')).toBe('2');
    expect(roundQuantity(0.1 + 0.2)).toBe(0.3);
  });

  it('names the units in each language', () => {
    expect(unitLabel('piece', 'uz')).toBe('dona');
    expect(unitLabel('piece', 'ru')).toBe('шт');
    expect(unitLabel(undefined, 'en')).toBe('pcs');
    expect(isMeasuredUnit('l')).toBe(true);
    expect(isMeasuredUnit('piece')).toBe(false);
  });
});
