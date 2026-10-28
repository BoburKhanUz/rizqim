import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { OkData } from './types';

// P4-32: the store's harakatlar jurnali (GET /owner/audit-logs, audit.view: owners by default), the page the old store
// panel had. Newest first, Laravel pages; phones are masked and secrets never reach it (the server's AuditRedactor).
export interface AuditLogQuery {
  // An exact action (order.cancelled) or a prefix with `.*` (order.*)
  action?: string;
  user_id?: number;
  branch_id?: number;
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

export type AuditLogPage = OkData<'auditLog.index'>;
export type AuditLogEntry = AuditLogPage['data'][number];

export async function fetchAuditLog(api: StaffApi, query: AuditLogQuery = {}): Promise<AuditLogPage> {
  const body = unwrap(await api.client.GET('/v1/owner/audit-logs', { params: { query } }));
  return body.data;
}
