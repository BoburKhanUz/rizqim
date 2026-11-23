import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPlatformOps, setPlatformOps } from '@/lib/realtime/platformOps';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { card, NOW, refundRequest, storeRefundReview } from './fixtures';
import { OrderCardView } from './OrderCardView';
import { exactMoney } from './ordersModel';

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

  it('shows refund money to the tiyin and keeps a partly approved payment flagged', async () => {
    const partly = card({
      status: 'canceled',
      status_label: 'Bekor qilindi',
      status_group: 'canceled',
      manual_refund_required: true,
      payments: [{ uuid: 'p1', provider: 'payme', amount: '50000.50', status: 'paid', paid_at: null, manual_refund_required: true, manual_refund_approved: [{ refund_request_id: 7, amount: '10000.25', approved_at: null }], created_at: null }],
      refunds: { paid: '50000.50', refunded: '10000.25', refundable: '40000.25', requests: [refundRequest({ amount: '10000.25', can_decide: false, status: 'approved', status_label: 'Tasdiqlandi' }), refundRequest({ id: 8, amount: '40000.25' })] },
    });
    const { user } = setup({ [`GET /orders/${UUID}`]: () => ok(partly) });
    expect(await screen.findByText("Qo'lda qaytarilishi kerak")).toBeInTheDocument();
    expect(screen.getByText(/10.000,25.so'm qo'lda qaytarish tasdiqlangan/)).toBeInTheDocument();
    expect(screen.getAllByText(/^40.000,25.so'm$/).length).toBeGreaterThanOrEqual(2); // refundable and the pending request
    expect(screen.getAllByText(/^50.000,50.so'm$/).length).toBeGreaterThanOrEqual(2); // paid and the payment

    await user.click(within(screen.getByTestId('refund-8')).getByRole('button', { name: 'Tasdiqlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni tasdiqlash' });
    expect(within(dialog).getByText(/^40.000,25.so'm\./)).toBeInTheDocument();
    expect(within(dialog).getByText(/darhol mijozning keshbek balansiga qaytadi/)).toBeInTheDocument();
  });

  it('marks a Paylov payment Paylov has not confirmed yet, and its refund through Paylov', async () => {
    const paylov = card({
      payments: [
        { uuid: 'p1', provider: 'paylov', amount: '50000.00', status: 'pending', paid_at: null, manual_refund_required: false, manual_refund_approved: [], confirmation: 'unconfirmed', provider_refund: null, created_at: null },
        { uuid: 'p2', provider: 'paylov', amount: '50000.00', status: 'paid', paid_at: null, manual_refund_required: false, manual_refund_approved: [], confirmation: 'confirmed', provider_refund: 'retrying', created_at: null },
      ],
    });
    setup({ [`GET /orders/${UUID}`]: () => ok(paylov) });
    expect(await screen.findByText('Paylov tasdiqlamagan')).toBeInTheDocument();
    expect(screen.getByText('Paylov orqali qaytarilmoqda')).toBeInTheDocument();
  });

  it('is calm about an order that is not there and about a view-only member', async () => {
    setup({ [`GET /orders/${UUID}`]: () => refusal(404, 'Buyurtma topilmadi.', { reason: 'not_found' }, { code: 'not_found' }) });
    expect(await screen.findByText('Buyurtma topilmadi')).toBeInTheDocument();
  });

  it('offers no action buttons to a viewer', async () => {
    setup({ [`GET /orders/${UUID}`]: () => ok(card({ actions: { can_intervene: false, can_cancel: false, cancel_reasons: [], transitions: [], message_store: false, message_customer: false, can_request_refund: false, can_approve_refund: false, can_decide_store_refund: false, can_approve_store_refund: false } })) }, ['admin.orders.view']);
    expect(await screen.findByText("Sizda faqat ko'rish ruxsati bor.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Ovqatxonaga yozish' })).not.toBeInTheDocument();
  });
});

// Wave P: a refund the store made from its own Payme cabinet on a delivered order keeps RIZQIM's commission until the team
// decides (POST /orders/{uuid}/store-refund-review/approve|dismiss: approving takes two members, dismissing one)
describe('OrderCardView: the store refund review', () => {
  const held = (overrides: Parameters<typeof storeRefundReview>[0] = {}, actions: Partial<ReturnType<typeof card>['actions']> = {}) => {
    const review = storeRefundReview(overrides);
    return card({
      payment_status: 'refunded',
      payments: [{ uuid: 'p1', provider: 'payme', amount: '85000.00', status: 'refunded', paid_at: null, manual_refund_required: false, manual_refund_approved: [], created_at: null }],
      refunds: { paid: '85000.00', refunded: '85000.00', refundable: '0.00', requests: [] },
      store_refund_review: review,
      store_refund_reviews: [review],
      audit: [
        { id: 12, action: 'admin.orders.store_refund_review.approved', actor: { id: 40, name: 'Operator 1', role: 'Moliya', team: true }, reason: 'Kabinetda tekshirdim', before: null, after: null, meta: { decision: 'proposed' }, created_at: new Date(NOW - 10 * 60_000).toISOString() },
      ],
      actions: { ...card().actions, can_request_refund: false, can_approve_refund: true, can_decide_store_refund: true, can_approve_store_refund: true, ...actions },
    });
  };
  const APPROVE = `/orders/${UUID}/store-refund-review/approve`;
  const DISMISS = `/orders/${UUID}/store-refund-review/dismiss`;
  const section = () => screen.findByRole('region', { name: 'Ovqatxona kassasidan qaytarish' });

  it('says plainly what happened and that the commission is kept until someone decides', async () => {
    setup({ [`GET /orders/${UUID}`]: () => ok(held()) });
    const block = await section();
    expect(within(block).getByText(/o'zining Payme kabinetidan mijozga qaytardi/)).toBeInTheDocument();
    expect(within(block).getByText(/komissiyasi qaror chiqarilguncha saqlanadi/)).toBeInTheDocument();
    expect(within(block).getByText('Qaror kutilmoqda')).toBeInTheDocument();
    expect(within(block).getAllByText(/^85.000.so'm$/).length).toBeGreaterThanOrEqual(1);
    expect(within(block).getByRole('button', { name: 'Qaytarishni taklif qilish' })).toBeInTheDocument();
    expect(within(block).getByRole('button', { name: 'Rad etish' })).toBeInTheDocument();
    expect(within(block).queryByRole('button', { name: 'Tasdiqlash' })).not.toBeInTheDocument();
  });

  it('proposes the refund with a reason and an Idempotency-Key, then shows it waits for another member', async () => {
    const proposed = held({ proposed_by: 1, proposed_at: new Date(NOW).toISOString(), proposal_note: 'Kabinetda tekshirdim' }, { can_approve_store_refund: false });
    let current = held();
    const { user, fetchMock, callsTo } = setup({ [`GET /orders/${UUID}`]: () => ok(current), [`POST ${APPROVE}`]: () => ok((current = proposed)) });
    await user.click(within(await section()).getByRole('button', { name: 'Qaytarishni taklif qilish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni taklif qilish' });
    expect(within(dialog).getByText(/boshqa xodim tasdiqlaganidan keyin/)).toBeInTheDocument();
    const submit = within(dialog).getByRole('button', { name: 'Taklif qilish' });
    expect(submit).toBeDisabled();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Kabinetda tekshirdim');
    await user.click(submit);
    await vi.waitFor(() => expect(callsTo('POST', APPROVE)[0]?.body).toEqual({ reason: 'Kabinetda tekshirdim' }));
    const init = fetchMock.mock.calls.find(([input]) => String(input).includes('store-refund-review/approve'))?.[1];
    expect(new Headers(init?.headers).get('Idempotency-Key')).toMatch(/^[\x21-\x7E]{8,}$/);
    expect(await screen.findByText('Qaytarish taklif qilindi: uni boshqa xodim tasdiqlashi kerak.')).toBeInTheDocument();
    const block = await section();
    expect(within(block).getByText(/Siz taklif qilgansiz/)).toBeInTheDocument();
    expect(within(block).queryByRole('button', { name: 'Tasdiqlash' })).not.toBeInTheDocument();
    expect(within(block).queryByRole('button', { name: 'Qaytarishni taklif qilish' })).not.toBeInTheDocument();
    expect(within(block).getByRole('button', { name: 'Rad etish' })).toBeInTheDocument();
  });

  it("lets a second member confirm a colleague's proposal, naming who proposed it", async () => {
    const proposed = held({ proposed_by: 40, proposed_at: new Date(NOW - 10 * 60_000).toISOString(), proposal_note: 'Kabinetda tekshirdim' });
    const review = storeRefundReview({ status: 'approved', proposed_by: 40, proposed_at: proposed.store_refund_review!.proposed_at, proposal_note: 'Kabinetda tekshirdim', decided_by: 1, decided_at: new Date(NOW).toISOString(), note: 'Ikkinchi tasdiq' });
    const approved = card({ ...proposed, status: 'refunded', status_label: 'Qaytarildi', store_refund_review: review, store_refund_reviews: [review], actions: { ...proposed.actions, can_decide_store_refund: false, can_approve_store_refund: false } });
    let current = proposed;
    const { user, callsTo } = setup({ [`GET /orders/${UUID}`]: () => ok(current), [`POST ${APPROVE}`]: () => ok((current = approved)) });
    const block = await section();
    expect(within(block).getByText(/Taklif qildi: Operator 1/)).toBeInTheDocument();
    expect(within(block).getByText(/Kabinetda tekshirdim/)).toBeInTheDocument();
    await user.click(within(block).getByRole('button', { name: 'Tasdiqlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni tasdiqlash (ikkinchi xodim)' });
    expect(within(dialog).getByText(/komissiya bekor qilinadi/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Ikkinchi tasdiq');
    await user.click(within(dialog).getByRole('button', { name: 'Tasdiqlash' }));
    await vi.waitFor(() => expect(callsTo('POST', APPROVE)[0]?.body).toEqual({ reason: 'Ikkinchi tasdiq' }));
    expect(await screen.findByText('Qaytarish tasdiqlandi: buyurtma qaytarildi, komissiya bekor qilindi.')).toBeInTheDocument();
    const after = await section();
    expect(within(after).getByText('Tasdiqlandi')).toBeInTheDocument();
    expect(within(after).queryByRole('button', { name: 'Rad etish' })).not.toBeInTheDocument();
  });

  it('dismisses with a reason: the commission stays', async () => {
    const review = storeRefundReview({ status: 'dismissed', decided_by: 1, decided_at: new Date(NOW).toISOString(), note: "Ovqatxona o'zi yuborgan" });
    const dismissed = held({}, { can_decide_store_refund: false, can_approve_store_refund: false });
    let current = held();
    const { user, callsTo } = setup({
      [`GET /orders/${UUID}`]: () => ok(current),
      [`POST ${DISMISS}`]: () => ok((current = { ...dismissed, store_refund_review: review, store_refund_reviews: [review] })),
    });
    await user.click(within(await section()).getByRole('button', { name: 'Rad etish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni rad etish' });
    expect(within(dialog).getByText(/komissiyasi saqlanadi/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), "Ovqatxona o'zi yuborgan");
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    await vi.waitFor(() => expect(callsTo('POST', DISMISS)[0]?.body).toEqual({ reason: "Ovqatxona o'zi yuborgan" }));
    expect(await screen.findByText('Qaytarish rad etildi: komissiya saqlanadi.')).toBeInTheDocument();
    const after = await section();
    expect(within(after).getByText('Rad etildi')).toBeInTheDocument();
    expect(within(after).getByText(/Ovqatxona o'zi yuborgan/)).toBeInTheDocument();
  });

  it('says four eyes in words when the proposer tries to confirm', async () => {
    const proposed = held({ proposed_by: 40, proposed_at: new Date(NOW).toISOString() });
    const { user } = setup({
      [`GET /orders/${UUID}`]: () => ok(proposed),
      [`POST ${APPROVE}`]: () => refusal(403, 'Qaytarishni taklif qilgan xodim uni tasdiqlay olmaydi.', { reason: 'four_eyes' }, { code: 'four_eyes' }),
    });
    await user.click(within(await section()).getByRole('button', { name: 'Tasdiqlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni tasdiqlash (ikkinchi xodim)' });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Tasdiqlayman');
    await user.click(within(dialog).getByRole('button', { name: 'Tasdiqlash' }));
    expect(await within(dialog).findByText("Siz bu qaytarishni taklif qilgansiz: ikkinchi tasdiqni boshqa xodim berishi kerak.")).toBeInTheDocument();
  });

  it('says a decision already made (409) in words and refreshes the card', async () => {
    let decided = false;
    const review = storeRefundReview({ status: 'dismissed', decided_by: 40, decided_at: new Date(NOW).toISOString(), note: 'Boshqa xodim' });
    const { user, callsTo } = setup({
      [`GET /orders/${UUID}`]: () => ok(decided ? { ...held({}, { can_decide_store_refund: false, can_approve_store_refund: false }), store_refund_review: review, store_refund_reviews: [review] } : held()),
      [`POST ${DISMISS}`]: () => {
        decided = true;
        return refusal(409, "Bu buyurtma bo'yicha tekshiruv kutayotgan qaytarish yo'q.", { reason: 'no_store_refund_review' }, { code: 'no_store_refund_review' });
      },
    });
    await user.click(within(await section()).getByRole('button', { name: 'Rad etish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Qaytarishni rad etish' });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Rad etaman');
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    expect(await within(dialog).findByText(/Bu qaytarish bo'yicha qaror allaqachon chiqarilgan/)).toBeInTheDocument();
    await vi.waitFor(() => expect(callsTo('GET', `/orders/${UUID}`).length).toBeGreaterThanOrEqual(2));
  });

  it('keeps the earlier decisions in the history, and shows no buttons to a member who may not decide', async () => {
    const latest = storeRefundReview({ payment_uuid: 'p2', amount: '40000.00' });
    const earlier = storeRefundReview({ status: 'dismissed', amount: '45000.00', decided_by: 40, decided_at: new Date(NOW - 60 * 60_000).toISOString(), note: 'Soxta so\'rov' });
    setup({ [`GET /orders/${UUID}`]: () => ok({ ...held({}, { can_decide_store_refund: false, can_approve_store_refund: false }), store_refund_review: latest, store_refund_reviews: [latest, earlier] }) }, ['admin.orders.view']);
    const block = await section();
    expect(within(block).getAllByRole('listitem')).toHaveLength(2);
    expect(within(block).getByText(/Ko'rib chiqdi: Operator 1/)).toBeInTheDocument();
    expect(within(block).getByText(/Soxta so'rov/)).toBeInTheDocument();
    expect(within(block).queryByRole('button')).not.toBeInTheDocument();
    expect(within(block).getByText(/Qarorni moliya ruxsati bor xodim chiqaradi/)).toBeInTheDocument();
  });

  it('has no such block on an ordinary order', async () => {
    setup();
    expect(await screen.findByRole('heading', { name: 'Buyurtma #ORD-AB12CD' })).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Ovqatxona kassasidan qaytarish' })).not.toBeInTheDocument();
  });
});

describe('exactMoney', () => {
  it('keeps the tiyin and leaves whole amounts as they are', () => {
    expect(exactMoney('50000.50')).toBe('50\u00a0000,50\u00a0so\'m');
    expect(exactMoney('0.05')).toBe('0,05\u00a0so\'m');
    expect(exactMoney('50000.00')).toBe('50\u00a0000\u00a0so\'m');
    expect(exactMoney('1234567.8')).toBe('1\u00a0234\u00a0567,80\u00a0so\'m');
    expect(exactMoney(12.5)).toBe('12,50\u00a0so\'m');
    expect(exactMoney(null)).toBe('0\u00a0so\'m');
  });
});
