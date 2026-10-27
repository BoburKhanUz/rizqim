// The admin API as the web app uses it (docs/API.md "Admin API", /api/v1/admin/*). Written against the P4-11 contract;
// the generated @rizqim/api-client replaces these shapes once the spec lands.

export const PLATFORM_ROLES = ['superadmin', 'operator', 'support', 'finance', 'sales', 'viewer'] as const;
export type PlatformRole = (typeof PLATFORM_ROLES)[number];

export const isPlatformRole = (value: unknown): value is PlatformRole => typeof value === 'string' && (PLATFORM_ROLES as readonly string[]).includes(value);

// --- Sign-in -----------------------------------------------------------------------------------------------------------

export type ChallengeStatus = 'otp_required' | 'setup_required';

export interface Challenge {
  status: ChallengeStatus;
  challenge_token: string;
  expires_at: string | null;
}

export interface TwoFactorSetup {
  secret: string;
  otpauth_uri: string;
  // The QR code as an inline SVG (drawn on the server)
  qr_svg: string;
}

export interface SignedInMember {
  id: number;
  name: string;
  email: string;
  platform_role: PlatformRole | null;
}

export interface SessionResult {
  expires_at: string | null;
  member: SignedInMember | null;
  // Only after 2fa/confirm: shown once
  recovery_codes: string[];
}

export interface AdminMe {
  member: SignedInMember;
  platform_role: PlatformRole | null;
  // Permission keys as the backend names them ("admin.team.manage"); use `hasPermission`
  permissions: string[];
  two_factor_enabled: boolean;
  recovery_codes_left: number;
  last_login_at: string | null;
}

export interface AdminSessionRow {
  id: string;
  current: boolean;
  ip: string | null;
  user_agent: string | null;
  created_at: string | null;
  last_used_at: string | null;
  expires_at: string | null;
}

// --- Team --------------------------------------------------------------------------------------------------------------

export interface TeamMember {
  id: number;
  name: string;
  email: string;
  platform_role: PlatformRole | null;
  disabled: boolean;
  // Invited and has not set a password and 2FA yet
  invite_pending: boolean;
  two_factor_enabled: boolean;
  last_login_at: string | null;
  active_sessions: number;
}

export interface InviteLink {
  // The link to hand over, shown once
  url: string;
  expires_at: string | null;
}

export interface InviteResult {
  member: TeamMember | null;
  invite: InviteLink;
}

// --- Roles -------------------------------------------------------------------------------------------------------------

export interface RoleColumn {
  key: string;
  label: string;
  editable: boolean;
}

export interface PermissionRow {
  key: string;
  label: string;
  group: string;
}

export interface RoleMatrix {
  roles: RoleColumn[];
  permissions: PermissionRow[];
  grants: Record<string, string[]>;
}

// --- Audit log ---------------------------------------------------------------------------------------------------------

export interface AuditEntry {
  id: string;
  action: string;
  actor_name: string | null;
  actor_id: number | null;
  subject_type: string | null;
  subject_id: string | null;
  store_id: number | null;
  store_name: string | null;
  reason: string | null;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  ip: string | null;
  user_agent: string | null;
  created_at: string | null;
}

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

export interface AuditPage {
  entries: AuditEntry[];
  nextCursor: string | null;
}

// --- Search ------------------------------------------------------------------------------------------------------------

export type SearchType = 'store' | 'order' | 'user' | 'member';

export interface SearchResult {
  type: SearchType | string;
  id: string;
  title: string;
  subtitle: string | null;
  // The path in this app ("/stores/12")
  href: string;
}
