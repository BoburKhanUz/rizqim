import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { AssignableRole, Employee } from './specGaps';
import type { JsonRequest, OkData } from './types';

// --- Employees (owner) -----------------------------------------------------------------------------------------------

// GET /owner/employees: the store's staff (the spec types the list as a string: api/specGaps.ts).
export async function fetchEmployees(api: StaffApi): Promise<Employee[]> {
  const body = unwrap(await api.client.GET('/v1/owner/employees'));
  return (body.data as unknown as Employee[] | null) ?? [];
}

export interface InviteInput {
  role: AssignableRole;
  name: string;
  // The backend requires one (422 without); null is sent as it is so the screen shows the server's message
  branchId: number | null;
}

export interface EmployeeInvite {
  employee: Employee;
  invite_link: string;
}

// POST /owner/employees/invite: creates the employee and the Telegram link he joins through.
export async function inviteEmployee(api: StaffApi, input: InviteInput): Promise<EmployeeInvite> {
  const body = unwrap(await api.client.POST('/v1/owner/employees/invite', { body: { role: input.role, name: input.name, branch_id: input.branchId as number } }));
  return body.data as unknown as EmployeeInvite;
}

// POST /owner/employees/{id}/transfer: moves the employee to another branch.
export async function transferEmployee(api: StaffApi, employeeId: number, branchId: number): Promise<Employee | null> {
  const body = unwrap(await api.client.POST('/v1/owner/employees/{employeeId}/transfer', { params: { path: { employeeId: String(employeeId) } }, body: { branch_id: branchId } }));
  return (body.data as unknown as Employee | null) ?? null;
}

// DELETE /owner/employees/{id}
export async function removeEmployee(api: StaffApi, employeeId: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/employees/{id}', { params: { path: { id: String(employeeId) } } }));
}

// --- PIN and role (staff access) -------------------------------------------------------------------------------------

export type StaffPinResult = OkData<'staffAccess.setPin'>;
export type StaffRoleResult = OkData<'staffAccess.updateRole'>;
export type StaffRoleInput = JsonRequest<'staffAccess.updateRole'>;

// PUT /owner/staff/{id}/pin: sets the PIN a terminal login uses.
export async function setStaffPin(api: StaffApi, userId: number, pin: string): Promise<StaffPinResult> {
  const body = unwrap(await api.client.PUT('/v1/owner/staff/{id}/pin', { params: { path: { id: userId } }, body: { pin } }));
  return body.data;
}

// DELETE /owner/staff/{id}/pin: clears the PIN (the person sets a new one).
export async function resetStaffPin(api: StaffApi, userId: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/staff/{id}/pin', { params: { path: { id: userId } } }));
}

// PUT /owner/staff/{id}/role
export async function updateStaffRole(api: StaffApi, userId: number, input: StaffRoleInput): Promise<StaffRoleResult> {
  const body = unwrap(await api.client.PUT('/v1/owner/staff/{id}/role', { params: { path: { id: userId } }, body: input }));
  return body.data;
}

// --- What the signed-in person may do -------------------------------------------------------------------------------------

export type MyPermissions = OkData<'permission.show'>;

// GET /me/permissions: role, store/branch scope and the permission keys; the backend checks every request anyway.
export async function fetchMyPermissions(api: StaffApi): Promise<MyPermissions> {
  const body = unwrap(await api.client.GET('/v1/me/permissions'));
  return body.data;
}
