import { describe, expect, it } from 'vitest';
import { addDays, inZone, isoWeekday, localDateTime, mondayOf, todayIn } from './storeTime';

describe('storeTime', () => {
  it("reads an instant as the wall clock of the store (Tashkent is UTC+5)", () => {
    expect(inZone('2026-10-22T15:30:00Z', 'Asia/Tashkent')).toEqual({ day: '2026-10-22', time: '20:30', minutes: 1230 });
    // 20:00 UTC is already the next morning in Tashkent
    expect(inZone('2026-10-22T20:00:00Z', 'Asia/Tashkent')).toEqual({ day: '2026-10-23', time: '01:00', minutes: 60 });
    expect(inZone('2026-10-22T20:00:00Z', 'UTC')?.day).toBe('2026-10-22');
  });

  it('shows midnight as 00:00, not 24:00', () => {
    expect(inZone('2026-10-22T19:00:00Z', 'Asia/Tashkent')).toEqual({ day: '2026-10-23', time: '00:00', minutes: 0 });
  });

  it('survives a time that is not one, and a zone that does not exist', () => {
    expect(inZone('not a date', 'Asia/Tashkent')).toBeNull();
    expect(inZone('2026-10-22T10:00:00Z', 'Nowhere/Land')).not.toBeNull();
  });

  it('writes a local date and time as the API reads it', () => {
    expect(localDateTime('2026-10-25', '18:00')).toBe('2026-10-25T18:00:00');
  });

  it('does calendar arithmetic on days', () => {
    expect(addDays('2026-10-31', 1)).toBe('2026-11-01');
    expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
    expect(isoWeekday('2026-10-22')).toBe(4); // Thursday
    expect(isoWeekday('2026-10-25')).toBe(7); // Sunday
    expect(mondayOf('2026-10-25')).toBe('2026-10-19');
    expect(mondayOf('2026-10-19')).toBe('2026-10-19');
  });

  it("knows today in the store's zone", () => {
    expect(todayIn('Asia/Tashkent', new Date('2026-10-22T20:00:00Z'))).toBe('2026-10-23');
  });
});
