import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockFetch, ok, testApi } from '../test/helpers';

afterEach(() => vi.unstubAllGlobals());

// Laravel's `boolean` rule takes 1/0/"1"/"0" only; openapi-fetch would send "true"/"false" and get a 422
describe('boolean query parameters', () => {
  it('are sent as 1 and 0, next to the other parameters', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/owner/devices': () => ok({ devices: [] }) });
    await testApi().client.GET('/v1/owner/devices', { params: { query: { include_revoked: false } } });
    await testApi().client.GET('/v1/owner/devices', { params: { query: { include_revoked: true } } });
    expect(calls.map((call) => new URL(call.url).search)).toEqual(['?include_revoked=0', '?include_revoked=1']);
  });

  it('leave strings, numbers and missing values as they were', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/pos/bookings/calendar': () => ok({}) });
    await testApi().client.GET('/v1/pos/bookings/calendar', {
      params: { query: { branch_id: 2, from: '2026-10-22', to: 'true', include_canceled: false } },
    });
    expect(Object.fromEntries(new URL(calls[0]!.url).searchParams)).toEqual({ branch_id: '2', from: '2026-10-22', to: 'true', include_canceled: '0' });
  });
});
