import { del, get, patch, post, put, call } from './client';
import {
  toAuditPage,
  toChallenge,
  toInvite,
  toInviteResult,
  toMe,
  toRoleMatrix,
  toSearchResults,
  toSession,
  toSessionResult,
  toSetup,
  toTeamMember,
} from './normalize';
import type { AdminMe, AdminSessionRow, AuditFilters, AuditPage, Challenge, InviteResult, PlatformRole, RoleMatrix, SearchResult, SessionResult, TeamMember, TwoFactorSetup } from './types';

// One function per admin endpoint (docs/API.md "Admin API"). Paths are relative to /api/v1/admin.

const origin = () => (typeof window === 'undefined' ? '' : window.location.origin);
const anonymous = { anonymous: true } as const;

// --- Sign-in (public until 2fa/verify or 2fa/confirm) ----------------------------------------------------------------------

export const login = async (email: string, password: string): Promise<Challenge> => toChallenge(await post('/auth/login', { email, password }, anonymous));

export const acceptInvite = async (token: string, password: string): Promise<Challenge> => toChallenge(await post('/auth/invite/accept', { token, password }, anonymous));

export const startTwoFactorSetup = async (challengeToken: string): Promise<TwoFactorSetup> => toSetup(await post('/auth/2fa/setup', { challenge_token: challengeToken }, anonymous));

export const confirmTwoFactorSetup = async (challengeToken: string, code: string): Promise<SessionResult> =>
  toSessionResult(await post('/auth/2fa/confirm', { challenge_token: challengeToken, code }, anonymous));

export const verifyTwoFactor = async (challengeToken: string, proof: { code: string } | { recovery_code: string }): Promise<SessionResult> =>
  toSessionResult(await post('/auth/2fa/verify', { challenge_token: challengeToken, ...proof }, anonymous));

// --- The session -------------------------------------------------------------------------------------------------------------

export const getMe = async (): Promise<AdminMe> => toMe(await get('/auth/me'));

export const logout = async (): Promise<void> => {
  await post('/auth/logout');
};

export const listSessions = async (): Promise<AdminSessionRow[]> => {
  const data = await get<unknown>('/auth/sessions');
  const rows = Array.isArray(data) ? data : [];
  return rows.map(toSession);
};

export const revokeSession = async (id: string): Promise<void> => {
  await del(`/auth/sessions/${encodeURIComponent(id)}`);
};

// Step-up: the current session counts as freshly confirmed for 10 minutes
export const confirmStepUp = async (code: string): Promise<void> => {
  await call('POST', '/auth/confirm', { body: { code } });
};

export const regenerateRecoveryCodes = async (): Promise<string[]> => toSessionResult(await post('/auth/2fa/recovery-codes')).recovery_codes;

// --- Team ----------------------------------------------------------------------------------------------------------------------

export const listTeam = async (): Promise<TeamMember[]> => {
  const data = await get<unknown>('/team');
  return (Array.isArray(data) ? data : []).map(toTeamMember);
};

export const inviteMember = async (input: { name: string; email: string; platform_role: PlatformRole }): Promise<InviteResult> => toInviteResult(await post('/team', input), origin());

export const reissueInvite = async (id: number) => toInvite(await post(`/team/${id}/invite`), origin());

export const updateMember = async (id: number, change: { platform_role?: PlatformRole; disabled?: boolean }): Promise<void> => {
  await patch(`/team/${id}`, change);
};

export const resetTwoFactor = async (id: number): Promise<void> => {
  await post(`/team/${id}/reset-2fa`);
};

// --- Roles -----------------------------------------------------------------------------------------------------------------------

export const getRoleMatrix = async (): Promise<RoleMatrix> => toRoleMatrix(await get('/roles'));

export const setRolePermission = async (change: { role: string; permission: string; allowed: boolean }): Promise<void> => {
  await put('/roles', change);
};

// --- Audit log -------------------------------------------------------------------------------------------------------------------

export async function listAudit(filters: AuditFilters, cursor: string | null, signal?: AbortSignal): Promise<AuditPage> {
  const { data, meta } = await call('GET', '/audit-logs', { query: { ...filters, cursor, per_page: 30 }, signal });
  return toAuditPage(data, meta);
}

// --- Search ----------------------------------------------------------------------------------------------------------------------

export const search = async (q: string, signal?: AbortSignal): Promise<SearchResult[]> => toSearchResults((await call('GET', '/search', { query: { q }, signal })).data);
