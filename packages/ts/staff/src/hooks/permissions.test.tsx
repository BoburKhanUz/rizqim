import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import type { RolePermissionTable } from '../api/permissions';
import { rolePermissionKeys, useRolePermissions, useSaveRolePermission } from './permissions';

afterEach(() => vi.unstubAllGlobals());

const TABLE: RolePermissionTable = {
  roles: [{ role: 'cashier', label: 'Kassir', editable: true }],
  permissions: [{ key: 'pos.refund', label: 'Smena ichida pulni qaytarish', grantable: true }],
  matrix: { cashier: { 'pos.refund': false } },
  defaults: { cashier: { 'pos.refund': false } },
};

describe('role permission hooks', () => {
  it('loads the table under its key', async () => {
    mockFetch({ 'GET /api/v1/owner/permissions': () => ok(TABLE) });
    const queryClient = makeQueryClient();
    const { result } = renderHook(() => useRolePermissions(), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(queryClient.getQueryData(rolePermissionKeys.table)).toEqual(TABLE);
  });

  it('puts the saved cell into the cached table at once and reads the table again', async () => {
    let held = false;
    const { calls } = mockFetch({
      'GET /api/v1/owner/permissions': () => ok({ ...TABLE, matrix: { cashier: { 'pos.refund': held } } }),
      'PUT /api/v1/owner/permissions': () => {
        held = true;
        return ok({ role: 'cashier', permission: 'pos.refund', allowed: true });
      },
    });
    const queryClient = makeQueryClient();
    queryClient.setQueryData(rolePermissionKeys.table, TABLE);
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const { result } = renderHook(() => useSaveRolePermission(), { wrapper: wrapperFor(queryClient) });
    await act(async () => {
      await result.current.mutateAsync({ role: 'cashier', permission: 'pos.refund', allowed: true });
    });
    expect(await calls[0]!.json()).toEqual({ role: 'cashier', permission: 'pos.refund', allowed: true });
    expect(queryClient.getQueryData<RolePermissionTable>(rolePermissionKeys.table)?.matrix.cashier).toEqual({ 'pos.refund': true });
    expect(invalidate).toHaveBeenCalledWith({ queryKey: rolePermissionKeys.table });
  });
});
