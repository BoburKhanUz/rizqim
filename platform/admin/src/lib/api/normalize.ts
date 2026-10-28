import type { AuditPage, InviteLink, SearchResult } from './types';

// The few places where the spec is looser than the answers, or where an answer is not to be trusted blindly.

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

// The invite of POST /team (201) and of POST /team/{id}/invite: the link itself, or just its token (then it is this app's /invite/<token>)
export function toInviteLink(value: { invite_url?: string; invite_token?: string; invite_expires_at?: string | null }, origin: string): InviteLink {
  return {
    url: value.invite_url || (value.invite_token ? `${origin}/invite/${value.invite_token}` : ''),
    expires_at: value.invite_expires_at ?? null,
  };
}

// The journal page: the backend's cursor page ({data, next_cursor}); a page that is not one is an empty one
export function toAuditPage(value: unknown): AuditPage {
  const page = isRecord(value) ? value : {};
  return {
    data: Array.isArray(page.data) ? (page.data as AuditPage['data']) : [],
    next_cursor: typeof page.next_cursor === 'string' && page.next_cursor !== '' ? page.next_cursor : null,
    prev_cursor: typeof page.prev_cursor === 'string' ? page.prev_cursor : null,
    per_page: typeof page.per_page === 'number' ? page.per_page : 0,
  };
}

// A search result leads to a path of this app only: a link to another site (or a protocol-relative one) is never followed
export function toSearchResults(value: unknown): SearchResult[] {
  if (!Array.isArray(value)) return [];
  return value.filter((item): item is SearchResult => isRecord(item) && typeof item.href === 'string' && item.href.startsWith('/') && !item.href.startsWith('//'));
}
