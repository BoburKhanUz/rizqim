import { cleanup, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { makeQueryClient, mockFetch, ok, wrapperFor } from '../test/helpers';
import { auditKeys, useAuditLog } from './audit';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('useAuditLog', () => {
  it('loads a page of the store log with its filters under a key of the query', async () => {
    const { fetchMock } = mockFetch({
      'GET /api/v1/owner/audit-logs': () =>
        ok({ current_page: 2, last_page: 3, per_page: 25, total: 60, data: [{ id: 7, action: 'order.cancelled', actor: { id: 1, name: 'Ega', role: 'owner' }, created_at: '2026-10-28T10:00:00+05:00' }] }),
    });
    const queryClient = makeQueryClient();
    const query = { action: 'order.*', from: '2026-10-01', page: 2, per_page: 25 };
    const { result } = renderHook(() => useAuditLog(query), { wrapper: wrapperFor(queryClient) });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.data[0]?.action).toBe('order.cancelled');
    const url = new URL((fetchMock.mock.calls[0]?.[0] as Request).url);
    expect(url.searchParams.get('action')).toBe('order.*');
    expect(url.searchParams.get('page')).toBe('2');
    expect(queryClient.getQueryData(auditKeys.page(query))).toBeDefined();
  });
});
