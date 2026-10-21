import { describe, expect, it } from 'vitest';
import { addDays, daysInRange, getPeriodProblem, isDay, MAX_REPORT_DAYS, resolvePeriod, startOfMonth, todayIn } from './period';

describe('day arithmetic', () => {
  it('moves across months, years and leap days', () => {
    expect(addDays('2026-10-21', -6)).toBe('2026-10-15');
    expect(addDays('2026-03-01', -1)).toBe('2026-02-28');
    expect(addDays('2028-03-01', -1)).toBe('2028-02-29');
    expect(addDays('2026-01-03', -5)).toBe('2025-12-29');
    expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
  });

  it('is not moved by a change of the clocks', () => {
    expect(addDays('2026-03-28', 1)).toBe('2026-03-29');
    expect(addDays('2026-10-24', 2)).toBe('2026-10-26');
  });

  it('counts both ends and refuses what is not a day', () => {
    expect(daysInRange({ from: '2026-10-21', to: '2026-10-21' })).toBe(1);
    expect(daysInRange({ from: '2026-10-15', to: '2026-10-21' })).toBe(7);
    expect(daysInRange({ from: 'x', to: '2026-10-21' })).toBeNaN();
    expect(isDay('2026-02-31')).toBe(false);
    expect(isDay('2026-2-3')).toBe(false);
    expect(isDay('2026-02-28')).toBe(true);
    expect(() => addDays('nope', 1)).toThrow(RangeError);
  });

  it('finds the first day of the month', () => {
    expect(startOfMonth('2026-10-21')).toBe('2026-10-01');
  });
});

describe('presets from the store\'s today', () => {
  const today = '2026-10-21';

  it('resolves every preset', () => {
    expect(resolvePeriod('today', today)).toEqual({ from: '2026-10-21', to: '2026-10-21' });
    expect(resolvePeriod('yesterday', today)).toEqual({ from: '2026-10-20', to: '2026-10-20' });
    expect(resolvePeriod('last7', today)).toEqual({ from: '2026-10-15', to: '2026-10-21' });
    expect(resolvePeriod('last30', today)).toEqual({ from: '2026-09-22', to: '2026-10-21' });
    expect(resolvePeriod('thisMonth', today)).toEqual({ from: '2026-10-01', to: '2026-10-21' });
  });

  it('keeps the counts: 7 and 30 days, both ends included', () => {
    expect(daysInRange(resolvePeriod('last7', today)!)).toBe(7);
    expect(daysInRange(resolvePeriod('last30', today)!)).toBe(30);
  });

  it('crosses a month and a year boundary', () => {
    expect(resolvePeriod('yesterday', '2026-01-01')).toEqual({ from: '2025-12-31', to: '2025-12-31' });
    expect(resolvePeriod('thisMonth', '2026-11-01')).toEqual({ from: '2026-11-01', to: '2026-11-01' });
    expect(resolvePeriod('last7', '2026-03-03')).toEqual({ from: '2026-02-25', to: '2026-03-03' });
  });

  it('takes a custom period as typed, and none until both days are there', () => {
    expect(resolvePeriod('custom', today, { from: '2026-10-01', to: '2026-10-05' })).toEqual({ from: '2026-10-01', to: '2026-10-05' });
    expect(resolvePeriod('custom', today, { from: '2026-10-01' })).toBeNull();
    expect(resolvePeriod('custom', today)).toBeNull();
  });
});

describe('what the server would refuse', () => {
  it('says missing, reversed and too long', () => {
    expect(getPeriodProblem(null)).toBe('missing');
    expect(getPeriodProblem({ from: '2026-10-01', to: '2026-1' })).toBe('missing');
    expect(getPeriodProblem({ from: '2026-10-05', to: '2026-10-01' })).toBe('reversed');
    expect(getPeriodProblem({ from: '2026-10-01', to: '2026-10-01' })).toBeNull();
  });

  it('allows 366 days and not 367', () => {
    expect(MAX_REPORT_DAYS).toBe(366);
    expect(getPeriodProblem({ from: '2025-10-21', to: '2026-10-21' })).toBeNull(); // 366 days
    expect(getPeriodProblem({ from: '2025-10-20', to: '2026-10-21' })).toBe('too_long'); // 367
  });
});

describe('today in a timezone', () => {
  it('is the store\'s calendar day, not the viewer\'s', () => {
    const lateInUtc = new Date('2026-10-20T20:30:00Z'); // 01:30 on the 21st in Tashkent (UTC+5)
    expect(todayIn('Asia/Tashkent', lateInUtc)).toBe('2026-10-21');
    expect(todayIn('UTC', lateInUtc)).toBe('2026-10-20');
    expect(todayIn('America/New_York', lateInUtc)).toBe('2026-10-20');
  });

  it('falls back to Tashkent for a zone it does not know', () => {
    expect(todayIn('Mars/Olympus', new Date('2026-10-20T20:30:00Z'))).toBe('2026-10-21');
  });
});
