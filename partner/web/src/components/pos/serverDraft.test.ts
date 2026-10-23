import { describe, expect, it } from 'vitest';
import { changedKeys, rebaseDraft, rebaseList } from './serverDraft';

describe('rebaseList', () => {
  it('keeps the additions and removals on top of the newer list', () => {
    expect(rebaseList(['2026-11-05', '2027-01-01'], ['2026-12-31', '2027-01-01'], ['2026-12-31', '2027-01-01', '2027-03-21'])).toEqual(['2026-11-05', '2027-01-01', '2027-03-21']);
    expect(rebaseList([1, 6, 7], [6, 7], [5, 6, 7])).toEqual([1, 5, 6, 7]);
  });
});

describe('rebaseDraft', () => {
  const before = { mode: 'hybrid', lock: '5', holidays: ['2026-12-31'], alerts: { void: { on: true, value: '200000' }, discount: { on: true, value: '10' } } };
  const after = { mode: 'table', lock: '7', holidays: ['2026-12-31', '2027-01-01'], alerts: { void: { on: true, value: '300000' }, discount: { on: true, value: '15' } } };

  it('takes the newer data when nothing was touched', () => {
    expect(rebaseDraft(before, before, after)).toBe(after);
  });

  it('keeps each changed field and takes the newer value of every other one', () => {
    const draft = { ...before, lock: '10', holidays: [], alerts: { ...before.alerts, discount: { on: false, value: '10' } } };
    expect(rebaseDraft(draft, before, after)).toEqual({ mode: 'table', lock: '10', holidays: ['2027-01-01'], alerts: { void: { on: true, value: '300000' }, discount: { on: false, value: '15' } } });
  });
});

describe('changedKeys', () => {
  it('names the fields that differ', () => {
    expect(changedKeys({ a: 1, b: [1], c: { d: 1 } }, { a: 1, b: [2], c: { d: 1 } })).toEqual(['b']);
  });
});
