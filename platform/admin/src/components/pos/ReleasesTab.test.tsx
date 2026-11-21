import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import type { ReleaseRow } from '@/lib/api/releases';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { ReleasesTab, sizeText } from './ReleasesTab';

// M5 S5-d: the "Versiyalar" tab: the registry of POS app versions (reads devices.view, writes stores.manage with a reason)

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const MANAGE = ['devices.view', 'stores.manage'];
const SHA = 'a'.repeat(64);
const ID = '3b1c0000-0000-4000-8000-000000000001';

const release = (over: Partial<ReleaseRow> = {}): ReleaseRow => ({
  uuid: ID,
  app: 'cashier',
  platform: 'android',
  version: '1.4.0',
  build: 7,
  label: '1.4.0+7',
  file_url: 'https://pos.rizqim.uz/files/cashier/android/rizqim-cashier-1.4.0+7-arm64.apk',
  sha256: SHA,
  size_bytes: 25_165_824,
  notes: 'Yangi chek dizayni',
  released_at: '2026-11-20T10:00:00+05:00',
  is_minimum: false,
  minimum_from: null,
  withdrawn_at: null,
  is_latest: true,
  created_at: '2026-11-20T10:00:00+05:00',
  ...over,
});
const OLD = release({ uuid: '3b1c0000-0000-4000-8000-000000000002', version: '1.3.0', build: 5, label: '1.3.0+5', is_latest: false, is_minimum: true, minimum_from: '2026-12-05T00:00:00+05:00', notes: null });

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  const button = within(dialog).getByRole('button', { name: confirm });
  expect(button).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  expect(button).toBeEnabled();
  await user.click(button);
  return dialog;
}

describe('ReleasesTab', () => {
  it('lists the versions with their marks and sends the filters', async () => {
    const { callsTo } = mockFetch({ ...ME(MANAGE), 'GET /pos/releases': () => ok({ data: [release(), OLD] }) });
    const user = userEvent.setup();
    renderPage(<ReleasesTab />);

    const list = await screen.findByRole('list', { name: "Versiyalar ro'yxati" });
    const items = within(list).getAllByRole('listitem');
    expect(items).toHaveLength(2);
    expect(within(items[0]!).getByText('1.4.0+7')).toBeInTheDocument();
    expect(within(items[0]!).getByText('Eng yangi')).toBeInTheDocument();
    expect(within(items[0]!).getByText('24,0 MB')).toBeInTheDocument();
    expect(within(items[0]!).getByText('Yangi chek dizayni')).toBeInTheDocument();
    expect(within(items[1]!).getByText(/Minimum · .*dek.* dan/)).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText('Ilova'), 'Ofitsiant');
    await waitFor(() => expect(callsTo('GET', '/pos/releases').at(-1)?.query.get('app')).toBe('waiter'));
    await user.selectOptions(screen.getByLabelText('Platforma'), 'Windows');
    await waitFor(() => expect(callsTo('GET', '/pos/releases').at(-1)?.query.get('platform')).toBe('windows'));
    await user.click(screen.getByRole('button', { name: 'Olib tashlangan' }));
    await waitFor(() => expect(callsTo('GET', '/pos/releases').at(-1)?.query.get('withdrawn')).toBe('1'));
  });

  it('says so when there is no version yet', async () => {
    mockFetch({ ...ME(MANAGE), 'GET /pos/releases': () => ok({ data: [] }) });
    renderPage(<ReleasesTab />);
    expect(await screen.findByText("Versiya yo'q")).toBeInTheDocument();
  });

  it('adds a version: the button stays off until the fields are valid, then sends them with the reason', async () => {
    const { callsTo } = mockFetch({
      ...ME(MANAGE),
      'GET /pos/releases': () => ok({ data: [] }),
      'POST /pos/releases': () => json({ success: true, message: '', data: release() }, 201),
    });
    const user = userEvent.setup();
    renderPage(<ReleasesTab />);

    await user.click(await screen.findByRole('button', { name: "Versiya qo'shish" }));
    const dialog = await screen.findByRole('dialog', { name: "Yangi versiya qo'shish" });
    await user.selectOptions(within(dialog).getByLabelText('Ilova'), 'Ofitsiant');
    await user.selectOptions(within(dialog).getByLabelText('Platforma'), 'Windows');
    await user.type(within(dialog).getByLabelText('Versiya'), '1.4.0');
    await user.type(within(dialog).getByLabelText('Build raqami'), '7');
    await user.type(within(dialog).getByLabelText('Fayl manzili (https)'), 'http://pos.rizqim.uz/x.zip');
    await user.type(within(dialog).getByLabelText('SHA-256 (64 ta belgi)'), SHA);
    await user.type(within(dialog).getByLabelText('Hajmi (bayt)'), '1000');
    expect(within(dialog).getByRole('button', { name: "Qo'shish" })).toBeDisabled();
    expect(within(dialog).getByText("Maydonni to'g'ri to'ldiring")).toBeInTheDocument();
    await user.clear(within(dialog).getByLabelText('Fayl manzili (https)'));
    await user.type(within(dialog).getByLabelText('Fayl manzili (https)'), 'https://pos.rizqim.uz/files/waiter/windows/x.zip');
    await user.type(within(dialog).getByLabelText('Izoh (2000 belgigacha)'), 'Yangi');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Yangi versiya chiqdi');
    await user.click(within(dialog).getByRole('button', { name: "Qo'shish" }));

    expect(await screen.findByText("Versiya qo'shildi.")).toBeInTheDocument();
    expect(callsTo('POST', '/pos/releases')[0]?.body).toEqual({
      app: 'waiter',
      platform: 'windows',
      version: '1.4.0',
      build: 7,
      file_url: 'https://pos.rizqim.uz/files/waiter/windows/x.zip',
      sha256: SHA,
      size_bytes: 1000,
      notes: 'Yangi',
      is_minimum: false,
      minimum_from: null,
      reason: 'Yangi versiya chiqdi',
    });
  });

  it('edits only what may change, sending the reason', async () => {
    const { callsTo } = mockFetch({
      ...ME(MANAGE),
      'GET /pos/releases': () => ok({ data: [release()] }),
      [`PATCH /pos/releases/${ID}`]: () => ok(release({ is_minimum: true })),
    });
    const user = userEvent.setup();
    renderPage(<ReleasesTab />);

    await user.click(await screen.findByRole('button', { name: 'Kassa Android 1.4.0+7 ni tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Kassa Android 1.4.0+7 versiyasini tahrirlash' });
    expect(within(dialog).queryByLabelText('Versiya')).not.toBeInTheDocument();
    await user.click(within(dialog).getByRole('switch', { name: /Minimal versiya/ }));
    await user.type(within(dialog).getByLabelText('Sabab'), 'Eskilari yangilansin');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Saqlandi.')).toBeInTheDocument();
    expect(callsTo('PATCH', `/pos/releases/${ID}`)[0]?.body).toMatchObject({ is_minimum: true, minimum_from: null, reason: 'Eskilari yangilansin', size_bytes: 25_165_824 });
  });

  it('withdraws a version with a reason, restores a withdrawn one, and deletes with a reason', async () => {
    const withdrawn = release({ withdrawn_at: '2026-11-21T09:00:00+05:00', is_latest: false });
    const { callsTo } = mockFetch({
      ...ME(MANAGE),
      'GET /pos/releases': () => ok({ data: [release(), withdrawn].map((row, index) => ({ ...row, uuid: index === 0 ? ID : '3b1c0000-0000-4000-8000-0000000000aa' })) }),
      [`POST /pos/releases/${ID}/withdraw`]: () => ok(withdrawn),
      'POST /pos/releases/3b1c0000-0000-4000-8000-0000000000aa/restore': () => ok(release()),
      'DELETE /pos/releases/3b1c0000-0000-4000-8000-0000000000aa': () => ok({ uuid: 'x' }),
    });
    const user = userEvent.setup();
    renderPage(<ReleasesTab />);

    const list = await screen.findByRole('list', { name: "Versiyalar ro'yxati" });
    const [first, second] = within(list).getAllByRole('listitem') as [HTMLElement, HTMLElement];
    expect(within(first).queryByRole('button', { name: 'Qaytarish' })).not.toBeInTheDocument();
    expect(within(second).queryByRole('button', { name: 'Olib tashlash' })).not.toBeInTheDocument();

    await user.click(within(first).getByRole('button', { name: 'Olib tashlash' }));
    await reasonDialog(user, 'Kassa Android 1.4.0+7 ni olib tashlash', 'Xato fayl', 'Olib tashlash');
    expect(await screen.findByText('Versiya olib tashlandi.')).toBeInTheDocument();
    expect(callsTo('POST', `/pos/releases/${ID}/withdraw`)[0]?.body).toEqual({ reason: 'Xato fayl' });

    await user.click(within(second).getByRole('button', { name: 'Qaytarish' }));
    await reasonDialog(user, 'Kassa Android 1.4.0+7 ni qaytarish', 'Fayl tuzatildi', 'Qaytarish');
    expect(await screen.findByText('Versiya qaytarildi.')).toBeInTheDocument();

    await user.click(within(second).getByRole('button', { name: "O'chirish" }));
    await reasonDialog(user, "Kassa Android 1.4.0+7 ni o'chirish", 'Xato qo‘shilgan', "O'chirish");
    expect(await screen.findByText("Versiya o'chirildi.")).toBeInTheDocument();
    expect(callsTo('DELETE', '/pos/releases/3b1c0000-0000-4000-8000-0000000000aa')[0]?.body).toEqual({ reason: 'Xato qo‘shilgan' });
  });

  it('hides every write for a member who may only read', async () => {
    mockFetch({ ...ME(['devices.view']), 'GET /pos/releases': () => ok({ data: [release()] }) });
    renderPage(<ReleasesTab />);

    expect(await screen.findByText('1.4.0+7')).toBeInTheDocument();
    for (const name of ["Versiya qo'shish", 'Tahrirlash', 'Olib tashlash', "O'chirish"]) expect(screen.queryByRole('button', { name })).not.toBeInTheDocument();
  });
});

describe('sizeText', () => {
  it('says the size in KB or MB', () => {
    expect(sizeText(500)).toBe('1 KB');
    expect(sizeText(2048)).toBe('2 KB');
    expect(sizeText(12_345_678)).toBe('11,8 MB');
  });
});
