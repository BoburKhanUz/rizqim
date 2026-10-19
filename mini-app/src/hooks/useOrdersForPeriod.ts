import { useMemo, useState } from 'react';
import { getTimeframeStart, type DateRange, type Timeframe } from '../lib/analytics';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { mergeOrders, useOrderHistory, useStaffOrders } from './useOrders';

const HISTORY_PAGE_SIZE = 50; // the largest page the backend serves: a period needs many of them

// The owner's orders for the analytics and reports screens: the open ones (polled) plus the finished ones back to the
// start of the timeframe, loaded page by page. A year of a busy store is more than is worth loading in full, so the
// history stops after a number of pages and `capped` tells the screen it is showing only the newest part.
//
// Refetching all those pages whenever an order finishes would cost up to 15 requests each time, so they are refreshed
// only every few minutes (useOrderHistory's `period`). What just finished comes from the newest page of the history
// instead, which is refetched, alone, when an order leaves the open ones.
export function useOrdersForPeriod(timeframe: Timeframe, range?: DateRange) {
  const active = useStaffOrders('admin', POLL_INTERVAL_MS.adminOrders);
  const rangeStart = range?.start;
  const rangeEnd = range?.end;

  // "Since" is measured from when the screen opened, so the pagination does not restart whenever the data refreshes
  const [openedAt] = useState(() => Date.now());
  const since = useMemo(
    () => getTimeframeStart(timeframe, openedAt, rangeStart && rangeEnd ? { start: rangeStart, end: rangeEnd } : undefined),
    [timeframe, openedAt, rangeStart, rangeEnd],
  );
  const history = useOrderHistory('admin', {
    pageSize: HISTORY_PAGE_SIZE,
    since: since !== null && Number.isFinite(since) ? since : undefined,
    period: true,
  });
  const latest = useOrderHistory('admin');

  const activeOrders = active.data?.orders;
  const historyOrders = history.orders;
  const latestOrders = latest.orders;
  // The latest page is refetched whenever an order finishes, so its copies win over the period's pages (and finished
  // copies over open ones)
  const orders = useMemo(
    () => mergeOrders(activeOrders ?? [], mergeOrders(historyOrders, latestOrders)),
    [activeOrders, historyOrders, latestOrders],
  );

  return {
    orders,
    isLoading: active.isLoading || history.isLoading || latest.isLoading,
    error: active.error ?? (history.isError ? history.error : null) ?? (latest.isError ? latest.error : null),
    dataUpdatedAt: Math.max(active.dataUpdatedAt, history.dataUpdatedAt, latest.dataUpdatedAt),
    truncated: active.data?.truncated === true,
    capped: history.capped,
  };
}
