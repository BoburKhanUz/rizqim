import type { ReactElement } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StaffApiProvider, type StaffOrder, type StaffOrderDetail } from '@rizqim/staff';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '../AuthProvider';
import { I18nProvider } from '../I18nProvider';
import { ApiConfigContext } from '@/lib/apiConfig';
import { getStaffApi, setSession } from '@/lib/session/client';
import { API, json, mockFetch } from '@/test/helpers';
import { OrdersBoard } from './OrdersBoard';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), usePathname: () => '/orders' }));

beforeEach(() => window.localStorage.clear());
afterEach(() => setSession(null));

const OWNER = ['orders.view', 'orders.manage', 'orders.cancel', 'orders.assign_courier', 'kitchen.manage'];
const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: 'ok', data, ...extra });
const page = (orders: unknown[], next: string | null = null) => ok(orders, { meta: { next_cursor: next, per_page: 100 } });

let id = 0;
function makeOrder(over: Partial<StaffOrder> = {}): StaffOrder {
  id += 1;
  return {
    uuid: `00000000-0000-4000-8000-${String(id).padStart(12, '0')}`,
    short_code: `A${100 + id}`,
    status: 'new',
    status_label: 'Yangi',
    type: 'delivery',
    channel: 'telegram',
    store_id: 1,
    branch_id: 2,
    branch_name: 'Chilonzor',
    payment_method: 'cash',
    payment_status: 'pending',
    subtotal: '40000.00',
    discount: '0.00',
    delivery_price: '5000.00',
    service_charge_amount: '0.00',
    place_fee_amount: '0.00',
    total_price: '45000.00',
    notes: null,
    guests_count: null,
    hall_ref: null,
    table_ref: null,
    cancel_reason: null,
    cancel_note: null,
    customer: { name: 'Aziz', phone: '+998901234567' },
    delivery: { address: 'Chilonzor 9', latitude: null, longitude: null, zone_name: 'Markaz' },
    courier: null,
    items_total: 1,
    items_ready: 0,
    items: [
      {
        id: id * 10,
        product_name: 'Osh',
        variant_name: null,
        quantity: 1.5,
        unit: 'kg',
        quantity_label: '1.5 kg',
        modifiers: [{ name: 'Qazi', quantity: 1 }],
        kitchen_status: 'pending',
        ready_at: null,
        total_price: '40000.00',
      },
    ],
    created_at: new Date(Date.now() - 2 * 60_000).toISOString(),
    updated_at: new Date().toISOString(),
    accepted_at: null,
    preparing_at: null,
    ready_at: null,
    closed_at: null,
    waiting_seconds: 120,
    ...over,
  };
}

const detailOf = (order: StaffOrder): StaffOrderDetail => ({
  ...order,
  status_history: [{ from_status: null, status: 'new', created_at: order.created_at }],
});

function renderBoard(permissions = OWNER, ui: ReactElement = <OrdersBoard />) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(
    <I18nProvider language="uz">
      <ApiConfigContext.Provider value={{ apiBaseUrl: API }}>
        <StaffApiProvider api={getStaffApi(API)}>
          <QueryClientProvider client={queryClient}>
            <AuthProvider apiBaseUrl={API}>{ui}</AuthProvider>
          </QueryClientProvider>
        </StaffApiProvider>
      </ApiConfigContext.Provider>
    </I18nProvider>,
  );
  return { permissions, queryClient };
}

const signIn = (permissions: string[]) => ({
  'POST /api/session/refresh': () => json({ access_token: 'access', expires_at: new Date(Date.now() + 900_000).toISOString(), user: { id: 1, name: 'Aziz', role: 'owner' } }),
  'GET /api/v1/me/permissions': () => json({ success: true, message: 'ok', data: { role: 'owner', store_id: 1, branch_id: null, branch_scoped: false, permissions, device_uuid: null, timezone: 'Asia/Tashkent' } }),
});

// GET /staff/orders answered by scope: `active` and `history` lists
function serveOrders(active: StaffOrder[], finished: StaffOrder[] = []) {
  return (request: Request) => (new URL(request.url).searchParams.get('scope') === 'history' ? page(finished) : page(active));
}

const column = (name: string) => screen.getByRole('region', { name });

describe('the orders board', () => {
  it('lays the orders out in columns, oldest first, with today’s finished ones last', async () => {
    const late = makeOrder({ created_at: new Date(Date.now() - 12 * 60_000).toISOString() });
    const fresh = makeOrder();
    const cooking = makeOrder({ status: 'preparing', items_total: 3, items_ready: 1 });
    const ready = makeOrder({ status: 'ready', type: 'pickup' });
    const away = makeOrder({ status: 'on_the_way', courier: { id: 9, name: 'Bobur', phone: null } });
    const done = makeOrder({ status: 'delivered', closed_at: new Date().toISOString() });
    const canceled = makeOrder({ status: 'canceled', closed_at: new Date().toISOString() });
    const { calls } = mockFetch({ ...signIn(OWNER), 'GET /api/v1/staff/orders': serveOrders([fresh, late, cooking, ready, away], [done, canceled]) });
    renderBoard();

    await screen.findByText(`#${late.short_code}`);
    const codes = (name: string) => within(column(name)).queryAllByRole('article').map((card) => card.getAttribute('aria-label'));
    expect(codes('Yangi')).toEqual([`#${late.short_code}`, `#${fresh.short_code}`]);
    expect(codes('Tayyorlanmoqda')).toEqual([`#${cooking.short_code}`]);
    expect(codes('Tayyor')).toEqual([`#${ready.short_code}`]);
    expect(codes("Yo'lda")).toEqual([`#${away.short_code}`]);
    expect(codes('Bugun yakunlangan')).toHaveLength(2);

    // Counts, waiting time, items with weights, kitchen progress, courier
    expect(within(column('Yangi')).getByLabelText('2 ta')).toHaveTextContent('2');
    expect(within(column('Yangi')).getByText('12 daq').closest('[data-tone]')).toHaveAttribute('data-tone', 'late');
    expect(within(column('Tayyorlanmoqda')).getByText('Oshxona: 1/3')).toBeInTheDocument();
    expect(within(column('Yangi')).getAllByText('1.5 kg')).toHaveLength(2);
    expect(within(column("Yo'lda")).getByText('Kuryer: Bobur')).toBeInTheDocument();

    const query = (scope: string) => calls.map((call) => new URL(call.url)).find((url) => url.pathname === '/api/v1/staff/orders' && url.searchParams.get('scope') === scope)!.searchParams;
    expect(query('active').get('per_page')).toBe('100');
    expect(query('history').get('date')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('accepts an order from its card with the staff status endpoint', async () => {
    const user = userEvent.setup();
    const order = makeOrder();
    let accepted = false;
    const { calls } = mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': (request) => serveOrders(accepted ? [{ ...order, status: 'accepted' }] : [order])(request),
      [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => {
        accepted = true;
        return ok({ ...order, status: 'accepted' });
      },
    });
    renderBoard();
    await user.click(await within(await screen.findByRole('article', { name: `#${order.short_code}` })).findByRole('button', { name: 'Qabul qilish' }));
    const post = calls.find((call) => call.method === 'POST' && call.url.endsWith('/status'))!;
    expect(await post.json()).toEqual({ status: 'accepted' });
    expect(await screen.findByText(`#${order.short_code}: Qabul qilindi`)).toBeInTheDocument();
    await waitFor(() => expect(within(column('Tayyorlanmoqda')).getByRole('article', { name: `#${order.short_code}` })).toBeInTheDocument());
  });

  it('rejects with a reason, and shows the server refusal in words', async () => {
    const user = userEvent.setup();
    const order = makeOrder();
    let attempts = 0;
    const { calls } = mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': serveOrders([order]),
      [`GET /api/v1/staff/orders/${order.uuid}`]: () => ok(detailOf(order)),
      [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => {
        attempts += 1;
        return attempts === 1
          ? json({ success: false, message: "Buyurtma allaqachon qabul qilingan, rad etib bo'lmaydi" }, 422)
          : ok({ ...order, status: 'rejected' });
      },
    });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: `#${order.short_code} buyurtmani ochish` }));
    const drawer = await screen.findByRole('dialog', { name: `Buyurtma #${order.short_code}` });
    await user.click(within(drawer).getByRole('button', { name: 'Rad etish' }));

    const dialog = await screen.findByRole('alertdialog', { name: 'Buyurtmani rad etish' });
    // No reason yet: asked for, nothing sent
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    expect(within(dialog).getByText('Sababni tanlang.')).toBeInTheDocument();
    // Only the reasons the API takes for a new delivery order (no "no_show")
    expect(within(dialog).queryByLabelText(/kelmadi/i)).not.toBeInTheDocument();
    await user.click(within(dialog).getByLabelText('Mahsulot qolmagan'));
    await user.type(within(dialog).getByLabelText('Izoh (ixtiyoriy)'), 'Osh tugadi');
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent("Buyurtma allaqachon qabul qilingan, rad etib bo'lmaydi");
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    const posts = calls.filter((call) => call.method === 'POST');
    expect(await posts[1]!.json()).toEqual({ status: 'rejected', reason: 'out_of_stock', note: 'Osh tugadi' });
  });

  it('says a 403 in its own words', async () => {
    const user = userEvent.setup();
    const order = makeOrder({ status: 'accepted' });
    mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': serveOrders([order]),
      [`POST /api/v1/staff/orders/${order.uuid}/status`]: () => json({ success: false, message: 'Ruxsat etilmagan', data: [] }, 403),
    });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: 'Tayyorlashni boshlash' }));
    expect(await screen.findByRole('alert')).toHaveTextContent("Bu amal uchun ruxsatingiz yo'q.");
  });

  it('assigns a courier to a ready delivery order', async () => {
    const user = userEvent.setup();
    const order = makeOrder({ status: 'ready' });
    const { calls } = mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': serveOrders([order]),
      'GET /api/v1/couriers': () => ok([{ id: 9, name: 'Bobur', phone: '+998901112233', telegram_username: null, branch_id: 2, courier_type: 'own' }]),
      [`POST /api/v1/orders/${order.uuid}/assign-courier`]: () => ok({ uuid: order.uuid, status: 'courier_assigned' }),
    });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: 'Kuryerni tayinlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Kuryerni tayinlash' });
    await user.click(await within(dialog).findByLabelText(/Bobur/));
    await user.click(within(dialog).getByRole('button', { name: 'Tayinlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Kuryerni tayinlash' })).not.toBeInTheDocument());
    const couriers = calls.find((call) => call.url.includes('/couriers'))!;
    expect(new URL(couriers.url).searchParams.get('branch_id')).toBe('2');
    expect(await calls.find((call) => call.url.endsWith('/assign-courier'))!.json()).toEqual({ courier_id: 9 });
  });

  it('offers only what the permissions open (a cook starts and finishes cooking)', async () => {
    const fresh = makeOrder();
    const accepted = makeOrder({ status: 'accepted' });
    mockFetch({ ...signIn(['orders.view', 'kitchen.manage']), 'GET /api/v1/staff/orders': serveOrders([fresh, accepted]) });
    renderBoard();
    await screen.findByRole('button', { name: 'Tayyorlashni boshlash' });
    expect(within(column('Yangi')).queryByRole('button', { name: 'Qabul qilish' })).not.toBeInTheDocument();
  });

  it('shows the detail: items with modifiers and weights, note, customer, money and timeline', async () => {
    const user = userEvent.setup();
    const order = makeOrder({ notes: "Eshik oldida qo'ng'iroq qiling", discount: '5000.00' });
    mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': serveOrders([order]),
      [`GET /api/v1/staff/orders/${order.uuid}`]: () =>
        ok({ ...detailOf(order), status_history: [{ from_status: null, status: 'new', created_at: order.created_at }, { from_status: 'new', status: 'accepted', created_at: order.created_at }] }),
    });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: `#${order.short_code} buyurtmani ochish` }));
    const drawer = await screen.findByRole('dialog', { name: `Buyurtma #${order.short_code}` });
    expect(within(drawer).getByText('Qazi')).toBeInTheDocument();
    expect(within(drawer).getByText('1.5 kg')).toBeInTheDocument();
    expect(within(drawer).getByText("Eshik oldida qo'ng'iroq qiling")).toBeInTheDocument();
    expect(within(drawer).getByRole('link', { name: "Qo'ng'iroq qilish: +998901234567" })).toHaveAttribute('href', 'tel:+998901234567');
    expect(within(drawer).getByText('Zona: Markaz')).toBeInTheDocument();
    expect(within(drawer).getByText("−5 000 so'm")).toBeInTheDocument();
    // The status timeline comes with the detail
    expect(await within(drawer).findByText('Qabul qilindi')).toBeInTheDocument();
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('sends the search and the filters to the API', async () => {
    const user = userEvent.setup();
    const { calls } = mockFetch({ ...signIn(OWNER), 'GET /api/v1/staff/orders': serveOrders([]) });
    renderBoard();
    await screen.findByRole('region', { name: 'Yangi' });
    await user.type(screen.getByLabelText('Buyurtma raqami'), '#a10');
    await user.selectOptions(screen.getByLabelText('Turi'), 'pickup');
    await user.selectOptions(screen.getByLabelText('Kanal'), 'pos_hall');
    await waitFor(() => {
      const last = calls.map((call) => new URL(call.url).searchParams).filter((params) => params.get('scope') === 'active').at(-1)!;
      expect([last.get('q'), last.get('type'), last.get('channel')]).toEqual(['a10', 'pickup', 'pos_hall']);
    });
    expect(await screen.findByText("Bu filtrlar bo'yicha buyurtma topilmadi")).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Filtrlarni tozalash' }));
    expect(screen.getByLabelText('Buyurtma raqami')).toHaveValue('');
  });

  it('collapses the finished column and remembers it', async () => {
    const user = userEvent.setup();
    mockFetch({ ...signIn(OWNER), 'GET /api/v1/staff/orders': serveOrders([], [makeOrder({ status: 'delivered' })]) });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: "Bugun yakunlangan ustunini yig'ish" }));
    expect(screen.getByRole('button', { name: 'Bugun yakunlangan ustunini ochish' })).toHaveAttribute('aria-expanded', 'false');
    expect(window.localStorage.getItem('rz_board_done_collapsed')).toBe('1');
  });

  it('pages today’s finished orders', async () => {
    const user = userEvent.setup();
    const [a, b] = [makeOrder({ status: 'delivered' }), makeOrder({ status: 'canceled' })];
    mockFetch({
      ...signIn(OWNER),
      'GET /api/v1/staff/orders': (request) => {
        const params = new URL(request.url).searchParams;
        if (params.get('scope') === 'active') return page([]);
        return params.get('cursor') ? page([b]) : page([a], 'next');
      },
    });
    renderBoard();
    await user.click(await screen.findByRole('button', { name: "Yana ko'rsatish" }));
    await waitFor(() => expect(within(column('Bugun yakunlangan')).getAllByRole('article')).toHaveLength(2));
  });

  it('offers a retry when the board cannot load', async () => {
    mockFetch({ ...signIn(OWNER), 'GET /api/v1/staff/orders': () => json({ message: 'boom' }, 500) });
    renderBoard();
    expect(await screen.findByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
    await act(async () => {});
  });
});
