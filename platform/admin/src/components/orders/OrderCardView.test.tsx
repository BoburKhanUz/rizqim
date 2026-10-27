import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPlatformOps, setPlatformOps } from '@/lib/realtime/platformOps';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { card, NOW, refundRequest } from './fixtures';
import { OrderCardView } from './OrderCardView';

const UUID = '01a17dcb-0000-7000-8000-000000000001';
const PERMS = ['orders.view', 'orders.intervene', 'orders.refund_request', 'orders.refund_approve'].map((name) => `admin.${name}`);

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(NOW);
  setPlatformOps(createPlatformOps({ loadConfig: async () => null, createEcho: async () => Promise.reject(new Error('off')) }));
});
afterEach(() => {
  setPlatformOps(null);
  vi.useRealTimers();
});

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = PERMS) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    [`GET /orders/${UUID}`]: () => ok(card()),
    ...routes,
  });
  renderPage(<OrderCardView uuid={UUID} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('OrderCardView', () => {
  it('shows the history with who did it, the SLA timeline, items, money, people, fiscal receipt and audit', async () => {
    setup();
    expect(await screen.findByRole('heading', { name: 'Buyurtma #ORD-AB12CD' })).toBeInTheDocument();
    const history = screen.getByRole('region', { name: 'Holatlar tarixi' });
    expect(within(history).getByText('Operator 1 (RIZQIM jamoasi)')).toBeInTheDocument();
    expect(within(history).getByText('Menejer 1 (Menejer)')).toBeInTheDocument();
    expect(within(history).getByText('Tizim')).toBeInTheDocument();
    const sla = screen.getByRole('region', { name: "SLA vaqt chizig'i" });
    expect(within(sla).getByText('Tayyorlash: 38 daqiqada, chegarasi 30 daq')).toBeInTheDocument();
    expect(within(sla).getByText('8 daqiqa kechikdi')).toBeInTheDocument();
    expect(screen.getByText(/2 × Osh \(Katta\) — Qazi/)).toBeInTheDocument();
    expect(screen.getByText("Navro'za Karimova · +998 93 *** ** 77")).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Chekni ochish/ })).toHaveAttribute('href', 'https://ofd.soliq.uz/check?t=1');
    expect(within(screen.getByRole('region', { name: 'Jurnal' })).getByText('admin.orders.status_fixed')).toBeInTheDocument();
  });

  it('messages the store with a reason', async () => {
    const { user, callsTo } = setup({ [`POST /orders/${UUID}/message`]: () => ok({ recipients: 2 }) });
    await user.click(await screen.findByRole('button', { name: 'Ovqatxonaga yozish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Ovqatxonaga xabar' });
    await user.type(within(dialog).getByLabelText('Xabar matni'), 'Buyurtmani qabul qiling');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Kechikmoqda');
    await user.click(within(dialog).getByRole('button', { name: 'Yuborish' }));
    await vi.waitFor(() => expect(callsTo('POST', `/orders/${UUID}/message`)[0]?.body).toEqual({ to: 'store', text: 'Buyurtmani qabul qiling', reason: 'Kechikmoqda' }));
    expect(await screen.findByText('Xabar yuborildi.')).toBeInTheDocument();
  });

  it('cancels with a store-side reason and shows the refreshed card', async () => {
    const active = card({ status: 'preparing', status_label: 'Tayyorlanmoqda', actions: { ...card().actions, can_cancel: true, cancel_reasons: [{ value: 'out_of_stock', label: 'Mahsulot qolmagan' }, { value: 'other', label: 'Boshqa sabab' }], transitions: [{ status: 'ready', label: 'Tayyor' }] } });
    const { user, callsTo } = setup({
      [`GET /orders/${UUID}`]: () => ok(active),
      [`POST /orders/${UUID}/cancel`]: () => ok(card({ status: 'canceled', status_label: 'Bekor qilindi', cancel: { reason: 'other', reason_label: 'Boshqa sabab', note: null } })),
    });
    await user.click(await screen.findByRole('button', { name: 'Bekor qilish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Buyurtmani bekor qilish' });
    await user.selectOptions(within(dialog).getByLabelText('Bekor qilish sababi'), 'other');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Ovqatxona javob bermadi');
    await user.click(within(dialog).getByRole('button', { name: 'Ha, bekor qilish' }));
    await vi.waitFor(() => expect(callsTo('POST', `/orders/${UUID}/cancel`)[0]?.body).toEqual({ cancel_reason: 'other', reason: 'Ovqatxona javob bermadi' }));
    expect(await screen.findByText('Buyurtma bekor qilindi.')).toBeInTheDocument();
  });

  it('fixes the status to one the lifecycle allows, and says a refusal in words', async () => {
    const active = card({ status: 'preparing', status_label: 'Tayyorlanmoqda', actions: { ...card().actions, transitions: [{ status: 'ready', label: 'Tayyor' }] } });
    const { user } = setup({
      [`GET /orders/${UUID}`]: () => ok(active),
      [`POST /orders/${UUID}/status`]: () => refusal(422, "Buyurtmani «Tayyor» holatidan «Tayyor» holatiga o'tkazib bo'lmaydi.", { reason: 'stale' }, { code: 'stale' }),
    });
    await user.click(await screen.findByRole('button', { name: 'Holatni tuzatish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Holatni tuzatish' });
    expect(within(dialog).getByLabelText('Yangi holat')).toHaveValue('ready');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Oshxona tugmani bosmagan');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText(/holatiga o'tkazib bo'lmaydi/)).toBeInTheDocument();
  });

  it('asks for a refund with an Idempotency-Key and the amount checked against what is refundable', async () => {
    const { user, fetchMock, callsTo } = setup({ [`POST /orders/${UUID}/refund-requests`]: () => ok(refundRequest({ mine: true, can_decide: false }), {}) });
    await user.click(await screen.findByRole('button', { name: "Pulni qaytarishni so'rash" }));
    const dialog = await screen.findByRole('dialog', { name: "Pulni qaytarishni so'rash" });
    const amount = within(dialog).getByLabelText("Summa (so'm)");
    expect(amount).toHaveValue('85000');
    await user.clear(amount);
    await user.type(amount, '90000');
    expect(within(dialog).getByText(/oshmasligi kerak/)).toBeInTheDocument();
    await user.clear(amount);
    await user.type(amount, '50 000');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Taom sovuq keldi');
    await user.click(within(dialog).getByRole('button', { name: "So'rov yuborish" }));
    await vi.waitFor(() => expect(callsTo('POST', `/orders/${UUID}/refund-requests`)[0]?.body).toEqual({ amount: '50000', reason: 'Taom sovuq keldi' }));
    const init = fetchMock.mock.calls.find(([input]) => String(input).includes('refund-requests'))?.[1];
    expect(new Headers(init?.headers).get('Idempotency-Key')).toMatch(/^[\x21-\x7E]{8,}$/);
    expect(await screen.findByText("So'rov yuborildi. Tasdiq kutilmoqda.")).toBeInTheDocument();
  });

  it("shows the requester 'Tasdiq kutilmoqda' and lets another member approve", async () => {
    const mine = card({ refunds: { paid: '85000.00', refunded: '0.00', refundable: '85000.00', requests: [refundRequest({ id: 7, mine: true, can_decide: false })] } });
    setup({ [`GET /orders/${UUID}`]: () => ok(mine) });
    const item = await screen.findByTestId('refund-7');
    expect(within(item).getByText('Tasdiq kutilmoqda: uni boshqa xodim tasdiqlaydi')).toBeInTheDocument();
    expect(within(item).queryByRole('button', { name: 'Tasdiqlash' })).not.toBeInTheDocument();
  });

  it('approves a refund request: four eyes decided by the backend, the manual part said in words', async () => {
    const pending = card({ refunds: { paid: '85000.00', refunded: '0.00', refundable: '85000.00', requests: [refundRequest()] } });
    const { user, callsTo } = setup({
      [`GET /orders/${UUID}`]: () => ok(pending),
      'POST /refund-requests/7/approve': () => ok(refundRequest({ status: 'approved', status_label: 'Tasdiqlandi', can_decide: false, execution: { mode: 'manual', order_status_before: 'delivered', order_status_after: 'delivered', payment_method: 'payme', automatic_amount: '0.00', manual_amount: '50000.00', provider_refund: 'manual' } })),
    });
    const item = await screen.findByTestId('refund-7');
    await user.click(within(item).getByRole('button', { name: 'Tasdiqlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni tasdiqlash' });
    expect(within(dialog).getByText(/Click, Payme va naqd pulni siz/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Shikoyat tasdiqlandi');
    await user.click(within(dialog).getByRole('button', { name: 'Tasdiqlash' }));
    await vi.waitFor(() => expect(callsTo('POST', '/refund-requests/7/approve')[0]?.body).toEqual({ reason: 'Shikoyat tasdiqlandi' }));
    expect(await screen.findByText('Tasdiqlandi.')).toBeInTheDocument();
  });

  it('is calm about an order that is not there and about a view-only member', async () => {
    setup({ [`GET /orders/${UUID}`]: () => refusal(404, 'Buyurtma topilmadi.', { reason: 'not_found' }, { code: 'not_found' }) });
    expect(await screen.findByText('Buyurtma topilmadi')).toBeInTheDocument();
  });

  it('offers no action buttons to a viewer', async () => {
    setup({ [`GET /orders/${UUID}`]: () => ok(card({ actions: { can_intervene: false, can_cancel: false, cancel_reasons: [], transitions: [], message_store: false, message_customer: false, can_request_refund: false, can_approve_refund: false } })) }, ['admin.orders.view']);
    expect(await screen.findByText("Sizda faqat ko'rish ruxsati bor.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Ovqatxonaga yozish' })).not.toBeInTheDocument();
  });
});
