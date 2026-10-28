import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { FinanceView, type FinanceInitial } from './FinanceView';
import { adjustment, adjustmentPage, balances, payoutCard, payoutPage, payoutRow, report, summary } from './fixtures';

const FINANCE = ['finance.view', 'finance.manage', 'payouts.approve', 'stores.view', 'export'].map((name) => `admin.${name}`);
const START: FinanceInitial = { tab: 'payouts', payout: null, adjustment: null, topup: '' };

function setup(routes: Parameters<typeof mockFetch>[0] = {}, { permissions = FINANCE, initial = START } = {}) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /finance/summary': () => ok(summary()),
    'GET /finance/payouts': (request) =>
      new URL(request.url).searchParams.get('status') === 'awaiting_second'
        ? ok(payoutPage([payoutRow({ id: 20, amount: '1000000.00', status: 'awaiting_second', status_label: 'Ikkinchi tasdiq kutilmoqda', needs_second_approval: true, first_approval: { by: { id: 2, name: 'Aziza Karimova' }, at: '2026-10-28T09:30:00+05:00', note: 'Birinchi' } })]))
        : ok(payoutPage([payoutRow()])),
    'GET /finance/adjustments': () => ok(adjustmentPage([adjustment()])),
    'GET /finance/payouts/12': () => ok(payoutCard()),
    ...routes,
  });
  renderPage(<FinanceView initial={initial} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('FinanceView', () => {
  it('lists the open payouts, counts the tabs and puts the second approvals on top', async () => {
    setup();
    const list = await screen.findByRole('list', { name: "Payout so'rovlari" });
    expect(within(list).getByRole('button', { name: 'Payout #12ni ochish' })).toHaveTextContent("400 000 so'm");
    expect(within(list).getByText('8600 **** **** 9012')).toBeInTheDocument();
    expect(await screen.findByRole('tab', { name: 'Payoutlar (2)' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: "Qo'lda tuzatish (1)" })).toBeInTheDocument();
    const queue = await screen.findByRole('region', { name: 'Ikkinchi tasdiq kutilmoqda' });
    expect(within(queue).getByText(/Payout: Lazzat Oshxona — 1 000 000.so'm \(birinchi tasdiq: Aziza Karimova\)/)).toBeInTheDocument();
    expect(within(queue).getByText(/Tuzatish: Lazzat Oshxona — 1 500 000.so'm \(Moliya 1\)/)).toBeInTheDocument();
    expect(screen.getByText(/1 000 000.so'm va undan katta summani ikki xodim tasdiqlaydi/)).toBeInTheDocument();
  });

  it('shows "Hisoblangan — Ushlangan qarz — O\'tkaziladi" and approves with a reason and one Idempotency-Key', async () => {
    const approved = payoutCard({ status: 'completed', status_label: "O'tkazildi", netted_amount: '298000.00', transferred_amount: '102000.00', breakdown: { ...payoutCard().breakdown!, preview: false }, actions: { can_approve: false, can_reject: false, approve_completes: true } });
    const { user, callsTo, fetchMock } = setup({ 'POST /finance/payouts/12/approve': () => ok(approved) });
    await user.click(await screen.findByRole('button', { name: 'Payout #12ni ochish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Payout #12' });
    const breakdown = within(dialog).getByRole('region', { name: 'Hisob' });
    expect(within(breakdown).getByText('Hisoblangan').nextSibling).toHaveTextContent("400 000 so'm");
    expect(within(breakdown).getByText('Ushlangan qarz').nextSibling).toHaveTextContent("−298 000 so'm");
    expect(within(breakdown).getByText('Obuna hisob-fakturasi OB-202609-3 (2026-09)')).toBeInTheDocument();
    expect(within(breakdown).getByText("O'tkaziladi").nextSibling).toHaveTextContent("102 000 so'm");
    expect(within(breakdown).getByText(/Hozir tasdiqlansa shunday/)).toBeInTheDocument();

    await user.click(within(dialog).getByRole('button', { name: 'Tasdiqlash' }));
    const form = await screen.findByRole('dialog', { name: 'Payoutni tasdiqlash' });
    expect(form).toHaveAccessibleDescription(/102\s000\sso'm kartaga o'tkaziladi, 298\s000\sso'm qarzga ushlanadi/);
    const submit = within(form).getByRole('button', { name: 'Tasdiqlash' });
    expect(submit).toBeDisabled();
    await user.type(within(form).getByLabelText('Sabab'), "Kartaga o'tkazildi");
    await user.click(submit);
    expect(await screen.findByText(/Tasdiqlandi: 102 000.so'm kartaga, 298 000.so'm qarzga/)).toBeInTheDocument();

    const [call] = callsTo('POST', '/finance/payouts/12/approve');
    expect(call?.body).toEqual({ reason: "Kartaga o'tkazildi" });
    const init = fetchMock.mock.calls.find(([input]) => String(input).endsWith('/finance/payouts/12/approve'))?.[1] as RequestInit;
    expect(new Headers(init.headers).get('Idempotency-Key')).toMatch(/.{8,}/);
  });

  it('tells the first approver that another member completes a payout of a million', async () => {
    const first = payoutCard({ id: 20, amount: '1000000.00', status: 'awaiting_second', status_label: 'Ikkinchi tasdiq kutilmoqda', needs_second_approval: true, first_approval: { by: { id: 1, name: 'Aziza Karimova' }, at: '2026-10-28T09:30:00+05:00', note: null }, actions: { can_approve: false, can_reject: true, approve_completes: true } });
    setup({ 'GET /finance/payouts/20': () => ok(first) }, { initial: { ...START, payout: 20 } });
    const dialog = await screen.findByRole('dialog', { name: 'Payout #20' });
    expect(await within(dialog).findByText('Siz birinchi tasdiqni bergansiz: ikkinchi tasdiqni boshqa xodim beradi.')).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Tasdiqlash' })).not.toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Rad etish' })).toBeInTheDocument();
  });

  it('records the first of two approvals and says so', async () => {
    const pending = payoutCard({ id: 30, amount: '2000000.00', needs_second_approval: true, actions: { can_approve: true, can_reject: true, approve_completes: false } });
    const { user } = setup({
      'GET /finance/payouts/30': () => ok(pending),
      'POST /finance/payouts/30/approve': () => ok({ ...pending, status: 'awaiting_second', status_label: 'Ikkinchi tasdiq kutilmoqda' }),
    }, { initial: { ...START, payout: 30 } });
    const dialog = await screen.findByRole('dialog', { name: 'Payout #30' });
    await user.click(await within(dialog).findByRole('button', { name: 'Birinchi tasdiq' }));
    const form = await screen.findByRole('dialog', { name: 'Payoutni tasdiqlash' });
    expect(form).toHaveAccessibleDescription(/birinchi tasdiq/);
    await user.type(within(form).getByLabelText('Sabab'), 'Tekshirildi');
    await user.click(within(form).getByRole('button', { name: 'Birinchi tasdiq' }));
    expect(await screen.findByText('Birinchi tasdiq yozildi. Endi boshqa xodim tasdiqlashi kerak.')).toBeInTheDocument();
  });

  it('says a refusal in the server\'s words (four eyes)', async () => {
    const { user } = setup({ 'POST /finance/payouts/12/approve': () => refusal(403, "Ikkinchi tasdiqni boshqa xodim beradi.", { reason: 'four_eyes' }, { code: 'four_eyes' }) }, { initial: { ...START, payout: 12 } });
    const dialog = await screen.findByRole('dialog', { name: 'Payout #12' });
    await user.click(await within(dialog).findByRole('button', { name: 'Tasdiqlash' }));
    const form = await screen.findByRole('dialog', { name: 'Payoutni tasdiqlash' });
    await user.type(within(form).getByLabelText('Sabab'), 'Tasdiq');
    await user.click(within(form).getByRole('button', { name: 'Tasdiqlash' }));
    expect(await within(form).findByText('Ikkinchi tasdiqni boshqa xodim beradi.')).toBeInTheDocument();
  });

  it('shows no actions to a member who may only look', async () => {
    const viewOnly = payoutCard({ actions: { can_approve: false, can_reject: false, approve_completes: true } });
    setup({ 'GET /finance/payouts/12': () => ok(viewOnly) }, { permissions: ['admin.finance.view'], initial: { ...START, payout: 12 } });
    const dialog = await screen.findByRole('dialog', { name: 'Payout #12' });
    expect(await within(dialog).findByText('Ovqatxona hozir')).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Tasdiqlash' })).not.toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Ikkinchi tasdiq kutilmoqda' })).not.toBeInTheDocument();
  });

  it('makes an adjustment: store, kind, exact amount, reason; a million waits for another member', async () => {
    const created = adjustment({ id: 7, mine: true, can_approve: false });
    const { user, callsTo } = setup(
      {
        'GET /stores/lookup': () => ok({ stores: [{ id: 3, name: 'Lazzat Oshxona', status: 'active', region: null }], regions: [], districts: [] }),
        'POST /finance/adjustments': () => json({ success: true, message: '', data: created }, 201),
        'GET /finance/adjustments/7': () => ok(created),
      },
      { initial: { ...START, tab: 'adjustments' } },
    );
    await user.click(await screen.findByRole('button', { name: 'Yangi tuzatish' }));
    const dialog = await screen.findByRole('dialog', { name: "Qo'lda tuzatish" });
    await user.type(within(dialog).getByRole('combobox', { name: 'Ovqatxona' }), 'Lazzat');
    await user.click(await within(dialog).findByRole('option', { name: /Lazzat Oshxona/ }));
    await user.selectOptions(within(dialog).getByLabelText("Nima o'zgaradi"), 'wallet_credit');
    const amount = within(dialog).getByLabelText("Summa, so'm");
    await user.type(amount, '1,234');
    expect(within(dialog).getByText(/0 dan katta, ko'pi bilan 2 xonali kasr/)).toBeInTheDocument();
    await user.clear(amount);
    await user.type(amount, '1 500 000');
    expect(within(dialog).getByText(/tasdiqlanguncha balans o'zgarmaydi/)).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), "Bank ko'chirmasi");
    await user.click(within(dialog).getByRole('button', { name: 'Yaratish' }));

    expect(await screen.findByText('Tuzatish yaratildi. Endi boshqa xodim tasdiqlashi kerak.')).toBeInTheDocument();
    expect(callsTo('POST', '/finance/adjustments')[0]?.body).toEqual({ store_id: 3, kind: 'wallet_credit', amount: '1500000', reason: "Bank ko'chirmasi" });
    const card = await screen.findByRole('dialog', { name: 'Tuzatish #7' });
    expect(within(card).getByText('Siz yaratgansiz: tasdiqni boshqa xodim beradi.')).toBeInTheDocument();
  });

  it('approves a pending adjustment of another member', async () => {
    const { user, callsTo } = setup(
      {
        'GET /finance/adjustments/5': () => ok(adjustment()),
        'POST /finance/adjustments/5/approve': () => ok(adjustment({ status: 'applied', status_label: "Qo'llandi", can_approve: false, can_reject: false })),
      },
      { initial: { ...START, adjustment: 5, tab: 'adjustments' } },
    );
    const dialog = await screen.findByRole('dialog', { name: 'Tuzatish #5' });
    await user.click(await within(dialog).findByRole('button', { name: 'Tasdiqlash' }));
    const form = await screen.findByRole('dialog', { name: 'Tuzatishni tasdiqlash' });
    await user.type(within(form).getByLabelText('Sabab'), "Ko'chirma tekshirildi");
    await user.click(within(form).getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText("Tasdiqlandi, tuzatish qo'llandi.")).toBeInTheDocument();
    expect(callsTo('POST', '/finance/adjustments/5/approve')[0]?.body).toEqual({ reason: "Ko'chirma tekshirildi" });
  });

  it('lists the stores over their credit limit with the debt split', async () => {
    const { user, callsTo } = setup({ 'GET /finance/balances': () => ok(balances()) }, { initial: { ...START, tab: 'balances' } });
    const list = await screen.findByRole('list', { name: 'Ovqatxonalar balansi' });
    expect(within(list).getByText('Limitdan oshgan')).toBeInTheDocument();
    expect(within(list).getByText('Buyurtmalar yopiq')).toBeInTheDocument();
    expect(screen.getByText(/faqat komissiya qarzi kiradi/)).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Limitdan oshgan (1)' }));
    await vi.waitFor(() => expect(callsTo('GET', '/finance/balances').at(-1)?.query.get('filter')).toBe('over_limit'));
  });

  it('shows the month and offers the Excel file only with the export permission', async () => {
    setup({ 'GET /finance/reports/monthly': () => ok(report()) }, { initial: { ...START, tab: 'report' } });
    expect(await screen.findByTestId('report-total')).toHaveTextContent("177 500 so'm");
    expect(screen.getByText(/Qaytarishlarda ayirildi: 5 000.so'm/)).toBeInTheDocument();
    expect(screen.getByRole('region', { name: "Komissiya kanal bo'yicha" })).toHaveTextContent('RIZQIM kuryeri');
    expect(screen.getByRole('button', { name: 'Excel yuklab olish' })).toBeInTheDocument();
  });

  it('hides the Excel export from a member without admin.export', async () => {
    setup({ 'GET /finance/reports/monthly': () => ok(report()) }, { permissions: ['admin.finance.view'], initial: { ...START, tab: 'report' } });
    await screen.findByTestId('report-total');
    expect(screen.queryByRole('button', { name: 'Excel yuklab olish' })).not.toBeInTheDocument();
  });

  it('refuses the section without finance.view', async () => {
    setup({}, { permissions: ['admin.orders.view'] });
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});
