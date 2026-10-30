import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import {
  addSession,
  adjustLine,
  correctSession,
  createRule,
  createStatement,
  deleteRule,
  deleteStatement,
  fetchRules,
  fetchSessions,
  fetchStatement,
  fetchStatements,
  payLine,
  removeSession,
  reopenStatement,
  saveSettings,
  statementAction,
  updateRule,
  type PaySettingsInput,
  type RuleInput,
  type SessionInput,
  type Statement,
  type StatementAction,
} from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const payKeys = {
  all: ['owner', 'staff-pay'] as const,
  rules: (branchId: number) => ['owner', 'staff-pay', 'rules', branchId] as const,
  sessions: (branchId: number, from: string, to: string) => ['owner', 'staff-pay', 'sessions', branchId, from, to] as const,
  statements: (branchId: number) => ['owner', 'staff-pay', 'statements', branchId] as const,
  statement: (uuid: string) => ['owner', 'staff-pay', 'statement', uuid] as const,
};

interface Done<T = unknown> {
  onSuccess?: (result: T) => void;
}

function useInvalidate() {
  const queryClient = useQueryClient();
  return () => void queryClient.invalidateQueries({ queryKey: payKeys.all });
}

export function useRules(branchId: number | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: payKeys.rules(branchId ?? 0), queryFn: () => fetchRules(api, branchId as number), enabled: branchId !== null, retry: false });
}

export function useSaveSettings({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: PaySettingsInput) => saveSettings(api, input),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useSaveRule({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: RuleInput & { uuid: string | null }) => (input.uuid === null ? createRule(api, input) : updateRule(api, input.uuid, input)),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useDeleteRule({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (uuid: string) => deleteRule(api, uuid),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useSessions(query: { branchId: number; from: string; to: string } | null) {
  const api = useStaffApi();
  return useQuery({
    queryKey: payKeys.sessions(query?.branchId ?? 0, query?.from ?? '', query?.to ?? ''),
    queryFn: () => fetchSessions(api, query as { branchId: number; from: string; to: string }),
    enabled: query !== null,
    retry: false,
  });
}

export function useSaveSession({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: SessionInput & { uuid: string | null; branchId: number; userId: number }) => (input.uuid === null ? addSession(api, input) : correctSession(api, input.uuid, input)),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useRemoveSession({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (input: { uuid: string; reason: string }) => removeSession(api, input.uuid, input.reason),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}

export function useStatements(branchId: number | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: payKeys.statements(branchId ?? 0), queryFn: () => fetchStatements(api, branchId as number), enabled: branchId !== null, retry: false });
}

export function useStatement(uuid: string | null) {
  const api = useStaffApi();
  return useQuery({ queryKey: payKeys.statement(uuid ?? ''), queryFn: () => fetchStatement(api, uuid as string), enabled: uuid !== null, retry: false });
}

// Every change to a statement answers with the whole statement: it replaces the cached one at once
function useStatementWrite<I>(write: (input: I) => Promise<Statement>, { onSuccess }: Done<Statement> = {}) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: write,
    onSuccess: (statement) => {
      queryClient.setQueryData(payKeys.statement(statement.uuid), statement);
      void queryClient.invalidateQueries({ queryKey: ['owner', 'staff-pay', 'statements'] });
      onSuccess?.(statement);
    },
  });
}

export function useCreateStatement(done: Done<Statement> = {}) {
  const api = useStaffApi();
  return useStatementWrite((input: { branchId: number; date: string; key: string }) => createStatement(api, input), done);
}

export function useStatementAction(done: Done<Statement> = {}) {
  const api = useStaffApi();
  return useStatementWrite((input: { uuid: string; action: StatementAction }) => statementAction(api, input.uuid, input.action), done);
}

export function useReopenStatement(done: Done<Statement> = {}) {
  const api = useStaffApi();
  return useStatementWrite((input: { uuid: string; reason: string }) => reopenStatement(api, input.uuid, input.reason), done);
}

export function useAdjustLine(done: Done<Statement> = {}) {
  const api = useStaffApi();
  return useStatementWrite((input: { uuid: string; line: string; adjustment: string; reason: string | null }) => adjustLine(api, input), done);
}

export function usePayLine(done: Done<Statement> = {}) {
  const api = useStaffApi();
  return useStatementWrite((input: { uuid: string; line: string; method: 'cash_drawer' | 'other'; shiftUuid: string | null; note: string | null; key: string }) => payLine(api, input), done);
}

export function useDeleteStatement({ onSuccess }: Done = {}) {
  const api = useStaffApi();
  const invalidate = useInvalidate();
  return useMutation({
    mutationFn: (uuid: string) => deleteStatement(api, uuid),
    onSuccess: (result) => {
      invalidate();
      onSuccess?.(result);
    },
  });
}
