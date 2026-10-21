import { afterEach, describe, expect, it, vi } from 'vitest';
import { API_ROOT, json, makeOrder, mockFetch, ok, testApi } from '../test/helpers';
import { createAuthFetch } from './auth';
import { createStaffApi, toApiRoot } from './client';
import { getApiErrorMessage, getApiErrorStatus, getApiFieldError, isStaffApiError, StaffApiError } from './errors';
import { addStopListEntry, deleteProduct, fetchBatches, fetchMenuAvailability, fetchOwnerProducts, fetchStopList, productFormData, removeProductImage, removeStopListEntry, saveProduct, toggleProductActive } from './menu';
import { assignCourier, changeOrderStatus, completeOrder, fetchActiveOrders, fetchCouriers, fetchOrdersPage, performOrderAction, updateOrderStatus } from './orders';

afterEach(() => vi.unstubAllGlobals());

describe('toApiRoot', () => {
  it('turns the versioned URL an app is configured with into the root the generated paths hang on', () => {
    expect(toApiRoot('/api/v1')).toBe('/api');
    expect(toApiRoot('https://api.rizqim.uz/api/v1/')).toBe('https://api.rizqim.uz/api');
    expect(toApiRoot('/api')).toBe('/api');
  });
});

describe('createStaffApi', () => {
  it('sends JSON-accepting requests with the extra headers, through the injected fetch', async () => {
    const sent: Request[] = [];
    const api = createStaffApi({
      baseUrl: API_ROOT,
      headers: { 'Bypass-Tunnel-Reminder': 'true' },
      fetch: async (request) => {
        sent.push(request);
        return ok([]);
      },
    });
    await fetchOwnerProducts(api);
    expect(sent).toHaveLength(1);
    expect(sent[0]!.url).toBe(`${API_ROOT}/v1/owner/products`);
    expect(sent[0]!.headers.get('Accept')).toBe('application/json');
    expect(sent[0]!.headers.get('Bypass-Tunnel-Reminder')).toBe('true');
  });

  it('resolves a root-relative base against the page origin (a Request needs an absolute URL)', async () => {
    const sent: string[] = [];
    const api = createStaffApi({ baseUrl: '/api', fetch: async (request) => (sent.push(request.url), ok([])) });
    await fetchOwnerProducts(api);
    expect(sent[0]).toBe(`${location.origin}/api/v1/owner/products`);
  });
});

describe('errors', () => {
  it('turns a refused answer into a StaffApiError carrying the server\'s message and status', async () => {
    mockFetch({ 'GET /api/v1/owner/products': () => json({ success: false, message: 'Ruxsat yo\'q' }, 403) });
    const error = await fetchOwnerProducts(testApi()).catch((e: unknown) => e);
    expect(isStaffApiError(error)).toBe(true);
    expect(error).toBeInstanceOf(StaffApiError);
    expect(getApiErrorMessage(error)).toBe("Ruxsat yo'q");
    expect(getApiErrorStatus(error)).toBe(403);
  });

  it('reads the legacy {error} body, validation errors per field and the domain reason', () => {
    expect(getApiErrorMessage(new StaffApiError(403, { error: 'Unauthorized' }))).toBe('Unauthorized');
    const invalid = new StaffApiError(422, { message: 'Xato', errors: { reason: ['Sabab kerak'], branch_id: 'Filial noto\'g\'ri' } });
    expect(getApiFieldError(invalid, 'reason')).toBe('Sabab kerak');
    expect(getApiFieldError(invalid, 'branch_id')).toBe("Filial noto'g'ri");
    expect(getApiFieldError(invalid, 'note')).toBeUndefined();
    // only a 422 has field errors
    expect(getApiFieldError(new StaffApiError(400, { errors: { reason: ['x'] } }), 'reason')).toBeUndefined();
    expect(new StaffApiError(422, { message: 'x', data: { reason: 'outside_zone' } }).reason).toBe('outside_zone');
  });

  it('has no server message for a bare status or an HTML error page, and says HTTP <status>', () => {
    const html = new StaffApiError(502, '<html><body>Bad gateway</body></html>');
    expect(getApiErrorMessage(html)).toBeUndefined();
    expect(html.message).toBe('HTTP 502');
    expect(getApiErrorMessage(new StaffApiError(500, undefined))).toBeUndefined();
  });

  it('knows nothing about errors that are not its own', () => {
    expect(getApiErrorMessage(new Error('Network'))).toBeUndefined();
    expect(getApiFieldError('x', 'reason')).toBeUndefined();
    expect(getApiErrorStatus(null)).toBeUndefined();
  });

  it('lets a network failure through as it is', async () => {
    mockFetch({ 'GET /api/v1/owner/products': () => { throw new TypeError('Failed to fetch'); } });
    await expect(fetchOwnerProducts(testApi())).rejects.toThrow('Failed to fetch');
  });
});

describe('createAuthFetch', () => {
  const request = (init?: RequestInit) => new Request(`${API_ROOT}/v1/orders`, init);

  it('signs the request with the token', async () => {
    const send = vi.fn(async (r: Request) => json({ ok: r.headers.get('Authorization') }));
    const authFetch = createAuthFetch({ getToken: () => 'T1', renewToken: async () => null }, send);
    const response = await authFetch(request());
    expect(await response.json()).toEqual({ ok: 'Bearer T1' });
  });

  it('sends an unsigned request when there is no token', async () => {
    const send = vi.fn(async (r: Request) => json({ auth: r.headers.get('Authorization') }));
    const authFetch = createAuthFetch({ getToken: () => null, renewToken: async () => null }, send);
    expect(await (await authFetch(request())).json()).toEqual({ auth: null });
  });

  it('renews the token once on a 401 and replays the request, body included, with the new one', async () => {
    const seen: Array<{ auth: string | null; body: string }> = [];
    const send = vi.fn(async (r: Request) => {
      seen.push({ auth: r.headers.get('Authorization'), body: await r.text() });
      return seen.length === 1 ? json({ message: 'expired' }, 401) : json({ done: true });
    });
    const renewToken = vi.fn(async (sentWith: string | null) => (sentWith === 'OLD' ? 'NEW' : null));
    const onSignedOut = vi.fn();
    const authFetch = createAuthFetch({ getToken: () => 'OLD', renewToken, onSignedOut }, send);

    const response = await authFetch(request({ method: 'POST', body: JSON.stringify({ a: 1 }) }));

    expect(response.status).toBe(200);
    expect(seen).toEqual([
      { auth: 'Bearer OLD', body: '{"a":1}' },
      { auth: 'Bearer NEW', body: '{"a":1}' },
    ]);
    expect(renewToken).toHaveBeenCalledWith('OLD');
    expect(onSignedOut).not.toHaveBeenCalled();
  });

  it('signs out and returns the 401 when the session cannot be renewed', async () => {
    const send = vi.fn(async () => json({ message: 'expired' }, 401));
    const onSignedOut = vi.fn();
    const authFetch = createAuthFetch({ getToken: () => 'OLD', renewToken: async () => null, onSignedOut }, send);

    const response = await authFetch(request());

    expect(response.status).toBe(401);
    expect(send).toHaveBeenCalledTimes(1);
    expect(onSignedOut).toHaveBeenCalledTimes(1);
  });

  it('never replays twice: a replay that is refused again signs out', async () => {
    const send = vi.fn(async () => json({ message: 'no' }, 401));
    const renewToken = vi.fn(async () => 'NEW');
    const onSignedOut = vi.fn();
    const authFetch = createAuthFetch({ getToken: () => 'OLD', renewToken, onSignedOut }, send);

    const response = await authFetch(request());

    expect(response.status).toBe(401);
    expect(send).toHaveBeenCalledTimes(2);
    expect(renewToken).toHaveBeenCalledTimes(1);
    expect(onSignedOut).toHaveBeenCalledTimes(1);
  });

  it('leaves other statuses alone', async () => {
    const renewToken = vi.fn();
    const authFetch = createAuthFetch({ getToken: () => 'T', renewToken }, async () => json({ message: 'no' }, 403));
    expect((await authFetch(request())).status).toBe(403);
    expect(renewToken).not.toHaveBeenCalled();
  });

  it('looks the global fetch up on every call by default', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/orders': () => ok([]) });
    const authFetch = createAuthFetch({ getToken: () => 'T', renewToken: async () => null });
    await authFetch(request());
    expect(calls).toHaveLength(1);
    expect(calls[0]!.headers.get('Authorization')).toBe('Bearer T');
  });
});

describe('orders', () => {
  it('reads the open orders and whether the list was cut', async () => {
    const order = makeOrder();
    const { calls } = mockFetch({ 'GET /api/v1/orders': () => ok([order], { meta: { truncated: true } }) });
    const result = await fetchActiveOrders(testApi());
    expect(result).toEqual({ orders: [order], truncated: true });
    expect(new URL(calls[0]!.url).searchParams.get('scope')).toBe('active');
  });

  it('is not truncated without the flag', async () => {
    mockFetch({ 'GET /api/v1/orders': () => ok([]) });
    expect(await fetchActiveOrders(testApi())).toEqual({ orders: [], truncated: false });
  });

  it('reads a page of the history with its cursor, capping the page size at 50', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/orders': () => ok([makeOrder()], { meta: { next_cursor: 'c2', per_page: 50 } }) });
    const page = await fetchOrdersPage(testApi(), 'c1', 500);
    expect(page.nextCursor).toBe('c2');
    expect(page.orders).toHaveLength(1);
    const params = new URL(calls[0]!.url).searchParams;
    expect(params.get('scope')).toBe('history');
    expect(params.get('per_page')).toBe('50');
    expect(params.get('cursor')).toBe('c1');
  });

  it('sends no cursor for the first page and ends the list when the server sends none', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/orders': () => ok([], { meta: { next_cursor: null, per_page: 20 } }) });
    expect(await fetchOrdersPage(testApi(), null)).toEqual({ orders: [], nextCursor: null });
    expect(new URL(calls[0]!.url).searchParams.has('cursor')).toBe(false);
    expect(new URL(calls[0]!.url).searchParams.get('per_page')).toBe('20');
  });

  it('lists the couriers of a branch', async () => {
    const { calls } = mockFetch({ 'GET /api/v1/couriers': () => ok([{ id: 4, name: 'Ali (Kuryer)', phone: null, telegram_username: 'ali', branch_id: 1, courier_type: 'own' }]) });
    expect((await fetchCouriers(testApi(), 3))[0]!.name).toBe('Ali (Kuryer)');
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');
  });

  it('sends a plain status change with nothing else', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/orders/o-1/status': () => ok(makeOrder({ status: 'accepted' })) });
    const order = await updateOrderStatus(testApi(), { uuid: 'o-1', status: 'accepted', reason: 'other', note: 'ignored' });
    expect(order.status).toBe('accepted');
    expect(await calls[0]!.json()).toEqual({ status: 'accepted' });
  });

  it('sends the reason (and the note when there is one) with a cancellation or a rejection', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/orders/o-1/status': () => ok(makeOrder({ status: 'canceled' })) });
    await updateOrderStatus(testApi(), { uuid: 'o-1', status: 'canceled', reason: 'out_of_stock', note: 'Go\'sht tugagan' });
    await updateOrderStatus(testApi(), { uuid: 'o-1', status: 'rejected', reason: 'store_closed' });
    expect(await calls[0]!.json()).toEqual({ status: 'canceled', reason: 'out_of_stock', note: "Go'sht tugagan" });
    expect(await calls[1]!.json()).toEqual({ status: 'rejected', reason: 'store_closed' });
  });

  it('moves to courier_assigned through the assign-courier endpoint', async () => {
    const { calls } = mockFetch({ 'POST /api/v1/orders/o-1/assign-courier': () => ok(makeOrder({ status: 'courier_assigned' })) });
    await changeOrderStatus(testApi(), { uuid: 'o-1', status: 'courier_assigned', courierId: 9 });
    expect(await calls[0]!.json()).toEqual({ courier_id: 9 });
    await expect(changeOrderStatus(testApi(), { uuid: 'o-1', status: 'courier_assigned' })).rejects.toThrow('courier');
  });

  it('assigns, completes and performs an action on the endpoint the action names', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/orders/o-1/assign-courier': () => ok(makeOrder()),
      'POST /api/v1/orders/o-1/complete': () => ok(makeOrder({ status: 'delivered' })),
      'POST /api/v1/orders/o-1/status': () => ok(makeOrder()),
    });
    const api = testApi();
    await assignCourier(api, 'o-1', 2);
    await completeOrder(api, 'o-1');
    await performOrderAction(api, { uuid: 'o-1', action: { to: 'delivered', via: 'complete' } });
    await performOrderAction(api, { uuid: 'o-1', action: { to: 'courier_assigned', via: 'assign-courier' }, courierId: 5 });
    await performOrderAction(api, { uuid: 'o-1', action: { to: 'preparing', via: 'status' } });
    expect(calls.map((c) => new URL(c.url).pathname.split('/').pop())).toEqual(['assign-courier', 'complete', 'complete', 'assign-courier', 'status']);
  });

  it('lets the server refuse a transition: the error carries its message', async () => {
    mockFetch({ 'POST /api/v1/orders/o-1/status': () => json({ success: false, message: "Bu holatga o'tib bo'lmaydi" }, 422) });
    const error = await updateOrderStatus(testApi(), { uuid: 'o-1', status: 'ready' }).catch((e: unknown) => e);
    expect(getApiErrorMessage(error)).toBe("Bu holatga o'tib bo'lmaydi");
    expect(getApiErrorStatus(error)).toBe(422);
  });
});

describe('menu', () => {
  it('builds the multipart body of a product', async () => {
    const image = new File(['x'], 'plov.png', { type: 'image/png' });
    const form = productFormData({ name: 'Plov', description: null, category_id: 3, is_active: true, image, variants: [{ id: null, name: '1 porsiya', price: '25000' }], batch_tracked: false });
    expect(form.get('name')).toBe('Plov');
    expect(form.get('description')).toBe('');
    expect(form.get('category_id')).toBe('3');
    expect(form.get('is_active')).toBe('1');
    expect(form.get('batch_tracked')).toBe('0');
    expect((form.get('image') as File).name).toBe('plov.png');
    expect(JSON.parse(form.get('variants') as string)).toEqual([{ id: null, name: '1 porsiya', price: '25000' }]);
    expect(productFormData({ name: 'A', category_id: '1', variants: [] }).has('is_active')).toBe(false);
  });

  it('creates with POST /owner/products and updates with POST /owner/products/{id}, as multipart', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/products': () => ok({ id: 1 }),
      'POST /api/v1/owner/products/7': () => ok({ id: 7 }),
    });
    const api = testApi();
    const form = productFormData({ name: 'Plov', category_id: 3, variants: [] });
    await saveProduct(api, null, form);
    await saveProduct(api, 7, form);
    expect(calls.map((c) => new URL(c.url).pathname)).toEqual(['/api/v1/owner/products', '/api/v1/owner/products/7']);
    for (const call of calls) {
      expect(call.headers.get('Content-Type')).toMatch(/^multipart\/form-data; boundary=/);
      expect((await call.formData()).get('name')).toBe('Plov');
    }
  });

  it('toggles a product with the toggle_active flag and deletes it', async () => {
    const { calls } = mockFetch({
      'POST /api/v1/owner/products/7': () => ok(null),
      'DELETE /api/v1/owner/products/7': () => json({ success: true, message: 'ok' }),
      'DELETE /api/v1/owner/products/7/image': () => json({ success: true, message: "Rasm o'chirildi.", data: { id: 7, image: null } }),
    });
    const api = testApi();
    await toggleProductActive(api, 7);
    await deleteProduct(api, 7);
    expect((await removeProductImage(api, 7)).image).toBeNull();
    expect(new URL(calls[0]!.url).searchParams.get('toggle_active')).toBe('1');
    expect(await calls[0]!.text()).toBe('');
    expect(calls[1]!.method).toBe('DELETE');
    expect(new URL(calls[2]!.url).pathname).toBe('/api/v1/owner/products/7/image');
  });

  it('reads and edits the stop-list of a branch', async () => {
    const entry = { id: 5, branch_id: 2, type: 'product', subject_id: 8, subject_uuid: null, name: 'Plov', reason: 'Tugadi', until: null, created_by: 1, created_at: null };
    const { calls } = mockFetch({
      'GET /api/v1/owner/stop-list': () => ok({ branch_id: 2, entries: [entry] }),
      'POST /api/v1/owner/stop-list': () => ok(entry),
      'DELETE /api/v1/owner/stop-list/5': () => ok(null),
    });
    const api = testApi();
    expect(await fetchStopList(api, 2)).toEqual([entry]);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('2');
    await addStopListEntry(api, { branch_id: 2, type: 'product', id: 8, reason: 'Tugadi' });
    expect(await calls[1]!.json()).toEqual({ branch_id: 2, type: 'product', id: 8, reason: 'Tugadi' });
    await removeStopListEntry(api, 5);
    expect(calls[2]!.method).toBe('DELETE');
  });

  it('reads the batches and the menu availability', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/batches': () => ok({ branch_id: 2, batches: [{ id: 1, label: 'Qozon 1' }] }),
      'GET /api/v1/catalog/availability': () => ok({ store_id: 4, branches: [{ branch_id: '2', stopped: [], sold_out: { variants: [9] }, stop_list: [], batches: [] }] }),
    });
    const api = testApi();
    expect(await fetchBatches(api, { branchId: 2, includeClosed: true })).toHaveLength(1);
    expect(new URL(calls[0]!.url).searchParams.get('include_closed')).toBe('true');
    const branches = await fetchMenuAvailability(api, 4);
    expect(branches[0]!.sold_out.variants).toEqual([9]);
    expect(new URL(calls[1]!.url).searchParams.get('store_id')).toBe('4');
  });
});
