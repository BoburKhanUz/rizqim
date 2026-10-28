import { renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { useEntitlements, useFeature } from './entitlements';
import { staffKeys } from './keys';

afterEach(() => vi.unstubAllGlobals());

const PAYLOAD = {
  plan: { code: 'start', name: 'Start', tier: 'start', version: 2 },
  legacy: false,
  period: 'monthly',
  status: 'active',
  trial_ends_at: null,
  current_period: null,
  term_ends_at: null,
  locked_since: null,
  lock_at: null,
  settings_locked: false,
  features: { hall: true, bookings: false },
  required_plans: { bookings: 'asosiy' },
  devices: { pos: { included: 1, active: 1, extra: 0, extra_price: '59000.00', can_add: true }, kds: { included: 0, active: 0, extra: 0, extra_price: '29000.00', can_add: false }, waiter: { included: null, unlimited: true, can_add: true } },
  notice: null,
};

describe('entitlements hooks', () => {
  it('loads the entitlements under their key', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(PAYLOAD) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useEntitlements(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(staffKeys.entitlements)).toEqual(PAYLOAD);
  });

  it('answers per feature: off with the plan that has it, on while unknown', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(PAYLOAD) });
    const { result } = renderHook(() => ({ bookings: useFeature('bookings'), hall: useFeature('hall') }), { wrapper: wrapperFor(makeQueryClient()) });
    // Not answered yet: open (the server judges), loading
    expect(result.current.bookings).toEqual({ allowed: true, planName: null, loading: true });
    await waitFor(() => expect(result.current.bookings.loading).toBe(false));
    expect(result.current.bookings).toEqual({ allowed: false, planName: 'Asosiy', loading: false });
    expect(result.current.hall.allowed).toBe(true);
  });

  it('keeps everything open when the read fails', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => json({ success: false, message: 'Server xatosi' }, 500) });
    const { result } = renderHook(() => useFeature('bookings'), { wrapper: wrapperFor(makeQueryClient()) });
    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.allowed).toBe(true);
  });
});
