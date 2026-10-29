import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { deviceRow, devicePage, fiscalCard, fiscalRow, invoiceCard, invoiceRow, overview, page, syncCard, syncRow } from './fixtures';
import { DevicesTab } from './DevicesTab';
import { FiscalTab } from './FiscalTab';
import { InvoicesTab } from './InvoicesTab';
import { SyncTab } from './SyncTab';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const INVOICE = '1a0c0000-0000-4000-8000-000000000001';
const DEVICE = '7a1b0000-0000-4000-8000-000000000001';
const FISCAL = '5f0c2a10-0000-4000-8000-000000000001';
const OP = '0b5f0000-0000-4000-8000-000000000001';

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  const button = within(dialog).getByRole('button', { name: confirm });
  expect(button).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  expect(button).toBeEnabled();
  await user.click(button);
  return dialog;
}

describe('InvoicesTab', () => {
  const counts = overview().invoices;

  it('lists invoices with the days overdue and the last reminder, and sends the filters', async () => {
    const { callsTo } = mockFetch({
      ...ME(['finance.view']),
      'GET /pos/invoices': () => ok(page([invoiceRow(), invoiceRow({ uuid: 'u2', number: 'OB-202610-000012', status: 'paid', overdue: false, overdue_days: 0, dunning_stage: null, dunning_label: null })], { counts })),
    });
    const user = userEvent.setup();
    renderPage(<InvoicesTab selected={null} onSelect={() => {}} />);

    const list = await screen.findByRole('list', { name: "Hisob-fakturalar ro'yxati" });
    const late = within(list).getByRole('button', { name: 'OB-202610-000015 hisob-fakturasini ochish' });
    expect(within(late).getByText('5 kun kechikdi')).toBeInTheDocument();
    expect(within(late).getByText('1-kun eslatmasi')).toBeInTheDocument();
    expect(within(late).getByText("To'lanmagan")).toBeInTheDocument();
    expect(screen.getByText(/To'lanmagan hisob-fakturalar jami: 432/)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: "Muddati o'tgan (1)" }));
    await waitFor(() => expect(callsTo('GET', '/pos/invoices').at(-1)?.query.get('status')).toBe('overdue'));
    await user.selectOptions(screen.getByLabelText('Oxirgi eslatma'), '3-kun eslatmasi');
    await waitFor(() => expect(callsTo('GET', '/pos/invoices').at(-1)?.query.get('dunning')).toBe('reminder_3'));
  });

  it('marks an invoice paid with a reason; a member without finance.manage does not see the buttons', async () => {
    const { callsTo } = mockFetch({
      ...ME(['finance.view', 'finance.manage']),
      [`GET /pos/invoices/${INVOICE}`]: () => ok(invoiceCard()),
      [`POST /pos/invoices/${INVOICE}/paid`]: () => ok(invoiceCard({ status: 'paid', overdue: false, overdue_days: 0 })),
    });
    const user = userEvent.setup();
    renderPage(<InvoicesTab selected={INVOICE} onSelect={() => {}} />);

    expect(await screen.findByRole('heading', { name: 'OB-202610-000015' })).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Qatorlar' })).toHaveTextContent('Chegirma 50%');
    expect(screen.getByRole('list', { name: 'Eslatmalar tarixi' })).toHaveTextContent('1-kun eslatmasi');
    expect(screen.getByText("Obuna qarzi (to'lanmagan hisob-fakturalar)")).toBeInTheDocument();

    await user.click(await screen.findByRole('button', { name: "To'langan deb belgilash" }));
    await reasonDialog(user, "To'langan deb belgilash", "Naqd to'landi, kvitansiya 12", 'Belgilash');
    await waitFor(() => expect(callsTo('POST', `/pos/invoices/${INVOICE}/paid`)).toHaveLength(1));
    expect(callsTo('POST', `/pos/invoices/${INVOICE}/paid`)[0]?.body).toEqual({ reason: "Naqd to'landi, kvitansiya 12" });
    expect(await screen.findByText("Hisob-faktura to'langan deb belgilandi.")).toBeInTheDocument();
  });

  it('shows no money buttons to a member who only reads', async () => {
    mockFetch({ ...ME(['finance.view']), [`GET /pos/invoices/${INVOICE}`]: () => ok(invoiceCard()) });
    renderPage(<InvoicesTab selected={INVOICE} onSelect={() => {}} />);
    await screen.findByRole('heading', { name: 'OB-202610-000015' });
    expect(screen.queryByRole('button', { name: "To'langan deb belgilash" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Bekor qilish' })).not.toBeInTheDocument();
  });

  it('says a large void waits for a second member instead of calling it done', async () => {
    const pending = invoiceCard();
    pending.invoice = { ...pending.invoice, status: 'issued', void_request: { by: { id: 3, name: 'Moliya Aziza' }, at: '2026-11-05T10:00:00+05:00', reason: 'Xato yozilgan' }, void_needs_second_approval: true };
    mockFetch({
      ...ME(['finance.view', 'finance.manage']),
      [`GET /pos/invoices/${INVOICE}`]: () => ok(invoiceCard()),
      [`POST /pos/invoices/${INVOICE}/void`]: () => ok(pending),
    });
    const user = userEvent.setup();
    renderPage(<InvoicesTab selected={INVOICE} onSelect={() => {}} />);
    await user.click(await screen.findByRole('button', { name: 'Bekor qilish' }));
    await reasonDialog(user, 'Hisob-fakturani bekor qilish', 'Xato yozilgan', 'Ha, bekor qilish');
    expect(await screen.findByText("Bekor qilish so'raldi: summa katta, uni boshqa xodim tasdiqlashi kerak.")).toBeInTheDocument();
    expect(screen.queryByText('Hisob-faktura bekor qilindi.')).not.toBeInTheDocument();
  });

  it('shows the answer of a refused void inside the dialog', async () => {
    mockFetch({
      ...ME(['finance.view', 'finance.manage']),
      [`GET /pos/invoices/${INVOICE}`]: () => ok(invoiceCard()),
      [`POST /pos/invoices/${INVOICE}/void`]: () => refusal(422, 'Hisob-faktura bekor qilinmadi.', { reason: 'billing_rule' }, { code: 'billing_rule' }),
    });
    const user = userEvent.setup();
    renderPage(<InvoicesTab selected={INVOICE} onSelect={() => {}} />);
    await user.click(await screen.findByRole('button', { name: 'Bekor qilish' }));
    const dialog = await reasonDialog(user, 'Hisob-fakturani bekor qilish', 'Xato yozilgan', 'Ha, bekor qilish');
    expect(await within(dialog).findByText('Hisob-faktura bekor qilinmadi.')).toBeInTheDocument();
  });
});

describe('DevicesTab', () => {
  it('lists devices by state and outdated version and sends the filters', async () => {
    const { callsTo } = mockFetch({ ...ME(['devices.view']), 'GET /pos/devices': () => ok(devicePage()) });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const list = await screen.findByRole('list', { name: "Qurilmalar ro'yxati" });
    const row = within(list).getByRole('button', { name: 'Kassa 1 qurilmasini ochish' });
    expect(within(row).getByText('Oflayn')).toBeInTheDocument();
    expect(within(row).getByText('Eski versiya')).toBeInTheDocument();
    expect(within(row).getByText('Navbatda 3 ta amal')).toBeInTheDocument();
    expect(within(row).getByText('oxirgi aloqa 25 daqiqa oldin')).toBeInTheDocument();
    expect(screen.getByText('Eng yangi versiyalar: Kassa 1.6.2')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Oflayn (1)' }));
    await waitFor(() => expect(callsTo('GET', '/pos/devices').at(-1)?.query.get('state')).toBe('offline'));
    await user.click(screen.getByRole('button', { name: 'Eski versiya (1)' }));
    await waitFor(() => expect(callsTo('GET', '/pos/devices').at(-1)?.query.get('outdated')).toBe('1'));
  });

  it('names the device types in the filter, the customer display included (P6-14)', async () => {
    const page = { ...devicePage([deviceRow({ name: 'Mijoz ekrani 1', type: 'display', type_label: 'Mijoz ekrani' })]), latest_versions: { display: '0.1.0' } };
    page.counts = { ...page.counts, by_type: { display: 1, kds: 1, pos: 2 } };
    const { callsTo } = mockFetch({ ...ME(['devices.view']), 'GET /pos/devices': () => ok(page) });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const list = await screen.findByRole('list', { name: "Qurilmalar ro'yxati" });
    expect(within(list).getByText('Mijoz ekrani')).toBeInTheDocument();
    expect(screen.getByText('Eng yangi versiyalar: Mijoz ekrani 0.1.0')).toBeInTheDocument();
    const type = screen.getByLabelText('Turi');
    expect(within(type).getAllByRole('option').map((option) => option.textContent)).toEqual(['Hammasi', 'Mijoz ekrani', 'Oshxona ekrani (KDS)', 'Kassa']);
    await user.selectOptions(type, 'Mijoz ekrani');
    await waitFor(() => expect(callsTo('GET', '/pos/devices').at(-1)?.query.get('type')).toBe('display'));
  });

  it('revokes a device with a reason', async () => {
    const { callsTo } = mockFetch({
      ...ME(['devices.view', 'stores.manage']),
      [`GET /pos/devices/${DEVICE}`]: () => ok(deviceRow()),
      [`POST /pos/devices/${DEVICE}/revoke`]: () => ok(deviceRow({ state: 'revoked', revoked_at: '2026-11-10T14:00:00+05:00' })),
    });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={DEVICE} onSelect={() => {}} />);

    expect(await screen.findByRole('heading', { name: 'Kassa 1' })).toBeInTheDocument();
    expect(screen.getByText(/soat noto'g'ri qo'yilgan/)).toBeInTheDocument();
    await user.click(await screen.findByRole('button', { name: "Qurilmani o'chirish" }));
    await reasonDialog(user, "Qurilmani o'chirish", "Kassa o'g'irlangan", "Ha, o'chirish");
    await waitFor(() => expect(callsTo('POST', `/pos/devices/${DEVICE}/revoke`)[0]?.body).toEqual({ reason: "Kassa o'g'irlangan" }));
    expect(await screen.findByText("Qurilma o'chirildi. Undagi barcha sessiyalar yopildi.")).toBeInTheDocument();
  });

  it('does not offer to revoke to a member who only reads', async () => {
    mockFetch({ ...ME(['devices.view']), [`GET /pos/devices/${DEVICE}`]: () => ok(deviceRow()) });
    renderPage(<DevicesTab selected={DEVICE} onSelect={() => {}} />);
    await screen.findByRole('heading', { name: 'Kassa 1' });
    expect(screen.queryByRole('button', { name: "Qurilmani o'chirish" })).not.toBeInTheDocument();
  });
});

describe('FiscalTab', () => {
  const summary = overview().fiscal;

  it('lists failed and pending receipts with the provider reason and the errors per provider', async () => {
    const { callsTo } = mockFetch({
      ...ME(['devices.view']),
      'GET /pos/fiscal-receipts': () => ok(page([fiscalRow(), fiscalRow({ uuid: 'f2', status: 'pending', error: null, driver: 'external', provider: 'payme', retryable: false })], { summary })),
    });
    const user = userEvent.setup();
    renderPage(<FiscalTab selected={null} onSelect={() => {}} />);

    const providers = await screen.findByRole('list', { name: "Provayder bo'yicha xatolar" });
    expect(providers).toHaveTextContent('sandbox');
    expect(providers).toHaveTextContent('Yiqilgan: 2, kutilayotgan: 1');
    const list = screen.getByRole('list', { name: "Fiskal cheklar ro'yxati" });
    expect(within(list).getByText('Soliq serveri javob bermadi')).toBeInTheDocument();
    expect(within(list).getByText("Payme/Click ro'yxatdan o'tkazgan")).toBeInTheDocument();

    await user.click(within(providers).getByRole('button', { name: /sandbox/ }));
    await waitFor(() => expect(callsTo('GET', '/pos/fiscal-receipts').at(-1)?.query.get('driver')).toBe('sandbox'));
  });

  it('retries a receipt and says whether the provider took it', async () => {
    let answer = fiscalCard({ status: 'failed', error: "MXIK kodi noto'g'ri" });
    const { callsTo } = mockFetch({
      ...ME(['devices.view', 'stores.manage']),
      [`GET /pos/fiscal-receipts/${FISCAL}`]: () => ok(fiscalCard()),
      [`POST /pos/fiscal-receipts/${FISCAL}/retry`]: () => ok(answer),
    });
    const user = userEvent.setup();
    renderPage(<FiscalTab selected={FISCAL} onSelect={() => {}} />);

    await user.click(await screen.findByRole('button', { name: 'Qayta yuborish' }));
    await reasonDialog(user, 'Chekni qayta yuborish', 'MXIK tuzatildi', 'Yuborish');
    await waitFor(() => expect(callsTo('POST', `/pos/fiscal-receipts/${FISCAL}/retry`)[0]?.body).toEqual({ reason: 'MXIK tuzatildi' }));
    expect(await screen.findByText("Chek hali ro'yxatdan o'tmadi: MXIK kodi noto'g'ri")).toBeInTheDocument();

    answer = fiscalCard({ status: 'issued', error: null, retryable: false });
    await user.click(screen.getByRole('button', { name: 'Qayta yuborish' }));
    await reasonDialog(user, 'Chekni qayta yuborish', 'Yana urinish', 'Yuborish');
    expect(await screen.findByText("Chek ro'yxatdan o'tdi.")).toBeInTheDocument();
  });

  it('explains that a Payme or Click receipt is not retried here', async () => {
    mockFetch({ ...ME(['devices.view', 'stores.manage']), [`GET /pos/fiscal-receipts/${FISCAL}`]: () => ok(fiscalCard({ status: 'pending', driver: 'external', provider: 'click', retryable: false })) });
    renderPage(<FiscalTab selected={FISCAL} onSelect={() => {}} />);
    expect(await screen.findByText(/Payme yoki Click o'zi ro'yxatdan o'tkazadi/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Qayta yuborish' })).not.toBeInTheDocument();
  });
});

describe('SyncTab', () => {
  it('lists unresolved errors and resolves one with a reason', async () => {
    const counts = { rejected: 1, conflict: 1, open: 2 };
    const { callsTo } = mockFetch({
      ...ME(['devices.view', 'stores.manage']),
      'GET /pos/sync-errors': () => ok(page([syncRow(), syncRow({ op_id: 'op2', status: 'conflict', type_label: 'Buyurtma ochildi', message: 'Osh tugagan edi' })], { counts })),
      [`GET /pos/sync-errors/${OP}`]: () => ok(syncCard()),
      [`POST /pos/sync-errors/${OP}/resolve`]: () => ok(syncCard({ resolution: 'resolved', resolved_at: '2026-11-10T14:00:00+05:00', resolved_by: { id: 1, name: 'Aziza' } })),
    });
    const user = userEvent.setup();
    renderPage(<SyncTab selected={null} onSelect={() => {}} />);

    const list = await screen.findByRole('list', { name: "Sinxron xatolari ro'yxati" });
    expect(within(list).getByText('Buyurtma bekor qilingan')).toBeInTheDocument();
    expect(within(list).getByText('Qabul qilinmadi')).toBeInTheDocument();
    expect(within(list).getByText('Farq bilan qabul qilindi')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Qabul qilinmagan (1)' }));
    await waitFor(() => expect(callsTo('GET', '/pos/sync-errors').at(-1)?.query.get('status')).toBe('rejected'));
  });

  it('resolves an error from its card', async () => {
    const { callsTo } = mockFetch({
      ...ME(['devices.view', 'stores.manage']),
      [`GET /pos/sync-errors/${OP}`]: () => ok(syncCard()),
      [`POST /pos/sync-errors/${OP}/resolve`]: () => ok(syncCard({ resolution: 'resolved' })),
    });
    const user = userEvent.setup();
    renderPage(<SyncTab selected={OP} onSelect={() => {}} />);

    expect(await screen.findByRole('heading', { name: /To'lov olindi · Lazzat Express/ })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: "Kassa yuborgan ma'lumot" })).toHaveTextContent('"amount": "50000"');
    await user.click(await screen.findByRole('button', { name: 'Hal qilindi' }));
    await reasonDialog(user, 'Hal qilindi deb belgilash', "Qo'lda kiritildi", 'Belgilash');
    await waitFor(() => expect(callsTo('POST', `/pos/sync-errors/${OP}/resolve`)[0]?.body).toEqual({ resolution: 'resolved', reason: "Qo'lda kiritildi" }));
    expect(await screen.findByText('Hal qilindi deb belgilandi.')).toBeInTheDocument();
  });
});
