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

const row = (key: string, label: string, over: Record<string, unknown> = {}) => ({
  key,
  label,
  description: `${label} haqida`,
  default: true,
  override: null,
  configured: true,
  plan_allows: true,
  required_plan: null,
  required_plan_name: null,
  locked: null,
  enabled: true,
  ...over,
});

const profile = (key: string, label: string, over: Record<string, unknown> = {}) => ({
  key,
  label,
  description: `${label}: qanday ishlaydi`,
  mode: 'hybrid',
  first_screen: 'hall_map',
  modules: [],
  locks_service_charge: false,
  recommended_plan: 'asosiy',
  stations: [],
  dishes: 3,
  ...over,
});

const formatOf = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  profile: 'choyxona',
  profile_label: 'Choyxona',
  mode: 'hybrid',
  first_screen: 'hall_map',
  modules: ['rooms', 'kds'],
  locks: { service_charge_zero: false },
  module_rows: [
    row('rooms', 'Xonalar va tapchanlar'),
    row('kds', 'Oshxona ekrani (KDS)', { plan_allows: false, required_plan: 'asosiy', required_plan_name: 'Asosiy', locked: 'plan', enabled: false }),
    row('queue_numbers', 'Buyurtma raqami', { default: false, override: true }),
    row('daily_menu', 'Bugungi menyu', { default: false, configured: false, enabled: false }),
  ],
  profiles: [
    profile('choyxona', 'Choyxona'),
    profile('osh_markazi', 'Osh markazi', { mode: 'counter', first_screen: 'quick_counter', recommended_plan: 'start' }),
    profile('stolovaya', 'Stolovaya (oshxona-liniya)', { mode: 'counter', locks_service_charge: true, recommended_plan: 'start' }),
  ],
  template: {
    profile: 'choyxona',
    stations: [
      { name: 'Osh va qozon', kind: 'osh', kind_label: 'Osh va qozon', exists: true },
      { name: 'Mangal', kind: 'mangal', kind_label: 'Mangal', exists: false },
    ],
    dishes: [{ category: 'Milliy taomlar', name: 'Osh', variants: ['1 kg'], exists: false }],
  },
  ...over,
});

const open = (over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () => ok(formatOf()),
    ...over,
  });
  renderPage(<PosSetupView />);
  return { ...mocked, typing: userEvent.setup() };
};

const last = (calls: Request[], method: string) => calls.filter((call) => call.method === method).at(-1)!;

describe('Format', () => {
  it('opens first and shows the profile, the modules with their locks and the template', async () => {
    const { calls } = open();
    expect(await screen.findByRole('tab', { name: 'Format', selected: true })).toBeInTheDocument();
    const group = await screen.findByRole('radiogroup', { name: 'Formatni tanlang' });
    expect(within(group).getByRole('radio', { name: /Choyxona/ })).toHaveAttribute('aria-checked', 'true');
    expect(within(group).getByRole('radio', { name: /Stolovaya/ })).toHaveTextContent('Xizmat haqi 0% (qonun)');
    expect(within(group).getByRole('radio', { name: /Osh markazi/ })).toHaveTextContent('Tavsiya: Start tarifi');
    expect(screen.getByText('Kassa ochilganda: zal xaritasi')).toBeInTheDocument();

    expect(screen.getByRole('switch', { name: 'Xonalar va tapchanlar: yoqilgan' })).toBeChecked();
    // The plan lacks KDS: off, cannot be switched on, says which plan has it
    const kds = screen.getByRole('switch', { name: 'Oshxona ekrani (KDS): yoqilgan' });
    expect(kds).not.toBeChecked();
    expect(kds).toBeDisabled();
    expect(screen.getByText('Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Buyurtma raqami: formatnikiga qaytarish' })).toBeInTheDocument();

    expect(screen.getByRole('region', { name: 'Sexlar' })).toHaveTextContent('Osh va qozon (Osh va qozon)(bor)');
    expect(calls.some((call) => new URL(call.url).searchParams.get('branch_id') === '1')).toBe(true);
  });

  it('switches a module on and back to the format default', async () => {
    const { calls, typing } = open({ 'PUT /api/v1/owner/pos/format': () => ok(formatOf()) });
    await typing.click(await screen.findByRole('switch', { name: 'Bugungi menyu: yoqilgan' }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Modullar saqlandi.'));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, modules: { daily_menu: true } });

    await typing.click(screen.getByRole('button', { name: 'Buyurtma raqami: formatnikiga qaytarish' }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, modules: { queue_numbers: null } });
  });

  it('asks before changing the profile, then shows the new one', async () => {
    const changed = formatOf({ profile: 'osh_markazi', profile_label: 'Osh markazi', mode: 'counter', first_screen: 'quick_counter' });
    const { calls, typing } = open({ 'PUT /api/v1/owner/pos/format': () => ok(changed) });
    await typing.click(await screen.findByRole('radio', { name: /Osh markazi/ }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('«Osh markazi» formatiga');
    await typing.click(within(dialog).getByRole('button', { name: "O'zgartirish" }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Format o'zgartirildi: Osh markazi."));
    expect(await last(calls, 'PUT').json()).toEqual({ branch_id: 1, profile: 'osh_markazi' });
    expect(screen.getByRole('radio', { name: /Osh markazi/ })).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByText('Kassa ochilganda: tez kassa')).toBeInTheDocument();
  });

  it("shows the server's refusal of a locked module", async () => {
    const { typing } = open({
      'PUT /api/v1/owner/pos/format': () => json({ success: false, message: "Stolovayada xizmat haqi qonun bo'yicha olinmaydi (0%), uni yoqib bo'lmaydi.", code: 'service_charge_locked', data: { reason: 'service_charge_locked', module: 'service_charge' } }, 422),
    });
    await typing.click(await screen.findByRole('switch', { name: 'Bugungi menyu: yoqilgan' }));
    expect(await screen.findByText("Stolovayada xizmat haqi qonun bo'yicha olinmaydi (0%), uni yoqib bo'lmaydi.")).toBeInTheDocument();
  });

  it('applies the template and says what was added', async () => {
    const { calls, typing } = open({
      'POST /api/v1/owner/pos/format/template': () => ok({ stations_created: ['Mangal'], stations_skipped: ['Osh va qozon'], categories_created: [], dishes_created: ['Osh'], dishes_skipped: [], format: formatOf() }),
    });
    await typing.click(await screen.findByRole('button', { name: "Namunani qo'shish" }));
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent("Qo'shildi: 1 ta sex, 1 ta taom."));
    expect(await last(calls, 'POST').json()).toEqual({ branch_id: 1 });
  });
});
