import { describe, expect, it } from 'vitest';
import type { CalendarHall, EventSummary, SlotTimes } from './api';
import { daysOf, eventsOn, monthOf, slotFree, span, stepFrom, weekOf } from './calendarModel';

const SLOTS: SlotTimes = { morning: { start: '06:00', end: '11:00' }, day: { start: '12:00', end: '16:00' }, evening: { start: '18:00', end: '23:00' } };

const event = (over: Partial<EventSummary>): EventSummary =>
  ({ uuid: 'e', event_date: '2030-03-20', start_time: '18:00', end_time: '23:00', starts_at: '2030-03-20T13:00:00Z', status: 'hold', hall_ids: [1], ...over }) as EventSummary;

describe('the calendar ranges', () => {
  it('finds the month and the week around a day and steps between them', () => {
    expect(monthOf('2030-02-14')).toEqual({ from: '2030-02-01', to: '2030-02-28' });
    expect(monthOf('2030-12-31')).toEqual({ from: '2030-12-01', to: '2030-12-31' });
    expect(weekOf('2030-03-20')).toEqual({ from: '2030-03-18', to: '2030-03-24' });
    expect(stepFrom('month', '2030-12-15', 1)).toBe('2031-01-01');
    expect(stepFrom('month', '2030-01-15', -1)).toBe('2029-12-01');
    expect(stepFrom('week', '2030-03-20', -1)).toBe('2030-03-11');
    expect(daysOf('2030-02-27', '2030-03-02')).toEqual(['2030-02-27', '2030-02-28', '2030-03-01', '2030-03-02']);
  });
});

describe('free slots', () => {
  it('a slot is taken by an event of that slot or a custom time overlapping it, not by a cancelled one', () => {
    const hall: CalendarHall = {
      id: 1,
      name: 'Oq zal',
      is_active: true,
      events: [
        event({ uuid: 'a', start_time: '15:00', end_time: '17:00', starts_at: '2030-03-20T10:00:00Z' }),
        event({ uuid: 'b', status: 'cancelled' }),
        event({ uuid: 'c', event_date: '2030-03-21', start_time: '22:00', end_time: '01:00' }),
      ],
    };
    expect(slotFree(hall, '2030-03-20', 'day', SLOTS)).toBe(false);
    expect(slotFree(hall, '2030-03-20', 'evening', SLOTS)).toBe(true);
    expect(slotFree(hall, '2030-03-20', 'morning', SLOTS)).toBe(true);
    expect(slotFree(hall, '2030-03-21', 'evening', SLOTS)).toBe(false);
    expect(span('22:00', '01:00')).toEqual([1320, 1500]);
    expect(eventsOn(hall, '2030-03-20').map((e) => e.uuid)).toEqual(['a', 'b']);
  });
});
