'use client';

import { useQuery } from '@tanstack/react-query';
import { call } from './client';

// Bugun (P4-13, docs/API.md "Bugun"): GET /admin/today and GET /admin/today/attention. Typed by hand until the generated
// client is regenerated with these endpoints (the shapes follow AdminTodayController's documented answer). Money is a decimal
// string ("120000.00"), times are ISO 8601 in Asia/Tashkent.

export type Direction = 'up' | 'down' | 'same';

export interface Change {
  direction: Direction;
  // Percent of the earlier value, one decimal ("12.5"); null when the earlier value was 0
  percent: string | null;
}

export interface Kpi<V extends string | number> {
  // Today until now
  value: V;
  // Yesterday and the same weekday last week, up to the same time of day
  yesterday_same_time: V;
  last_week_same_time: V;
  // The whole day
  yesterday: V;
  last_week: V;
  vs_yesterday: Change;
  vs_last_week: Change;
}

export interface ChannelRow {
  channel: string;
  label: string;
  // Counted in GMV (Telegram, mobile): POS and aggregators are not
  marketplace: boolean;
  orders: number;
  gmv: string;
}

export interface RegionRow {
  region_id: number | null;
  name: string;
  orders: number;
  gmv: string;
  active_stores: number;
}

export interface TodaySummary {
  date: string;
  timezone: string;
  generated_at: string;
  stats_computed_at: string | null;
  live_from: string;
  kpis: {
    gmv: Kpi<string>;
    orders: Kpi<number>;
    commission: Kpi<string>;
    subscription_revenue: Kpi<string>;
    canceled_orders: Kpi<number>;
  };
  channels: ChannelRow[];
  regions: RegionRow[];
}

export type Severity = 'critical' | 'warning' | 'info';

export interface AttentionItem {
  kind: string;
  severity: Severity;
  title: string;
  subtitle: string | null;
  // The admin app path of the record's card
  href: string;
  since: string | null;
  store_id: number | null;
}

export interface AttentionGroup {
  kind: string;
  label: string;
  // All of them; `items` holds at most `limit`
  count: number;
  severity: Severity;
  items: AttentionItem[];
}

export interface TodayAttention {
  groups: AttentionGroup[];
  generated_at: string;
}

export const ATTENTION_LIMIT = 5;

// Both parts of the page are asked again every 30 seconds (and when the tab comes back)
export const TODAY_REFRESH_MS = 30_000;

export const getTodaySummary = async (signal?: AbortSignal): Promise<TodaySummary> => (await call('GET', '/today', { signal })).data as TodaySummary;

export const getTodayAttention = async (limit = ATTENTION_LIMIT, signal?: AbortSignal): Promise<TodayAttention> => {
  const data = (await call('GET', '/today/attention', { query: { limit }, signal })).data as Partial<TodayAttention> | null;
  return { groups: Array.isArray(data?.groups) ? data.groups : [], generated_at: data?.generated_at ?? new Date().toISOString() };
};

export const TODAY_KEYS = {
  summary: ['admin-today'] as const,
  attention: ['admin-today-attention'] as const,
};

export const useTodaySummary = (enabled = true) =>
  useQuery({ queryKey: TODAY_KEYS.summary, queryFn: ({ signal }) => getTodaySummary(signal), enabled, refetchInterval: TODAY_REFRESH_MS });

export const useTodayAttention = (enabled = true) =>
  useQuery({ queryKey: TODAY_KEYS.attention, queryFn: ({ signal }) => getTodayAttention(ATTENTION_LIMIT, signal), enabled, refetchInterval: TODAY_REFRESH_MS });
