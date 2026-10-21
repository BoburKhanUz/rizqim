import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchAllStaffOrders,
  fetchBranchList,
  fetchStaffOrder,
  fetchStaffOrders,
  performBoardAction,
  type BoardActionRequest,
  type BranchListItem,
  type StaffOrderDetail,
  type StaffOrdersList,
  type StaffOrdersQuery,
} from '../api/board';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// P2-21: hooks of the live orders board over GET /staff/orders*. The keys are their own (every one under
// ['staff-orders']), so a realtime event can refetch the board's lists or one order without touching the other screens.
export const boardKeys = {
  all: ['staff-orders'] as const,
  lists: ['staff-orders', 'list'] as const,
  list: (query: StaffOrdersQuery) => ['staff-orders', 'list', query] as const,
  pages: (query: StaffOrdersQuery) => ['staff-orders', 'pages', query] as const,
  details: ['staff-orders', 'detail'] as const,
  detail: (uuid: string) => ['staff-orders', 'detail', uuid] as const,
  branches: ['owner', 'branches', 'list'] as const,
};

// Every page of a filter (a board's open orders), see fetchAllStaffOrders. Polling comes in through the options.
export function useStaffOrders(query: StaffOrdersQuery, options: QueryExtras<StaffOrdersList> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: boardKeys.list(query), queryFn: () => fetchAllStaffOrders(api, query) });
}

// A filter a page at a time (finished orders): `fetchNextPage` follows the cursor.
export function useStaffOrderPages(query: StaffOrdersQuery, options: { enabled?: boolean; refetchInterval?: number | false } = {}) {
  const api = useStaffApi();
  return useInfiniteQuery({
    queryKey: boardKeys.pages(query),
    queryFn: ({ pageParam }) => fetchStaffOrders(api, query, pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.nextCursor ?? undefined,
    enabled: options.enabled ?? true,
    refetchInterval: options.refetchInterval,
  });
}

// One order with its items, kitchen state and status history (GET /staff/orders/{uuid}). `uuid` undefined: idle.
export function useStaffOrder(uuid: string | undefined, options: QueryExtras<StaffOrderDetail> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    queryKey: boardKeys.detail(uuid ?? ''),
    queryFn: () => fetchStaffOrder(api, uuid ?? ''),
    enabled: uuid !== undefined && uuid !== '' && (options.enabled ?? true),
  });
}

// A board action (accept, reject, status, courier, cancel) on the endpoint that takes it; afterwards every board list,
// the order's detail and the older order lists are refetched. The caller's own callbacks run as well.
export function useBoardAction(options: MutationExtras<{ uuid: string; status: string }, BoardActionRequest> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (request: BoardActionRequest) => performBoardAction(api, request),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: boardKeys.all });
      void queryClient.invalidateQueries({ queryKey: staffKeys.orders });
      return options.onSuccess?.(...args);
    },
  });
}

// The store's branches for a branch picker (a branch-bound caller gets only their own)
export function useBranchList(options: QueryExtras<BranchListItem[]> = {}) {
  const api = useStaffApi();
  return useQuery({ staleTime: 5 * 60_000, ...options, queryKey: boardKeys.branches, queryFn: () => fetchBranchList(api) });
}
