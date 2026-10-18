import { describe, expect, it } from 'vitest';
import { parseAmount, sumAmounts, toAmount } from './money';

describe('parseAmount', () => {
  it.each([
    ['15000.00', 15000],
    ['0.00', 0],
    ['-250.50', -250.5],
    ['  12000 ', 12000],
    ['1e3', 1000],
    [15000, 15000],
    [0, 0],
    [-3.5, -3.5],
  ])('reads %j as %s', (input, expected) => {
    expect(parseAmount(input)).toBe(expected);
  });

  it.each([
    [''],
    ['   '],
    ['abc'],
    ['12abc'],
    ['15 000'],
    ['Infinity'],
    [Number.NaN],
    [Number.POSITIVE_INFINITY],
    [Number.NEGATIVE_INFINITY],
    [null],
    [undefined],
    [true],
    [{}],
    [[]],
    [['15000']],
  ])('rejects %j', (input) => {
    expect(parseAmount(input)).toBeNull();
  });
});

describe('toAmount', () => {
  it('reads the decimal strings of the API and plain numbers', () => {
    expect(toAmount('15000.00')).toBe(15000);
    expect(toAmount('99.99')).toBe(99.99);
    expect(toAmount(8000)).toBe(8000);
  });

  it('counts a missing or broken value as zero instead of NaN', () => {
    expect(toAmount(undefined)).toBe(0);
    expect(toAmount(null)).toBe(0);
    expect(toAmount('')).toBe(0);
    expect(toAmount('abc')).toBe(0);
    expect(toAmount(Number.NaN)).toBe(0);
    expect(toAmount(Number.POSITIVE_INFINITY)).toBe(0);
  });

  it('is what makes decimal strings safe to add: the raw + would glue them together', () => {
    const subtotal = '15000.00';
    const delivery = '5000.50';

    expect(subtotal + delivery).toBe('15000.005000.50'); // the bug this guards against
    expect(toAmount(subtotal) + toAmount(delivery)).toBe(20000.5);
  });
});

describe('sumAmounts', () => {
  it('adds decimal strings and numbers together', () => {
    expect(sumAmounts(['30000.00', 45000.5, '20000.00'])).toBe(95000.5);
  });

  it('is zero for nothing', () => {
    expect(sumAmounts([])).toBe(0);
  });

  it('skips what is not an amount, so one bad row does not poison the total', () => {
    expect(sumAmounts(['1000.00', null, undefined, 'n/a', '', '500.00'])).toBe(1500);
  });

  it('is exact to the tiyin', () => {
    expect(0.1 + 0.2).not.toBe(0.3); // plain float addition
    expect(sumAmounts(['0.10', '0.20'])).toBe(0.3);
    expect(sumAmounts(Array.from({ length: 10 }, () => '0.10'))).toBe(1);
    expect(sumAmounts(['1234567.89', '0.01'])).toBe(1234567.9);
  });

  it('takes any iterable', () => {
    expect(sumAmounts(new Set(['100.00', '250.00']))).toBe(350);
    expect(sumAmounts((function* () { yield '1.50'; yield 2; })())).toBe(3.5);
  });
});
