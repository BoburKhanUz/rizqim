import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, ok, refusal, renderPage } from '@/test/helpers';
import { mockMarketingFetch } from '../marketing/fixtures';
import { deviceRow, devicePage } from './fixtures';
import { DevicesTab } from './DevicesTab';

// M5 S4: the support person uploads a till's `.rzq` queue file (POST /pos/sync-import, stores.manage, with a reason)

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const MANAGE = ['devices.view', 'stores.manage'];
const TOTALS = { operations: 12, applied: 9, conflict: 1, rejected: 0, duplicate: 2, skipped: 3, pending: 0, renumbered: 0 };
const answer = (over: Record<string, unknown> = {}, totals: Record<string, number> = {}) =>
  json({ success: true, message: "Fayl o'qildi: 9 ta amal yozildi, 2 tasi avval kelgan.", data: { complete: true, stopped_reason: null, totals: { ...TOTALS, ...totals }, results: [], skipped: [], ...over } });
const rzq = () => new File(['{"format":"rizqim-sync-export"}\n'], 'rizqim-navbat.rzq');

async function open(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole('button', { name: 'Navbat faylini import qilish' }));
  const dialog = await screen.findByRole('dialog', { name: 'Kassa navbatini fayldan tiklash' });
  const submit = within(dialog).getByRole('button', { name: 'Import qilish' });
  expect(submit).toBeDisabled();
  await user.upload(within(dialog).getByLabelText('Navbat fayli (.rzq)'), rzq());
  expect(submit).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), 'Kassa buzildi, egasi so‘radi');
  return { dialog, submit };
}

describe('SyncImportDialog', () => {
  it('uploads the file with the reason and shows the server message and the totals', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /pos/devices': () => ok(devicePage([deviceRow()])),
      'POST /pos/sync-import': () => answer({ skipped: [{ line: 4, op_id: null, seq: null, device: null, reason: 'invalid_payload', message: "Qator o'qilmadi." }] }),
    });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const { dialog, submit } = await open(user);
    await user.click(submit);

    expect(await screen.findByText("Fayl o'qildi: 9 ta amal yozildi, 2 tasi avval kelgan.")).toBeInTheDocument();
    for (const line of ['Yozildi: 9', 'Avval kelgan: 2', 'Rad etilgan: 0', 'Ziddiyat: 1', "O'tkazib yuborilgan: 3", "4-qator: Qator o'qilmadi."]) expect(screen.getByText(line)).toBeInTheDocument();
    expect(screen.queryByText(/yana bir marta yuklang/)).not.toBeInTheDocument();
    expect(callsTo('POST', '/pos/sync-import')[0]?.body).toEqual({ reason: 'Kassa buzildi, egasi so‘radi', file: expect.objectContaining({ size: 32 }) });
    // Done: only a close button is left
    expect(screen.queryByRole('button', { name: 'Shu faylni yana yuklash' })).not.toBeInTheDocument();
    await user.click(within(screen.getByRole('dialog')).getAllByRole('button', { name: 'Yopish' })[0]!);
    await waitFor(() => expect(dialog).not.toBeInTheDocument());
  });

  it('asks to upload the same file once more when it was not read completely, and the retry sends it again with the same reason', async () => {
    let round = 0;
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /pos/devices': () => ok(devicePage([deviceRow()])),
      'POST /pos/sync-import': () => (++round === 1 ? answer({ complete: false, stopped_reason: 'time_budget' }, { applied: 5, pending: 7 }) : answer({}, { duplicate: 5, applied: 7 })),
    });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const { submit } = await open(user);
    await user.click(submit);
    expect(await screen.findByText("Fayl to'liq o'qilmadi: 7 ta amal qoldi. Shu faylni yana bir marta yuklang.")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Shu faylni yana yuklash' }));
    await waitFor(() => expect(callsTo('POST', '/pos/sync-import')).toHaveLength(2));
    expect(callsTo('POST', '/pos/sync-import')[1]?.body).toEqual(callsTo('POST', '/pos/sync-import')[0]?.body);
    expect(await screen.findByText('Yozildi: 7')).toBeInTheDocument();
    expect(screen.queryByText(/yana bir marta yuklang/)).not.toBeInTheDocument();
  });

  it('shows a whole-file refusal as the server worded it', async () => {
    mockMarketingFetch({
      ...ME(MANAGE),
      'GET /pos/devices': () => ok(devicePage([deviceRow()])),
      'POST /pos/sync-import': () => refusal(422, "Fayl imzosi noto'g'ri: u o'zgartirilgan yoki to'liq emas.", { reason: 'file_mac_invalid' }),
    });
    const user = userEvent.setup();
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);

    const { dialog, submit } = await open(user);
    await user.click(submit);
    expect(await within(dialog).findByText("Fayl imzosi noto'g'ri: u o'zgartirilgan yoki to'liq emas.")).toBeInTheDocument();
    expect(within(dialog).queryByText(/Yozildi/)).not.toBeInTheDocument();
  });

  it('is offered only with stores.manage', async () => {
    mockMarketingFetch({ ...ME(['devices.view']), 'GET /pos/devices': () => ok(devicePage([deviceRow()])) });
    renderPage(<DevicesTab selected={null} onSelect={() => {}} />);
    await screen.findByRole('list', { name: "Qurilmalar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'Navbat faylini import qilish' })).not.toBeInTheDocument();
  });
});
