import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { StopListView } from './StopListView';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, group, ok, permissionsAnswer, product, variant } from './menuFixtures';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const entry = (over: Record<string, unknown> = {}) => ({ id: 91, branch_id: 1, type: 'product', subject_id: 7, subject_uuid: 'p-7', name: 'Osh', reason: 'Guruch tugadi', until: null, created_by: 5, created_at: '2026-10-21T11:30:00+05:00', ...over });

const lagman = product({ id: 8, name: "Lag'mon", variants: [variant({ id: 21, product_id: 8, name: 'Kichik' }), variant({ id: 22, product_id: 8, name: 'Katta' })] });

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}, entries: unknown[] = [entry()]) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(),
    'GET /api/v1/owner/branches': () => ok([branch()]),
    'GET /api/v1/owner/stop-list': () => ok({ branch_id: 1, entries }),
    'GET /api/v1/owner/products': () => json({ success: true, data: [product(), lagman] }),
    'GET /api/v1/owner/modifier-groups': () => ok([group()]),
    'GET /api/v1/owner/employees': () => json({ success: true, data: [{ id: 5, name: 'Dilnoza', role: 'manager' }] }),
    ...extra,
  };
}

describe('the stop-list', () => {
  it('shows what is stopped in the branch, why, since when and by whom', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<StopListView />);
    const stopped = await screen.findByRole('region', { name: 'Hozir stop-listda: 1' });
    expect(within(stopped).getAllByText('Osh').length).toBeGreaterThan(0);
    expect(within(stopped).getByText('Taom')).toBeInTheDocument();
    await waitFor(() => expect(within(stopped).getByText(/Dilnoza/)).toBeInTheDocument());
    expect(within(stopped).getByText(/Guruch tugadi/)).toBeInTheDocument();
    expect(within(stopped).getByText(/dan beri/)).toBeInTheDocument();
    expect(new URL(calls.find((call) => call.url.includes('/stop-list'))!.url).searchParams.get('branch_id')).toBe('1');
  });

  it('puts a dish back on sale with one tap', async () => {
    let entries = [entry()];
    const { calls } = mockFetch(routes({ 'DELETE /api/v1/owner/stop-list/91': () => { entries = []; return ok(null); }, 'GET /api/v1/owner/stop-list': () => ok({ branch_id: 1, entries }) }));
    renderPage(<StopListView />);
    const user = userEvent.setup();
    const stopped = await screen.findByRole('region', { name: 'Hozir stop-listda: 1' });
    await user.click(within(stopped).getByRole('button', { name: /Sotuvga qaytarish/ }));
    expect(await screen.findByText("Stop-list bo'sh")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'DELETE')).toBe(true);
  });

  it('stops a dish, a variant and an extra with one tap, searching first', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/stop-list': () => json({ success: true, message: 'ok', data: entry({ id: 92, type: 'variant', subject_id: 22 }) }, 201) }, []));
    renderPage(<StopListView />);
    const user = userEvent.setup();
    await screen.findByText("Stop-list bo'sh");

    await user.type(screen.getByRole('searchbox'), 'katta');
    expect(screen.queryByText('Osh')).not.toBeInTheDocument();
    await user.click(await screen.findByRole('button', { name: /Tugadi.*Katta/ }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ branch_id: 1, type: 'variant', id: 22 });

    await user.clear(screen.getByRole('searchbox'));
    await user.type(screen.getByRole('searchbox'), 'achchiq');
    await user.click(await screen.findByRole('button', { name: /Tugadi.*Achchiq/ }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'POST')).toHaveLength(2));
    expect(await calls.filter((call) => call.method === 'POST')[1]!.json()).toEqual({ branch_id: 1, type: 'modifier', id: 31 });
  });

  it('marks what is stopped in the menu and offers to restore it', async () => {
    mockFetch(routes());
    renderPage(<StopListView />);
    const menu = await screen.findByRole('region', { name: 'Menyu' });
    expect(await within(menu).findByText('Stop-listda')).toBeInTheDocument();
    expect(within(menu).getByRole('button', { name: /Sotuvga qaytarish.*Osh/ })).toBeInTheDocument();
    expect(within(menu).getByRole('button', { name: /Tugadi.*Lag'mon/ })).toBeInTheDocument();
  });

  it('stops with a reason and a time', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/stop-list': () => json({ success: true, message: 'ok', data: entry({ id: 93, subject_id: 8 }) }, 201) }, []));
    renderPage(<StopListView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: "Sabab va vaqt bilan to'xtatish: Lag'mon" }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText('Sabab (ixtiyoriy)'), 'Un tugadi');
    await user.type(within(dialog).getByLabelText('Qachongacha (ixtiyoriy)'), '2026-10-22T09:00');
    await user.click(within(dialog).getByRole('button', { name: 'Tugadi' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    const body = (await calls.find((call) => call.method === 'POST')!.json()) as { reason: string; until: string; id: number };
    expect(body.reason).toBe('Un tugadi');
    expect(body.id).toBe(8);
    expect(new Date(body.until).getTime()).toBe(new Date('2026-10-22T09:00').getTime());
  });

  it("shows the server's refusal in Uzbek", async () => {
    mockFetch(routes({ 'POST /api/v1/owner/stop-list': () => json({ message: 'Ruxsat etilmagan' }, 403) }, []));
    renderPage(<StopListView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /Tugadi.*Osh/ }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Ruxsat etilmagan');
  });

  it('works on the branch that was picked', async () => {
    const { calls } = mockFetch(routes({ 'GET /api/v1/owner/branches': () => ok([branch(), branch({ id: 2, name: 'Yunusobod' })]) }));
    renderPage(<StopListView />);
    const user = userEvent.setup();
    await user.selectOptions(await screen.findByLabelText('Filial'), 'Yunusobod');
    await waitFor(() => expect(calls.some((call) => call.url.includes('/stop-list') && new URL(call.url).searchParams.get('branch_id') === '2')).toBe(true));
  });

  it('is read-only without stoplist.manage', async () => {
    mockFetch(routes({ 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']) }));
    renderPage(<StopListView />);
    await screen.findByRole('region', { name: 'Hozir stop-listda: 1' });
    await screen.findAllByText('Lag\'mon');
    expect(screen.queryByRole('button', { name: /Tugadi|Sotuvga qaytarish/ })).not.toBeInTheDocument();
  });

  it('retries when the stop-list does not load', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/stop-list': () => json({ message: 'x' }, 500) }));
    renderPage(<StopListView />);
    expect(await screen.findByText("Ma'lumot yuklanmadi")).toBeInTheDocument();
  });
});
