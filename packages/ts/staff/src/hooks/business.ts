import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  exportReport,
  fetchOwnerAnalytics,
  getCustomRangeProblem,
  toAnalyticsPeriod,
  type DateRange,
  type OwnerAnalytics,
  type ReportExportInput,
  type Timeframe,
} from '../api/analytics';
import {
  fetchInvoices,
  fetchOwnerFinance,
  fetchSubscription,
  initiateTopup,
  requestPayout,
  type InvoicePage,
  type InvoiceQuery,
  type OwnerFinance,
  type PayoutInput,
  type PayoutResult,
  type Subscription,
  type TopupInitiated,
  type TopupInput,
} from '../api/payments';
import {
  createStore,
  fetchLocations,
  fetchSuperadminDashboard,
  fetchSuperadminStores,
  type CreatedStore,
  type CreateStoreInput,
  type DashboardStats,
  type SuperadminStore,
} from '../api/superadmin';
import { fetchOwnerSettings, saveOwnerSettings, uploadSettingsFile, type UploadedFile } from '../api/settings';
import type { Locations, OwnerSettings, OwnerSettingsInput } from '../api/specGaps';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// --- Store settings --------------------------------------------------------------------------------------------------

export function useOwnerSettings(options: QueryExtras<OwnerSettings | null> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.settings, queryFn: () => fetchOwnerSettings(api) });
}

export function useSaveOwnerSettings(options: MutationExtras<void, OwnerSettingsInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: OwnerSettingsInput) => saveOwnerSettings(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.settings });
      return options.onSuccess?.(...args);
    },
  });
}

export function useUploadSettingsFile(options: MutationExtras<UploadedFile, File | Blob> = {}) {
  const api = useStaffApi();
  return useMutation({ ...options, mutationFn: (file: File | Blob) => uploadSettingsFile(api, file) });
}

// --- Payments --------------------------------------------------------------------------------------------------------

// The balances move whenever an order is commissioned or a top-up lands: a screen passes its polling options.
export function useOwnerFinance(options: QueryExtras<OwnerFinance> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.finance, queryFn: () => fetchOwnerFinance(api) });
}

export function useInitiateTopup(options: MutationExtras<TopupInitiated, TopupInput> = {}) {
  const api = useStaffApi();
  return useMutation({ ...options, mutationFn: (input: TopupInput) => initiateTopup(api, input) });
}

export function useRequestPayout(options: MutationExtras<PayoutResult, PayoutInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: PayoutInput) => requestPayout(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.finance });
      return options.onSuccess?.(...args);
    },
  });
}

export function useSubscription(options: QueryExtras<Subscription> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.subscription, queryFn: () => fetchSubscription(api) });
}

export function useInvoices(query: InvoiceQuery = {}, options: QueryExtras<InvoicePage> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: staffKeys.invoices(query), queryFn: () => fetchInvoices(api, query) });
}

// --- Analytics and reports -------------------------------------------------------------------------------------------

export const ownerAnalyticsKey = (timeframe: Timeframe, range?: DateRange) =>
  staffKeys.analyticsPeriod(toAnalyticsPeriod(timeframe), timeframe === 'custom' ? range?.start : null, timeframe === 'custom' ? range?.end : null);

// The figures of the analytics and reports screens for a timeframe: ONE request, whatever the size of the store (the
// server aggregates). A custom range that the server would refuse (empty, reversed, over 93 days) is not requested:
// `rangeProblem` says why, for the screen to show.
export function useOwnerAnalytics(timeframe: Timeframe, range?: DateRange, options: QueryExtras<OwnerAnalytics> = {}) {
  const api = useStaffApi();
  const rangeProblem = timeframe === 'custom' ? getCustomRangeProblem(range) : null;
  const query = useQuery({
    enabled: rangeProblem === null,
    ...options,
    queryKey: ownerAnalyticsKey(timeframe, range),
    queryFn: () => fetchOwnerAnalytics(api, timeframe, range),
  });
  return { ...query, rangeProblem };
}

export function useExportReport(options: MutationExtras<string | undefined, ReportExportInput> = {}) {
  const api = useStaffApi();
  return useMutation({ ...options, mutationFn: (input: ReportExportInput) => exportReport(api, input) });
}

// --- Platform (super admin) ------------------------------------------------------------------------------------------

export function useSuperadminDashboard(options: QueryExtras<DashboardStats> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: [...staffKeys.superadmin, 'dashboard'], queryFn: () => fetchSuperadminDashboard(api) });
}

export function useSuperadminStores(options: QueryExtras<SuperadminStore[]> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: [...staffKeys.superadmin, 'stores'], queryFn: () => fetchSuperadminStores(api) });
}

export function useLocations(options: QueryExtras<Locations> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: [...staffKeys.superadmin, 'locations'], queryFn: () => fetchLocations(api) });
}

export function useCreateStore(options: MutationExtras<CreatedStore, CreateStoreInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: CreateStoreInput) => createStore(api, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: [...staffKeys.superadmin, 'stores'] });
      return options.onSuccess?.(...args);
    },
  });
}
