import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { BOOKING_DEPOSIT_MODES, fetchBookingRules, fetchOwnerWaitlist, saveBookingRules, WAITLIST_STATUSES } from './hostess';

afterEach(() => vi.unstubAllGlobals());

const RULES = {
  branch_id: 3,
  configured: true,
  table_slot_minutes: 120,
  room_slot_minutes: null,
  tapchan_slot_minutes: 240,
  table_min_advance_minutes: 60,
  room_min_advance_minutes: 180,
  tapchan_min_advance_minutes: 0,
  max_advance_days: 30,
  deposit_mode: 'per_guest',
  deposit_mode_label: "Har bir mehmon uchun bo'nak",
  deposit_amount: '50000.00',
  deposit_from_guests: 6,
  deposit_required_to_confirm: true,
  updated_at: '2026-11-05T10:00:00Z',
};

describe('the table booking rules (P6-26)', () => {
  it("reads a branch's rules and saves only what changed", async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/booking-rules': () => ok(RULES),
      'PUT /api/v1/owner/branches/3/booking-rules': () => ok({ ...RULES, table_slot_minutes: 90 }),
    });
    const api = testApi();
    expect((await fetchBookingRules(api, 3)).room_min_advance_minutes).toBe(180);
    expect((await saveBookingRules(api, 3, { table_slot_minutes: 90, room_slot_minutes: null })).table_slot_minutes).toBe(90);
    expect(await calls[1]!.json()).toEqual({ table_slot_minutes: 90, room_slot_minutes: null });
  });

  it("keeps the server's refusal of a table booking module that is off", async () => {
    mockFetch({
      'GET /api/v1/owner/branches/3/booking-rules': () => json({ success: false, message: 'Stol broni yoqilmagan.', data: { reason: 'module_disabled' } }, 403),
    });
    const error = (await fetchBookingRules(testApi(), 3).catch((e: unknown) => e)) as { reason?: string; status?: number };
    expect(error.status).toBe(403);
    expect(error.reason).toBe('module_disabled');
  });
});

describe("the owner's waitlist view (P6-24)", () => {
  it('reads the days with the guests masked and the summary', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/waitlist': () =>
        ok({
          branch_id: 3,
          from: '2026-11-05',
          to: '2026-11-06',
          entries: [{ uuid: 'w1', guest_name: 'Akmal', guest_phone: null, guest_phone_masked: '+99890*****67', party_size: 4, status: 'seated' }],
          summary: { total: 1, waiting: 0, seated: 1, canceled: 0, no_show: 0, party_size_total: 4, avg_quoted_wait_minutes: 25, avg_seated_wait_minutes: 18, longest_waiting_minutes: null },
        }),
    });
    const list = await fetchOwnerWaitlist(testApi(), { branch_id: 3, from: '2026-11-05', to: '2026-11-06', status: 'seated' });
    expect(list.entries[0]!.guest_phone).toBeNull();
    expect(list.summary.avg_seated_wait_minutes).toBe(18);
    const params = new URL(calls[0]!.url).searchParams;
    expect([params.get('branch_id'), params.get('from'), params.get('to'), params.get('status')]).toEqual(['3', '2026-11-05', '2026-11-06', 'seated']);
  });

  it('names the deposit modes and the waitlist states of the backend', () => {
    expect(BOOKING_DEPOSIT_MODES).toEqual(['off', 'flat', 'per_guest']);
    expect(WAITLIST_STATUSES).toEqual(['waiting', 'seated', 'canceled', 'no_show']);
  });
});
