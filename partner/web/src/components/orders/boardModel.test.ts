import { describe, expect, it } from 'vitest';
import { StaffApiError, type StaffOrder } from '@rizqim/staff';
import { createTranslator } from '@/i18n';
import { actionErrorMessage, channelLabel, columnOf, groupBoard, searchTerm, todayIn, waitingOf } from './boardModel';

const order = (uuid: string, status: string, created: string, extra: Partial<StaffOrder> = {}) =>
  ({ uuid, status, created_at: created, updated_at: created, accepted_at: null, closed_at: null, waiting_seconds: 0, ...extra }) as StaffOrder;

describe('the board layout', () => {
  it('puts each status in its column', () => {
    expect(['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery', 'delivered', 'canceled', 'rejected', 'refunded', 'odd'].map(columnOf)).toEqual([
      'new', 'preparing', 'preparing', 'ready', 'courier', 'courier', 'courier', 'done', 'done', 'done', 'done', null,
    ]);
  });

  it('groups the open orders oldest first and the finished newest first, each order once', () => {
    const a = order('a', 'new', '2026-10-20T10:05:00+05:00');
    const b = order('b', 'new', '2026-10-20T10:01:00+05:00');
    const c = order('c', 'preparing', '2026-10-20T09:00:00+05:00');
    const d = order('d', 'on_the_way', '2026-10-20T09:00:00+05:00');
    const e = order('e', 'delivered', '2026-10-20T08:00:00+05:00', { closed_at: '2026-10-20T09:00:00+05:00' });
    const f = order('f', 'canceled', '2026-10-20T08:00:00+05:00', { closed_at: '2026-10-20T09:30:00+05:00' });
    // c finished between the two requests: the newer copy wins
    const cDone = order('c', 'delivered', '2026-10-20T09:00:00+05:00', { updated_at: '2026-10-20T10:10:00+05:00', closed_at: '2026-10-20T10:10:00+05:00' });
    const groups = groupBoard([a, b, c, d], [e, f, cDone]);
    expect(groups.new.map((o) => o.uuid)).toEqual(['b', 'a']);
    expect(groups.preparing).toEqual([]);
    expect(groups.courier.map((o) => o.uuid)).toEqual(['d']);
    expect(groups.done.map((o) => o.uuid)).toEqual(['c', 'f', 'e']);
  });

  it('tells how long an order has waited and how worrying that is', () => {
    const now = Date.parse('2026-10-20T10:12:00+05:00');
    expect(waitingOf(order('a', 'new', '2026-10-20T10:10:00+05:00'), now)).toEqual({ minutes: 2, tone: 'calm' });
    expect(waitingOf(order('a', 'new', '2026-10-20T10:05:00+05:00'), now)).toEqual({ minutes: 7, tone: 'warning' });
    expect(waitingOf(order('a', 'new', '2026-10-20T10:00:00+05:00'), now)).toEqual({ minutes: 12, tone: 'late' });
    expect(waitingOf(order('a', 'preparing', '2026-10-20T10:00:00+05:00'), now)?.tone).toBe('calm');
    expect(waitingOf(order('a', 'preparing', '2026-10-20T09:30:00+05:00'), now)?.tone).toBe('late');
    expect(waitingOf(order('a', 'delivered', '2026-10-20T09:30:00+05:00'), now)).toBeNull();
  });

  it("counts by the server's rule: from acceptance while cooking, the server's number once ready", () => {
    const now = Date.parse('2026-10-20T10:12:00+05:00');
    // Accepted at 10:02 (created 09:50): 10 minutes in the kitchen, not 22
    expect(waitingOf(order('a', 'preparing', '2026-10-20T09:50:00+05:00', { accepted_at: '2026-10-20T10:02:00+05:00', waiting_seconds: 480 }), now)).toEqual({ minutes: 10, tone: 'calm' });
    // The server's snapshot when the local clock is behind it
    expect(waitingOf(order('a', 'accepted', '2026-10-20T10:12:00+05:00', { accepted_at: '2026-10-20T10:13:00+05:00', waiting_seconds: 120 }), now)?.minutes).toBe(2);
    // Ready: the clock stopped when it became ready (25 minutes), however late it is now
    expect(waitingOf(order('a', 'ready', '2026-10-20T08:00:00+05:00', { waiting_seconds: 1500 }), now)).toEqual({ minutes: 25, tone: 'warning' });
    expect(waitingOf(order('a', 'on_the_way', '2026-10-20T08:00:00+05:00', { waiting_seconds: 2700 }), now)).toEqual({ minutes: 45, tone: 'late' });
  });

  it('cleans the search box to what the API takes', () => {
    expect(searchTerm(' #a-105 ')).toBe('a105');
    expect(searchTerm('###')).toBeUndefined();
    expect(searchTerm('x'.repeat(30))).toHaveLength(20);
  });

  it('takes today in the store time zone', () => {
    expect(todayIn('Asia/Tashkent', new Date('2026-10-20T20:30:00Z'))).toBe('2026-10-21');
    expect(todayIn('Not/AZone', new Date('2026-10-20T10:00:00Z'))).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('names the channels it knows', () => {
    const t = createTranslator('uz');
    expect(channelLabel(t, 'pos_hall')).toBe('Zal (POS)');
    expect(channelLabel(t, 'kiosk')).toBe('kiosk');
  });
});

describe('refusals in words', () => {
  const t = createTranslator('uz');
  it('says a 403, 404, 409 and 429 in its own words', () => {
    expect(actionErrorMessage(new StaffApiError(403, { success: false, message: 'Unauthorized.' }), t)).toBe("Bu amal uchun ruxsatingiz yo'q.");
    expect(actionErrorMessage(new StaffApiError(403, { message: 'x', data: { reason: 'staff_session_not_allowed' } }), t)).toMatch(/Terminaldagi/);
    expect(actionErrorMessage(new StaffApiError(404, { message: 'Not found' }), t)).toMatch(/topilmadi/);
    expect(actionErrorMessage(new StaffApiError(409, {}), t)).toMatch(/boshqa joyda/);
    expect(actionErrorMessage(new StaffApiError(429, {}), t)).toMatch(/Juda ko'p/);
  });

  it('shows the server reason of a 422, or a field error, or its own words', () => {
    expect(actionErrorMessage(new StaffApiError(422, { success: false, message: "Tayyor buyurtmani qabul qilib bo'lmaydi" }), t)).toBe("Tayyor buyurtmani qabul qilib bo'lmaydi");
    expect(actionErrorMessage(new StaffApiError(422, { message: 'The given data was invalid.', errors: { reason: ['Bekor qilish sababini tanlang.'] } }), t)).toBe('Bekor qilish sababini tanlang.');
    expect(actionErrorMessage(new StaffApiError(422, null), t)).toMatch(/allaqachon/);
  });

  it('tells a network failure from anything else', () => {
    expect(actionErrorMessage(new TypeError('Failed to fetch'), t)).toMatch(/Serverga ulanib/);
    expect(actionErrorMessage(new Error('boom'), t)).toMatch(/Xatolik/);
  });
});
