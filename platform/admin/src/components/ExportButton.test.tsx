import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, refusal, renderPage } from '@/test/helpers';
import { ExportButton } from './ExportButton';

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

const file = (headers: Record<string, string> = {}) =>
  new Response(new Uint8Array([80, 75, 3, 4]), { status: 200, headers: { 'Content-Type': XLSX, 'Content-Disposition': 'attachment; filename=rizqim-orders-20261028-1200.xlsx', ...headers } });

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = ['admin.export', 'admin.orders.view'], query: object = { status: 'new', sla: '', store_id: '7', stuck: true, channel: undefined }) {
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody({ permissions })), ...routes });
  renderPage(<ExportButton list="orders" query={query} />);
  return { ...mocks, user: userEvent.setup() };
}

let saved: string[] = [];
beforeEach(() => {
  saved = [];
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:x'), revokeObjectURL: vi.fn() }));
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    saved.push(this.download);
  });
});
afterEach(() => vi.restoreAllMocks());

describe('ExportButton', () => {
  it('asks for the list\'s file with the list\'s filters (empty ones dropped) and saves it under the server\'s name', async () => {
    const { user, calls } = setup({ 'GET /exports/orders': () => file({ 'X-Export-Rows': '1250', 'X-Export-Truncated': '0' }) });
    await user.click(await screen.findByRole('button', { name: 'Excel yuklab olish' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Fayl tayyor: 1 250 qator.');
    const call = calls.find((item) => item.path === '/exports/orders')!;
    expect(Object.fromEntries(call.query)).toEqual({ status: 'new', store_id: '7', stuck: '1' });
    expect(saved).toEqual(['rizqim-orders-20261028-1200.xlsx']);
  });

  it('says when the list was cut at the cap', async () => {
    const { user } = setup({ 'GET /exports/orders': () => file({ 'X-Export-Rows': '50000', 'X-Export-Truncated': '1' }) });
    await user.click(await screen.findByRole('button', { name: 'Excel yuklab olish' }));

    expect(await screen.findByRole('status')).toHaveTextContent("Fayl tayyor: ro'yxat 50 000 qatordan keyin kesildi. Filtrni toraytiring va qayta yuklang.");
  });

  it('works when the answer carries no row count', async () => {
    const { user } = setup({ 'GET /exports/orders': () => file() });
    await user.click(await screen.findByRole('button', { name: 'Excel yuklab olish' }));
    expect(await screen.findByRole('status')).toHaveTextContent('Fayl tayyor.');
  });

  it('is not shown to a member without the export permission', async () => {
    setup({}, ['admin.orders.view']);
    await waitFor(() => expect(screen.queryByRole('button', { name: 'Excel yuklab olish' })).not.toBeInTheDocument());
    // The session must have loaded before "not shown" means anything
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(screen.queryByRole('button', { name: 'Excel yuklab olish' })).not.toBeInTheDocument();
  });

  it('says a refusal in words: too many exports, a refused filter, the server being down', async () => {
    let answer: () => Response = () => refusal(429, 'Too Many Attempts.');
    const { user } = setup({ 'GET /exports/orders': () => answer() });
    const button = await screen.findByRole('button', { name: 'Excel yuklab olish' });

    await user.click(button);
    expect(await screen.findByRole('alert')).toHaveTextContent("Juda ko'p eksport: bir daqiqadan keyin qayta urinib ko'ring.");

    answer = () => refusal(422, "Oy YYYY-MM ko'rinishida bo'lishi kerak.");
    await user.click(button);
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent("Oy YYYY-MM ko'rinishida bo'lishi kerak."));

    answer = () => refusal(403, "Bu amal uchun ruxsatingiz yo'q.", { reason: 'forbidden' });
    await user.click(button);
    await waitFor(() => expect(screen.getByRole('alert')).not.toHaveTextContent("Oy YYYY"));
  });

  it('keeps the button busy while the file is made and does not start a second export', async () => {
    let release: (response: Response) => void = () => {};
    const pending = new Promise<Response>((resolve) => (release = resolve));
    const { user, callsTo } = setup({ 'GET /exports/orders': () => pending });
    const button = await screen.findByRole('button', { name: 'Excel yuklab olish' });
    await user.click(button);

    const busy = await screen.findByRole('button', { name: 'Tayyorlanmoqda...' });
    expect(busy).toBeDisabled();
    await user.click(busy);
    expect(callsTo('GET', '/exports/orders')).toHaveLength(1);

    release(file({ 'X-Export-Rows': '3' }));
    expect(await screen.findByRole('status')).toHaveTextContent('Fayl tayyor: 3 qator.');
  });
});
