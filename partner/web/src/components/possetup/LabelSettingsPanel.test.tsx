import { screen, waitFor } from '@testing-library/react';
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

const formatOf = (modules: string[]) => ({
  branch_id: 1,
  profile: 'qahvaxona',
  profile_label: 'Qahvaxona',
  mode: 'counter',
  first_screen: 'quick_counter',
  modules,
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { profile: 'qahvaxona', stations: [], dishes: [] },
});

const LABELS = {
  branch_id: 1,
  configured: false,
  size: '40x30',
  size_label: '40 × 30 mm',
  width_mm: 40,
  height_mm: 30,
  scope: 'all',
  scope_label: 'Hamma taomlarga',
  category_ids: [] as number[],
  per_unit: true,
  bag_label: true,
  updated_at: null,
};

const CATEGORIES = [
  { id: 2, uuid: 'c-2', parent_id: null, name: 'Issiq taomlar', slug: 'issiq', sort_order: 0, is_active: true },
  { id: 5, uuid: 'c-5', parent_id: null, name: 'Ichimliklar', slug: 'ichimliklar', sort_order: 1, is_active: true },
];

const open = async (modules: string[], over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(formatOf(modules)),
    'GET /api/v1/owner/categories': () => json({ success: true, data: CATEGORIES }),
    ...over,
  });
  renderPage(<PosSetupView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('the Stiker va yorliq tab', () => {
  it('is not on the page without the labels module', async () => {
    await open(['quick_counter']);
    await screen.findByRole('tab', { name: 'Format' });
    await waitFor(() => expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Format', 'Sexlar', "Yo'naltirish", 'Printerlar', 'Kassa sozlamalari']));
  });

  it('comes with the module and shows the defaults', async () => {
    const { typing } = await open(['labels'], { 'GET /api/v1/owner/branches/1/label-settings': () => ok(LABELS) });
    await typing.click(await screen.findByRole('tab', { name: 'Stiker va yorliq' }));

    expect(await screen.findByRole('combobox', { name: "Rulon o'lchami" })).toHaveValue('40x30');
    expect(screen.getByRole('combobox', { name: 'Stiker chiqadigan taomlar' })).toHaveValue('all');
    expect(screen.getByRole('checkbox', { name: /Har dona uchun alohida stiker/ })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /Olib ketish paketiga yorliq/ })).toBeChecked();
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    // The categories show only for the "chosen categories" scope
    expect(screen.queryByRole('checkbox', { name: 'Ichimliklar' })).not.toBeInTheDocument();
  });

  it('saves only what changed: the roll size and the chosen categories', async () => {
    const { typing, calls } = await open(['labels'], {
      'GET /api/v1/owner/branches/1/label-settings': () => ok(LABELS),
      'PUT /api/v1/owner/branches/1/label-settings': () => ok({ ...LABELS, configured: true, size: '58x40', scope: 'categories', category_ids: [5] }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stiker va yorliq' }));

    await typing.selectOptions(await screen.findByRole('combobox', { name: "Rulon o'lchami" }), '58x40');
    await typing.selectOptions(screen.getByRole('combobox', { name: 'Stiker chiqadigan taomlar' }), 'categories');
    await typing.click(await screen.findByRole('checkbox', { name: 'Ichimliklar' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Stiker sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ size: '58x40', scope: 'categories', category_ids: [5] });
    expect(screen.getByRole('checkbox', { name: 'Ichimliklar' })).toBeChecked();
    expect(screen.getByRole('button', { name: 'Saqlash' })).toBeDisabled();
  });

  it('asks for a category before it asks the server', async () => {
    const { typing, calls } = await open(['labels'], { 'GET /api/v1/owner/branches/1/label-settings': () => ok(LABELS) });
    await typing.click(await screen.findByRole('tab', { name: 'Stiker va yorliq' }));

    await typing.selectOptions(await screen.findByRole('combobox', { name: 'Stiker chiqadigan taomlar' }), 'categories');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Kamida bitta kategoriya tanlang.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('switching back to every item sends the scope alone and the flags as they are', async () => {
    const saved = { ...LABELS, configured: true, scope: 'categories', category_ids: [5], per_unit: false };
    const { typing, calls } = await open(['labels'], {
      'GET /api/v1/owner/branches/1/label-settings': () => ok(saved),
      'PUT /api/v1/owner/branches/1/label-settings': () => ok({ ...saved, scope: 'all', category_ids: [] }),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stiker va yorliq' }));

    expect(await screen.findByRole('checkbox', { name: 'Ichimliklar' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /Har dona uchun alohida stiker/ })).not.toBeChecked();
    await typing.selectOptions(screen.getByRole('combobox', { name: 'Stiker chiqadigan taomlar' }), 'all');
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Stiker sozlamalari saqlandi.')).toBeInTheDocument();
    expect(await last(calls, 'PUT').json()).toEqual({ scope: 'all' });
  });

  it("shows the server's message under the field it names", async () => {
    const { typing } = await open(['labels'], {
      'GET /api/v1/owner/branches/1/label-settings': () => ok(LABELS),
      'PUT /api/v1/owner/branches/1/label-settings': () => json({ message: 'Kategoriya topilmadi.', errors: { category_ids: ['Kategoriya topilmadi.'] } }, 422),
    });
    await typing.click(await screen.findByRole('tab', { name: 'Stiker va yorliq' }));
    await typing.selectOptions(await screen.findByRole('combobox', { name: 'Stiker chiqadigan taomlar' }), 'categories');
    await typing.click(await screen.findByRole('checkbox', { name: 'Ichimliklar' }));
    await typing.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Kategoriya topilmadi.')).toBeInTheDocument();
  });
});
