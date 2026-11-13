import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useStaffApi } from '@rizqim/staff';
import { blockVoucher, downloadVouchers, fetchVoucher, fetchVoucherReport, fetchVouchers, unblockVoucher, type Voucher, type VoucherFilter } from './api';

// Keys sit under ['owner', ...] like the other owner screens', so a sign-out clears them all
export const voucherKeys = {
  all: ['owner', 'vouchers'] as const,
  list: (filter: VoucherFilter, page: number) => ['owner', 'vouchers', 'list', filter, page] as const,
  one: (uuid: string) => ['owner', 'vouchers', 'one', uuid] as const,
  report: (range: VoucherFilter) => ['owner', 'vouchers', 'report', range] as const,
};

export function useVouchers(filter: VoucherFilter, page: number) {
  const api = useStaffApi();
  return useQuery({ queryKey: voucherKeys.list(filter, page), queryFn: () => fetchVouchers(api, filter, page), placeholderData: keepPreviousData, retry: false });
}

export function useVoucher(uuid: string) {
  const api = useStaffApi();
  return useQuery({ queryKey: voucherKeys.one(uuid), queryFn: () => fetchVoucher(api, uuid), retry: false });
}

export function useVoucherReport(range: VoucherFilter, enabled = true) {
  const api = useStaffApi();
  return useQuery({ queryKey: voucherKeys.report(range), queryFn: () => fetchVoucherReport(api, range), enabled, retry: false });
}

export function useBlockVoucher({ onSuccess }: { onSuccess?: (voucher: Voucher) => void } = {}) {
  const api = useStaffApi();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, block, reason }: { uuid: string; block: boolean; reason: string }) => (block ? blockVoucher(api, uuid, reason) : unblockVoucher(api, uuid, reason || null)),
    onSuccess: (voucher) => {
      void queryClient.invalidateQueries({ queryKey: voucherKeys.all });
      onSuccess?.(voucher);
    },
  });
}

export function useDownloadVouchers({ onSuccess, onError }: { onSuccess?: (file: { blob: Blob; filename: string }) => void; onError?: (error: unknown) => void } = {}) {
  const api = useStaffApi();
  return useMutation({ mutationFn: (filter: VoucherFilter) => downloadVouchers(api, filter), onSuccess, onError });
}
