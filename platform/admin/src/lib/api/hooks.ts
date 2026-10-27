'use client';

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getRoleMatrix, inviteMember, listAudit, listSessions, listTeam, reissueInvite, resetTwoFactor, revokeSession, setRolePermission, updateMember } from './endpoints';
import type { AuditFilters, PlatformRole } from './types';

// TanStack Query over the endpoints: reads are cached per screen, writes refresh what they changed.

export const KEYS = {
  team: ['admin-team'] as const,
  roles: ['admin-roles'] as const,
  sessions: ['admin-sessions'] as const,
  audit: (filters: AuditFilters) => ['admin-audit', filters] as const,
};

export const useTeam = (enabled = true) => useQuery({ queryKey: KEYS.team, queryFn: listTeam, enabled });

export function useInviteMember() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: inviteMember,
    onSuccess: () => client.invalidateQueries({ queryKey: KEYS.team }),
  });
}

export function useReissueInvite() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => reissueInvite(id),
    onSuccess: () => client.invalidateQueries({ queryKey: KEYS.team }),
  });
}

export function useUpdateMember() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ id, change }: { id: number; change: { platform_role?: PlatformRole; disabled?: boolean } }) => updateMember(id, change),
    onSuccess: () => client.invalidateQueries({ queryKey: KEYS.team }),
  });
}

export function useResetTwoFactor() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => resetTwoFactor(id),
    onSuccess: () => client.invalidateQueries({ queryKey: KEYS.team }),
  });
}

export const useRoleMatrix = (enabled = true) => useQuery({ queryKey: KEYS.roles, queryFn: getRoleMatrix, enabled });

export function useSetRolePermission() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: setRolePermission,
    // The answer is the whole matrix: no second round trip
    onSuccess: (matrix) => client.setQueryData(KEYS.roles, matrix),
  });
}

export const useSessions = () => useQuery({ queryKey: KEYS.sessions, queryFn: listSessions });

export function useRevokeSession() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => revokeSession(id),
    onSuccess: () => client.invalidateQueries({ queryKey: KEYS.sessions }),
  });
}

// The audit log, newest first, a page at a time by cursor. The filters are part of the key: another filter, another list.
export function useAuditLog(filters: AuditFilters, enabled = true) {
  return useInfiniteQuery({
    queryKey: KEYS.audit(filters),
    queryFn: ({ pageParam, signal }) => listAudit(filters, pageParam, signal),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.next_cursor,
    enabled,
  });
}
