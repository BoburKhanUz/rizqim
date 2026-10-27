import { call, del, get, patch, post, put } from './client';
import { toAuditPage, toInviteLink, toSearchResults } from './normalize';
import type { AdminMe, AdminSessionRow, AuditFilters, AuditPage, Challenge, InviteLink, PlatformRole, RoleMatrix, SearchResult, SessionResult, TeamMember, TwoFactorSetup } from './types';

// One function per admin endpoint (docs/API.md "Admin API"). Paths are relative to /api/v1/admin. The types are the generated ones
// (types.ts); the transport is this app's own (client.ts): the session is a cookie held by the proxy, and a call may have to be
// repeated after a step-up confirmation, which the generated client has no place for.

const origin = () => (typeof window === 'undefined' ? '' : window.location.origin);
const anonymous = { anonymous: true } as const;

// --- Sign-in (public until 2fa/verify or 2fa/confirm) ----------------------------------------------------------------------

export const login = (email: string, password: string) => post<Challenge>('/auth/login', { email, password }, anonymous);

export const acceptInvite = (token: string, password: string) => post<Challenge>('/auth/invite/accept', { token, password }, anonymous);

export const startTwoFactorSetup = (challengeToken: string) => post<TwoFactorSetup>('/auth/2fa/setup', { challenge_token: challengeToken }, anonymous);

export const confirmTwoFactorSetup = (challengeToken: string, code: string) => post<SessionResult>('/auth/2fa/confirm', { challenge_token: challengeToken, code }, anonymous);

export const verifyTwoFactor = (challengeToken: string, proof: { code: string } | { recovery_code: string }) =>
  post<SessionResult>('/auth/2fa/verify', { challenge_token: challengeToken, ...proof }, anonymous);

// --- The session -------------------------------------------------------------------------------------------------------------

export const getMe = () => get<AdminMe>('/auth/me');

export const logout = async (): Promise<void> => {
  await post('/auth/logout');
};

export const listSessions = () => get<AdminSessionRow[]>('/auth/sessions');

export const revokeSession = async (id: number): Promise<void> => {
  await del(`/auth/sessions/${id}`);
};

// Step-up: the current session counts as freshly confirmed for 10 minutes
export const confirmStepUp = async (code: string): Promise<void> => {
  await call('POST', '/auth/confirm', { body: { code } });
};

export const regenerateRecoveryCodes = async (): Promise<string[]> => (await post<{ recovery_codes: string[] }>('/auth/2fa/recovery-codes')).recovery_codes;

// --- Team ----------------------------------------------------------------------------------------------------------------------

export const listTeam = () => get<TeamMember[]>('/team');

export async function inviteMember(input: { name: string; email: string; platform_role: PlatformRole }): Promise<InviteLink> {
  return toInviteLink(await post<Parameters<typeof toInviteLink>[0]>('/team', input), origin());
}

export async function reissueInvite(id: number): Promise<InviteLink> {
  return toInviteLink(await post<Parameters<typeof toInviteLink>[0]>(`/team/${id}/invite`), origin());
}

export const updateMember = async (id: number, change: { platform_role?: PlatformRole; disabled?: boolean }): Promise<void> => {
  await patch(`/team/${id}`, change);
};

export const resetTwoFactor = async (id: number): Promise<void> => {
  await post(`/team/${id}/reset-2fa`);
};

// --- Roles -----------------------------------------------------------------------------------------------------------------------

export const getRoleMatrix = () => get<RoleMatrix>('/roles');

// allowed: true grants, false takes away, null goes back to the role's default. The answer is the whole matrix.
export const setRolePermission = (change: { role: string; permission: string; allowed: boolean | null }) => put<RoleMatrix>('/roles', change);

// --- Audit log -------------------------------------------------------------------------------------------------------------------

export async function listAudit(filters: AuditFilters, cursor: string | null, signal?: AbortSignal): Promise<AuditPage> {
  return toAuditPage((await call('GET', '/audit-logs', { query: { ...filters, cursor, per_page: 30 }, signal })).data);
}

// --- Search ----------------------------------------------------------------------------------------------------------------------

export const search = async (q: string, signal?: AbortSignal): Promise<SearchResult[]> => toSearchResults((await call('GET', '/search', { query: { q }, signal })).data);
