import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  cancelBooking,
  confirmBooking,
  createBooking,
  fetchBooking,
  fetchBookingCalendar,
  fetchBookings,
  markBookingNoShow,
  updateBooking,
  type BookingCalendar,
  type BookingCalendarQuery,
  type BookingDetail,
  type BookingInput,
  type BookingListQuery,
  type BookingPatch,
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
