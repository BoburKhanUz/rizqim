import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  cancelBooking,
  confirmBooking,
  createBooking,
  fetchBooking,
  fetchBookingCalendar,
  fetchBookingConfirmation,
  fetchBookings,
  markBookingNoShow,
  sendBookingConfirmation,
  updateBooking,
  type BookingCalendar,
  type BookingCalendarQuery,
  type BookingConfirmation,
  type BookingDetail,
  type BookingInput,
  type BookingListQuery,
  type BookingPatch,
  type ConfirmationChannel,
  type ConfirmationSent,
  type Booking,
} from '../api/bookings';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The booking book (P3-17). The calendar is the screen's main query; every change reads the calendar and the lists again.
export const bookingKeys = {
  all: ['pos', 'bookings'] as const,
  calendar: (query: BookingCalendarQuery) => ['pos', 'bookings', 'calendar', query] as const,
  list: (query: BookingListQuery) => ['pos', 'bookings', 'list', query] as const,
  detail: (uuid: string) => ['pos', 'bookings', 'detail', uuid] as const,
  confirmation: (uuid: string) => ['pos', 'bookings', 'confirmation', uuid] as const,
};

export function useBookingCalendar(query: BookingCalendarQuery | null, options: QueryExtras<BookingCalendar> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: query !== null && options.enabled !== false,
    queryKey: bookingKeys.calendar(query ?? { from: '', to: '' }),
    queryFn: () => fetchBookingCalendar(api, query as BookingCalendarQuery),
  });
}

export function useBookings(query: BookingListQuery = {}, options: QueryExtras<Booking[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: bookingKeys.list(query), queryFn: () => fetchBookings(api, query) });
}

export function useBooking(uuid: string | null, options: QueryExtras<BookingDetail> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: bookingKeys.detail(uuid ?? ''),
    queryFn: () => fetchBooking(api, uuid as string),
  });
}

function useBookingMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: bookingKeys.all });
      return options.onSuccess?.(...args);
    },
  });
}

// 409 `booking_conflict` (`data.conflicts`: the bookings in the way) is the caller's to show
export function useCreateBooking(options: MutationExtras<BookingDetail, BookingInput> = {}) {
  const api = useStaffApi();
  return useBookingMutation((input: BookingInput) => createBooking(api, input), options);
}

export function useUpdateBooking(options: MutationExtras<BookingDetail, { uuid: string; patch: BookingPatch }> = {}) {
  const api = useStaffApi();
  return useBookingMutation(({ uuid, patch }: { uuid: string; patch: BookingPatch }) => updateBooking(api, uuid, patch), options);
}

export function useConfirmBooking(options: MutationExtras<BookingDetail, string> = {}) {
  const api = useStaffApi();
  return useBookingMutation((uuid: string) => confirmBooking(api, uuid), options);
}

export function useCancelBooking(options: MutationExtras<BookingDetail, { uuid: string; reason: string; version?: number | null }> = {}) {
  const api = useStaffApi();
  return useBookingMutation(({ uuid, reason, version }: { uuid: string; reason: string; version?: number | null }) => cancelBooking(api, uuid, reason, version), options);
}

export function useMarkBookingNoShow(options: MutationExtras<BookingDetail, string> = {}) {
  const api = useStaffApi();
  return useBookingMutation((uuid: string) => markBookingNoShow(api, uuid), options);
}

// P3-38: the confirmation text the guest gets. It is built by the server from the booking and the room's tariff, so it is read
// again whenever the booking changes (every booking mutation invalidates `bookingKeys.all`).
export function useBookingConfirmation(uuid: string | null, options: QueryExtras<BookingConfirmation> = {}) {
  const api = useStaffApi();
  return useQuery({
    // Always read again when shown: the tariff, the channels and the branch settings can change under a cached text
    staleTime: 0,
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: bookingKeys.confirmation(uuid ?? ''),
    queryFn: () => fetchBookingConfirmation(api, uuid as string),
  });
}

// Sends the confirmation (a new idempotency key per send, so sending again is a new message). 422 `no_channel` is the caller's to
// show: the guest has no Telegram chat with the bot and the branch has not switched SMS on.
export function useSendBookingConfirmation(options: MutationExtras<ConfirmationSent, { uuid: string; channel?: ConfirmationChannel }> = {}) {
  const api = useStaffApi();
  return useBookingMutation(({ uuid, channel }: { uuid: string; channel?: ConfirmationChannel }) => sendBookingConfirmation(api, uuid, channel), options);
}
