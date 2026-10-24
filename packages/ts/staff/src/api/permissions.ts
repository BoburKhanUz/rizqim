import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// What each role may do in the store, and the owner's changes to it (P3-37; docs/API.md "Ruxsatlar", AUTH.md section 6).
// GET /owner/permissions answers the roles, every permission (with the server's short Uzbek label and whether the owner
// may grant it to another role), the EFFECTIVE `matrix` (role -> permission -> allowed) and the `defaults` alongside:
// a cell is "changed" when the two differ. PUT /owner/permissions {role, permission, allowed} grants (true), takes away
// (false) or goes back to the default (null, which removes the store's override). Both need `permissions.manage` (owner).

export type RolePermissionTable = OkData<'staffAccess.permissions'>;
export type PermissionInfo = RolePermissionTable['permissions'][number];
export type RolePermissionChange = JsonRequest<'staffAccess.updatePermission'>;
export type RolePermissionSaved = OkData<'staffAccess.updatePermission'>;

// The roles whose permissions a store may change (RolePermissions::overridableRoles), in the order the panel shows them.
// The owner's own set is fixed and the server never lets it be edited.
export const PERMISSION_ROLES = ['manager', 'cashier', 'waiter', 'cook', 'courier'] as const;
export type PermissionRole = (typeof PERMISSION_ROLES)[number];

export interface PermissionState {
  // What the role may do now
  allowed: boolean;
  // What it may do without the store's changes
  defaultAllowed: boolean;
  // The store changed it (allowed differs from the default)
  overridden: boolean;
}

// One cell of the table. A permission the server does not list for the role is not allowed.
export function permissionState(table: Pick<RolePermissionTable, 'matrix' | 'defaults'>, role: string, key: string): PermissionState {
  const allowed = table.matrix[role]?.[key] === true;
  const defaultAllowed = table.defaults[role]?.[key] === true;
  return { allowed, defaultAllowed, overridden: allowed !== defaultAllowed };
}

// The `allowed` to send when the owner wants `wanted`: the default is sent as null (the server then drops its override
// instead of keeping a row that says the same as the default); anything else as the explicit true or false.
export function overrideFor(defaultAllowed: boolean, wanted: boolean): boolean | null {
  return wanted === defaultAllowed ? null : wanted;
}

export async function fetchRolePermissions(api: StaffApi): Promise<RolePermissionTable> {
  return unwrap(await api.client.GET('/v1/owner/permissions')).data;
}

// 422 when a permission the owner keeps to themselves (money out of the platform, settings, staff, audit) is granted:
// the message says so in Uzbek. Answers the cell as it is now.
export async function saveRolePermission(api: StaffApi, change: RolePermissionChange): Promise<RolePermissionSaved> {
  return unwrap(await api.client.PUT('/v1/owner/permissions', { body: change })).data;
}
