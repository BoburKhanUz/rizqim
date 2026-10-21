import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ModifierGroupEditor } from './ModifierGroupEditor';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { group, ok, permissionsAnswer, product } from './menuFixtures';

const router = vi.hoisted(() => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }));
vi.mock('next/navigation', () => ({ useRouter: () => router }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => {
  setSession(null);
  router.replace.mockReset();
});

const base = {
  'GET /api/v1/me/permissions': () => permissionsAnswer(),
  'GET /api/v1/owner/modifier-groups': () => ok([group(), group({ id: 4, name: "Qo'shimchalar", product_ids: [7], modifiers: [] })]),
  'GET /api/v1/owner/products': () => json({ success: true, data: [product(), product({ id: 8, name: "Lag'mon" })] }),
};

describe('a new modifier group', () => {
  it('needs a name and an option name before it asks the server', async () => {
    const { calls } = mockFetch({ ...base });
    renderPage(<ModifierGroupEditor id="new" />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: 'Yaratish' }));
    expect(await screen.findAllByText("Maydonni to'ldiring.")).toHaveLength(2);
    expect(screen.getByText('Maydonlarni tekshiring.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
  });

  it('creates the group with its options, price deltas and Russian names', async () => {
    const { calls } = mockFetch({ ...base, 'POST /api/v1/owner/modifier-groups': () => json({ success: true, message: 'ok', data: group({ id: 10 }) }, 201) });
    renderPage(<ModifierGroupEditor id="new" />);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Guruh nomi'), 'Sous tanlang');
    await user.type(screen.getByLabelText('Guruh nomi (ruscha)'), 'Выберите соус');
    await user.clear(screen.getByLabelText('Eng kam tanlov'));
    await user.type(screen.getByLabelText('Eng kam tanlov'), '1');
    await user.type(screen.getByLabelText('Eng ko\'p tanlov'), '2');
    await user.click(screen.getByRole('switch', { name: 'Majburiy tanlov' }));

    const first = screen.getByRole('group', { name: '1-variant' });
    await user.type(within(first).getByLabelText('Nomi'), 'Achchiq');
    await user.type(within(first).getByLabelText('Nomi (ruscha)'), 'Острый');
    await user.type(within(first).getByLabelText("Narx qo'shimchasi (so'm)"), '3000');
    await user.click(screen.getByRole('button', { name: "Variant qo'shish" }));
    await user.type(within(screen.getByRole('group', { name: '2-variant' })).getByLabelText('Nomi'), 'Qatiq');

    await user.click(screen.getByRole('button', { name: 'Yaratish' }));
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith('/menu/groups/10'));
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({
      name: 'Sous tanlang',
      name_i18n: { ru: 'Выберите соус' },
      min_select: 1,
      max_select: 2,
      is_required: true,
      sort_order: 0,
      is_active: true,
      modifiers: [
        { name: 'Achchiq', name_i18n: { ru: 'Острый' }, price_delta: 3000, max_quantity: 1, sort_order: 0, is_active: true },
        { name: 'Qatiq', name_i18n: null, price_delta: 0, max_quantity: 1, sort_order: 1, is_active: true },
      ],
    });
  });

  it("shows the server's message under the field it names", async () => {
    mockFetch({
      ...base,
      'POST /api/v1/owner/modifier-groups': () => json({ message: "Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak.", errors: { max_select: ["Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak."] } }, 422),
    });
    renderPage(<ModifierGroupEditor id="new" />);
    const user = userEvent.setup();
    await user.type(await screen.findByLabelText('Guruh nomi'), 'Sous');
    await user.type(within(screen.getByRole('group', { name: '1-variant' })).getByLabelText('Nomi'), 'Achchiq');
    await user.click(screen.getByRole('button', { name: 'Yaratish' }));
    await waitFor(() => expect(screen.getByLabelText("Eng ko'p tanlov")).toHaveAttribute('aria-invalid', 'true'));
    expect(screen.getAllByText("Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak.").length).toBeGreaterThan(0);
  });
});

describe('an existing modifier group', () => {
  it('sends the ids of the options it keeps and leaves out the one that was removed', async () => {
    const { calls } = mockFetch({ ...base, 'PUT /api/v1/owner/modifier-groups/3': () => ok(group({ modifiers: [group().modifiers[0]] })) });
    renderPage(<ModifierGroupEditor id="3" />);
    const user = userEvent.setup();

    expect(await screen.findByLabelText('Guruh nomi')).toHaveValue('Sous tanlang');
    expect(screen.getByLabelText('Guruh nomi (ruscha)')).toHaveValue('Выберите соус');
    expect(within(screen.getByRole('group', { name: '1-variant' })).getByLabelText("Narx qo'shimchasi (so'm)")).toHaveValue('3000');
    await user.click(screen.getByRole('button', { name: "Variantni o'chirish: 2" }));
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Saqlandi.')).toBeInTheDocument();
    const body = (await calls.find((call) => call.method === 'PUT')!.json()) as { modifiers: Array<{ id: number }>; max_select: number };
    expect(body.modifiers.map((option) => option.id)).toEqual([31]);
    expect(body.max_select).toBe(2);
  });

  it('attaches the group to dishes, sending each changed dish its whole list', async () => {
    const { calls } = mockFetch({ ...base, 'PUT /api/v1/owner/products/8/modifier-groups': () => ok({ product_id: 8, modifier_groups: [] }) });
    renderPage(<ModifierGroupEditor id="3" />);
    const user = userEvent.setup();
    expect(await screen.findByRole('switch', { name: 'Guruhni taklif qilish: Osh' })).toHaveAttribute('aria-checked', 'true');
    const lagman = screen.getByRole('switch', { name: "Guruhni taklif qilish: Lag'mon" });
    expect(screen.getByRole('button', { name: 'Taomlarni saqlash' })).toBeDisabled();

    await user.click(lagman);
    await user.click(screen.getByRole('button', { name: 'Taomlarni saqlash' }));
    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ groups: [{ modifier_group_id: 3, sort_order: 0 }] });
  });

  it('says so when the group does not exist', async () => {
    mockFetch({ ...base });
    renderPage(<ModifierGroupEditor id="999" />);
    expect(await screen.findByText('Guruh topilmadi')).toBeInTheDocument();
  });
});
