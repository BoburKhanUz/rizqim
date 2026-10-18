import { QueryClient, QueryClientProvider, focusManager } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { makeFinance, makeLedgerTransaction, makePayoutRequest } from '../test/factories';
import { failedRequest } from '../test/httpErrors';
import type { LedgerTransaction, OwnerFinance } from '../types';
import OwnerPayments from './OwnerPayments';

// The owner's balance screen on a fake API and a controlled clock: it polls, and its money arrives as floats and
// as decimal strings

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

let client: QueryClient;

const settle = (ms = 0) => act(() => vi.advanceTimersByTimeAsync(ms));

// How the page writes an amount. The digit grouping follows the locale of the device (a no-break space in some),
// and Testing Library compares text with every kind of space turned into a plain one.
const uzs = (value: number) => `${value.toLocaleString()} UZS`.replace(/\s+/g, ' ');

function serveFinance(finance: OwnerFinance) {
  get.mockImplementation(() => Promise.resolve({ data: { success: true, data: finance } }));
}

const financeCalls = () => get.mock.calls.filter(([url]) => url === '/owner/finance').length;

async function renderPage() {
  render(
    <QueryClientProvider client={client}>
      <OwnerPayments />
    </QueryClientProvider>,
  );
  await settle();
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  vi.setSystemTime(new Date(2026, 9, 18, 12, 0, 0));
  client = new QueryClient();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  focusManager.setFocused(undefined);
  vi.useRealTimers();
});

describe('OwnerPayments: money', () => {
  it('shows the balances, the ledger and the payout requests, reading decimal strings as numbers', async () => {
    serveFinance(
      makeFinance({
        debt_balance: 150000,
        wallet_balance: 500000,
        credit_limit: 1000000,
        ledger_transactions: [
          makeLedgerTransaction({ id: 2, type: 'commission', amount: '15000.00', balance_after: '165000.00' }),
          makeLedgerTransaction({ id: 1, type: 'topup', amount: '-100000.00', balance_after: '65000.00' }),
        ],
        payout_requests: [makePayoutRequest({ amount: '250000.00' })],
      }),
    );

    await renderPage();

    expect(screen.getAllByText(uzs(150000))).not.toHaveLength(0); // the debt, read from `debt_balance`
    expect(screen.getByText(uzs(850000))).toBeInTheDocument(); // the credit that is left
    expect(screen.getByText(uzs(500000))).toBeInTheDocument(); // the wallet
    expect(screen.getByText(`+${uzs(15000)}`)).toBeInTheDocument(); // a commission
    expect(screen.getByText(`Qarzdorlik: ${uzs(165000)}`)).toBeInTheDocument();
    expect(screen.getByText(`-${uzs(100000)}`)).toBeInTheDocument(); // a top-up
    expect(screen.getByText(`Qarzdorlik: ${uzs(65000)}`)).toBeInTheDocument();
    expect(screen.getByText(uzs(250000))).toBeInTheDocument(); // the payout request
    expect(document.body).not.toHaveTextContent('NaN');
  });

  it('reads the balances the same when the API sends them as decimal strings', async () => {
    serveFinance(
      makeFinance({
        debt_balance: '150000.00',
        wallet_balance: '500000.50',
        credit_limit: '1000000.00',
        min_payout_reserve: '100000.00',
        commission_rate: '12.50',
      }),
    );

    await renderPage();

    expect(screen.getAllByText(uzs(150000))).not.toHaveLength(0);
    expect(screen.getByText(uzs(850000))).toBeInTheDocument();
    expect(screen.getByText(uzs(500000.5))).toBeInTheDocument();
    expect(screen.getByText(`Min. qoldiq: ${uzs(100000)}`)).toBeInTheDocument();
    expect(screen.getByText(/^12\.5% /)).toBeInTheDocument();
    expect(document.body).not.toHaveTextContent('NaN');
  });

  it('shows a prepaid balance as an advance and no debt', async () => {
    serveFinance(makeFinance({ debt_balance: '-80000.00', credit_limit: 1000000, payment_account_type: 'own_account' }));

    await renderPage();

    expect(screen.getByText(uzs(80000))).toBeInTheDocument(); // the advance
    expect(screen.getByText(uzs(1080000))).toBeInTheDocument(); // the credit left grows by it
    expect(screen.getByText('Qarz yo\'q')).toBeInTheDocument();
  });

  it('shows 0 for an amount the server left out, not NaN', async () => {
    const broken = { ...makeLedgerTransaction({ id: 3 }), amount: undefined, balance_after: undefined } as unknown as LedgerTransaction;
    serveFinance(makeFinance({ ledger_transactions: [broken] }));

    await renderPage();

    expect(screen.getByText(`Qarzdorlik: ${uzs(0)}`)).toBeInTheDocument();
    expect(document.body).not.toHaveTextContent('NaN');
  });
});

describe('OwnerPayments: polling', () => {
  it('refreshes every 10 seconds while the page is visible', async () => {
    serveFinance(makeFinance());
    await renderPage();
    expect(financeCalls()).toBe(1);

    await settle(10_000);
    expect(financeCalls()).toBe(2);

    await settle(10_000);
    expect(financeCalls()).toBe(3);
  });

  it('does not poll while the page is hidden, and refreshes when the user comes back', async () => {
    serveFinance(makeFinance());
    await renderPage();
    const callsAfterLoad = financeCalls();

    focusManager.setFocused(false);
    await settle(5 * 60_000);
    expect(financeCalls()).toBe(callsAfterLoad);

    focusManager.setFocused(true); // the data is older than an interval by now
    await settle();
    expect(financeCalls()).toBe(callsAfterLoad + 1);
  });

  it('keeps the balances on screen when a later refresh fails', async () => {
    serveFinance(makeFinance({ debt_balance: 150000 }));
    await renderPage();
    expect(screen.getAllByText(uzs(150000))).not.toHaveLength(0);

    get.mockRejectedValue(failedRequest(502, {}, '/owner/finance'));
    await settle(40_000); // the polls fail, and so do their retries: the query is in its error state by now

    expect(client.getQueryState(['ownerFinance'])?.status).toBe('error');
    expect(screen.getAllByText(uzs(150000))).not.toHaveLength(0);
    expect(screen.queryByText(/yuklashda xatolik/)).not.toBeInTheDocument();
  });

  it('waits longer and longer between refreshes while the server keeps failing, instead of asking every 10 seconds', async () => {
    serveFinance(makeFinance());
    await renderPage();
    const callsAfterLoad = financeCalls();

    get.mockRejectedValue(failedRequest(502, {}, '/owner/finance'));
    await settle(5 * 60_000);

    const requestsDuringOutage = financeCalls() - callsAfterLoad;
    expect(requestsDuringOutage).toBeGreaterThanOrEqual(4); // it keeps trying
    expect(requestsDuringOutage).toBeLessThanOrEqual(14); // a fixed 10 s interval would make 30 or more

    serveFinance(makeFinance({ debt_balance: 777000 })); // and the first answer after the outage is shown
    await settle(2 * 60_000);
    expect(screen.getAllByText(uzs(777000))).not.toHaveLength(0);
  });

  it('shows the error when the very first load fails: there is nothing else to show', async () => {
    get.mockRejectedValue(failedRequest(502, {}, '/owner/finance'));

    await renderPage();
    await settle(5_000);

    expect(screen.getByText(/yuklashda xatolik/)).toBeInTheDocument();
  });
});

describe('OwnerPayments: payout request', () => {
  const fillPayout = (amount: string, card = '8600 1234 5678 1234') => {
    fireEvent.change(screen.getByPlaceholderText('Masalan: 50000'), { target: { value: amount } });
    fireEvent.change(screen.getByPlaceholderText('0000 0000 0000 0000'), { target: { value: card } });
  };
  const submitPayout = () => fireEvent.click(screen.getByRole('button', { name: /Yechib olishni so'rash/ }));

  beforeEach(() => {
    serveFinance(makeFinance({ wallet_balance: '500000.00', min_payout_reserve: 100000 }));
  });

  it('sends the amount as a number and the card number without spaces', async () => {
    post.mockResolvedValue({ data: { success: true, data: makePayoutRequest() } });
    await renderPage();

    fillPayout('50000');
    submitPayout();
    await settle();

    expect(post).toHaveBeenCalledWith('/owner/payouts', { amount: 50000, card_number: '8600123456781234', notes: undefined });
    expect(screen.getByText('Payout so\'rovi muvaffaqiyatli yuborildi!')).toBeInTheDocument();
    expect(financeCalls()).toBe(2); // the balances are fetched again
  });

  it('keeps the minimum balance back: says how much can be paid out and sends nothing', async () => {
    await renderPage();

    fillPayout('450000'); // 500 000 - 450 000 leaves less than the 100 000 that must stay
    submitPayout();
    await settle();

    expect(screen.getByText(new RegExp(`Maksimal yechish summasi: ${uzs(400000)}`))).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });

  it('asks for an amount and for the whole card number before sending anything', async () => {
    await renderPage();

    fillPayout('', '');
    submitPayout();
    expect(screen.getByText('To\'g\'ri summani kiriting')).toBeInTheDocument();

    fillPayout('50000', '8600 1234');
    submitPayout();
    expect(screen.getByText('Karta raqamini to\'liq kiriting')).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });
});

describe('OwnerPayments: balance top-up', () => {
  const openTopup = async () => {
    await renderPage();
    fireEvent.click(screen.getByRole('button', { name: /Balansni To'ldirish \(Card Top Up\)/ }));
  };
  const payButton = () => screen.getByRole('button', { name: /To'lashga o'tish/ });

  beforeEach(() => {
    serveFinance(makeFinance({ payment_account_type: 'own_account' }));
  });

  it('starts the payment for the chosen amount, as a number', async () => {
    post.mockResolvedValue({ data: { success: true, data: { topup_uuid: 'abc', payment_url: 'https://my.click.uz/pay' } } });
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    await openTopup();

    fireEvent.click(screen.getByRole('button', { name: '250k' }));
    expect(screen.getByDisplayValue('250000')).toBeInTheDocument();
    fireEvent.click(payButton());
    await settle();

    expect(post).toHaveBeenCalledWith('/owner/finance/top-up', { amount: 250000, provider: 'click' });
    expect(open).toHaveBeenCalledWith('https://my.click.uz/pay', '_blank');
  });

  it('does not let a top-up below the minimum, or an empty one, through', async () => {
    await openTopup();
    const amountInput = screen.getByDisplayValue('100000');

    expect(payButton()).toBeEnabled();
    fireEvent.change(amountInput, { target: { value: '999' } });
    expect(payButton()).toBeDisabled();
    fireEvent.change(amountInput, { target: { value: '' } });
    expect(payButton()).toBeDisabled();
    fireEvent.change(amountInput, { target: { value: '1000' } });
    expect(payButton()).toBeEnabled();
    expect(post).not.toHaveBeenCalled();
  });
});
