import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BatchesView } from './BatchesView';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { batch, branch, ok, permissionsAnswer, product, variant } from './menuFixtures';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const lagman = product({ id: 8, name: "Lag'mon", batch_tracked: false, variants: [variant({ id: 21, product_id: 8, name: 'Kichik' }), variant({ id: 22, product_id: 8, name: 'Katta' })] });

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}, batches: unknown[] = [batch()]) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(),
    'GET /api/v1/owner/branches': () => ok([branch()]),
    'GET /api/v1/owner/batches': (request: Request) => ok({ branch_id: Number(new URL(request.url).searchParams.get('branch_id')), batches }),
    'GET /api/v1/owner/products': () => json({ success: true, data: [lagman, product()] }),
    // P6-15: the planned batches and which batch features the branch's format has
    'GET /api/v1/owner/batch-plans': () => ok({ branch_id: 1, modules: { batches: true, batch_writeoff: false }, plans: [] }),
    ...extra,
  };
}

describe('the batches', () => {
  it('shows what is left of each open batch', async () => {
    const { calls } = mockFetch(routes({}, [batch(), batch({ id: 42, product_name: 'Sho\'rva', quantity_produced: 20, quantity_left: 0, label: null })]));
    renderPage(<BatchesView />);

    const osh = (await screen.findAllByText('Osh'))[0]!.closest('li')!;
    expect(within(osh).getByText('12')).toBeInTheDocument();
    expect(within(osh).getByText(/\/\s30/)).toBeInTheDocument();
    expect(within(osh).getByText('Ochiq')).toBeInTheDocument();
    expect(within(osh).getByRole('progressbar', { name: 'Qoldiq' })).toHaveAttribute('aria-valuenow', '40');
    const soldOut = screen.getAllByText("Sho'rva")[0]!.closest('li')!;
    expect(within(soldOut).getByText('Tugadi')).toBeInTheDocument();
    expect(new URL(calls.find((call) => call.url.includes('/batches'))!.url).searchParams.get('branch_id')).toBe('1');
  });

  it('says there is no open batch and offers to open one', async () => {
    mockFetch(routes({}, []));
    renderPage(<BatchesView />);
    expect(await screen.findByText("Ochiq qozon yo'q")).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Qozon ochish' }).length).toBeGreaterThan(0);
  });

  it('asks for the closed ones too when asked', async () => {
    const { calls } = mockFetch(routes({}, [batch({ id: 43, is_open: false, closed_at: '2026-10-21T15:00:00+05:00' })]));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('switch', { name: "Yopilganlarni ko'rsatish" }));
    expect(await screen.findByText('Yopilgan')).toBeInTheDocument();
    expect(calls.some((call) => new URL(call.url).searchParams.get('include_closed') === '1')).toBe(true);
    // a closed batch has no actions
    expect(screen.queryByRole('button', { name: /To'ldirish/ })).not.toBeInTheDocument();
  });

  it('opens a batch of a dish with a quantity and a note', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/batches': () => json({ success: true, message: 'ok', data: batch({ id: 44 }) }, 201) }, []));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click((await screen.findAllByRole('button', { name: 'Qozon ochish' }))[0]!);
    const dialog = await screen.findByRole('dialog');

    // the dishes sold by batch come first
    const options = within(dialog).getAllByRole('option').map((option) => option.textContent);
    expect(options.slice(0, 2)).toEqual(['Taomni tanlang', "Osh (Qozon bo'yicha)"]);

    await user.click(within(dialog).getByRole('button', { name: 'Qozon ochish' }));
    expect(await within(dialog).findAllByText("Maydonni to'ldiring.")).toHaveLength(2);

    await user.selectOptions(within(dialog).getByLabelText('Taom'), "Lag'mon");
    await user.selectOptions(within(dialog).getByLabelText('Variant'), 'Katta');
    await user.type(within(dialog).getByLabelText('Qancha tayyorlandi'), '25,5');
    await user.type(within(dialog).getByLabelText('Izoh (ixtiyoriy)'), 'Kechki qozon');
    await user.click(within(dialog).getByRole('button', { name: 'Qozon ochish' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ branch_id: 1, product_id: 8, product_variant_id: 22, quantity: 25.5, label: 'Kechki qozon' });
  });

  it("shows the server's message when a batch cannot be opened", async () => {
    mockFetch(routes({ 'POST /api/v1/owner/batches': () => json({ message: "Bu taom uchun ochiq qozon allaqachon bor.", errors: { product_id: ["Bu taom uchun ochiq qozon allaqachon bor."] } }, 422) }, []));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click((await screen.findAllByRole('button', { name: 'Qozon ochish' }))[0]!);
    const dialog = await screen.findByRole('dialog');
    await user.selectOptions(within(dialog).getByLabelText('Taom'), "Osh (Qozon bo'yicha)");
    await user.type(within(dialog).getByLabelText('Qancha tayyorlandi'), '30');
    await user.click(within(dialog).getByRole('button', { name: 'Qozon ochish' }));
    expect((await within(dialog).findAllByText('Bu taom uchun ochiq qozon allaqachon bor.')).length).toBeGreaterThan(0);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('tops a batch up', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/batches/41/adjust': () => ok(batch({ quantity_left: 17 })) }));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /To'ldirish/ }));
    const dialog = await screen.findByRole('dialog');
    expect(dialog).toHaveTextContent('hozir 12 qoldi');
    await user.type(within(dialog).getByLabelText("Qancha qo'shildi"), '5');
    await user.type(within(dialog).getByLabelText('Sabab (ixtiyoriy)'), "Yana guruch qo'shildi");
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ add: 5, reason: "Yana guruch qo'shildi" });
  });

  it('corrects what is left', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/batches/41/adjust': () => ok(batch({ quantity_left: 9 })) }));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /Qoldiqni to'g'rilash/ }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText("Maydonni to'ldiring.")).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Haqiqiy qoldiq'), '9');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ quantity_left: 9 });
  });

  it('closes a batch after a confirmation', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/batches/41/close': () => ok(batch({ is_open: false })) }));
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /Yopish/ }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('«Osh» qozoni yopiladi');
    await user.click(within(dialog).getByRole('button', { name: 'Yopish' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(calls.some((call) => call.method === 'POST' && call.url.endsWith('/close'))).toBe(true);
  });

  it('gives an overview of every branch and works on the one tapped', async () => {
    const { calls } = mockFetch(
      routes({
        'GET /api/v1/owner/branches': () => ok([branch(), branch({ id: 2, name: 'Yunusobod' })]),
        'GET /api/v1/owner/batches': (request: Request) => {
          const id = Number(new URL(request.url).searchParams.get('branch_id'));
          return ok({ branch_id: id, batches: id === 1 ? [batch()] : [batch({ id: 50, branch_id: 2, product_name: 'Sho\'rva' }), batch({ id: 51, branch_id: 2, quantity_left: 0 })] });
        },
      }),
    );
    renderPage(<BatchesView />);
    const user = userEvent.setup();
    const overview = await screen.findByRole('region', { name: 'Filiallar bo\'yicha qozonlar' });
    await waitFor(() => expect(within(overview).getByRole('button', { name: /Yunusobod/ })).toHaveTextContent('2 ta ochiq qozon · 1 tasi tugagan'));
    expect(within(overview).getByRole('button', { name: /Chilonzor/ })).toHaveTextContent('1 ta ochiq qozon');

    await user.click(within(overview).getByRole('button', { name: /Yunusobod/ }));
    expect((await screen.findAllByText("Sho'rva")).length).toBeGreaterThan(0);
    expect(calls.some((call) => new URL(call.url).searchParams.get('branch_id') === '2')).toBe(true);
  });

  it('only shows the batches to someone without batches.manage', async () => {
    mockFetch(routes({ 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']) }));
    renderPage(<BatchesView />);
    await screen.findAllByText('Osh');
    expect(screen.queryByRole('button', { name: /Qozon ochish|To'ldirish|Yopish/ })).not.toBeInTheDocument();
  });

  describe('next batch and write-off (P6-15)', () => {
    const plan = (over: Record<string, unknown> = {}) => ({
      id: 5,
      uuid: 'p-5',
      branch_id: 1,
      product_id: 7,
      product_variant_id: null,
      product_name: 'Osh',
      variant_name: null,
      planned_at: '2026-11-14T07:30:00Z',
      time: '12:30',
      quantity: 12,
      label: null,
      status: 'planned',
      overdue: false,
      ...over,
    });
    const withPlans = (modules: { batches: boolean; batch_writeoff: boolean }, plans: unknown[] = []) => ({ 'GET /api/v1/owner/batch-plans': () => ok({ branch_id: 1, modules, plans }) });

    it('lists the planned batches and cancels one', async () => {
      const { calls } = mockFetch(routes({ ...withPlans({ batches: true, batch_writeoff: false }, [plan()]), 'DELETE /api/v1/owner/batch-plans/5': () => ok(null) }));
      renderPage(<BatchesView />);
      const user = userEvent.setup();
      const section = await screen.findByRole('region', { name: 'Keyingi partiya' });
      expect(await within(section).findByText('12:30')).toBeInTheDocument();
      expect(section).toHaveTextContent('12 porsiya');
      await user.click(within(section).getByRole('button', { name: 'Rejani bekor qilish' }));
      await waitFor(() => expect(calls.some((call) => call.method === 'DELETE' && call.url.endsWith('/batch-plans/5'))).toBe(true));
    });

    it('plans the next batch of a dish at a time', async () => {
      const { calls } = mockFetch(routes({ ...withPlans({ batches: true, batch_writeoff: false }), 'POST /api/v1/owner/batch-plans': () => json({ success: true, message: 'ok', data: plan() }, 201) }));
      renderPage(<BatchesView />);
      const user = userEvent.setup();
      await user.click(await screen.findByRole('button', { name: 'Keyingi partiya vaqti' }));
      const dialog = await screen.findByRole('dialog');
      await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
      expect((await within(dialog).findAllByText("Maydonni to'ldiring.")).length).toBe(2);
      await user.selectOptions(within(dialog).getByLabelText('Taom'), "Osh (Qozon bo'yicha)");
      await user.type(within(dialog).getByLabelText('Soat'), '12:30');
      await user.type(within(dialog).getByLabelText('Qancha tayyorlanadi (ixtiyoriy)'), '12');
      await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ branch_id: 1, product_id: 7, product_variant_id: null, time: '12:30', quantity: 12, label: null });
    });

    it('writes off unsold portions with a reason, once per attempt', async () => {
      const { calls } = mockFetch(routes({ ...withPlans({ batches: true, batch_writeoff: true }), 'POST /api/v1/owner/batches/41/write-off': () => json({ success: true, message: 'ok', data: { write_off: { uuid: 'w', quantity: 4, unit: 'piece', reason: 'spoiled' } } }, 201) }));
      renderPage(<BatchesView />);
      const user = userEvent.setup();
      await user.click(await screen.findByRole('button', { name: /Hisobdan chiqarish/ }));
      const dialog = await screen.findByRole('dialog');
      expect(dialog).toHaveTextContent('hozir 12 qoldi');

      // more than is left, and "other" without a note, are refused before the request
      await user.type(within(dialog).getByLabelText('Qancha chiqariladi'), '13');
      await user.selectOptions(within(dialog).getByLabelText('Sabab'), 'Boshqa sabab');
      await user.click(within(dialog).getByRole('button', { name: 'Hisobdan chiqarish' }));
      expect(await within(dialog).findByText('Partiyada faqat 12 qolgan.')).toBeInTheDocument();
      expect(within(dialog).getByText('«Boshqa sabab» tanlansa, izoh yozing.')).toBeInTheDocument();
      expect(calls.some((call) => call.method === 'POST')).toBe(false);

      await user.clear(within(dialog).getByLabelText('Qancha chiqariladi'));
      await user.type(within(dialog).getByLabelText('Qancha chiqariladi'), '4');
      await user.selectOptions(within(dialog).getByLabelText('Sabab'), 'Buzildi');
      await user.click(within(dialog).getByRole('button', { name: 'Hisobdan chiqarish' }));
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      const post = calls.find((call) => call.method === 'POST')!;
      expect(await post.json()).toEqual({ quantity: 4, reason: 'spoiled', note: null, close: false });
      expect(post.headers.get('Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
    });

    it('writes the rest off and closes the batch in one step', async () => {
      const { calls } = mockFetch(routes({ ...withPlans({ batches: true, batch_writeoff: true }), 'POST /api/v1/owner/batches/41/write-off': () => json({ success: true, message: 'ok', data: { write_off: { uuid: 'w', quantity: 12, unit: 'piece', reason: 'unsold' } } }, 201) }));
      renderPage(<BatchesView />);
      const user = userEvent.setup();
      await user.click(await screen.findByRole('button', { name: /Hisobdan chiqarish/ }));
      const dialog = await screen.findByRole('dialog');
      await user.click(within(dialog).getByRole('button', { name: 'Hammasini chiqarish va yopish' }));
      await user.click(within(dialog).getByRole('button', { name: 'Hisobdan chiqarish' }));
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
      expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ quantity: 12, reason: 'unsold', note: null, close: true });
    });

    it('has no write-off button where the format lacks the write-off module', async () => {
      mockFetch(routes());
      renderPage(<BatchesView />);
      await screen.findAllByText('Osh');
      expect(screen.queryByRole('button', { name: /Hisobdan chiqarish/ })).not.toBeInTheDocument();
    });

    it('hides the planning where the format has no batches module', async () => {
      mockFetch(routes({ 'GET /api/v1/owner/batch-plans': () => json({ success: false, message: 'Modul o\'chirilgan', code: 'module_disabled' }, 403) }));
      renderPage(<BatchesView />);
      await screen.findAllByText('Osh');
      expect(screen.queryByRole('region', { name: 'Keyingi partiya' })).not.toBeInTheDocument();
    });
  });
});
