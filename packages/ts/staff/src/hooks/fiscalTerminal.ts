import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  enterBankReport,
  fetchBankReports,
  fetchTerminalFlow,
  saveSmartKassa,
  saveTerminalSetup,
  type BankReportInput,
  type BankReportsQuery,
  type BankTerminalReport,
  type SmartKassaPatch,
  type TerminalFlowDevice,
  type TerminalFlowOverview,
  type TerminalSetupPatch,
  type TerminalSetupView,
} from '../api/fiscalTerminal';
import { useStaffApi } from './context';
import { shiftKeys } from './shifts';
import type { MutationExtras, QueryExtras } from './orders';

// Fiscal terminal flows (P6-16) of the partner web's fiscal page. Keys sit under ['owner', 'fiscal', 'terminal'].
export const fiscalTerminalKeys = {
  all: ['owner', 'fiscal', 'terminal'] as const,
  flow: (branchId?: number) => ['owner', 'fiscal', 'terminal', 'flow', branchId ?? null] as const,
  allReports: ['owner', 'fiscal', 'terminal', 'reports'] as const,
  reports: (query: BankReportsQuery) => ['owner', 'fiscal', 'terminal', 'reports', query] as const,
};

export function useTerminalFlow(branchId?: number, options: QueryExtras<TerminalFlowOverview> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: fiscalTerminalKeys.flow(branchId), queryFn: () => fetchTerminalFlow(api, branchId) });
}

export function useSaveTerminalSetup(options: MutationExtras<TerminalSetupView, { branchId: number; patch: TerminalSetupPatch }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ branchId, patch }: { branchId: number; patch: TerminalSetupPatch }) => saveTerminalSetup(api, branchId, patch),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: fiscalTerminalKeys.all });
      return options.onSuccess?.(...args);
    },
  });
}

export function useSaveSmartKassa(options: MutationExtras<TerminalFlowDevice, { uuid: string; patch: SmartKassaPatch }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ uuid, patch }: { uuid: string; patch: SmartKassaPatch }) => saveSmartKassa(api, uuid, patch),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: fiscalTerminalKeys.all });
      return options.onSuccess?.(...args);
    },
  });
}

export function useBankReports(query: BankReportsQuery = {}, options: QueryExtras<BankTerminalReport[]> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: fiscalTerminalKeys.reports(query), queryFn: () => fetchBankReports(api, query) });
}

// An entry changes the report list and the shift's Z (its `bank_terminal` row)
export function useEnterBankReport(options: MutationExtras<BankTerminalReport, { shiftUuid: string; input: BankReportInput }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ shiftUuid, input }: { shiftUuid: string; input: BankReportInput }) => enterBankReport(api, shiftUuid, input),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: fiscalTerminalKeys.allReports });
      void queryClient.invalidateQueries({ queryKey: shiftKeys.all });
      return options.onSuccess?.(...args);
    },
  });
}
