import { describe, expect, it } from 'vitest';
import { formatUzbekSum } from './format';

describe('formatUzbekSum', () => {
  it('groups thousands with a space and adds the currency', () => {
    expect(formatUzbekSum(0)).toBe("0 so'm");
    expect(formatUzbekSum(999)).toBe("999 so'm");
    expect(formatUzbekSum(12000)).toBe("12 000 so'm");
    expect(formatUzbekSum(1234567)).toBe("1 234 567 so'm");
  });

  it('rounds, and reads the decimal strings the API sends', () => {
    expect(formatUzbekSum('25000.00')).toBe("25 000 so'm");
    expect(formatUzbekSum('12000.5')).toBe("12 001 so'm");
    expect(formatUzbekSum(99.4)).toBe("99 so'm");
  });

  it('shows a missing value as zero', () => {
    expect(formatUzbekSum(undefined)).toBe("0 so'm");
    expect(formatUzbekSum(null)).toBe("0 so'm");
  });
});
