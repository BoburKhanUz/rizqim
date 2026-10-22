import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchFiscalReadiness,
  fetchFiscalReceipt,
  fetchFiscalReceipts,
  fetchFiscalSettings,
  productFiscalFormData,
  retryFiscalReceipt,
  saveFiscalSettings,
  type FiscalReadiness,
  type FiscalReceipt,
  type FiscalReceiptsPage,
  type FiscalReceiptsQuery,
  type FiscalSettingsInput,
  type FiscalSettingsView,
} from '../api/fiscal';
import { saveProduct, type ProductFiscalInput } from '../api/menu';
import type { Product } from '../types';
import { useStaffApi } from './context';
import { staffKeys } from './keys';
import type { MutationExtras, QueryExtras } from './orders';

// Fiscal setup and receipts (P3-11, P3-14) of the partner web. Keys sit under ['owner', 'fiscal'].
export const fiscalKeys = {
  all: ['owner', 'fiscal'] as const,
  readiness: ['owner', 'fiscal', 'readiness'] as const,
  settings: ['owner', 'fiscal', 'settings'] as const,
  receipts: (query: FiscalReceiptsQuery) => ['owner', 'fiscal', 'receipts', query] as const,
  allReceipts: ['owner', 'fiscal', 'receipts'] as const,
  receipt: (uuid: string) => ['owner', 'fiscal', 'receipt', uuid] as const,
};

export function useFiscalReadiness(options: QueryExtras<FiscalReadiness> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: fiscalKeys.readiness, queryFn: () => fetchFiscalReadiness(api) });
}

export function useFiscalSettings(options: QueryExtras<FiscalSettingsView> = {}) {
  const api = useStaffApi();
  return useQuery({ ...options, queryKey: fiscalKeys.settings, queryFn: () => fetchFiscalSettings(api) });
}

// A save changes what is "ready" too (a charge code filled in, the TIN set)
export function useSaveFiscalSettings(options: MutationExtras<FiscalSettingsView, FiscalSettingsInput> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (input: FiscalSettingsInput) => saveFiscalSettings(api, input),
    onSuccess: (...args) => {
      queryClient.setQueryData(fiscalKeys.settings, args[0]);
      void queryClient.invalidateQueries({ queryKey: fiscalKeys.readiness });
      return options.onSuccess?.(...args);
    },
  });
}

// Pages of receipts: the previous page stays while the next one loads
export function useFiscalReceipts(query: FiscalReceiptsQuery = {}, options: QueryExtras<FiscalReceiptsPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: fiscalKeys.receipts(query), queryFn: () => fetchFiscalReceipts(api, query) });
}

export function useFiscalReceipt(uuid: string | null, options: QueryExtras<FiscalReceipt> = {}) {
  const api = useStaffApi();
  return useQuery({
    ...options,
    enabled: uuid !== null && options.enabled !== false,
    queryKey: fiscalKeys.receipt(uuid ?? ''),
    queryFn: () => fetchFiscalReceipt(api, uuid as string),
  });
}

// "Qayta yuborish" of a pending or failed receipt: the lists and the receipt itself are read again
export function useRetryFiscalReceipt(options: MutationExtras<FiscalReceipt, string> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: (uuid: string) => retryFiscalReceipt(api, uuid),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: fiscalKeys.allReceipts });
      queryClient.setQueryData(fiscalKeys.receipt(args[0].uuid), args[0]);
      return options.onSuccess?.(...args);
    },
  });
}

// Writes the fiscal fields of one product (kind, MXIK, package code, VAT) and nothing else: the product is sent back as it
// is (api/fiscal.ts productFiscalFormData). The menu and the readiness list are read again.
export function useSaveProductFiscal(options: MutationExtras<unknown, { product: Product; fiscal: ProductFiscalInput }> = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    ...options,
    mutationFn: ({ product, fiscal }: { product: Product; fiscal: ProductFiscalInput }) => saveProduct(api, product.id, productFiscalFormData(product, fiscal)),
    onSuccess: (...args) => {
      void queryClient.invalidateQueries({ queryKey: staffKeys.products });
      void queryClient.invalidateQueries({ queryKey: fiscalKeys.readiness });
      return options.onSuccess?.(...args);
    },
  });
}
