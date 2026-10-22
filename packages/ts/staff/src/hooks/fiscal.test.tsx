import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Product } from '../types';
import { json, makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { fiscalKeys, useFiscalReadiness, useFiscalReceipt, useFiscalReceipts, useFiscalSettings, useRetryFiscalReceipt, useSaveFiscalSettings, useSaveProductFiscal } from './fiscal';
import { staffKeys } from './keys';

afterEach(() => vi.unstubAllGlobals());

const READINESS = { enabled: true, tin_set: true, tax_regime: 'vat', products_missing_mxik: [{ id: 1, name: 'Osh' }], charges_missing_mxik: [] };

describe('fiscal hooks', () => {
  it('loads the readiness and the settings under their keys', async () => {
    mockFetch({
      'GET /api/v1/owner/fiscal/readiness': () => ok(READINESS),
      'GET /api/v1/owner/fiscal/settings': () => ok({ tin: '123456789', tax_regime: 'vat', fiscal_settings: { enabled: true } }),
    });
    const queryClient = makeQueryClient();
    const readiness = renderHook(() => useFiscalReadiness(), { wrapper: wrapperFor(queryClient) });
    const settings = renderHook(() => useFiscalSettings(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(readiness.result.current.isSuccess && settings.result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(fiscalKeys.readiness)).toEqual(READINESS);
    expect(settings.result.current.data?.tin).toBe('123456789');
  });

  it('keeps the settings the server answered and reads the readiness again after a save', async () => {
    mockFetch({ 'PUT /api/v1/owner/fiscal/settings': () => ok({ tin: '987654321', tax_regime: 'turnover', fiscal_settings: { enabled: false } }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useSaveFiscalSettings(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ tin: '987654321', tax_regime: 'turnover' });
    });
    expect(queryClient.getQueryData<{ tin: string }>(fiscalKeys.settings)?.tin).toBe('987654321');
    expect(invalidate).toHaveBeenCalledWith({ queryKey: fiscalKeys.readiness });
  });

  it('pages receipts by their filters and loads one by uuid only when asked', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/fiscal/receipts': () => ok({ data: [{ uuid: 'r1' }], current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 }),
      'GET /api/v1/owner/fiscal/receipts/r1': () => ok({ uuid: 'r1', status: 'pending' }),
    });
    const queryClient = makeQueryClient();
    const list = renderHook(() => useFiscalReceipts({ status: 'pending' }), { wrapper: wrapperFor(queryClient) });
    const idle = renderHook(() => useFiscalReceipt(null), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    expect(idle.result.current.fetchStatus).toBe('idle');
    expect(calls).toHaveLength(1);
    const one = renderHook(() => useFiscalReceipt('r1'), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(one.result.current.isSuccess).toBe(true));
    expect(one.result.current.data?.status).toBe('pending');
  });

  it('reads the receipts again after a retry and stores the answered receipt', async () => {
    mockFetch({ 'POST /api/v1/owner/fiscal/receipts/r1/retry': () => ok({ uuid: 'r1', status: 'issued' }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useRetryFiscalReceipt(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync('r1');
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: fiscalKeys.allReceipts });
    expect(queryClient.getQueryData<{ status: string }>(fiscalKeys.receipt('r1'))?.status).toBe('issued');
  });

  it('writes the MXIK of a product and reads the menu and the readiness again', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/owner/products/1': () => ok({ id: 1 }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const product = { id: 1, name: 'Osh', description: null, category_id: 2, is_active: true, batch_tracked: false, sort_order: 0, variants: [{ id: 5, name: null, price: '35000.00', unit: 'piece', quantity_step: null, min_quantity: null, batch_consumption: '1.000', unit_label: null }] } as unknown as Product;
    const { result } = renderHook(() => useSaveProductFiscal(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ product, fiscal: { mxik_code: '10202001001000000' } });
    });
    const form = await calls[0]!.formData();
    expect(form.get('mxik_code')).toBe('10202001001000000');
    expect(form.get('category_id')).toBe('2');
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.products });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: fiscalKeys.readiness });
  });

  it('hands the server refusal of a bad MXIK to the caller', async () => {
    mockFetch({ 'POST /api/v1/owner/products/1': () => json({ success: false, message: 'Xato', errors: { mxik_code: ["MXIK kodi 17 ta raqamdan iborat bo'lishi kerak."] } }, 422) });
    const onError = vi.fn();
    const product = { id: 1, name: 'Osh', category_id: 2, is_active: true, batch_tracked: false, sort_order: 0, variants: [] } as unknown as Product;
    const { result } = renderHook(() => useSaveProductFiscal({ onError }), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync({ product, fiscal: { mxik_code: '123' } }).catch(() => undefined);
    });
    expect(onError).toHaveBeenCalledTimes(1);
  });
});
