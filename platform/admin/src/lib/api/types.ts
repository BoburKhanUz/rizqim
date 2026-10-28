import type { components, operations } from '@rizqim/api-client';

// The admin API as the web app uses it (docs/API.md "Admin API", /api/v1/admin/*). The shapes are the ones of the generated
// @rizqim/api-client (packages/api-spec/openapi.json): the data of the 200/201 answer of an operation. What the spec leaves
// loose (plain `string` for a status, a role) is compared with the literal values where it is used.

type JsonBody<R> = R extends { content: { 'application/json': infer B } } ? B : never;
type Data<K extends keyof operations, S extends number = 200> = operations[K] extends { responses: infer R }
  ? S extends keyof R
    ? JsonBody<R[S]> extends { data: infer D }
      ? D
      : never
    : never
  : never;

export type PlatformRole = components['schemas']['PlatformRole'];
// In the order of the team table of docs/PHASE4_PLAN.md section 5
export const PLATFORM_ROLES: readonly PlatformRole[] = ['superadmin', 'operator', 'support', 'finance', 'sales', 'viewer'];
export const isPlatformRole = (value: unknown): value is PlatformRole => typeof value === 'string' && (PLATFORM_ROLES as readonly string[]).includes(value);

// --- Sign-in -----------------------------------------------------------------------------------------------------------

export type Challenge = Data<'admin.auth.login'>;
export type TwoFactorSetup = Data<'admin.auth.2fa_setup'>;
// 2fa/verify: the session; 2fa/confirm: the same and the recovery codes (shown once)
export type SessionResult = Data<'admin.auth.2fa_confirm'>;
export type AdminMember = SessionResult['member'];
export type AdminMe = Data<'admin.auth.me'>;
export type AdminSessionRow = Data<'admin.auth.sessions'>[number];

// --- Team --------------------------------------------------------------------------------------------------------------

export type TeamMember = Data<'admin.team'>[number];
// `status` is invited (has not accepted the invite), active or disabled
export const isInvited = (member: Pick<TeamMember, 'status'>): boolean => member.status === 'invited';

export interface InviteLink {
  // The link to hand over, shown once
  url: string;
  expires_at: string | null;
}

// --- Roles -------------------------------------------------------------------------------------------------------------

export type RoleMatrix = Data<'admin.roles'>;
export type RoleColumn = RoleMatrix['roles'][number];
export type PermissionRow = RoleMatrix['permissions'][number];

// --- Audit log ---------------------------------------------------------------------------------------------------------

export type AuditPage = Data<'admin.audit_logs'>;
export type AuditEntry = AuditPage['data'][number];

// The filters of the screen, as typed (the empty ones are not sent)
export interface AuditFilters {
  q: string;
  action: string;
  actor_id: string;
  store_id: string;
  subject_type: string;
  subject_id: string;
  from: string;
  to: string;
}

export const EMPTY_AUDIT_FILTERS: AuditFilters = { q: '', action: '', actor_id: '', store_id: '', subject_type: '', subject_id: '', from: '', to: '' };

// --- Search ------------------------------------------------------------------------------------------------------------

export type SearchResult = Data<'admin.search'>[number];
