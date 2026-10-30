import type { StaffApi } from './client';
import { unwrap } from './errors';

// The hostess desk and the table booking rules (P6-24, P6-26; docs/API.md "Hostes, ofitsiantni almashtirish va stol broni"):
//  - GET/PUT /owner/branches/{id}/booking-rules: the branch's slot by place kind, lead time and optional deposit
//    (settings.manage, the owner; the table_booking module), never from a terminal's PIN session;
//  - GET /owner/waitlist: the owner's read-only view of the hostess' waitlist, guests' numbers masked (reports.view; the
//    hostess module).
// The routes are not in the generated spec yet: their paths are borrowed from routes that are (the cast keeps the call typed)
// and the answers are read as the contract says. Replace by the generated types once the spec has them.

export const BOOKING_DEPOSIT_MODES = ['off', 'flat', 'per_guest'] as const;
export type BookingDepositMode = (typeof BOOKING_DEPOSIT_MODES)[number];

export interface BookingRules {
  branch_id: number;
  // false: nothing was saved, the values are the defaults (the choyxona's behaviour)
  configured: boolean;
  // How long a booking without an end holds a table (30-480, 120); a room or tapchan: null keeps the branch's own slot (5 hours)
  table_slot_minutes: number;
  room_slot_minutes: number | null;
  tapchan_slot_minutes: number | null;
  // The least time ahead a booking may be made, per place kind (0-1440, 0: none; the choyxona's "kamida 3 soat" is 180)
  table_min_advance_minutes: number;
  room_min_advance_minutes: number;
  tapchan_min_advance_minutes: number;
  // The furthest day ahead (1-365), null: no limit
  max_advance_days: number | null;
  deposit_mode: BookingDepositMode;
  deposit_mode_label: string;
  // Money as a decimal string
  deposit_amount: string;
  // The deposit applies from a party of this many guests
  deposit_from_guests: number;
  // A booking stays a request (409 `deposit_required` on confirm) until the deposits taken add up to the amount
  deposit_required_to_confirm: boolean;
  updated_at: string | null;
}

// PUT body: only what changes; null clears a nullable key
export type BookingRulesPatch = Partial<Omit<BookingRules, 'branch_id' | 'configured' | 'deposit_mode_label' | 'updated_at'>>;

const BOOKING_RULES_PATH = '/v1/owner/branches/{id}/booking-rules' as '/v1/owner/branches/{id}/pos-settings';

export async function fetchBookingRules(api: StaffApi, branchId: number): Promise<BookingRules> {
  return unwrap(await api.client.GET(BOOKING_RULES_PATH, { params: { path: { id: branchId } } })).data as unknown as BookingRules;
}

export async function saveBookingRules(api: StaffApi, branchId: number, patch: BookingRulesPatch): Promise<BookingRules> {
  return unwrap(await api.client.PUT(BOOKING_RULES_PATH, { params: { path: { id: branchId } }, body: patch as never })).data as unknown as BookingRules;
}

export const WAITLIST_STATUSES = ['waiting', 'seated', 'canceled', 'no_show'] as const;
export type WaitlistStatus = (typeof WAITLIST_STATUSES)[number];

export interface WaitlistEntry {
  uuid: string;
  branch_id: number;
  // The branch's business day, Y-m-d
  business_date: string;
  guest_name: string;
  // The owner's view never has the number (null); the masked one is always there
  guest_phone: string | null;
  guest_phone_masked: string | null;
  party_size: number;
  quoted_wait_minutes: number | null;
  note: string | null;
  status: WaitlistStatus;
  status_label: string;
  // Place in the queue: the hostess' list only, waiting entries only
  position: number | null;
  // Minutes from writing the guest down to now (or to the moment it was closed)
  waited_minutes: number;
  dining_table_id: number | null;
  table_name: string | null;
  order_uuid: string | null;
  notified_at: string | null;
  seated_at: string | null;
  closed_at: string | null;
  version: number;
  created_at: string | null;
}

export interface WaitlistSummary {
  total: number;
  waiting: number;
  seated: number;
  canceled: number;
  no_show: number;
  party_size_total: number;
  // The wait the hostess quoted, on average, against the wait of the parties that were seated
  avg_quoted_wait_minutes: number | null;
  avg_seated_wait_minutes: number | null;
  longest_waiting_minutes: number | null;
}

export interface OwnerWaitlist {
  branch_id: number;
  from: string;
  to: string;
  entries: WaitlistEntry[];
  summary: WaitlistSummary;
}

export interface OwnerWaitlistQuery {
  branch_id?: number;
  // Business days, Y-m-d, `to` inclusive (at most 31 days); default today
  from?: string;
  to?: string;
  status?: WaitlistStatus;
}

const OWNER_WAITLIST_PATH = '/v1/owner/waitlist' as '/v1/owner/staff';

export async function fetchOwnerWaitlist(api: StaffApi, query: OwnerWaitlistQuery = {}): Promise<OwnerWaitlist> {
  return unwrap(await api.client.GET(OWNER_WAITLIST_PATH, { params: { query: query as never } })).data as unknown as OwnerWaitlist;
}
