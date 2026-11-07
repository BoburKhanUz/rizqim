import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const lan = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  key_id: 1,
  rotated_at: null,
  port: 18480,
  hub: { device: 'till-1', name: 'Kassa 1', chosen: false },
  candidates: [
    { uuid: 'till-1', name: 'Kassa 1', platform: 'windows', app: 'cashier', last_seen_at: '2026-11-07T08:00:00Z' },
    { uuid: 'till-2', name: 'Kassa 2', platform: 'android', app: null, last_seen_at: null },
  ],
  ...over,
});

const open = async (over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok({
        branch_id: 1,
        profile: 'restoran',
        profile_label: 'Restoran',
        mode: 'table',
        first_screen: 'floor',
        modules: [],
        locks: { service_charge_zero: false },
        module_rows: [],
        profiles: [],
        template: { profile: 'restoran', stations: [], dishes: [] },
      }),
    'GET /api/v1/owner/pos/lan': () => ok(lan()),
    ...over,
  });
  const typing = userEvent.setup();
  renderPage(<PosSetupView />);
  await typing.click(await screen.findByRole('tab', { name: 'Lokal tarmoq' }));
  return { ...mocked, typing };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('Lokal tarmoq (P6)', () => {
  it("shows the hub the server picked, the branch's tills with their app, the port and the key version", async () => {
    const { calls } = await open();
    expect(await screen.findByTestId('lan-hub')).toHaveTextContent('Hozir: Kassa 1');
    expect(screen.getByTestId('lan-hub')).toHaveTextContent('Avtomatik');
    const picker = screen.getByLabelText('Markaz');
    expect(picker).toHaveValue('');
    const options = within(picker).getAllByRole('option').map((option) => option.textContent);
    expect(options[0]).toMatch(/^Avtomatik/);
    expect(options[1]).toMatch(/^Kassa 1 · Kassa · windows · /);
    expect(options[2]).toBe("Kassa 2 · ilova noma'lum · android · hali ko'rinmagan");
    expect(screen.getByLabelText('Port')).toHaveValue('18480');
    expect(screen.getByTestId('lan-key')).toHaveTextContent('Kalit versiyasi: 1 · Hali almashtirilmagan');
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    expect(new URL(calls.find((call) => call.url.includes('/owner/pos/lan'))!.url).searchParams.get('branch_id')).toBe('1');
  });

  it('picks another till as the hub and sends only that, then shows it as chosen', async () => {
    const { calls, typing } = await open({ 'PUT /api/v1/owner/pos/lan': () => ok(lan({ hub: { device: 'till-2', name: 'Kassa 2', chosen: true } })) });
    await typing.selectOptions(await screen.findByLabelText('Markaz'), 'till-2');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Lokal tarmoq sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, hub_device_id: 'till-2' });
    expect(screen.getByTestId('lan-hub')).toHaveTextContent('Hozir: Kassa 2');
    expect(screen.getByTestId('lan-hub')).toHaveTextContent('Siz tanlagansiz');
    expect(screen.getByLabelText('Markaz')).toHaveValue('till-2');
  });

  it('goes back to the automatic hub with null and checks the port before saving', async () => {
    const { calls, typing } = await open({
      'GET /api/v1/owner/pos/lan': () => ok(lan({ hub: { device: 'till-2', name: 'Kassa 2', chosen: true } })),
      'PUT /api/v1/owner/pos/lan': () => ok(lan({ port: 18500 })),
    });
    await typing.selectOptions(await screen.findByLabelText('Markaz'), '');
    await typing.clear(screen.getByLabelText('Port'));
    await typing.type(screen.getByLabelText('Port'), '80');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("Port 1024 dan 65535 gacha butun son bo'lishi kerak.")).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
    await typing.clear(screen.getByLabelText('Port'));
    await typing.type(screen.getByLabelText('Port'), '18500');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, hub_device_id: null, port: 18500 });
  });

  it("shows the server's refusal of the hub under its field", async () => {
    const { typing } = await open({
      'PUT /api/v1/owner/pos/lan': () => json({ success: false, message: 'Xato', errors: { hub_device_id: ['Markaz kassa filialning faol kassasi emas.'] } }, 422),
    });
    await typing.selectOptions(await screen.findByLabelText('Markaz'), 'till-2');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Markaz kassa filialning faol kassasi emas.')).toBeInTheDocument();
  });

  it('changes the key only after the confirmation, and says when devices take it', async () => {
    const { calls, typing } = await open({ 'POST /api/v1/owner/pos/lan/rotate': () => ok(lan({ key_id: 2, rotated_at: '2026-11-07T09:00:00Z' })) });
    await typing.click(await screen.findByRole('button', { name: 'Kalitni almashtirish' }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('Kalit almashtirilsinmi?');
    expect(dialog).toHaveTextContent('keyingi kirishda');
    // Cancel: nothing is sent
    await typing.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));
    expect(calls.some((call) => call.method === 'POST')).toBe(false);

    await typing.click(screen.getByRole('button', { name: 'Kalitni almashtirish' }));
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Almashtirish' }));
    expect(await screen.findByText('Kalit almashtirildi: qurilmalar uni keyingi kirishda oladi.')).toBeInTheDocument();
    expect(await last(calls, 'POST').json()).toEqual({ branch_id: 1 });
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(screen.getByTestId('lan-key')).toHaveTextContent('Kalit versiyasi: 2 · Oxirgi almashtirilgan:');
  });

  it('says to pair a till first when the branch has none', async () => {
    await open({ 'GET /api/v1/owner/pos/lan': () => ok(lan({ hub: null, candidates: [] })) });
    expect(await screen.findByText("Filialda faol kassa yo'q: avval kassani ulang (Sozlamalar → Qurilmalar).")).toBeInTheDocument();
    expect(screen.queryByLabelText('Markaz')).not.toBeInTheDocument();
  });
});
