import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { getApiErrorMessage, StaffApiError } from './errors';
import { fetchRolePermissions, overrideFor, PERMISSION_ROLES, permissionState, saveRolePermission } from './permissions';

afterEach(() => vi.unstubAllGlobals());

const TABLE = {
  roles: [
    { role: 'owner', label: 'Ega', editable: false },
    { role: 'cashier', label: 'Kassir', editable: true },
  ],
  permissions: [
    { key: 'pos.reprint_bill', label: 'Schyotni qayta chiqarish', grantable: true },
    { key: 'pos.refund', label: 'Smena ichida pulni qaytarish', grantable: true },
    { key: 'finance.manage', label: "Pul yechish va to'ldirish", grantable: false },
  ],
  matrix: { owner: { 'pos.reprint_bill': true, 'pos.refund': true }, cashier: { 'pos.reprint_bill': true, 'pos.refund': true, 'finance.manage': false } },
  defaults: { owner: { 'pos.reprint_bill': true, 'pos.refund': true }, cashier: { 'pos.reprint_bill': true, 'pos.refund': false, 'finance.manage': false } },
};

describe('role permissions', () => {
  it('reads the table: roles, permissions, the effective matrix and the defaults', async () => {
    mockFetch({ 'GET /api/v1/owner/permissions': () => ok(TABLE) });
    const table = await fetchRolePermissions(testApi());
    expect(table.roles.map((role) => role.role)).toEqual(['owner', 'cashier']);
    expect(table.permissions[2]).toEqual({ key: 'finance.manage', label: "Pul yechish va to'ldirish", grantable: false });
    expect(table.matrix.cashier!['pos.refund']).toBe(true);
    expect(table.defaults.cashier!['pos.refund']).toBe(false);
  });

  it('sends the role, the permission and true, false or null (back to the default)', async () => {
    const { calls } = mockFetch({
      'PUT /api/v1/owner/permissions': async (request) => {
        const body = (await request.clone().json()) as { role: string; permission: string; allowed: boolean | null };
        return ok({ role: body.role, permission: body.permission, allowed: body.allowed ?? false }, {});
      },
    });
    const api = testApi();
    await saveRolePermission(api, { role: 'cashier', permission: 'pos.refund', allowed: true });
    await saveRolePermission(api, { role: 'cashier', permission: 'pos.reprint_bill', allowed: false });
    const reset = await saveRolePermission(api, { role: 'cashier', permission: 'pos.refund', allowed: null });
    expect(await Promise.all(calls.map((call) => call.json()))).toEqual([
      { role: 'cashier', permission: 'pos.refund', allowed: true },
      { role: 'cashier', permission: 'pos.reprint_bill', allowed: false },
      { role: 'cashier', permission: 'pos.refund', allowed: null },
    ]);
    expect(reset.permission).toBe('pos.refund');
  });

  it('carries the 422 of a permission only the owner holds, in the server words', async () => {
    mockFetch({ 'PUT /api/v1/owner/permissions': () => json({ success: false, message: "Bu ruxsatni faqat do'kon egasi bajaradi." }, 422) });
    const error = await saveRolePermission(testApi(), { role: 'manager', permission: 'finance.manage', allowed: true }).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(StaffApiError);
    expect(getApiErrorMessage(error)).toBe("Bu ruxsatni faqat do'kon egasi bajaradi.");
  });
});

describe('permissionState', () => {
  it('tells what is allowed now, by default, and whether the store changed it', () => {
    expect(permissionState(TABLE, 'cashier', 'pos.reprint_bill')).toEqual({ allowed: true, defaultAllowed: true, overridden: false });
    expect(permissionState(TABLE, 'cashier', 'pos.refund')).toEqual({ allowed: true, defaultAllowed: false, overridden: true });
    expect(permissionState(TABLE, 'cashier', 'finance.manage')).toEqual({ allowed: false, defaultAllowed: false, overridden: false });
  });

  it('treats a role or permission the table does not list as not allowed', () => {
    expect(permissionState(TABLE, 'courier', 'pos.refund')).toEqual({ allowed: false, defaultAllowed: false, overridden: false });
    expect(permissionState(TABLE, 'cashier', 'nope')).toEqual({ allowed: false, defaultAllowed: false, overridden: false });
  });
});

describe('overrideFor', () => {
  it('sends null when the wish is the default, so no redundant override is kept', () => {
    expect(overrideFor(false, false)).toBeNull();
    expect(overrideFor(true, true)).toBeNull();
    expect(overrideFor(false, true)).toBe(true);
    expect(overrideFor(true, false)).toBe(false);
  });

  it('lists the roles an owner can change, without the owner', () => {
    expect(PERMISSION_ROLES).toEqual(['manager', 'cashier', 'waiter', 'cook', 'hostess', 'courier']);
  });
});
