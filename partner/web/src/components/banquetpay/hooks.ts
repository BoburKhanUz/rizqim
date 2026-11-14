import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  cancelEvent,
  downloadBanquetReport,
  fetchBanquetReport,
  fetchCancellationQuote,
  fetchPaymentRules,
  fetchSchedule,
  recordTransfer,
  savePaymentRules,
  saveSchedule,
  type PaymentRules,
  type ReportRange,
  type Schedule,
  type ScheduleRowInput,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const banquetPayKeys = {
  all: ['owner', 'banquetpay'] as const,
  schedule: (uuid: string) => ['owner', 'banquetpay', 'schedule', uuid] as const,
  quote: (uuid: string, retained: string) => ['owner', 'banquetpay', 'quote', uuid, retained] as const,
  rules: ['owner', 'banquetpay', 'rules'] as const,
  report: (range: ReportRange) => ['owner', 'banquetpay', 'report', range] as const,
};

export function useSchedule(uuid: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetPayKeys.schedule(uuid), queryFn: () => fetchSchedule(api, uuid), retry: false });
}

function useScheduleWrite<I>(write: (input: I) => Promise<Schedule | { schedule: Schedule }>, uuid: string, onSuccess?: (schedule: Schedule) => void) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: write,
    onSuccess: (result) => {
      const schedule = 'schedule' in result ? result.schedule : result;
      queryClient.setQueryData(banquetPayKeys.schedule(uuid), schedule);
      void queryClient.invalidateQueries({ queryKey: banquetPayKeys.all });
      // The event itself (status, paid total) lives in the events screens' cache
      void queryClient.invalidateQueries({ queryKey: ['owner', 'banquets'] });
      onSuccess?.(schedule);
    },
  });
}

export function useSaveSchedule(uuid: string, onSuccess?: (schedule: Schedule) => void) {
  const api = useStaffApi();
  return useScheduleWrite(({ rows, version, key }: { rows: ScheduleRowInput[]; version: number; key: string }) => saveSchedule(api, uuid, rows, version, key), uuid, onSuccess);
}

export function useRecordTransfer(uuid: string, onSuccess?: (schedule: Schedule) => void) {
  const api = useStaffApi();
  return useScheduleWrite((input: { row: string; reference: string; paid_on?: string; amount?: string; key: string }) => recordTransfer(api, { uuid, ...input }), uuid, onSuccess);
}

export function useCancellationQuote(uuid: string, retained: string, enabled: boolean) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetPayKeys.quote(uuid, retained), queryFn: () => fetchCancellationQuote(api, uuid, retained), enabled, placeholderData: keepPreviousData, retry: false });
}

export function useCancelEvent(uuid: string, onSuccess?: (schedule: Schedule) => void) {
  const api = useStaffApi();
  return useScheduleWrite((input: { reason: string; retained?: string; override_reason?: string; key: string }) => cancelEvent(api, { uuid, ...input }), uuid, onSuccess);
}

export function usePaymentRules(enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetPayKeys.rules, queryFn: () => fetchPaymentRules(api), enabled, retry: false });
}

export function useSavePaymentRules({ onSuccess }: { onSuccess?: (rules: PaymentRules) => void } = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (rules: PaymentRules) => savePaymentRules(api, rules),
    onSuccess: (rules) => {
      queryClient.setQueryData(banquetPayKeys.rules, rules);
      onSuccess?.(rules);
    },
  });
}

export function useBanquetReport(range: ReportRange, enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: banquetPayKeys.report(range), queryFn: () => fetchBanquetReport(api, range), enabled, retry: false });
}

export function useDownloadBanquetReport({ onSuccess, onError }: { onSuccess?: (file: { blob: Blob; filename: string }) => void; onError?: (error: unknown) => void } = {}) {
  const api = useStaffApi();
  return useMutation({ mutationFn: (range: ReportRange) => downloadBanquetReport(api, range), onSuccess, onError });
}
