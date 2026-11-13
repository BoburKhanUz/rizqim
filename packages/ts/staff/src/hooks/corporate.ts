import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createCorporateMember,
  downloadCorporateStatement,
  fetchCorporateMembers,
  fetchCorporateStatement,
  importCorporateMembers,
  updateCorporateMember,
  type CorporateBulkInput,
  type CorporateBulkResult,
  type CorporateMember,
  type CorporateMemberInput,
  type CorporateMemberPatch,
  type CorporateMembers,
  type CorporateMembersQuery,
  type CorporateStatement,
  type CorporateStatementFile,
} from '../api/corporate';
import { accountKeys } from './accounts';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// P6-44: a corporate account's members and monthly statement (partner web "Korxona hisobi").
export const corporateKeys = {
  members: (uuid: string, query: CorporateMembersQuery) => [...accountKeys.all, 'members', uuid, query] as const,
  statement: (uuid: string, month: string | null) => [...accountKeys.all, 'corporate-statement', uuid, month] as const,
};

export function useCorporateMembers(uuid: string | null, query: CorporateMembersQuery = {}, options: QueryExtras<CorporateMembers> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: corporateKeys.members(uuid ?? '', query),
    queryFn: () => fetchCorporateMembers(api, uuid as string, query),
  });
}

export function useCorporateStatement(uuid: string | null, month: string | null, options: QueryExtras<CorporateStatement> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: corporateKeys.statement(uuid ?? '', month),
    queryFn: () => fetchCorporateStatement(api, uuid as string, month ?? undefined),
  });
}

// Every member change refreshes the accounts (members_count) and the members lists
function useMemberMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn,
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: accountKeys.all });
      return options.onSuccess?.(...args);
    },
  });
}

export function useCreateCorporateMember(uuid: string, options: MutationExtras<CorporateMember, CorporateMemberInput> = {}) {
  const api = useStaffApi();
  return useMemberMutation((input: CorporateMemberInput) => createCorporateMember(api, uuid, input), options);
}

export function useUpdateCorporateMember(uuid: string, options: MutationExtras<CorporateMember, { member: string; patch: CorporateMemberPatch }> = {}) {
  const api = useStaffApi();
  return useMemberMutation(({ member, patch }: { member: string; patch: CorporateMemberPatch }) => updateCorporateMember(api, uuid, member, patch), options);
}

export function useImportCorporateMembers(uuid: string, options: MutationExtras<CorporateBulkResult, CorporateBulkInput> = {}) {
  const api = useStaffApi();
  return useMemberMutation((input: CorporateBulkInput) => importCorporateMembers(api, uuid, input), options);
}

export function useDownloadCorporateStatement(uuid: string, options: MutationExtras<CorporateStatementFile, string> = {}) {
  const api = useStaffApi();
  return useMutation({
    ...options,
    mutationFn: (month: string) => downloadCorporateStatement(api, uuid, month),
  });
}
