import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createCustomerAccount,
  fetchAccountStatement,
  fetchCustomerAccount,
  fetchCustomerAccounts,
  fetchDebtors,
  updateCustomerAccount,
  type AccountDetail,
  type AccountStatement,
  type CustomerAccount,
  type CustomerAccountInput,
  type CustomerAccountPatch,
  type CustomerAccounts,
  type CustomerAccountsQuery,
  type Debtors,
} from '../api/accounts';
import { useStaffApi } from './context';
import type { MutationExtras, QueryExtras } from './orders';

// Customer accounts (nasiya, P3-17) and the debtors list. Used by the partner web and the Mini App ("Qarzdorlar").
export const accountKeys = {
  all: ['pos', 'customer-accounts'] as const,
  list: (query: CustomerAccountsQuery) => ['pos', 'customer-accounts', 'list', query] as const,
  debtors: ['pos', 'customer-accounts', 'debtors'] as const,
  detail: (uuid: string) => ['pos', 'customer-accounts', 'detail', uuid] as const,
  statement: (uuid: string, range: { from?: string; to?: string }) => ['pos', 'customer-accounts', 'statement', uuid, range.from ?? null, range.to ?? null] as const,
};

export function useCustomerAccounts(query: CustomerAccountsQuery = {}, options: QueryExtras<CustomerAccounts> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: accountKeys.list(query), queryFn: () => fetchCustomerAccounts(api, query) });
}

export function useDebtors(options: QueryExtras<Debtors> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: accountKeys.debtors, queryFn: () => fetchDebtors(api) });
}

export function useCustomerAccount(uuid: string | null, options: QueryExtras<AccountDetail> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: accountKeys.detail(uuid ?? ''),
    queryFn: () => fetchCustomerAccount(api, uuid as string),
  });
}

export function useAccountStatement(uuid: string | null, range: { from?: string; to?: string } = {}, options: QueryExtras<AccountStatement> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: accountKeys.statement(uuid ?? '', range),
    queryFn: () => fetchAccountStatement(api, uuid as string, range),
  });
}

function useAccountMutation<TData, TVariables>(mutationFn: (variables: TVariables) => Promise<TData>, options: MutationExtras<TData, TVariables>) {
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

export function useCreateCustomerAccount(options: MutationExtras<CustomerAccount, CustomerAccountInput> = {}) {
  const api = useStaffApi();
  return useAccountMutation((input: CustomerAccountInput) => createCustomerAccount(api, input), options);
}

export function useUpdateCustomerAccount(options: MutationExtras<CustomerAccount, { uuid: string; patch: CustomerAccountPatch }> = {}) {
  const api = useStaffApi();
  return useAccountMutation(({ uuid, patch }: { uuid: string; patch: CustomerAccountPatch }) => updateCustomerAccount(api, uuid, patch), options);
}
