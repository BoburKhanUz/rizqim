import { afterEach, describe, expect, it, vi } from 'vitest';
import { queryClient } from '../lib/queryClient';
import { revisions } from '../lib/revisions';
import { SIGNED_OUT_ERROR, useSessionStatus } from '../lib/session';
import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { makeStore, makeUser } from '../test/factories';
import { staffApi } from './staffApi';

// Finding 5: the staff screens' API (@rizqim/staff) gives up on a session the same way the axios client does: through
// signOut (lib/session.ts), so the app lands on the sign-in error screen with the session and the caches gone.
describe('the staff API, when the session is refused and cannot be renewed', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('signs out to the error screen, with the query cache and the change tokens cleared', async () => {
    useAuthStore.getState().setAuth('dead-token', makeUser({ role: 'manager' }), makeStore(), false);
    useSessionStatus.setState({ signingIn: false, error: null, attempt: 0 });
    useCartStore.getState().addItem({ id: 'v1', productId: 1, variantId: 'v1', storeId: 's1', name: 'Lavash', price: 25000, quantity: 1 });
    queryClient.setQueryData(['staff', 'orders'], [{ id: 1 }]);
    const clearRevisions = vi.spyOn(revisions, 'clear');
    const fetch = vi.fn(async () => new Response(JSON.stringify({ message: 'Unauthenticated.' }), { status: 401, headers: { 'Content-Type': 'application/json' } }));
    vi.stubGlobal('fetch', fetch);

    // No initData outside Telegram: the session cannot be renewed
    await staffApi.client.GET('/v1/orders', { params: { query: { scope: 'active' } } });

    expect(fetch).toHaveBeenCalled();
    expect(useAuthStore.getState()).toMatchObject({ token: null, user: null, isAuthenticated: false });
    expect(useSessionStatus.getState()).toMatchObject({ signingIn: false, error: SIGNED_OUT_ERROR });
    expect(queryClient.getQueryCache().getAll()).toEqual([]);
    expect(clearRevisions).toHaveBeenCalled();
    expect(useCartStore.getState().items).toHaveLength(1);
  });
});
