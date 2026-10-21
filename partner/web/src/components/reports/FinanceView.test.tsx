import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { invoiceRows, ledgerRows, ok, ownerFinance, pageOf, paymentRows, payoutRows, subscription, walletRows } from './fixtures';
import { FinanceView } from './FinanceView';

const scope = vi.hoisted(() => ({ branchId: null as number | null }));
vi.mock('../shell/BranchScope', () => ({ useBranchScope: () => ({ branchId: scope.branchId }) }));
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: { id: 7, name: 'Aziz', role: 'owner' } }));
afterEach(() => {
  setSession(null);
  scope.branchId = null;
});

const params = (request: Request) => Object.fromEntries(new URL(request.url).searchParams);
const callsTo = (calls: Request[], path: string, method = 'GET') => calls.filter((call) => call.method === method && new URL(call.url).pathname === `/api/v1${path}`);

function routes(over: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return {
    'GET /api/v1/owner/finance': () => json(ok(ownerFinance)),
    'GET /api/v1/owner/finance/ledger': () => json(ok(pageOf(ledgerRows))),
    'GET /api/v1/owner/finance/wallet': () => json(ok(pageOf(walletRows))),
    'GET /api/v1/owner/payments': () => json(ok(pageOf(paymentRows))),
    'GET /api/v1/owner/payouts': () => json(ok(pageOf(payoutRows))),
    'GET /api/v1/owner/subscription': () => json(ok(subscription)),
    'GET /api/v1/owner/invoices': () => json(ok(pageOf(invoiceRows, { total: 2 }))),
    ...over,
  };
}

const openTab = (name: string) => userEvent.click(screen.getByRole('tab', { name }));

describe('FinanceView balances', () => {
  it('shows the balances as the server sent them, and the ledger first', async () => {
    mockFetch(routes());
    renderPage(<FinanceView />);

    expect(await screen.findByText("500 000 so'm")).toBeInTheDocument(); // wallet
    expect(screen.getByText("120 000 so'm")).toBeInTheDocument(); // debt
    expect(screen.getByText(/Kredit limiti: 1 000 000 so'm · Komissiya: 10%/)).toBeInTheDocument();

    const table = within(await screen.findByRole('table', { name: 'Qarz hisobi' }));
    expect(table.getByText('Komissiya')).toBeInTheDocument();
    expect(table.getByText('Obuna')).toBeInTheDocument();
    expect(table.getByText('#E101')).toBeInTheDocument();
    expect(table.getByText("4 500 so'm")).toBeInTheDocument();
    expect(table.getByText("115 500 so'm → 120 000 so'm")).toBeInTheDocument();
    expect(table.getByText('OB-202609-000042')).toBeInTheDocument();
  });

  it('warns when the server says orders are blocked', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/finance': () => json(ok({ ...ownerFinance, is_order_locked: true })) }));
    renderPage(<FinanceView />);
    expect(await screen.findByText(/Buyurtma qabul qilish hozir bloklangan/)).toBeInTheDocument();
  });

  it('keeps the history for someone who may see money but is not the owner', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/finance': () => json({ success: false, message: 'Ruxsat etilmagan', data: [] }, 403) }));
    renderPage(<FinanceView />);
    expect(await screen.findByText(/faqat ega uchun/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Pul yechish' })).not.toBeInTheDocument();
    expect(await screen.findByRole('table', { name: 'Qarz hisobi' })).toBeInTheDocument();
  });
});

describe('FinanceView history', () => {
  it('filters the ledger by type and dates, and pages', async () => {
    const { calls } = mockFetch(
      routes({
        'GET /api/v1/owner/finance/ledger': (request) => {
          const page = Number(new URL(request.url).searchParams.get('page'));
          return json(ok(pageOf(ledgerRows, { current_page: page, last_page: 3, total: 60 })));
        },
      }),
    );
    renderPage(<FinanceView />);
    await screen.findByRole('table', { name: 'Qarz hisobi' });
    expect(params(callsTo(calls, '/owner/finance/ledger')[0]!)).toEqual({ page: '1', per_page: '25' });
    expect(screen.getByText(/Sahifa 1 \/ 3 · Jami: 60/)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Keyingi' }));
    await waitFor(() => expect(screen.getByText(/Sahifa 2 \/ 3/)).toBeInTheDocument());
    expect(params(callsTo(calls, '/owner/finance/ledger').at(-1)!)).toMatchObject({ page: '2' });

    // a filter starts again from page 1
    await userEvent.selectOptions(screen.getByLabelText('Turi'), 'commission');
    await waitFor(() => expect(params(callsTo(calls, '/owner/finance/ledger').at(-1)!)).toMatchObject({ type: 'commission', page: '1' }));
    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-10-01' } });
    fireEvent.change(screen.getByLabelText('Tugash sanasi'), { target: { value: '2026-10-20' } });
    await waitFor(() => expect(params(callsTo(calls, '/owner/finance/ledger').at(-1)!)).toMatchObject({ type: 'commission', from: '2026-10-01', to: '2026-10-20', page: '1' }));
  });

  it('does not ask for a reversed pair of dates', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<FinanceView />);
    await screen.findByRole('table', { name: 'Qarz hisobi' });
    fireEvent.change(screen.getByLabelText('Tugash sanasi'), { target: { value: '2026-10-01' } });
    await waitFor(() => expect(callsTo(calls, '/owner/finance/ledger')).toHaveLength(2));
    fireEvent.change(screen.getByLabelText('Boshlanish sanasi'), { target: { value: '2026-10-10' } });
    expect(await screen.findByRole('alert')).toHaveTextContent("Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.");
    expect(callsTo(calls, '/owner/finance/ledger')).toHaveLength(2);
  });

  it('shows the wallet history', async () => {
    mockFetch(routes());
    renderPage(<FinanceView />);
    await openTab('Hamyon tarixi');
    const table = within(await screen.findByRole('table', { name: 'Hamyon tarixi' }));
    expect(table.getByText('Hisob-kitob')).toBeInTheDocument();
    expect(table.getByText("45 000 so'm")).toBeInTheDocument();
    expect(table.getByText('#E102')).toBeInTheDocument();
  });

  it('shows online payments with their status, provider transaction and filters', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<FinanceView />);
    await openTab("Onlayn to'lovlar");
    const table = within(await screen.findByRole('table', { name: "Onlayn to'lovlar" }));
    expect(table.getByText('CLK-998877')).toBeInTheDocument();
    expect(table.getByText("To'langan")).toBeInTheDocument();
    expect(table.getByText('Muvaffaqiyatsiz')).toBeInTheDocument();
    expect(table.getByText('Payme')).toBeInTheDocument();
    expect(table.getByText('#CCCCCCCC')).toBeInTheDocument(); // an order without a short code

    await userEvent.selectOptions(screen.getByLabelText('Holati'), 'paid');
    await waitFor(() => expect(params(callsTo(calls, '/owner/payments').at(-1)!)).toMatchObject({ status: 'paid', page: '1' }));
    await userEvent.selectOptions(screen.getByLabelText("To'lov tizimi"), 'click');
    await waitFor(() => expect(params(callsTo(calls, '/owner/payments').at(-1)!)).toMatchObject({ status: 'paid', provider: 'click' }));
  });

  it('narrows the online payments to the branch chosen in the top bar', async () => {
    scope.branchId = 4;
    const { calls } = mockFetch(routes());
    renderPage(<FinanceView />);
    await openTab("Onlayn to'lovlar");
    await screen.findByRole('table', { name: "Onlayn to'lovlar" });
    expect(params(callsTo(calls, '/owner/payments')[0]!)).toMatchObject({ branch_id: '4' });
  });

  it('shows payout requests with the card masked as the server returned it', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<FinanceView />);
    await openTab("Pul yechish so'rovlari");
    const table = within(await screen.findByRole('table', { name: "Pul yechish so'rovlari" }));
    expect(table.getAllByText('8600 **** **** 9012')).toHaveLength(2);
    expect(table.getByText('Kutilmoqda')).toBeInTheDocument();
    expect(table.getByText("To'langan")).toBeInTheDocument();
    expect(table.getByText('Yuborildi')).toBeInTheDocument();
    expect(table.getByText("500 000 so'm")).toBeInTheDocument();

    await userEvent.selectOptions(screen.getByLabelText('Holati'), 'rejected');
    await waitFor(() => expect(params(callsTo(calls, '/owner/payouts').at(-1)!)).toMatchObject({ status: 'rejected' }));
  });

  it('says nothing was found, and shows a failure with a retry', async () => {
    let fail = true;
    mockFetch(routes({ 'GET /api/v1/owner/payments': () => (fail ? json({ message: 'boom' }, 500) : json(ok(pageOf([])))) }));
    renderPage(<FinanceView />);
    await openTab("Onlayn to'lovlar");
    expect(await screen.findByText("Ma'lumot yuklanmadi")).toBeInTheDocument();
    fail = false;
    await userEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByText("Yozuvlar yo'q")).toBeInTheDocument();
  });
});

describe('FinanceView plan', () => {
  it('shows the plan, the estimate of the running invoice and the invoices with their status', async () => {
    const { calls } = mockFetch(routes());
    renderPage(<FinanceView />);
    await openTab('Tarif va hisob-fakturalar');

    expect(await screen.findByText('Aralash')).toBeInTheDocument();
    expect(screen.getByText('Faol')).toBeInTheDocument();
    expect(screen.getByText('Amaldagi komissiya: 5%')).toBeInTheDocument();
    expect(screen.getByText('Terminallar: hozir 2, hisoblangan 2')).toBeInTheDocument();
    expect(screen.getByText("Qo'shimcha terminal")).toBeInTheDocument();
    expect(screen.getByText("200 000 so'm")).toBeInTheDocument();

    const table = within(await screen.findByRole('table', { name: 'Hisob-fakturalar' }));
    expect(table.getByText('OB-202609-000042')).toBeInTheDocument();
    expect(table.getByText("To'lanmagan")).toBeInTheDocument();
    expect(table.getByText("To'langan")).toBeInTheDocument();

    await userEvent.selectOptions(screen.getByLabelText('Holati'), 'paid');
    await waitFor(() => expect(params(callsTo(calls, '/owner/invoices').at(-1)!)).toMatchObject({ status: 'paid', page: '1' }));
  });

  it('says so for a store on no plan', async () => {
    mockFetch(routes({ 'GET /api/v1/owner/subscription': () => json(ok({ subscription: null, commission_rate: 10, terminals: { active_now: 0, counted: null }, next_invoice: null })), 'GET /api/v1/owner/invoices': () => json(ok(pageOf([]))) }));
    renderPage(<FinanceView />);
    await openTab('Tarif va hisob-fakturalar');
    expect(await screen.findByText(/Tarif biriktirilmagan/)).toBeInTheDocument();
    expect(await screen.findByText('Hisob-fakturalar hali yo\'q')).toBeInTheDocument();
  });
});

describe('FinanceView payout', () => {
  async function openPayout() {
    await userEvent.click(await screen.findByRole('button', { name: 'Pul yechish' }));
    return screen.getByRole('dialog');
  }

  it('sends the request as typed (spaces out of the card) and says it was sent; the payouts list is refreshed', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/payouts': () => json(ok({ id: 9, status: 'pending' }), 200) }));
    renderPage(<FinanceView />);
    await openTab("Pul yechish so'rovlari");
    await screen.findByRole('table', { name: "Pul yechish so'rovlari" });
    const dialog = within(await openPayout());

    expect(dialog.getByText(/kamida 100 000 so'm qolishi kerak/)).toBeInTheDocument(); // the reserve the server named
    await userEvent.type(dialog.getByLabelText("Summa (so'm)"), '500000');
    await userEvent.type(dialog.getByLabelText('Karta raqami'), '8600123412341234');
    expect(dialog.getByLabelText('Karta raqami')).toHaveValue('8600 1234 1234 1234');
    await userEvent.type(dialog.getByLabelText('Izoh (ixtiyoriy)'), 'Oy oxiri');
    await userEvent.click(dialog.getByRole('button', { name: "So'rov yuborish" }));

    expect(await dialog.findByRole('status')).toHaveTextContent("So'rov yuborildi");
    expect(await callsTo(calls, '/owner/payouts', 'POST')[0]!.json()).toEqual({ amount: 500000, card_number: '8600123412341234', notes: 'Oy oxiri' });
    await waitFor(() => expect(callsTo(calls, '/owner/payouts')).toHaveLength(2)); // the list was read again
    await waitFor(() => expect(callsTo(calls, '/owner/finance')).toHaveLength(2)); // and the balances
  });

  it('shows the server\'s own refusal, as it came', async () => {
    const message = "Yechib olingandan so'ng hamyonda kamida 100 000 UZS qolishi shart. Kutilayotgan so'rovlarni hisobga olganda siz ko'pi bilan 400 000 UZS yechishingiz mumkin.";
    mockFetch(routes({ 'POST /api/v1/owner/payouts': () => json({ success: false, message, data: [] }, 422) }));
    renderPage(<FinanceView />);
    const dialog = within(await openPayout());
    await userEvent.type(dialog.getByLabelText("Summa (so'm)"), '500000');
    await userEvent.type(dialog.getByLabelText('Karta raqami'), '8600123412341234');
    await userEvent.click(dialog.getByRole('button', { name: "So'rov yuborish" }));
    expect(await dialog.findByRole('alert')).toHaveTextContent(message);
    expect(dialog.queryByRole('status')).not.toBeInTheDocument(); // not reported as sent
  });

  it('shows a field error by its field', async () => {
    mockFetch(routes({ 'POST /api/v1/owner/payouts': () => json({ message: 'x', errors: { card_number: ["Karta raqami 16 ta raqamdan iborat bo'lishi kerak."] } }, 422) }));
    renderPage(<FinanceView />);
    const dialog = within(await openPayout());
    await userEvent.type(dialog.getByLabelText("Summa (so'm)"), '5000');
    await userEvent.type(dialog.getByLabelText('Karta raqami'), '8600');
    await userEvent.click(dialog.getByRole('button', { name: "So'rov yuborish" }));
    expect(await dialog.findByText("Karta raqami 16 ta raqamdan iborat bo'lishi kerak.")).toBeInTheDocument();
  });
});

describe('FinanceView top-up', () => {
  it('asks the server for a payment page and offers it as a link', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/finance/top-up': () => json(ok({ topup_uuid: 't-1', payment_url: 'https://my.click.uz/services/pay?x=1' })) }));
    renderPage(<FinanceView />);
    await userEvent.click(await screen.findByRole('button', { name: "Balansni to'ldirish" }));
    const dialog = within(screen.getByRole('dialog'));
    await userEvent.type(dialog.getByLabelText("Summa (so'm)"), '200000');
    await userEvent.selectOptions(dialog.getByLabelText("To'lov tizimi"), 'payme');
    await userEvent.click(dialog.getByRole('button', { name: "To'lov sahifasini yaratish" }));

    const link = await dialog.findByRole('link', { name: "To'lov sahifasini ochish" });
    expect(link).toHaveAttribute('href', 'https://my.click.uz/services/pay?x=1');
    expect(link).toHaveAttribute('rel', expect.stringContaining('noopener'));
    expect(await callsTo(calls, '/owner/finance/top-up', 'POST')[0]!.json()).toEqual({ amount: 200000, provider: 'payme' });
  });

  it('does not offer an address that is not a web page', async () => {
    mockFetch(routes({ 'POST /api/v1/owner/finance/top-up': () => json(ok({ topup_uuid: 't-1', payment_url: 'javascript:alert(1)' })) }));
    renderPage(<FinanceView />);
    await userEvent.click(await screen.findByRole('button', { name: "Balansni to'ldirish" }));
    const dialog = within(screen.getByRole('dialog'));
    await userEvent.type(dialog.getByLabelText("Summa (so'm)"), '200000');
    await userEvent.click(dialog.getByRole('button', { name: "To'lov sahifasini yaratish" }));
    expect(await dialog.findByRole('alert')).toBeInTheDocument();
    expect(dialog.queryByRole('link')).not.toBeInTheDocument();
  });
});
