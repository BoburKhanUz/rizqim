import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { getCustomRangeProblem, toAnalyticsPeriod, type DateRange, type Timeframe } from '../lib/analytics';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import type { ApiResponse, OwnerAnalytics } from '../types';

export const ownerAnalyticsKeys = {
  all: ['owner', 'analytics'] as const,
  period: (timeframe: Timeframe, range?: DateRange) =>
    ['owner', 'analytics', toAnalyticsPeriod(timeframe), timeframe === 'custom' ? range?.start : null, timeframe === 'custom' ? range?.end : null] as const,
};

export async function fetchOwnerAnalytics(timeframe: Timeframe, range?: DateRange): Promise<OwnerAnalytics> {
  const params: Record<string, string> = { period: toAnalyticsPeriod(timeframe) };
  if (timeframe === 'custom' && range) {
    params.from = range.start;
    params.to = range.end;
  }
  const { data } = await api.get<ApiResponse<OwnerAnalytics>>('/owner/analytics', { params });
  return data.data;
}

// The figures of the analytics and reports screens for a timeframe: ONE request, whatever the size of the store (the
// server aggregates; the app used to page through up to 15 pages of finished orders). Refreshed every minute while the
// screen is visible, which is as long as the server keeps a period's figures. A custom range that the server would
// refuse (empty, reversed, over 93 days) is not requested: `rangeProblem` says why, for the screen to show.
export function useOwnerAnalytics(timeframe: Timeframe, range?: DateRange) {
  const rangeProblem = timeframe === 'custom' ? getCustomRangeProblem(range) : null;

  const query = useQuery({
    queryKey: ownerAnalyticsKeys.period(timeframe, range),
    queryFn: () => fetchOwnerAnalytics(timeframe, range),
    enabled: rangeProblem === null,
    ...pollingQueryOptions(POLL_INTERVAL_MS.ownerAnalytics),
  });

  return { ...query, rangeProblem };
}
