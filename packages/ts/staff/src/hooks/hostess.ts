import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchBookingRules,
  fetchOwnerWaitlist,
  saveBookingRules,
  type BookingRules,
  type BookingRulesPatch,
  type OwnerWaitlist,
  type OwnerWaitlistQuery,
} from '../api/hostess';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// The table booking rules of a branch and the owner's waitlist view (P6-24, P6-26)
export const hostessKeys = {
  rules: (branchId: number) => ['owner', 'booking-rules', branchId] as const,
  waitlist: (query: OwnerWaitlistQuery) => ['owner', 'waitlist', query] as const,
};

export function useBookingRules(branchId: number | null, options: QueryExtras<BookingRules> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: branchId !== null && options.enabled !== false,
    queryKey: hostessKeys.rules(branchId ?? 0),
    queryFn: () => fetchBookingRules(api, branchId as number),
  });
}

export interface BookingRulesChange {
  branchId: number;
  patch: BookingRulesPatch;
}

// The answer is the branch's whole rules: it replaces the cached ones
export function useSaveBookingRules(options: MutationExtras<BookingRules, BookingRulesChange> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: BookingRulesChange) => saveBookingRules(api, branchId, patch),
    onSuccess: (...args) => {
      queryClient.setQueryData(hostessKeys.rules(args[0].branch_id), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}

export function useOwnerWaitlist(query: OwnerWaitlistQuery | null, options: QueryExtras<OwnerWaitlist> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: query !== null && options.enabled !== false,
    queryKey: hostessKeys.waitlist(query ?? {}),
    queryFn: () => fetchOwnerWaitlist(api, query as OwnerWaitlistQuery),
  });
}
