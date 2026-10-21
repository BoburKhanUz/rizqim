import { useOwnerAnalytics as useStaffOwnerAnalytics, type DateRange, type Timeframe } from '@rizqim/staff';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';

export { ownerAnalyticsKey } from '@rizqim/staff';

// The figures of the analytics and reports screens for a timeframe: ONE request, whatever the size of the store (the
// server aggregates). The request, the cache key and the rule for a custom range the server would refuse (`rangeProblem`)
// are @rizqim/staff's (shared with the partner web); the Mini App adds its polling: every minute while the screen is
// visible, which is as long as the server keeps a period's figures.
export function useOwnerAnalytics(timeframe: Timeframe, range?: DateRange) {
  return useStaffOwnerAnalytics(timeframe, range, pollingQueryOptions(POLL_INTERVAL_MS.ownerAnalytics));
}
