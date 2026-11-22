import { afterEach, describe, expect, it, vi } from 'vitest';
import { mockFetch, ok, testApi } from '../test/helpers';
import { fetchUzQrSettings, saveUzQrTillKey } from './uzqr';

afterEach(() => vi.unstubAllGlobals());

const TILL = { uuid: '0d7f0c2e-6f4a-4f4e-9d5e-3a6b0d5d1a11', name: 'Kassa 1', branch_id: 3, has_api_key: true, api_key_last4: 'a1b2', api_key_set_at: null, ready: true, message: null };

describe("the UzQR tills' keys (Wave P)", () => {
  it('reads the tills and sends a key, a masked one or a removal as given', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/fiscal/uzqr': () => ok({ driver: 'uzqr_operator', tin: '301234567', tin_set: true, tills: [TILL] }),
      [`PUT /api/v1/owner/fiscal/uzqr/tills/${TILL.uuid}`]: () => ok(TILL),
    });
    const api = testApi();
    const view = await fetchUzQrSettings(api, 3);
    expect(view.tills[0]?.has_api_key).toBe(true);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');

    const key = `k${Math.random().toString(36).slice(2)}${Date.now()}`;
    expect((await saveUzQrTillKey(api, TILL.uuid, { api_key: key })).api_key_last4).toBe('a1b2');
    expect(await calls[1]!.json()).toEqual({ api_key: key });
    await saveUzQrTillKey(api, TILL.uuid, { remove_api_key: true });
    expect(await calls[2]!.json()).toEqual({ remove_api_key: true });
  });
});
