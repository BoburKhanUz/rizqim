import { describe, expect, it } from 'vitest';
import { createTranslator } from '@/i18n';
import { code } from './fixtures';
import { discountText, fromLocalInput, IMAGE_MAX_BYTES, imageProblem, moved, sameOrder, toLocalInput } from './marketingModel';

const t = createTranslator('uz');

describe('Tashkent time in the schedule fields', () => {
  it('shows an ISO moment in Tashkent time, whatever offset it came with', () => {
    expect(toLocalInput('2026-12-01T09:00:00+05:00')).toBe('2026-12-01T09:00');
    expect(toLocalInput('2026-12-01T04:00:00Z')).toBe('2026-12-01T09:00');
    expect(toLocalInput('2026-12-31T21:30:00-05:00')).toBe('2027-01-01T07:30');
    expect(toLocalInput(null)).toBe('');
    expect(toLocalInput('kecha')).toBe('');
  });

  it('turns the field value back into ISO 8601 with the Tashkent offset', () => {
    expect(fromLocalInput('2026-12-01T09:00')).toBe('2026-12-01T09:00:00+05:00');
    expect(fromLocalInput('')).toBeNull();
    expect(fromLocalInput('2026-13-45T99:00')).toBeNull();
    expect(fromLocalInput(toLocalInput('2026-12-10T21:00:00+05:00'))).toBe('2026-12-10T21:00:00+05:00');
  });
});

describe('ordering', () => {
  it('moves one place up or down in a copy and stops at the ends', () => {
    const ids = [1, 2, 3];
    expect(moved(ids, 1, -1)).toEqual([2, 1, 3]);
    expect(moved(ids, 1, 1)).toEqual([1, 3, 2]);
    expect(moved(ids, 0, -1)).toEqual([1, 2, 3]);
    expect(moved(ids, 2, 1)).toEqual([1, 2, 3]);
    expect(ids).toEqual([1, 2, 3]);
  });

  it('compares two orders', () => {
    expect(sameOrder([1, 2], [1, 2])).toBe(true);
    expect(sameOrder([1, 2], [2, 1])).toBe(false);
    expect(sameOrder([1], [1, 2])).toBe(false);
  });
});

describe('images', () => {
  const file = (type: string, size: number) => new File([new Uint8Array(size)], 'x', { type });

  it('accepts JPG, PNG and WebP up to 4 MB', () => {
    expect(imageProblem(file('image/png', 1000))).toBeNull();
    expect(imageProblem(file('image/webp', IMAGE_MAX_BYTES))).toBeNull();
    expect(imageProblem(file('image/jpeg', IMAGE_MAX_BYTES + 1))).toBe('size');
    expect(imageProblem(file('image/svg+xml', 10))).toBe('type');
    expect(imageProblem(file('application/pdf', 10))).toBe('type');
  });
});

describe('discountText', () => {
  // Amounts hold non-breaking spaces; compare with plain ones
  const plain = (text: string) => text.replace(/\u00a0/g, ' ');

  it('says a percentage with its cap and a fixed amount in so\'m', () => {
    expect(plain(discountText(code(), t))).toBe("10 % (eng ko'pi 8 000 so'm)");
    expect(discountText(code({ max_discount_amount: null }), t)).toBe('10 %');
    expect(plain(discountText(code({ type: 'fixed', value: '5000.00', max_discount_amount: null }), t))).toBe("5 000 so'm");
    expect(discountText(code({ value: '12.50', max_discount_amount: null }), t)).toBe('12.5 %');
  });
});
