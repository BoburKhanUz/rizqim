import { describe, expect, it } from 'vitest';
import { axisFor, byDay, clock, DEFAULT_AXIS, hourMarks, isOpenBooking, minutesOnDay, place } from './calendarModel';

const TZ = 'Asia/Tashkent'; // UTC+5
// 18:00-22:00 in Tashkent on 22 October
const evening = { starts_at: '2026-10-22T13:00:00Z', ends_at: '2026-10-22T17:00:00Z' };

describe('calendar model', () => {
  it('reads a booking as minutes of the store day', () => {
    expect(minutesOnDay(evening, '2026-10-22', TZ)).toEqual({ start: 1080, end: 1320, continuesAfter: false, continuesBefore: false });
    expect(minutesOnDay(evening, '2026-10-23', TZ)).toBeNull();
  });

  it('clips a booking over midnight to each day it touches', () => {
    const late = { starts_at: '2026-10-22T17:00:00Z', ends_at: '2026-10-22T20:00:00Z' }; // 22:00 - 01:00
    expect(minutesOnDay(late, '2026-10-22', TZ)).toEqual({ start: 1320, end: 1440, continuesAfter: true, continuesBefore: false });
    expect(minutesOnDay(late, '2026-10-23', TZ)).toEqual({ start: 0, end: 60, continuesAfter: false, continuesBefore: true });
  });

  it('positions a booking as percents of the line', () => {
    const placed = place(evening, '2026-10-22', TZ, DEFAULT_AXIS)!;
    expect(placed.left).toBeCloseTo(62.5); // (1080-480)/960
    expect(placed.width).toBeCloseTo(25);
    expect(place(evening, '2026-10-21', TZ, DEFAULT_AXIS)).toBeNull();
  });

  it('keeps a short booking clickable', () => {
    const short = { starts_at: '2026-10-22T13:00:00Z', ends_at: '2026-10-22T13:05:00Z' };
    expect(place(short, '2026-10-22', TZ, DEFAULT_AXIS)!.width).toBe(2);
  });

  it('stretches the line to hold a morning or a late booking, in whole hours', () => {
    expect(axisFor([evening], '2026-10-22', TZ)).toEqual(DEFAULT_AXIS);
    const morning = { starts_at: '2026-10-22T02:30:00Z', ends_at: '2026-10-22T03:30:00Z' }; // 07:30 - 08:30
    expect(axisFor([morning], '2026-10-22', TZ)).toEqual({ start: 420, end: DEFAULT_AXIS.end });
  });

  it('marks the hours, every second one on a long line', () => {
    expect(hourMarks({ start: 600, end: 840 })).toEqual([600, 660, 720, 780, 840]);
    expect(hourMarks(DEFAULT_AXIS)).toHaveLength(9);
    expect(clock(1080)).toBe('18:00');
    expect(clock(1440)).toBe('00:00');
  });

  it('groups a room\'s bookings by the day they start on, earliest first', () => {
    const next = { starts_at: '2026-10-23T05:00:00Z', ends_at: '2026-10-23T08:00:00Z' };
    const early = { starts_at: '2026-10-22T07:00:00Z', ends_at: '2026-10-22T09:00:00Z' };
    const days = byDay([evening, next, early], TZ);
    expect([...days.keys()].sort()).toEqual(['2026-10-22', '2026-10-23']);
    expect(days.get('2026-10-22')).toEqual([early, evening]);
  });

  it('knows which bookings still hold their room', () => {
    expect(isOpenBooking('requested')).toBe(true);
    expect(isOpenBooking('confirmed')).toBe(true);
    expect(isOpenBooking('arrived')).toBe(false);
    expect(isOpenBooking('canceled')).toBe(false);
  });
});
