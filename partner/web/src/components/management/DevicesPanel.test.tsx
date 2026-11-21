import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { DevicesPanel } from './DevicesPanel';
import { makeDevice, ok, permissions, refusal, signIn } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const TOTALS = { operations: 12, applied: 9, conflict: 1, rejected: 0, duplicate: 2, skipped: 3, pending: 0, renumbered: 0 };
const answer = (over: Record<string, unknown> = {}, totals: Record<string, number> = {}) =>
  json({ success: true, message: "Fayl o'qildi: 9 ta amal yozildi, 2 tasi avval kelgan.", data: { complete: true, stopped_reason: null, totals: { ...TOTALS, ...totals }, results: [], skipped: [], ...over } });

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissions(keys),
    'GET /api/v1/owner/devices': () => ok([makeDevice()]),
    'GET /api/v1/owner/branches': () => ok([]),
    ...over,
  });

const pickFile = async (user: ReturnType<typeof userEvent.setup>) => {
  await user.click(await screen.findByRole('button', { name: 'Navbat faylini yuklash' }));
  const dialog = await screen.findByRole('dialog');
  await user.upload(within(dialog).getByLabelText('Navbat fayli (.rzq)'), new File(['{"format":"rizqim-sync-export"}\n'], 'rizqim-navbat.rzq'));
  return dialog;
};

describe('DevicesPanel: till queue file import', () => {
  it('uploads the file as multipart `file` and shows the server message with the totals', async () => {
    const user = userEvent.setup();
    const { calls } = base({ 'POST /api/v1/owner/pos/sync/import': () => answer({ skipped: [{ line: 4, op_id: null, seq: null, device: null, reason: 'invalid_payload', message: "Qator o'qilmadi." }] }) });
    renderPage(<DevicesPanel />);

    const dialog = await pickFile(user);
    await user.click(within(dialog).getByRole('button', { name: 'Yuklash' }));

    expect(await within(dialog).findByText("Fayl o'qildi: 9 ta amal yozildi, 2 tasi avval kelgan.")).toBeInTheDocument();
    expect(within(dialog).getByText('Yozildi: 9')).toBeInTheDocument();
    expect(within(dialog).getByText('Avval kelgan: 2')).toBeInTheDocument();
    expect(within(dialog).getByText('Rad etilgan: 0')).toBeInTheDocument();
    expect(within(dialog).getByText('Ziddiyat: 1')).toBeInTheDocument();
    expect(within(dialog).getByText("O'tkazib yuborilgan: 3")).toBeInTheDocument();
    expect(within(dialog).getByText("4-qator: Qator o'qilmadi.")).toBeInTheDocument();
    expect(within(dialog).queryByText(/yana bir marta yuklang/)).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Shu faylni yana yuklash' })).not.toBeInTheDocument();

    const upload = calls.find((request) => request.url.endsWith('/owner/pos/sync/import'))!;
    expect(upload.headers.get('content-type')).toMatch(/^multipart\/form-data/);
    expect(((await upload.formData()).get('file') as Blob).size).toBeGreaterThan(10);
  });

  it('says to upload the same file once more when it was not read completely, and the retry button sends it again', async () => {
    const user = userEvent.setup();
    let round = 0;
    const { calls } = base({
      'POST /api/v1/owner/pos/sync/import': () => (++round === 1 ? answer({ complete: false, stopped_reason: 'time_budget' }, { applied: 5, pending: 7 }) : answer({}, { duplicate: 5, applied: 7 })),
    });
    renderPage(<DevicesPanel />);

    const dialog = await pickFile(user);
    await user.click(within(dialog).getByRole('button', { name: 'Yuklash' }));
    expect(await within(dialog).findByText("Fayl to'liq o'qilmadi: 7 ta amal qoldi. Shu faylni yana bir marta yuklang.")).toBeInTheDocument();

    await user.click(within(dialog).getByRole('button', { name: 'Shu faylni yana yuklash' }));
    await waitFor(() => expect(within(dialog).queryByText(/yana bir marta yuklang/)).not.toBeInTheDocument());
    expect(within(dialog).getByText('Yozildi: 7')).toBeInTheDocument();
    const uploads = calls.filter((request) => request.url.endsWith('/owner/pos/sync/import'));
    expect(uploads).toHaveLength(2);
    expect(((await uploads[1]!.formData()).get('file') as Blob).size).toBeGreaterThan(10);
  });

  it('shows a whole-file refusal as the server worded it and applies nothing', async () => {
    const user = userEvent.setup();
    base({ 'POST /api/v1/owner/pos/sync/import': () => refusal("Fayl imzosi noto'g'ri: u o'zgartirilgan yoki to'liq emas.", 422, { data: { reason: 'file_mac_invalid' } }) });
    renderPage(<DevicesPanel />);

    const dialog = await pickFile(user);
    await user.click(within(dialog).getByRole('button', { name: 'Yuklash' }));
    expect(await within(dialog).findByText("Fayl imzosi noto'g'ri: u o'zgartirilgan yoki to'liq emas.")).toBeInTheDocument();
    expect(within(dialog).queryByText(/Yozildi/)).not.toBeInTheDocument();
  });

  it('offers the upload only with devices.manage', async () => {
    base({}, ['devices.view']);
    renderPage(<DevicesPanel />);
    await screen.findByText(makeDevice().name);
    expect(screen.queryByRole('button', { name: 'Navbat faylini yuklash' })).not.toBeInTheDocument();
  });
});
