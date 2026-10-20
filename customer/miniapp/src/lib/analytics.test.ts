import { describe, expect, it } from 'vitest';
import { MAX_CUSTOM_DAYS, getCustomRangeProblem, toAnalyticsPeriod, toDateInputValue } from './analytics';

describe('toAnalyticsPeriod', () => {
  it('names the timeframes the way GET /owner/analytics does', () => {
    expect(toAnalyticsPeriod('today')).toBe('today');
    expect(toAnalyticsPeriod('weekly')).toBe('week');
    expect(toAnalyticsPeriod('monthly')).toBe('month');
    expect(toAnalyticsPeriod('yearly')).toBe('year');
    expect(toAnalyticsPeriod('custom')).toBe('custom');
  });
});

describe('getCustomRangeProblem', () => {
  it('accepts one day and up to the longest period the server serves', () => {
    expect(getCustomRangeProblem({ start: '2026-10-05', end: '2026-10-05' })).toBeNull();
    expect(MAX_CUSTOM_DAYS).toBe(93);
    expect(getCustomRangeProblem({ start: '2026-06-01', end: '2026-09-01' })).toBeNull(); // 93 days, both ends counted
  });

  it('refuses a period of 94 days', () => {
    expect(getCustomRangeProblem({ start: '2026-06-01', end: '2026-09-02' })).toBe('too_long');
  });

  it('refuses an end before the start', () => {
    expect(getCustomRangeProblem({ start: '2026-10-05', end: '2026-10-04' })).toBe('reversed');
  });

  it('refuses a missing or half-typed date', () => {
    expect(getCustomRangeProblem(undefined)).toBe('missing');
    expect(getCustomRangeProblem({ start: '', end: '2026-10-04' })).toBe('missing');
    expect(getCustomRangeProblem({ start: '2026-10-05', end: '2026-1' })).toBe('missing');
  });

  it('counts days across a change of the clocks the same as any other', () => {
    expect(getCustomRangeProblem({ start: '2026-03-01', end: '2026-05-31' })).toBeNull(); // 92 days over a DST change in many zones
  });
});

describe('toDateInputValue', () => {
  it('is the local calendar day, not the UTC one', () => {
    expect(toDateInputValue(new Date(2026, 9, 5, 0, 30))).toBe('2026-10-05');
    expect(toDateInputValue(new Date(2026, 9, 5, 23, 30))).toBe('2026-10-05');
    expect(toDateInputValue(new Date(2026, 0, 9))).toBe('2026-01-09');
  });
});
