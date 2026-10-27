import {
  isPlatformRole,
  type AdminMe,
  type AdminSessionRow,
  type AuditEntry,
  type AuditPage,
  type Challenge,
  type InviteLink,
  type InviteResult,
  type PermissionRow,
  type RoleMatrix,
  type SearchResult,
  type SessionResult,
  type SignedInMember,
  type TeamMember,
  type TwoFactorSetup,
} from './types';

// The answers of the admin API, read defensively: a field the backend renames or leaves out turns into an empty value
// here instead of a crash on the screen. Shapes follow the P4-11 contract (docs/API.md "Admin API").

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);
const str = (value: unknown): string => (typeof value === 'string' ? value : typeof value === 'number' ? String(value) : '');
const strOrNull = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value : typeof value === 'number' ? String(value) : null);
const numOrNull = (value: unknown): number | null => (typeof value === 'number' && Number.isFinite(value) ? value : typeof value === 'string' && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null);
const bool = (value: unknown): boolean => value === true || value === 1 || value === '1';
const list = (value: unknown): unknown[] => (Array.isArray(value) ? value : []);
const strings = (value: unknown): string[] => list(value).filter((item): item is string => typeof item === 'string');
const record = (value: unknown): Record<string, unknown> => (isRecord(value) ? value : {});

export function toMember(value: unknown): SignedInMember {
  const m = record(value);
  return {
    id: numOrNull(m.id) ?? 0,
    name: str(m.name),
    email: str(m.email),
    platform_role: isPlatformRole(m.platform_role) ? m.platform_role : null,
  };
}

export function toChallenge(value: unknown): Challenge {
  const c = record(value);
  return {
    status: c.status === 'setup_required' ? 'setup_required' : 'otp_required',
    challenge_token: str(c.challenge_token),
    expires_at: strOrNull(c.expires_at),
  };
}

export function toSetup(value: unknown): TwoFactorSetup {
  const s = record(value);
  return { secret: str(s.secret), otpauth_uri: str(s.otpauth_uri), qr_svg: str(s.qr_svg) };
}

export function toSessionResult(value: unknown): SessionResult {
  const s = record(value);
  return { expires_at: strOrNull(s.expires_at), member: isRecord(s.member) ? toMember(s.member) : null, recovery_codes: strings(s.recovery_codes) };
}

export function toMe(value: unknown): AdminMe {
  const me = record(value);
  const member = toMember(me.member);
  const role = isPlatformRole(me.platform_role) ? me.platform_role : member.platform_role;
  return {
    member: { ...member, platform_role: role },
    platform_role: role,
    permissions: strings(me.permissions),
    two_factor_enabled: bool(me.two_factor_enabled),
    recovery_codes_left: numOrNull(me.recovery_codes_left) ?? 0,
    last_login_at: strOrNull(me.last_login_at),
  };
}

export function toSession(value: unknown): AdminSessionRow {
  const s = record(value);
  return {
    id: str(s.id),
    current: bool(s.current ?? s.is_current),
    ip: strOrNull(s.ip ?? s.ip_address),
    user_agent: strOrNull(s.user_agent),
    created_at: strOrNull(s.created_at),
    last_used_at: strOrNull(s.last_used_at),
    expires_at: strOrNull(s.expires_at),
  };
}

export function toTeamMember(value: unknown): TeamMember {
  const m = record(value);
  return {
    id: numOrNull(m.id) ?? 0,
    name: str(m.name),
    email: str(m.email),
    platform_role: isPlatformRole(m.platform_role) ? m.platform_role : null,
    disabled: bool(m.disabled ?? m.is_disabled),
    invite_pending: bool(m.invite_pending) || m.status === 'invited',
    two_factor_enabled: bool(m.two_factor_enabled),
    last_login_at: strOrNull(m.last_login_at),
    active_sessions: numOrNull(m.active_sessions_count ?? m.active_sessions) ?? 0,
  };
}

// The one-time invite: the link itself (`url`) or just its token (the link is then this app's /invite/<token>)
export function toInvite(value: unknown, origin: string): InviteLink {
  const root = record(value);
  const nested = record(root.invite);
  const pick = (key: string) => nested[key] ?? root[key];
  const url = str(pick('url') ?? pick('invite_url'));
  const token = str(pick('token') ?? pick('invite_token'));
  return {
    url: url || (token ? `${origin}/invite/${token}` : ''),
    expires_at: strOrNull(pick('expires_at') ?? pick('invite_expires_at')),
  };
}

export function toInviteResult(value: unknown, origin: string): InviteResult {
  const root = record(value);
  return { member: isRecord(root.member) ? toTeamMember(root.member) : null, invite: toInvite(value, origin) };
}

export function toRoleMatrix(value: unknown): RoleMatrix {
  const root = record(value);
  const grants: Record<string, string[]> = {};
  for (const [role, keys] of Object.entries(record(root.grants))) grants[role] = strings(keys);
  return {
    roles: list(root.roles).map((item) => {
      const r = record(item);
      return { key: str(r.key), label: str(r.label) || str(r.key), editable: bool(r.editable) };
    }),
    permissions: list(root.permissions).map((item): PermissionRow => {
      const p = record(item);
      return { key: str(p.key), label: str(p.label) || str(p.key), group: str(p.group) };
    }),
    grants,
  };
}

const jsonObject = (value: unknown): Record<string, unknown> | null => (isRecord(value) ? value : null);

export function toAuditEntry(value: unknown): AuditEntry {
  const e = record(value);
  const actor = record(e.actor);
  const store = record(e.store);
  return {
    id: str(e.id),
    action: str(e.action),
    actor_name: strOrNull(actor.name ?? e.actor_name),
    actor_id: numOrNull(actor.id ?? e.actor_id ?? e.user_id),
    subject_type: strOrNull(e.subject_type),
    subject_id: strOrNull(e.subject_id),
    store_id: numOrNull(store.id ?? e.store_id),
    store_name: strOrNull(store.name),
    reason: strOrNull(e.reason),
    before: jsonObject(e.before ?? e.old_values),
    after: jsonObject(e.after ?? e.new_values),
    ip: strOrNull(e.ip ?? e.ip_address),
    user_agent: strOrNull(e.user_agent),
    created_at: strOrNull(e.created_at),
  };
}

// The journal page: `data` is the list with `next_cursor` beside it (the backend's cursor pages), or a bare list with the
// cursor in `meta` (the other cursor lists of the API)
export function toAuditPage(data: unknown, meta: Record<string, unknown>): AuditPage {
  const body = isRecord(data) ? data : null;
  const rows = Array.isArray(data) ? data : list(body?.data);
  const next = body?.next_cursor ?? meta.next_cursor;
  return { entries: rows.map(toAuditEntry), nextCursor: typeof next === 'string' && next !== '' ? next : null };
}

// The search answer: a flat list, `{results: [...]}`, or lists by type
export function toSearchResults(value: unknown): SearchResult[] {
  let items: unknown[];
  if (Array.isArray(value)) items = value;
  else if (isRecord(value) && Array.isArray(value.results)) items = value.results;
  else if (isRecord(value)) items = Object.values(value).flatMap((group) => (Array.isArray(group) ? group : []));
  else items = [];
  return items.flatMap((item): SearchResult[] => {
    const r = record(item);
    const href = str(r.href);
    // Only a path of this app: a link to another site is never followed
    if (!href.startsWith('/') || href.startsWith('//')) return [];
    return [{ type: str(r.type), id: str(r.id), title: str(r.title), subtitle: strOrNull(r.subtitle), href }];
  });
}
