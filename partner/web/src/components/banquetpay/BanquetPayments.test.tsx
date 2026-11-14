import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { BanquetPaymentsSection } from './BanquetPaymentsSection';
import { BanquetReportView } from './BanquetReportView';
import { EVENT_UUID, INTERIM_UUID, quoteFixture, reportFixture, rulesFixture, scheduleFixture } from './fixtures';
import { sumAmounts } from './money';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
const saveBlob = vi.hoisted(() => vi.fn());
vi.mock('../reports/download', () => ({ saveBlob }));

beforeEach(() => {
  signIn();
  saveBlob.mockReset();
});
afterEach(() => setSession(null));

const base = `/api/v1/pos/banquets/${EVENT_UUID}`;

const section = (over: Parameters<typeof mockFetch>[0] = {}, keys: string[] = ['banquets.manage']) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    [`GET ${base}/payments`]: () => ok(scheduleFixture()),
    ...over,
  });
  renderPage(<BanquetPaymentsSection eventUuid={EVENT_UUID} />);
  return { ...mocked, typing: userEvent.setup() };
};

describe('Tadbir to\'lov jadvali', () => {
  it('shows the figures, the rows and the overdue one', async () => {
    section();
    const table = await screen.findByRole('table', { name: "To'lov qatorlari" });
    expect(table).toHaveTextContent('Zaklad');
    expect(table).toHaveTextContent("To'langan");
    expect(table).toHaveTextContent('Naqd');
    expect(within(table).getAllByText("Muddati o'tgan")).toHaveLength(1);
    expect(screen.getByRole('alert')).toHaveTextContent("1 ta to'lov muddati o'tgan");
    expect(screen.getByRole('table', { name: 'Pul harakatlari' })).toHaveTextContent('Kassir');
  });

  it('records a bank transfer against an unpaid row with an Idempotency-Key', async () => {
    const { calls, typing } = section({ [`POST ${base}/payments/${INTERIM_UUID}/bank-transfer`]: () => ok({ schedule: scheduleFixture(), entry: {} }, 201) });
    await typing.click(await screen.findByRole('button', { name: "Oraliq to'lov uchun bank o'tkazmasini yozish" }));
    const dialog = await screen.findByRole('dialog');
    await typing.type(within(dialog).getByLabelText("To'lov topshirig'i raqami"), 'PP-145');
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Bank o'tkazmasi yozildi.")).toBeInTheDocument();
    const post = calls.filter((call) => call.method === 'POST').at(-1)!;
    expect(await post.json()).toEqual({ reference: 'PP-145' });
    expect(post.headers.get('Idempotency-Key')).toBeTruthy();
  });

  it('edits the unpaid rows and shows the running sum', async () => {
    const { calls, typing } = section({ [`PUT ${base}/payments`]: () => ok(scheduleFixture()) });
    await typing.click(await screen.findByRole('button', { name: 'Jadvalni tahrirlash' }));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText(/Hozirgi yig'indi/)).toHaveTextContent('7');
    await typing.click(within(dialog).getAllByRole('button', { name: "Qatorni o'chirish" })[1]!);
    await typing.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    const put = calls.filter((call) => call.method === 'PUT').at(-1)!;
    expect(await put.json()).toEqual({ rows: [{ uuid: INTERIM_UUID, kind: 'interim', due_date: '2026-11-10', amount: '4000000.00' }], version: 3 });
  });

  it('cancels with the computed parts, an override needs its reason', async () => {
    const { calls, typing } = section({
      [`GET ${base}/cancellation`]: (request) => ok(new URL(request.url).searchParams.get('retained') ? quoteFixture({ retained: '500000.00', refund: '2500000.00', overridden: true }) : quoteFixture()),
      [`POST ${base}/cancellation`]: () => ok(scheduleFixture({ event: { ...scheduleFixture().event, status: 'cancelled', status_label: 'Bekor qilingan' } })),
    });
    await typing.click(await screen.findByRole('button', { name: 'Tadbirni bekor qilish' }));
    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findByText('Tadbirgacha 20 kun: qoida — 30% ushlab qolinadi')).toBeInTheDocument();
    expect(within(dialog).getByTestId('bqp-quote')).toHaveTextContent('kassadan naqd');
    expect(within(dialog).getByText(/Naqd qaytarish kassadagi ochiq smenadan/)).toBeInTheDocument();

    await typing.click(within(dialog).getByLabelText("Ushlab qolinadigan summani o'zgartirish"));
    await typing.type(within(dialog).getByLabelText('Ushlab qolinadigan summa'), '500000');
    await typing.type(within(dialog).getByLabelText('Bekor qilish sababi'), "Mijoz ko'chirdi");
    await typing.click(within(dialog).getByRole('button', { name: 'Ha, bekor qilish' }));
    expect(await within(dialog).findByText('Sababni yozing.')).toBeInTheDocument();
    await typing.type(within(dialog).getByLabelText("O'zgartirish sababi"), 'Ega rozi');
    await typing.click(within(dialog).getByRole('button', { name: 'Ha, bekor qilish' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    const post = calls.filter((call) => call.method === 'POST').at(-1)!;
    expect(await post.json()).toEqual({ reason: "Mijoz ko'chirdi", retained: '500000', override_reason: 'Ega rozi' });
  });

  it('a cashier (pos.take_payment) only reads it', async () => {
    section({}, ['pos.take_payment']);
    await screen.findByRole('table', { name: "To'lov qatorlari" });
    expect(screen.queryByRole('button', { name: 'Jadvalni tahrirlash' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Tadbirni bekor qilish' })).toBeNull();
  });

  it('sums amounts exactly', () => {
    expect(sumAmounts(['0.10', '0.20', ' 1 000 ', 'x'])).toBe(1000.3);
  });
});

describe('Tadbirlar hisoboti', () => {
  const report = (over: Parameters<typeof mockFetch>[0] = {}, keys: string[] = ['banquets.manage', 'reports.view']) => {
    const mocked = mockFetch({
      'GET /api/v1/me/permissions': () => ownerAnswer(keys),
      'GET /api/v1/owner/pos/banquets/report': () => ok(reportFixture),
      'GET /api/v1/owner/pos/banquets/payment-settings': () => ok(rulesFixture),
      ...over,
    });
    renderPage(<BanquetReportView />);
    return { ...mocked, typing: userEvent.setup() };
  };

  it('shows the totals and the events with a link to their schedule', async () => {
    report();
    const table = await screen.findByRole('table', { name: 'Tadbirlar' });
    expect(table).toHaveTextContent("Karimovlar to'yi");
    expect(table).toHaveTextContent('+99890*****67');
    expect(within(table).getByRole('link', { name: "To'lov jadvali" })).toHaveAttribute('href', `/banquets/schedule?event=${EVENT_UUID}`);
    expect(screen.getByText('2 ta tadbir, 350 mehmon')).toBeInTheDocument();
  });

  it('saves the payment rules', async () => {
    const { calls, typing } = report({ 'PUT /api/v1/owner/pos/banquets/payment-settings': () => ok({ ...rulesFixture, reminder_days: [5], version: 1 }) });
    const reminders = await screen.findByLabelText('Eslatma kunlari (vergul bilan)');
    await typing.clear(reminders);
    await typing.type(reminders, '5');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Qoidalar saqlandi.')).toBeInTheDocument();
    const put = calls.filter((call) => call.method === 'PUT').at(-1)!;
    expect(await put.json()).toMatchObject({ reminder_days: [5], overdue_reminders: true, version: 0, cancellation_rules: rulesFixture.cancellation_rules });
  });

  it('a report-only reader sees no rules; without either permission no page', async () => {
    report({}, ['reports.view']);
    await screen.findByRole('table', { name: 'Tadbirlar' });
    expect(screen.queryByText("To'lov va bekor qilish qoidalari")).toBeNull();
  });

  it('refuses a person who may not read it', async () => {
    report({}, ['orders.view']);
    expect(await screen.findByText("Bu bo'limni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });

  it('downloads the Excel file', async () => {
    const { typing } = report({
      'GET /api/v1/owner/pos/banquets/export': () => new Response('xlsx', { status: 200, headers: { 'Content-Disposition': 'attachment; filename=tadbirlar-2026-11-01-2026-11-30.xlsx' } }),
    });
    await screen.findByRole('table', { name: 'Tadbirlar' });
    await typing.click(screen.getByRole('button', { name: 'Excel yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(1));
    expect(saveBlob.mock.calls[0]![1]).toBe('tadbirlar-2026-11-01-2026-11-30.xlsx');
  });
});
