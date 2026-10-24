import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeQueryClient, mockFetch, ok, json, wrapperFor } from '../test/helpers';
import { StaffApiError } from '../api/errors';
import { floorKeys } from './floor';
import { staffKeys } from './keys';
import { menuAdminKeys, useMenuBranches } from './menuAdmin';
import {
  managementKeys,
  useChangeStaffRole,
  useCreateBranch,
  useCreateDeliveryZone,
  useCreatePairingCode,
  useDeleteBranch,
  useDeleteDeliveryZone,
  useDeliveryZones,
  useManagedStaff,
  useRemoveManagedStaff,
  useRevokeStoreDevice,
  useSaveServiceFee,
  useSaveStoreSettings,
  useSetManagedStaffPin,
  useStoreDevices,
  useUpdateManagedStaff,
} from './management';

afterEach(() => vi.unstubAllGlobals());

const POLYGON = { type: 'Polygon' as const, coordinates: [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.3]]] };

describe('staff hooks', () => {
  it('loads the staff list under its key', async () => {
    mockFetch({ 'GET /api/v1/owner/staff': () => ok([{ id: 1, name: 'Ali' }]) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useManagedStaff(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(managementKeys.staff)).toEqual([{ id: 1, name: 'Ali' }]);
  });

  it.each([
    ['edit', 'PATCH /api/v1/owner/staff/1', () => useUpdateManagedStaff(), { id: 1, input: { name: 'Vali' } }],
    ['role', 'PUT /api/v1/owner/staff/1/role', () => useChangeStaffRole(), { id: 1, input: { role: 'cook' as const } }],
    ['PIN', 'PUT /api/v1/owner/staff/1/pin', () => useSetManagedStaffPin(), { id: 1, pin: '4821' }],
    ['removal', 'DELETE /api/v1/owner/employees/1', () => useRemoveManagedStaff(), 1],
  ])('refetches the staff list after a %s', async (_name, route, useHook, variables) => {
    mockFetch({ [route]: () => ok({ id: 1 }), 'GET /api/v1/owner/staff': () => ok([]) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useHook() as { mutateAsync: (v: unknown) => Promise<unknown> }, { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync(variables);
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: managementKeys.staff });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.employees });
  });

  it('hands the server refusal to the caller (a phone the person proved themselves)', async () => {
    mockFetch({ 'PATCH /api/v1/owner/staff/1': () => json({ success: false, message: 'Xato', errors: { phone: ['SMS bilan tasdiqlangan'] } }, 422) });
    const onError = vi.fn();
    const { result } = renderHook(() => useUpdateManagedStaff({ onError }), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync({ id: 1, input: { phone: '+998901112233' } }).catch(() => undefined);
    });
    expect(onError.mock.calls[0]![0]).toBeInstanceOf(StaffApiError);
  });
});

describe('branch hooks', () => {
  it('loads the branches and refetches branches, zones and staff after a change', async () => {
    mockFetch({
      'GET /api/v1/owner/branches': () => ok([{ id: 1, name: 'Markaz' }]),
      'POST /api/v1/owner/branches': () => ok({ id: 2 }),
      'DELETE /api/v1/owner/branches/2': () => ok(null),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const list = renderHook(() => useMenuBranches(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const create = renderHook(() => useCreateBranch(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ name: 'Yunusobod', address: 'x', region_id: 1, district_id: 1, latitude: 41, longitude: 69 });
    });
    for (const key of [menuAdminKeys.branches, managementKeys.zones, managementKeys.staff]) expect(invalidate).toHaveBeenCalledWith({ queryKey: key });
    const remove = renderHook(() => useDeleteBranch(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await remove.result.current.mutateAsync(2);
    });
    expect(invalidate).toHaveBeenCalledTimes(6);
  });

  it('keeps the refusal reason of a branch in use', async () => {
    mockFetch({ 'DELETE /api/v1/owner/branches/1': () => json({ success: false, message: 'Filialda buyurtmalar bor', data: { reason: 'branch_in_use' } }, 422) });
    const { result } = renderHook(() => useDeleteBranch(), { wrapper: wrapperFor(makeQueryClient()) });
    const error = await act(async () => result.current.mutateAsync(1).catch((e: unknown) => e));
    expect((error as StaffApiError).reason).toBe('branch_in_use');
  });
});

describe('zone hooks', () => {
  it('loads the zones, creates one and deletes one, refetching the list', async () => {
    mockFetch({
      'GET /api/v1/owner/delivery-zones': () => ok([]),
      'POST /api/v1/owner/delivery-zones': () => ok({ id: 3 }),
      'DELETE /api/v1/owner/delivery-zones/3': () => ok(null),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const list = renderHook(() => useDeliveryZones(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const create = renderHook(() => useCreateDeliveryZone(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ name: 'Markaz', polygon: POLYGON });
    });
    const remove = renderHook(() => useDeleteDeliveryZone(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await remove.result.current.mutateAsync(3);
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: managementKeys.zones });
    expect(invalidate).toHaveBeenCalledTimes(2);
  });
});

describe('settings and device hooks', () => {
  it('refetches the settings after a save and the fees after a fee save', async () => {
    mockFetch({
      'POST /api/v1/owner/settings': () => ok(null),
      'PUT /api/v1/owner/service-fees': () => ok({ branch_id: null }),
    });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const save = renderHook(() => useSaveStoreSettings(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await save.result.current.mutateAsync({
        logo: null, banner: null, description: null, address: null, location_url: null, is_open: true, min_order_amount: 0,
        payment_settings: { delivery_fee: 0, delivery_enabled: true, pickup_enabled: true, working_hours: { open: '09:00', close: '22:00' }, cash: { enabled: true }, click: { enabled: false }, payme: { enabled: false }, uzum: { enabled: false }, paynet: { enabled: false } },
      });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: staffKeys.settings });
    const fee = renderHook(() => useSaveServiceFee(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await fee.result.current.mutateAsync({ service_charge_percent: 10, place_fee_amount: 0, place_fee_mode: 'per_person' });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: managementKeys.serviceFees });
    // The store's or a branch's fee is not shown with the halls
    expect(invalidate).not.toHaveBeenCalledWith({ queryKey: floorKeys.allHalls });
  });

  it("refetches the halls after a hall's own fee is saved (the hall carries its fee)", async () => {
    mockFetch({ 'PUT /api/v1/owner/service-fees': () => ok({ branch_id: 1, hall_id: 1 }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const fee = renderHook(() => useSaveServiceFee(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await fee.result.current.mutateAsync({ hall_id: 1, branch_id: 1, service_charge_percent: 20, place_fee_amount: 0, place_fee_mode: 'per_person' });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: managementKeys.serviceFees });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: floorKeys.allHalls });
  });

  it('keeps the revoked and the live device lists apart, and refetches both after a revoke', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/devices': () => ok([]),
      'POST /api/v1/owner/devices/d-1/revoke': () => ok({ uuid: 'd-1' }),
    });
    const queryClient = makeQueryClient();
    const live = renderHook(() => useStoreDevices(false), { wrapper: wrapperFor(queryClient) });
    const all = renderHook(() => useStoreDevices(true), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(live.result.current.isSuccess && all.result.current.isSuccess).toBe(true));
    const revoke = renderHook(() => useRevokeStoreDevice(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await revoke.result.current.mutateAsync('d-1');
    });
    await waitFor(() => expect(calls.filter((call) => call.method === 'GET')).toHaveLength(4));
  });

  it('creates a pairing code without touching the device list', async () => {
    mockFetch({ 'POST /api/v1/owner/devices/pairing-codes': () => ok({ code: 'ABCD-EFGH', expires_at: '2026-10-21T10:10:00+05:00', type: 'pos', branch_id: null }) });
    const queryClient = makeQueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useCreatePairingCode(), { wrapper: wrapperFor(queryClient) });
    const code = await act(async () => result.current.mutateAsync({ type: 'pos' }));
    expect(code.code).toBe('ABCD-EFGH');
    expect(invalidate).not.toHaveBeenCalled();
  });
});
