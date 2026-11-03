import { describe, expect, it } from 'vitest';
import { NBSP } from './format';
import { formatPickupTime, pickupDayWord } from './pickupTime';

const plain = (text: string) => text.replace(new RegExp(NBSP, 'g'), ' ');
// Local noon, so the test does not depend on the runner's zone
const now = new Date(2026, 9, 30, 12, 0);
const at = (day: number, hour: number, minute = 0) => new Date(2026, 9, day, hour, minute);

describe('pickup time wording', () => {
  it('says today and tomorrow in words, any other day by its date', () => {
    expect(formatPickupTime(at(30, 18, 30), 'uz', undefined, now)).toBe('Bugun, 18:30');
    expect(formatPickupTime(at(31, 9), 'uz', undefined, now)).toBe('Ertaga, 09:00');
    expect(plain(formatPickupTime(new Date(2026, 10, 2, 9), 'uz', undefined, now))).toBe('2-noy, 09:00');
    expect(formatPickupTime(at(30, 18, 30), 'ru', undefined, now)).toBe('Сегодня, 18:30');
    expect(formatPickupTime(at(31, 9), 'en', undefined, now)).toBe('Tomorrow, 09:00');
  });

  it("takes the time from the store's clock when the slot gives it, the day from the instant", () => {
    expect(formatPickupTime(at(30, 13, 30), 'uz', '18:30', now)).toBe('Bugun, 18:30');
  });

  it('a slot after midnight of a 24/7 branch is tomorrow, not the business day it belongs to', () => {
    expect(pickupDayWord(at(31, 2), 'uz', now)).toBe('Ertaga');
  });

  it('shows a dash for a time it cannot read', () => {
    expect(formatPickupTime('nope', 'uz', undefined, now)).toBe('—');
  });
});
