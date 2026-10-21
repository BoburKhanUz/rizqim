import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { BranchesView } from './BranchesView';
import { LOCATIONS, makeBranch, ok, permissions, refusal, signIn } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const BRANCHES = [
  makeBranch(),
  makeBranch({ id: 2, name: 'Tungi filial', is_open_now: false, working_hours: { open: '18:00:00', close: '02:00:00', all_day: false, overnight: true, timezone: 'Asia/Tashkent' } }),
  makeBranch({ id: 3, name: 'Sutkalik', status: 'temporarily_closed', working_hours: { open: null, close: null, all_day: true, overnight: false, timezone: 'Asia/Tashkent' } }),
];

const base = (over: Parameters<typeof mockFetch>[0] = {}, keys?: string[]) =>
  mockFetch({
    'GET /api/v1/me/permissions': () => permissions(keys),
    'GET /api/v1/owner/branches': () => ok(BRANCHES),
    'GET /api/v1/locations': () => ok(LOCATIONS),
    ...over,
  });

const card = async (name: string) => within((await screen.findByRole('heading', { name })).closest('li') as HTMLElement);

describe('BranchesView: the list', () => {
  it('shows the working hours (a window, overnight, 24 hours) and the open-now badge the server computed', async () => {
    base();
    renderPage(<BranchesView />);

    const main = await card('Markaz');
    expect(main.getByText('09:00–22:00')).toBeInTheDocument();
    expect(main.getByText('Hozir ochiq')).toBeInTheDocument();
    expect(main.getByText('Faol')).toBeInTheDocument();

    const night = await card('Tungi filial');
    expect(night.getByText('18:00–02:00 (tungi)')).toBeInTheDocument();
    expect(night.getByText('Hozir yopiq')).toBeInTheDocument();

    const allDay = await card('Sutkalik');
    expect(allDay.getByText('24 soat')).toBeInTheDocument();
    expect(allDay.getByText('Vaqtincha yopiq')).toBeInTheDocument();
  });

  it('hides create and delete from a manager who may only edit', async () => {
    base({}, ['branches.manage', 'orders.view']);
    renderPage(<BranchesView />);
    const main = await card('Markaz');
    await waitFor(() => expect(main.getByRole('button', { name: 'Markaz: tahrirlash' })).toBeInTheDocument());
    expect(main.queryByRole('button', { name: "Markaz: o'chirish" })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Filial qo'shish" })).not.toBeInTheDocument();
  });

  it('shows the error with a retry when the list does not load', async () => {
    base({ 'GET /api/v1/owner/branches': () => refusal('Ruxsat etilmagan', 403) });
    renderPage(<BranchesView />);
    expect(await screen.findByRole('alert')).toHaveTextContent('Ruxsat etilmagan');
  });
});

describe('BranchesView: create and edit', () => {
  it('creates a branch with an overnight window', async () => {
    const { calls } = base({ 'POST /api/v1/owner/branches': () => ok(makeBranch({ id: 9, name: 'Yangi' }), 201) });
    const typing = userEvent.setup();
    renderPage(<BranchesView />);
    await typing.click(await screen.findByRole('button', { name: "Filial qo'shish" }));
    const dialog = within(await screen.findByRole('dialog'));

    await typing.click(dialog.getByRole('button', { name: "Filial qo'shish" }));
    expect(dialog.getByText('Nomini kiriting.')).toBeInTheDocument();
    expect(dialog.getByText('Manzilni kiriting.')).toBeInTheDocument();
    expect(dialog.getByText('Viloyatni tanlang.')).toBeInTheDocument();

    await typing.type(dialog.getByLabelText('Nomi'), 'Yangi');
    await typing.type(dialog.getByLabelText('Manzil'), 'Bobur 5');
    await typing.selectOptions(dialog.getByLabelText('Viloyat'), 'Toshkent');
    // Only the districts of the chosen region are offered
    expect(dialog.queryByRole('option', { name: 'Registon' })).not.toBeInTheDocument();
    await typing.selectOptions(dialog.getByLabelText('Tuman'), 'Yunusobod');
    await typing.type(dialog.getByLabelText('Kenglik'), '41.35');
    await typing.type(dialog.getByLabelText('Uzunlik'), '69.28');
    await typing.type(dialog.getByLabelText('Yetkazish radiusi'), '6,5');

    await typing.click(dialog.getByRole('checkbox', { name: /24 soat/ }));
    await typing.clear(dialog.getByLabelText('Ochilish'));
    await typing.type(dialog.getByLabelText('Ochilish'), '18:00');
    await typing.clear(dialog.getByLabelText('Yopilish'));
    await typing.type(dialog.getByLabelText('Yopilish'), '02:00');
    expect(dialog.getByText(/ertasi kuni shu vaqtgacha ishlaydi/)).toBeInTheDocument();
    await typing.click(dialog.getByRole('button', { name: "Filial qo'shish" }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({
      name: 'Yangi',
      address: 'Bobur 5',
      region_id: 1,
      district_id: 11,
      latitude: 41.35,
      longitude: 69.28,
      radius_km: 6.5,
      status: 'active',
      is_active: true,
      open_time: '18:00',
      close_time: '02:00',
    });
    expect(screen.getByRole('status')).toHaveTextContent("Yangi filiali qo'shildi.");
  });

  it('sends only the hours when only the hours changed, and null times for 24 hours', async () => {
    const { calls } = base({ 'PATCH /api/v1/owner/branches/1': () => ok(makeBranch()) });
    const typing = userEvent.setup();
    renderPage(<BranchesView />);
    await typing.click(await (await card('Markaz')).findByRole('button', { name: 'Markaz: tahrirlash' }));
    const dialog = within(await screen.findByRole('dialog'));
    expect(dialog.getByLabelText('Ochilish')).toHaveValue('09:00');

    await typing.click(dialog.getByRole('checkbox', { name: /24 soat/ }));
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({ open_time: null, close_time: null });
  });

  it('shows the server reason next to the field it belongs to', async () => {
    base({ 'PATCH /api/v1/owner/branches/1': () => refusal('Xato', 422, { errors: { latitude: ['Kenglik -90 va 90 orasida bo\'lsin.'] } }) });
    const typing = userEvent.setup();
    renderPage(<BranchesView />);
    await typing.click(await (await card('Markaz')).findByRole('button', { name: 'Markaz: tahrirlash' }));
    const dialog = within(await screen.findByRole('dialog'));
    await typing.clear(dialog.getByLabelText('Kenglik'));
    await typing.type(dialog.getByLabelText('Kenglik'), '95');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));
    expect(await dialog.findByText("Kenglik -90 va 90 orasida bo'lsin.")).toBeInTheDocument();
  });
});

describe('BranchesView: delete', () => {
  it('deletes after the confirmation', async () => {
    const { calls } = base({ 'DELETE /api/v1/owner/branches/3': () => ok(null) });
    const typing = userEvent.setup();
    renderPage(<BranchesView />);
    await typing.click(await (await card('Sutkalik')).findByRole('button', { name: "Sutkalik: o'chirish" }));
    expect(calls.some((call) => call.method === 'DELETE')).toBe(false);
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent("Sutkalik o'chirildi.");
  });

  it('shows the refusal of a branch in use and offers to deactivate it instead', async () => {
    const { calls } = base({
      'DELETE /api/v1/owner/branches/1': () => refusal("Filialda buyurtmalar bor, uni o'chirib bo'lmaydi.", 422, { data: { reason: 'branch_in_use' } }),
      'PATCH /api/v1/owner/branches/1': () => ok(makeBranch({ is_active: false, status: 'inactive' })),
    });
    const typing = userEvent.setup();
    renderPage(<BranchesView />);
    await typing.click(await (await card('Markaz')).findByRole('button', { name: "Markaz: o'chirish" }));
    const dialog = within(await screen.findByRole('alertdialog'));
    await typing.click(dialog.getByRole('button', { name: "O'chirish" }));
    expect(await dialog.findByRole('alert')).toHaveTextContent("Filialda buyurtmalar bor, uni o'chirib bo'lmaydi.");

    await typing.click(dialog.getByRole('button', { name: 'Faol emas qilish' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({ is_active: false, status: 'inactive' });
    expect(screen.getByRole('status')).toHaveTextContent('Markaz faol emas qilindi.');
  });
});
