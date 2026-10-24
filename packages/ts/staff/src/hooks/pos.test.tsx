import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { accountKeys, useCreateCustomerAccount, useCustomerAccounts, useDebtors, useUpdateCustomerAccount } from './accounts';
import { bookingKeys, useBookingCalendar, useBookingConfirmation, useCancelBooking, useCreateBooking, useSendBookingConfirmation, useUpdateBooking } from './bookings';
import { floorKeys, useCreateDiningTable, useCreateHall, useCreateRoomTariff, useDeleteHall, useHalls, useRoomTariffs, useSaveFloorLayout, useSaveTariffCalendar } from './floor';
import { posSetupKeys, useAssignStationRoutes, useBranchPosSettings, useCreateKitchenStation, useDeleteKitchenStation, useKitchenStations, useSaveBranchPosSettings } from './posSetup';
import { shiftKeys, useBranchShiftSummary, useShift, useShifts } from './shifts';

afterEach(() => vi.unstubAllGlobals());

const invalidations = (queryClient: ReturnType<typeof makeQueryClient>) => vi.spyOn(queryClient, 'invalidateQueries');

describe('floor hooks', () => {
  it('loads the halls of a branch under a key per branch', async () => {
    mockFetch({ 'GET /api/v1/owner/halls': () => ok([{ id: 1, name: 'Asosiy', tables: [] }]) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useHalls(2), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(floorKeys.halls(2))).toEqual([{ id: 1, name: 'Asosiy', tables: [] }]);
  });

  it.each([
    ['a hall', 'POST /api/v1/owner/halls', () => useCreateHall(), { branch_id: 2, name: 'Zal' }],
    ['a place', 'POST /api/v1/owner/dining-tables', () => useCreateDiningTable(), { hall_id: 1, name: 'T-1' }],
    ['the layout', 'PUT /api/v1/owner/dining-tables/layout', () => useSaveFloorLayout(), { branchId: 2, tables: [{ id: 1, layout: { x: 0, y: 0, w: 2, h: 2 } }] }],
    ['a deleted hall', 'DELETE /api/v1/owner/halls/1', () => useDeleteHall(), 1],
  ])('reads the halls again after %s is saved', async (_name, route, useHook, variables) => {
    mockFetch({ [route]: () => ok({}) });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const { result } = renderHook(() => useHook() as { mutateAsync: (v: unknown) => Promise<unknown> }, { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync(variables);
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: floorKeys.allHalls });
  });

  it('hands the reason of a hall in use to the caller', async () => {
    mockFetch({ 'DELETE /api/v1/owner/halls/1': () => json({ success: false, message: 'Zalda ochiq buyurtma bor.', data: { reason: 'hall_in_use' } }, 422) });
    const onError = vi.fn();
    const { result } = renderHook(() => useDeleteHall({ onError }), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync(1).catch(() => undefined);
    });
    expect((onError.mock.calls[0]![0] as { reason?: string }).reason).toBe('hall_in_use');
  });

  it('reads the tariffs again after a tariff or the calendar changes', async () => {
    mockFetch({
      'GET /api/v1/owner/room-tariffs': () => ok({ calendar: { branch_id: 2, weekend_days: [6, 7], holidays: [] }, tariffs: [] }),
      'POST /api/v1/owner/room-tariffs': () => ok({ id: 1 }),
      'PUT /api/v1/owner/room-tariffs/calendar': () => ok({ branch_id: 2, weekend_days: [5], holidays: [] }),
    });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const list = renderHook(() => useRoomTariffs(2), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const create = renderHook(() => useCreateRoomTariff(), { wrapper: wrapperFor(queryClient) });
    const calendar = renderHook(() => useSaveTariffCalendar(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ branch_id: 2, table_kind: 'room', day_type: 'weekday', price: 100000 });
      await calendar.result.current.mutateAsync({ branchId: 2, weekend_days: [5], holidays: [] });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: floorKeys.allTariffs });
    // The holidays live in the branch POS settings (P3-26), so saving the calendar refreshes them too
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['owner', 'branch-pos-settings'] });
    expect(invalidate).toHaveBeenCalledTimes(3);
  });
});

describe('POS setup hooks', () => {
  it('loads the stations of a branch', async () => {
    mockFetch({ 'GET /api/v1/owner/kitchen-stations': () => ok({ kinds: [], stations: [{ id: 1, name: 'Osh' }] }) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useKitchenStations(3), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(posSetupKeys.stations(3))).toBeDefined();
  });

  it('reads the stations, routes and printers again after a station is added or deleted', async () => {
    mockFetch({ 'POST /api/v1/owner/kitchen-stations': () => ok({ id: 2 }), 'DELETE /api/v1/owner/kitchen-stations/2': () => ok(null) });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const create = renderHook(() => useCreateKitchenStation(), { wrapper: wrapperFor(queryClient) });
    const remove = renderHook(() => useDeleteKitchenStation(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ branch_id: 3, name: 'Mangal', kind: 'mangal' });
      await remove.result.current.mutateAsync(2);
    });
    for (const key of [posSetupKeys.allStations, posSetupKeys.allRoutes, posSetupKeys.allPrinters]) expect(invalidate).toHaveBeenCalledWith({ queryKey: key });
  });

  it('keeps the routes the bulk assignment answered', async () => {
    mockFetch({ 'PUT /api/v1/owner/kitchen-station-routes': () => ok([{ id: 1, station_id: 1, category_id: 4 }]) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useAssignStationRoutes(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ branch_id: 3, station_id: 1, category_ids: [4] });
    });
    expect(queryClient.getQueryData(posSetupKeys.routes(3))).toEqual([{ id: 1, station_id: 1, category_id: 4 }]);
  });

  it('does not ask for the settings of no branch, and keeps the saved ones', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, business_day_starts_at: '05:00', pos_settings: {} }),
      'PUT /api/v1/owner/branches/3/pos-settings': () => ok({ branch_id: 3, business_day_starts_at: '04:00', pos_settings: {} }),
    });
    const queryClient = makeQueryClient();
    const idle = renderHook(() => useBranchPosSettings(null), { wrapper: wrapperFor(queryClient) });
    expect(idle.result.current.fetchStatus).toBe('idle');
    expect(calls).toHaveLength(0);
    const save = renderHook(() => useSaveBranchPosSettings(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await save.result.current.mutateAsync({ branchId: 3, patch: { business_day_starts_at: '04:00' } });
    });
    expect(queryClient.getQueryData<{ business_day_starts_at: string }>(posSetupKeys.branchSettings(3))?.business_day_starts_at).toBe('04:00');
  });
});

describe('booking hooks', () => {
  it('loads the calendar of a range, and waits while there is no range', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/bookings/calendar': () => ok({ branch_id: 2, rooms: [], unassigned: [], timezone: 'Asia/Tashkent' }) });
    const queryClient = makeQueryClient();
    const waiting = renderHook(() => useBookingCalendar(null), { wrapper: wrapperFor(queryClient) });
    expect(waiting.result.current.fetchStatus).toBe('idle');
    const query = { branch_id: 2, from: '2026-10-22', to: '2026-10-22' };
    const { result } = renderHook(() => useBookingCalendar(query), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(calls).toHaveLength(1);
    expect(queryClient.getQueryData(bookingKeys.calendar(query))).toBeDefined();
  });

  it('reads the book again after a booking is made, edited or canceled', async () => {
    mockFetch({
      'POST /api/v1/pos/bookings': () => ok({ uuid: 'b1' }),
      'PATCH /api/v1/pos/bookings/b1': () => ok({ uuid: 'b1' }),
      'POST /api/v1/pos/bookings/b1/cancel': () => ok({ uuid: 'b1' }),
    });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const create = renderHook(() => useCreateBooking(), { wrapper: wrapperFor(queryClient) });
    const update = renderHook(() => useUpdateBooking(), { wrapper: wrapperFor(queryClient) });
    const cancel = renderHook(() => useCancelBooking(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ starts_at: '2026-10-25T18:00:00', contact_name: 'Aziz' });
      await update.result.current.mutateAsync({ uuid: 'b1', patch: { guests_planned: 30 } });
      await cancel.result.current.mutateAsync({ uuid: 'b1', reason: 'Mijoz bekor qildi' });
    });
    expect(invalidate).toHaveBeenCalledTimes(3);
    expect(invalidate).toHaveBeenCalledWith({ queryKey: bookingKeys.all });
  });

  it('hands a collision to the caller with the bookings in the way', async () => {
    mockFetch({ 'POST /api/v1/pos/bookings': () => json({ success: false, message: 'Xona band.', data: { reason: 'booking_conflict', conflicts: [{ uuid: 'x' }] } }, 409) });
    const onError = vi.fn();
    const { result } = renderHook(() => useCreateBooking({ onError }), { wrapper: wrapperFor(makeQueryClient()) });
    await act(async () => {
      await result.current.mutateAsync({ starts_at: '2026-10-25T18:00:00', contact_name: 'Aziz' }).catch(() => undefined);
    });
    expect((onError.mock.calls[0]![0] as { reason?: string }).reason).toBe('booking_conflict');
  });
});

describe('booking confirmation hooks (P3-38)', () => {
  it('loads the confirmation of a booking under its own key, and waits while there is no booking', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/bookings/b1/confirmation': () => ok({ text: 'Bron tasdiqlandi', lines: [], room_fee: null, channels: { telegram: false, sms: true } }) });
    const queryClient = makeQueryClient();
    const waiting = renderHook(() => useBookingConfirmation(null), { wrapper: wrapperFor(queryClient) });
    expect(waiting.result.current.fetchStatus).toBe('idle');
    const { result } = renderHook(() => useBookingConfirmation('b1'), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(calls).toHaveLength(1);
    expect(queryClient.getQueryData(bookingKeys.confirmation('b1'))).toMatchObject({ channels: { sms: true } });
  });

  it('reads the book again after a send (the booking now says when it was sent) and hands no_channel to the caller', async () => {
    let refuse = false;
    mockFetch({
      'POST /api/v1/pos/bookings/b1/confirmation/send': () => (refuse ? json({ success: false, message: "Kanal yo'q.", data: { reason: 'no_channel' } }, 422) : ok({ channel: 'sms', queued: true })),
    });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const onError = vi.fn();
    const { result } = renderHook(() => useSendBookingConfirmation({ onError }), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      expect(await result.current.mutateAsync({ uuid: 'b1' })).toEqual({ channel: 'sms', queued: true });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: bookingKeys.all });
    refuse = true;
    await act(async () => {
      await result.current.mutateAsync({ uuid: 'b1' }).catch(() => undefined);
    });
    expect((onError.mock.calls[0]![0] as { reason?: string }).reason).toBe('no_channel');
    expect(invalidate).toHaveBeenCalledTimes(1);
  });
});

describe('customer account hooks', () => {
  it('loads the debtors and a search page', async () => {
    mockFetch({
      'GET /api/v1/pos/customer-accounts/debtors': () => ok({ total_debt: '300000.00', count: 2, accounts: [] }),
      'GET /api/v1/pos/customer-accounts': () => ok({ accounts: [], meta: { current_page: 1, last_page: 1, per_page: 30, total: 0 } }),
    });
    const queryClient = makeQueryClient();
    const debtors = renderHook(() => useDebtors(), { wrapper: wrapperFor(queryClient) });
    const list = renderHook(() => useCustomerAccounts({ q: 'Ka' }), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(debtors.result.current.isSuccess && list.result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(accountKeys.debtors)).toBeDefined();
    expect(debtors.result.current.data?.total_debt).toBe('300000.00');
  });

  it('reads the accounts and the debtors again after an account is opened or edited', async () => {
    mockFetch({ 'POST /api/v1/pos/customer-accounts': () => ok({ uuid: 'a1' }), 'PATCH /api/v1/pos/customer-accounts/a1': () => ok({ uuid: 'a1' }) });
    const queryClient = makeQueryClient();
    const invalidate = invalidations(queryClient);
    const create = renderHook(() => useCreateCustomerAccount(), { wrapper: wrapperFor(queryClient) });
    const update = renderHook(() => useUpdateCustomerAccount(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await create.result.current.mutateAsync({ name: 'Karim' });
      await update.result.current.mutateAsync({ uuid: 'a1', patch: { is_blocked: true } });
    });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: accountKeys.all });
    expect(invalidate).toHaveBeenCalledTimes(2);
  });
});

describe('shift hooks', () => {
  it('lists the shifts of a branch and loads the summary of a branch', async () => {
    mockFetch({
      'GET /api/v1/pos/shifts': () => ok([]),
      'GET /api/v1/pos/shifts/current': () => ok({ shift: null, report: null, shifts: [] }),
    });
    const queryClient = makeQueryClient();
    const list = renderHook(() => useShifts({ branch_id: 2 }), { wrapper: wrapperFor(queryClient) });
    const summary = renderHook(() => useBranchShiftSummary(2), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess && summary.result.current.isSuccess).toBe(true));
    expect(summary.result.current.data).toEqual({ open: [], lastClosed: null });
    expect(queryClient.getQueryData(shiftKeys.summary(2))).toBeDefined();
  });

  it('loads a shift only for a uuid', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/shifts/s1': () => ok({ uuid: 's1', status: 'closed', summary: { cash: { expected: '1.00' } } }) });
    const queryClient = makeQueryClient();
    const idle = renderHook(() => useShift(null), { wrapper: wrapperFor(queryClient) });
    expect(idle.result.current.fetchStatus).toBe('idle');
    const { result } = renderHook(() => useShift('s1'), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.summary?.cash.expected).toBe('1.00');
    expect(calls).toHaveLength(1);
  });
});
