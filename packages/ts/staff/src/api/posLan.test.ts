import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, makeQueryClient, mockFetch, ok, testApi, wrapperFor } from '../test/helpers';
import { posLanKeys, useBranchLan, useRotateBranchLanKey, useSaveBranchLan } from '../hooks/posLan';
import { getApiFieldError } from './errors';
import { fetchBranchLan, rotateBranchLanKey, saveBranchLan } from './posLan';

afterEach(() => vi.unstubAllGlobals());

const view = (over: Record<string, unknown> = {}) => ({
  branch_id: 3,
  key_id: 1,
  rotated_at: null,
  port: 18480,
  hub: { device: 'till-1', name: 'Kassa 1', chosen: false },
  candidates: [
    { uuid: 'till-1', name: 'Kassa 1', platform: 'windows', app: 'cashier', last_seen_at: '2026-11-07T08:00:00Z' },
    { uuid: 'till-2', name: 'Kassa 2', platform: 'android', app: null, last_seen_at: null },
  ],
  ...over,
});

describe('local bridge (P6)', () => {
  it('reads the branch view with the app of each candidate, picks the hub and rotates the key with the branch in the body', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/pos/lan': () => ok(view()),
      'PUT /api/v1/owner/pos/lan': () => ok(view({ hub: { device: 'till-2', name: 'Kassa 2', chosen: true } })),
      'POST /api/v1/owner/pos/lan/rotate': () => ok(view({ key_id: 2, rotated_at: '2026-11-07T09:00:00Z' })),
    });
    const api = testApi();
    const read = await fetchBranchLan(api, 3);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');
    expect(read.candidates.map((candidate) => candidate.app)).toEqual(['cashier', null]);

    const saved = await saveBranchLan(api, 3, { hub_device_id: 'till-2' });
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, hub_device_id: 'till-2' });
    expect(saved.hub).toEqual({ device: 'till-2', name: 'Kassa 2', chosen: true });
    await saveBranchLan(api, 3, { hub_device_id: null, port: 18500 });
    expect(await calls[2]!.json()).toEqual({ branch_id: 3, hub_device_id: null, port: 18500 });

    const rotated = await rotateBranchLanKey(api, 3);
    expect(await calls[3]!.json()).toEqual({ branch_id: 3 });
    expect(rotated.key_id).toBe(2);
  });

  it("carries the field error of a hub that is not the branch's till", async () => {
    mockFetch({
      'PUT /api/v1/owner/pos/lan': () => json({ success: false, message: 'Xato', errors: { hub_device_id: ['Markaz kassa filialning faol kassasi emas.'] } }, 422),
    });
    const error = await saveBranchLan(testApi(), 3, { hub_device_id: 'phone' }).catch((e: unknown) => e);
    expect(getApiFieldError(error, 'hub_device_id')).toBe('Markaz kassa filialning faol kassasi emas.');
  });

  it('keeps the view under a key per branch and puts the answer of a save or a rotation there', async () => {
    mockFetch({
      'GET /api/v1/owner/pos/lan': () => ok(view()),
      'PUT /api/v1/owner/pos/lan': () => ok(view({ port: 18500 })),
      'POST /api/v1/owner/pos/lan/rotate': () => ok(view({ key_id: 2 })),
    });
    const queryClient = makeQueryClient();
    const list = renderHook(() => useBranchLan(3), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(list.result.current.isSuccess).toBe(true));
    const save = renderHook(() => useSaveBranchLan(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await save.result.current.mutateAsync({ branchId: 3, patch: { port: 18500 } });
    });
    expect(queryClient.getQueryData<{ port: number }>(posLanKeys.branch(3))?.port).toBe(18500);
    const rotate = renderHook(() => useRotateBranchLanKey(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await rotate.result.current.mutateAsync({ branchId: 3 });
    });
    expect(queryClient.getQueryData<{ key_id: number }>(posLanKeys.branch(3))?.key_id).toBe(2);
  });

  it('does not ask without a branch', () => {
    const { fetchMock } = mockFetch({});
    renderHook(() => useBranchLan(null), { wrapper: wrapperFor(makeQueryClient()) });
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
