'use client';

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { download, get, type DownloadedFile } from './client';

// Ko'rsatkichlar (P4-31, docs/API.md "Ko'rsatkichlar") and the Excel exports of the lists ("Excel eksport"). Typed by hand
// until the generated client is regenerated (the shapes follow AdminMetricsController / AdminExportController). Money is a
// decimal string ("120000.00"), percents are strings with one decimal ("4.2"), months are "YYYY-MM" (Asia/Tashkent).

export const METRICS_MONTHS = [6, 12, 24] as const;
export const DEFAULT_MONTHS = 12;

export interface MrrChange {
  direction: 'up' | 'down' | 'same';
  // Against the start of the month
  amount: string;
  percent: string | null;
}

export interface MetricsCards {
  mrr: string;
  mrr_change: MrrChange;
  arr: string;
  paying_stores: number;
  trialing_stores: number;
  // MRR per paying store; null when none pays
  arpa: string | null;
  active_stores: number;
  // The latest finished month
  churn_rate: string | null;
  churn_month: string | null;
  gmv_month: string;
  commission_month: string;
  // false while the Bugun aggregate lacks days of the month
  revenue_complete: boolean;
}

export interface MetricsSeriesPoint {
  month: string;
  partial: boolean;
  mrr: string;
  paying_stores: number;
  trialing_stores: number;
  active_stores: number;
}

export interface MovementPart {
  mrr: string;
  stores: number;
}

export interface MovementMonth {
  month: string;
  partial: boolean;
  opening: string;
  closing: string;
  new: MovementPart;
  expansion: MovementPart;
  contraction: MovementPart;
  churned: MovementPart;
  net_new: string;
  paying_stores: number;
  trialing_stores: number;
}

export interface ChurnMonth {
  month: string;
  partial: boolean;
  active_at_start: number;
  active_at_end: number;
  churned: number;
  went_inactive: number;
  subscription_ended: number;
  rate: string | null;
  revenue_rate: string | null;
}

export interface RevenueMonth {
  month: string;
  partial: boolean;
  orders: number;
  gmv: string;
  commission: string;
  subscription_revenue: string;
  missing_days: number;
  complete: boolean;
}

export interface MetricsOverview {
  timezone: string;
  generated_at: string;
  months: number;
  cards: MetricsCards;
  series: MetricsSeriesPoint[];
  movement: MovementMonth[];
  churn: ChurnMonth[];
  revenue: RevenueMonth[];
}

export interface FunnelStage {
  stage: string;
  label: string;
  reached: number;
  share: string | null;
  median_days: number | null;
  samples: number;
}

export interface Funnel {
  months: number;
  from: string;
  applications: number;
  active: number;
  rejected: number;
  open: number;
  conversion: string | null;
  median_days_to_active: number | null;
  stages: FunnelStage[];
}

export const getMetrics = (months: number, refresh: boolean, signal?: AbortSignal) =>
  get<MetricsOverview>('/metrics', { query: { months, refresh: refresh ? 1 : undefined }, signal });
export const getFunnel = (months: number, signal?: AbortSignal) => get<Funnel>('/metrics/funnel', { query: { months }, signal });

export const METRICS_KEYS = {
  all: ['admin-metrics'] as const,
  overview: (months: number) => ['admin-metrics', 'overview', months] as const,
  funnel: (months: number) => ['admin-metrics', 'funnel', months] as const,
};

const POLL_MS = 5 * 60_000;

export const useMetrics = (months: number, enabled = true) =>
  useQuery({ queryKey: METRICS_KEYS.overview(months), queryFn: ({ signal }) => getMetrics(months, false, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POLL_MS });

export const useFunnel = (months: number, enabled = true) =>
  useQuery({ queryKey: METRICS_KEYS.funnel(months), queryFn: ({ signal }) => getFunnel(months, signal), enabled, placeholderData: keepPreviousData, refetchInterval: POLL_MS });

// --- Excel exports -------------------------------------------------------------------------------------------------------

// The lists that export, and the path under /admin/exports of each (the same filters as the list's own endpoint)
export const EXPORT_PATHS = {
  orders: 'orders',
  stores: 'stores',
  applications: 'store-applications',
  payouts: 'finance/payouts',
  topups: 'finance/topups',
  balances: 'finance/balances',
  invoices: 'pos/invoices',
  devices: 'pos/devices',
  audit: 'audit-logs',
} as const;

export type ExportList = keyof typeof EXPORT_PATHS;

// The list's own filters, as the screen holds them (an interface of strings, numbers and booleans); empty ones are dropped
export type ExportQuery = object;

export function downloadExport(list: ExportList, query: ExportQuery = {}): Promise<DownloadedFile> {
  const clean: Record<string, string | number> = {};
  for (const [name, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '' || value === false) continue;
    if (value === true) clean[name] = 1;
    else if (typeof value === 'string' || typeof value === 'number') clean[name] = value;
  }
  return download(`/exports/${EXPORT_PATHS[list]}`, clean, `rizqim-${EXPORT_PATHS[list].replace('/', '-')}.xlsx`);
}
