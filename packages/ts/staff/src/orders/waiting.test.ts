import { describe, expect, it } from 'vitest';
import { getOrderWaiting, WAITING_THRESHOLDS, waitingSecondsOf, waitingToneOf } from './waiting';

const NOW = Date.parse('2026-10-21T12:00:00+05:00');
const ago = (minutes: number) => new Date(NOW - minutes * 60_000).toISOString();
const order = (over: Partial<{ status: string; created_at: string | null; accepted_at: string | null; waiting_seconds: number }>) => ({
  status: 'new',
  created_at: ago(3),
  accepted_at: null,
  waiting_seconds: 0,
  ...over,
});

describe('the waiting time of an order', () => {
  it('runs from the creation of a new order and from the acceptance after it', () => {
    expect(getOrderWaiting(order({}), NOW).minutes).toBe(3);
    expect(getOrderWaiting(order({ status: 'preparing', created_at: ago(30), accepted_at: ago(12) }), NOW).minutes).toBe(12);
  });

  it('takes the server\'s number when it is ahead, and the stopped clock of a ready order as the server says', () => {
    expect(waitingSecondsOf(order({ waiting_seconds: 600 }), NOW)).toBe(600);
    expect(getOrderWaiting(order({ status: 'ready', created_at: ago(90), accepted_at: ago(80), waiting_seconds: 25 * 60 }), NOW)).toEqual({ minutes: 25, tone: 'warning' });
  });

  it('turns amber and red earlier for a new order than for one in the kitchen', () => {
    expect(WAITING_THRESHOLDS.new).toEqual({ warning: 5, late: 10 });
    expect(WAITING_THRESHOLDS.kitchen).toEqual({ warning: 20, late: 40 });
    expect([4, 5, 10].map((minutes) => waitingToneOf('new', minutes))).toEqual(['calm', 'warning', 'late']);
    expect([19, 20, 40].map((minutes) => waitingToneOf('preparing', minutes))).toEqual(['calm', 'warning', 'late']);
  });
});
