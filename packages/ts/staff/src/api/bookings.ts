import type { StaffApi } from './client';
import { unwrap } from './errors';
import { newIdempotencyKey, withIdempotency } from './idempotency';
import type { JsonRequest, OkData } from './types';

// The booking book of P3-17 (docs/API.md "Bron, avans va nasiya"). The routes are the POS ones
// (/pos/bookings): the web panel uses them with a normal owner or manager sign-in (no terminal
// needed), `bookings.manage` for bookings. The server decides collisions (409 `booking_conflict` with the bookings in the
// way), status changes (422 `invalid_transition`) and stale edits (409 `stale_version`); the screen shows its message.

// --- Bookings ----------------------------------------------------------------------------------------------------------

// P3-38: the booking JSON carries when and how the guest was last sent the confirmation (null: never). Written out here until
// the generated client carries it (the intersection then says the same thing twice, harmlessly).
export interface BookingConfirmationFields {
  confirmation_sent_at?: string | null;
  // "telegram" or "sms"
  confirmation_channel?: string | null;
}

export type BookingCalendar = OkData<'booking.calendar'>;
export type CalendarRoom = BookingCalendar['rooms'][number];
export type Booking = CalendarRoom['bookings'][number] & BookingConfirmationFields;
export type BookingDetail = OkData<'booking.show'> & BookingConfirmationFields;
export type BookingPayment = BookingDetail['payments'][number];
export type BookingInput = JsonRequest<'booking.store'>;
export type BookingPatch = NonNullable<JsonRequest<'booking.update'>>;

export const BOOKING_STATUSES = ['requested', 'confirmed', 'arrived', 'completed', 'canceled', 'no_show'] as const;
export type BookingStatus = (typeof BOOKING_STATUSES)[number];
export const BOOKING_EVENT_TYPES = ['gap', 'ziyofat', 'tugilgan_kun', 'nahor_oshi', 'korporativ', 'other'] as const;
export type BookingEventType = (typeof BOOKING_EVENT_TYPES)[number];
export const BOOKING_SOURCES = ['phone', 'telegram', 'instagram', 'walk_in', 'other'] as const;
export type BookingSource = (typeof BOOKING_SOURCES)[number];

export interface BookingCalendarQuery {
  branch_id?: number;
  // Y-m-d in the store's timezone; at most 31 days
  from: string;
  to: string;
  include_canceled?: boolean;
}

// GET /pos/bookings/calendar: the branch's rooms in hall order, each with its bookings in the range; bookings without a room
// are in `unassigned`. Times are UTC ISO strings; `timezone` is the store's.
export async function fetchBookingCalendar(api: StaffApi, query: BookingCalendarQuery): Promise<BookingCalendar> {
  return unwrap(await api.client.GET('/v1/pos/bookings/calendar', { params: { query } })).data;
}

export interface BookingListQuery {
  branch_id?: number;
  status?: BookingStatus;
  from?: string;
  to?: string;
  // At least 4 digits, in any format: the history of one guest or group
  phone?: string;
}

export async function fetchBookings(api: StaffApi, query: BookingListQuery = {}): Promise<Booking[]> {
  const body = unwrap(await api.client.GET('/v1/pos/bookings', { params: { query } }));
  return body.data?.bookings ?? [];
}

// GET /pos/bookings/{uuid}: with the pre-ordered lines (`items`) and the deposits (`payments`)
export async function fetchBooking(api: StaffApi, uuid: string): Promise<BookingDetail> {
  return unwrap(await api.client.GET('/v1/pos/bookings/{uuid}', { params: { path: { uuid } } })).data;
}

// POST /pos/bookings. `contact_phone` may be typed in any format (the server makes it +998XXXXXXXXX); a time without a zone
// is read in the store's timezone. `confirm: true` books it as confirmed at once. 409 `booking_conflict`.
export async function createBooking(api: StaffApi, input: BookingInput, key: string = newIdempotencyKey()): Promise<BookingDetail> {
  return unwrap(await api.client.POST('/v1/pos/bookings', { body: input, ...withIdempotency(key) })).data as BookingDetail;
}

// PATCH: only what is sent changes; a new time or room is checked for collisions again. `version` (from the booking) makes a
// stale edit a 409 `stale_version` instead of overwriting another device's change.
export async function updateBooking(api: StaffApi, uuid: string, patch: BookingPatch, key: string = newIdempotencyKey()): Promise<BookingDetail> {
  return unwrap(await api.client.PATCH('/v1/pos/bookings/{uuid}', { params: { path: { uuid } }, body: patch, ...withIdempotency(key) })).data as BookingDetail;
}

// POST /pos/bookings/{uuid}/confirm: requested -> confirmed
export async function confirmBooking(api: StaffApi, uuid: string, key: string = newIdempotencyKey()): Promise<BookingDetail> {
  return unwrap(await api.client.POST('/v1/pos/bookings/{uuid}/confirm', { params: { path: { uuid } }, ...withIdempotency(key) })).data as BookingDetail;
}

// POST /pos/bookings/{uuid}/cancel: the reason is required
export async function cancelBooking(api: StaffApi, uuid: string, reason: string, version?: number | null, key: string = newIdempotencyKey()): Promise<BookingDetail> {
  const body = { reason, ...(version === undefined || version === null ? {} : { version }) };
  return unwrap(await api.client.POST('/v1/pos/bookings/{uuid}/cancel', { params: { path: { uuid } }, body, ...withIdempotency(key) })).data as BookingDetail;
}

// POST /pos/bookings/{uuid}/no-show: only after the booking's start
export async function markBookingNoShow(api: StaffApi, uuid: string, key: string = newIdempotencyKey()): Promise<BookingDetail> {
  return unwrap(await api.client.POST('/v1/pos/bookings/{uuid}/no-show', { params: { path: { uuid } }, ...withIdempotency(key) })).data as BookingDetail;
}

// --- The confirmation the guest receives (P3-38; docs/API.md "Bron tasdig'i") -------------------------------------------------------
// Written out by hand from the contract until the generated client carries the routes. The web panel reads bookings through
// the /pos routes (an owner or manager sign-in), so the confirmation uses them too; there is no owner twin to call.

export const CONFIRMATION_LINE_KINDS = ['header', 'place', 'time', 'room', 'room_fee', 'min_spend', 'service_charge', 'place_fee', 'pre_order', 'deposit', 'cancel_policy', 'contact'] as const;
export type ConfirmationLineKind = (typeof CONFIRMATION_LINE_KINDS)[number];

export interface ConfirmationLine {
  kind: ConfirmationLineKind;
  label: string;
  value: string;
}

export interface BookingConfirmation {
  // The ready message, as the guest gets it
  text: string;
  lines: ConfirmationLine[];
  // The room fee the order will use for that date's day type; null when no room is assigned yet
  room_fee: { amount: string; day_type: string; tariff_id: number } | null;
  // What a send can use right now
  channels: { telegram: boolean; sms: boolean };
}

export const CONFIRMATION_CHANNELS = ['auto', 'telegram', 'sms'] as const;
export type ConfirmationChannel = (typeof CONFIRMATION_CHANNELS)[number];

export interface ConfirmationSent {
  // The channel the message was queued on: "telegram" or "sms"
  channel: string;
  queued: boolean;
}

// The routes are not in the generated spec yet: their paths are borrowed from the booking's own routes (the cast keeps the
// call typed) and the answers are read as the contract says. Replace by the generated types once the spec has them.
const CONFIRMATION_PATH = '/v1/pos/bookings/{uuid}/confirmation' as '/v1/pos/bookings/{uuid}';
const CONFIRMATION_SEND_PATH = '/v1/pos/bookings/{uuid}/confirmation/send' as '/v1/pos/bookings/{uuid}/confirm';

// GET /pos/bookings/{uuid}/confirmation: the text and its lines, built from the same room tariff, day type, minimum spend and
// service charge the order will use later
export async function fetchBookingConfirmation(api: StaffApi, uuid: string): Promise<BookingConfirmation> {
  return unwrap(await api.client.GET(CONFIRMATION_PATH, { params: { path: { uuid } } })).data as unknown as BookingConfirmation;
}

// POST /pos/bookings/{uuid}/confirmation/send: queues the message ("auto": Telegram when the guest has a chat with the store's
// bot, else SMS when the branch switched it on, else 422 `no_channel`). Sending again is allowed, each time with a new key.
export async function sendBookingConfirmation(api: StaffApi, uuid: string, channel: ConfirmationChannel = 'auto', key: string = newIdempotencyKey()): Promise<ConfirmationSent> {
  const response = await api.client.POST(CONFIRMATION_SEND_PATH, { params: { path: { uuid } }, body: { channel } as never, ...withIdempotency(key) });
  return unwrap(response).data as unknown as ConfirmationSent;
}
