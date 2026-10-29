import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { fetchAuditLog, type AuditLogPage, type AuditLogQuery } from '../api/audit';
import { useStaffApi } from './context';
import type { QueryExtras } from './orders';

export const auditKeys = {
  all: ['owner', 'audit-logs'] as const,
  page: (query: AuditLogQuery) => ['owner', 'audit-logs', query] as const,
};

// The store's audit log, a page at a time: the previous page stays on screen while the next one loads.
export function useAuditLog(query: AuditLogQuery = {}, options: QueryExtras<AuditLogPage> = {}) {
  const api = useStaffApi();
  return useQuery({ placeholderData: keepPreviousData, ...options, queryKey: auditKeys.page(query), queryFn: () => fetchAuditLog(api, query) });
}
