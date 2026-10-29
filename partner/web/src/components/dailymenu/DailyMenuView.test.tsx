import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, ok, permissionsAnswer, product, variant } from '../menu/menuFixtures';
import { DailyMenuView } from './DailyMenuView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const dish = (over: Record<string, unknown> = {}) => ({ id: 1, name: "Lag'mon", category_id: 2, category_name: 'Birinchi', daily_rotation: true, on_menu: true, is_set: false, ...over });

const menu = (over: Record<string, unknown> = {}) => ({
  branch_id: 1,
  date: '2026-11-14',
  business_date: '2026-11-14',
  enforced: true,
  set: true,
  product_ids: [1],
  previous_date: '2026-11-13',
  products: [dish(), dish({ id: 2, name: 'Mastava', on_menu: false }), dish({ id: 3, name: 'Qora choy', category_name: 'Ichimlik', daily_rotation: false, on_menu: false })],
  ...over,
});

function routes(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}, data: Record<string, unknown> = menu()) {
  return {
    'GET /api/v1/me/permissions': () => permissionsAnswer(),
    'GET /api/v1/owner/branches': () => ok([branch()]),
    'GET /api/v1/owner/daily-menu': () => ok(data),
    ...extra,
  };
}

describe('Bugungi menyu', () => {
  it("shows the day's dishes ticked, the rest unticked and the permanent ones apart", async () => {
    mockFetch(routes());
    renderPage(<DailyMenuView />);

    const lagman = await screen.findByRole('checkbox', { name: "Lag'mon" });
    expect(lagman).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Mastava' })).not.toBeChecked();
    expect(screen.getByText('Bugun bor: 1 ta')).toBeInTheDocument();
    // a dish that does not rotate is on sale every day: it has no tick, only a way to put it on the menu
    expect(screen.queryByRole('checkbox', { name: 'Qora choy' })).not.toBeInTheDocument();
    expect(screen.getByText('Doimiy taomlar')).toBeInTheDocument();
    expect(screen.queryByText(/menyu belgilanmagan/)).not.toBeInTheDocument();
  });

  it('warns when no menu is set for the day', async () => {
    mockFetch(routes({}, menu({ set: false, product_ids: [] })));
    renderPage(<DailyMenuView />);
    expect(await screen.findByText(/menyu belgilanmagan: hamma taom sotuvda/)).toBeInTheDocument();
  });

  it('saves the ticked dishes of the day for the branch', async () => {
    const { calls } = mockFetch(routes({ 'PUT /api/v1/owner/daily-menu': () => ok(menu()) }));
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('checkbox', { name: 'Mastava' }));
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Menyu saqlandi.')).toBeInTheDocument();
    const put = calls.find((call) => call.method === 'PUT')!;
    expect(await put.json()).toEqual({ branch_id: 1, date: '2026-11-14', product_ids: [1, 2] });
    // nothing changed about which dishes rotate: only the menu is saved
    expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1);
  });

  it('keeps the saved message when the menu comes back changed', async () => {
    // After the save the page reads the new menu: the editor starts again from it, and the message must stay
    let saved = false;
    mockFetch(
      routes({
        'GET /api/v1/owner/daily-menu': () => ok(saved ? menu({ product_ids: [1, 2], products: [dish(), dish({ id: 2, name: 'Mastava', on_menu: true }), dish({ id: 3, name: 'Qora choy', daily_rotation: false, on_menu: false })] }) : menu()),
        'PUT /api/v1/owner/daily-menu': () => {
          saved = true;
          return ok(menu());
        },
      }),
    );
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('checkbox', { name: 'Mastava' }));
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.getByText('Bugun bor: 2 ta')).toBeInTheDocument());
    expect(screen.getByText('Menyu saqlandi.')).toBeInTheDocument();
  });

  it('turns a permanent dish into a daily one and saves the rotation first', async () => {
    const { calls } = mockFetch(routes({ 'PUT /api/v1/owner/daily-menu/rotation': () => ok({ product_ids: [1, 2, 3] }), 'PUT /api/v1/owner/daily-menu': () => ok(menu()) }));
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: /Kunlik qilish/ }));
    expect(await screen.findByRole('checkbox', { name: 'Qora choy' })).toBeChecked();
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(2));
    const puts = calls.filter((call) => call.method === 'PUT');
    expect(new URL(puts[0]!.url).pathname).toBe('/api/v1/owner/daily-menu/rotation');
    expect(await puts[0]!.json()).toEqual({ product_ids: [1, 2, 3] });
    expect(await puts[1]!.json()).toEqual({ branch_id: 1, date: '2026-11-14', product_ids: [1, 3] });
  });

  it("copies the previous day's menu, asking first when one is already set", async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/daily-menu/copy': () => ok(menu()) }));
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: /Nusxalash: / }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('almashtiriladi');
    await user.click(within(dialog).getByRole('button', { name: 'Nusxalash' }));

    expect(await screen.findByText('Menyu nusxalandi.')).toBeInTheDocument();
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ branch_id: 1, date: '2026-11-14', from_date: '2026-11-13' });
  });

  it('copies at once when the day has no menu yet, and has no copy without an earlier one', async () => {
    const { calls } = mockFetch(routes({ 'POST /api/v1/owner/daily-menu/copy': () => ok(menu()) }, menu({ set: false, product_ids: [] })));
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: /Nusxalash: / }));
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
  });

  it('disables the copy button without an earlier menu', async () => {
    mockFetch(routes({}, menu({ previous_date: null })));
    renderPage(<DailyMenuView />);
    expect(await screen.findByRole('button', { name: 'Oldingi menyudan nusxalash' })).toBeDisabled();
  });

  it("shows the server's words when the module is off", async () => {
    mockFetch(routes({ 'GET /api/v1/owner/daily-menu': () => json({ success: false, message: '"Bugungi menyu" moduli bu filialda o\'chirilgan. Uni hamkor panelidagi "Format" sahifasida yoqish mumkin.', code: 'module_disabled' }, 403) }));
    renderPage(<DailyMenuView />);
    expect(await screen.findByText(/moduli bu filialda o'chirilgan/)).toBeInTheDocument();
  });

  it('only shows the menu to someone who may not change it', async () => {
    mockFetch(routes({ 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']) }));
    renderPage(<DailyMenuView />);
    expect(await screen.findByRole('checkbox', { name: "Lag'mon" })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Saqlash' })).not.toBeInTheDocument();
  });

  it('manages the kompleks: lists them and makes a product one', async () => {
    const soup = product({ id: 21, name: 'Mastava', batch_tracked: false, variants: [variant({ id: 211, product_id: 21, name: 'Porsiya' })] });
    const complex = product({ id: 22, name: 'Kompleks 1', batch_tracked: false, variants: [variant({ id: 221, product_id: 22, name: 'Kompleks' })] });
    const set = { id: 4, uuid: 's-4', product_id: 30, product_name: 'Kompleks 2', prices: ['40000.00'], is_active: true, parts: [{ variant_id: 211, variant_uuid: 'v', product_id: 21, name: 'Mastava', variant_name: 'Porsiya', quantity: '1.000', unit: 'piece', label: 'Birinchi' }] };
    const { calls } = mockFetch(
      routes({
        'GET /api/v1/owner/meal-sets': () => ok({ sets: [set] }),
        'GET /api/v1/owner/products': () => json({ success: true, data: [soup, complex] }),
        'POST /api/v1/owner/meal-sets': () => json({ success: true, message: 'ok', data: { ...set, id: 5, product_id: 22, product_name: 'Kompleks 1' } }, 201),
      }),
    );
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('tab', { name: 'Komplekslar' }));
    const card = (await screen.findAllByText('Kompleks 2'))[0]!.closest('li')!;
    expect(card).toHaveTextContent('Mastava · Porsiya');
    expect(card).toHaveTextContent('Birinchi');

    await user.click(screen.getByRole('button', { name: "Kompleks qo'shish" }));
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText("Maydonni to'ldiring.")).toBeInTheDocument();

    await user.selectOptions(within(dialog).getByLabelText('Kompleks mahsuloti'), 'Kompleks 1');
    // the kompleks itself is not among the dishes it is made of
    expect(within(within(dialog).getByLabelText('Taom')).queryByRole('option', { name: /Kompleks 1/ })).toBeNull();
    await user.selectOptions(within(dialog).getByLabelText('Taom'), 'Mastava · Porsiya');
    await user.clear(within(dialog).getByLabelText('Miqdor'));
    await user.type(within(dialog).getByLabelText('Miqdor'), '2');
    await user.type(within(dialog).getByLabelText('Nomi'), 'Birinchi');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ product_id: 22, parts: [{ variant_id: 211, quantity: 2, label: 'Birinchi' }] });
  });

  it('switches a kompleks off and deletes it after a confirmation', async () => {
    const set = { id: 4, uuid: 's-4', product_id: 30, product_name: 'Kompleks 2', prices: ['40000.00'], is_active: true, parts: [] };
    const { calls } = mockFetch(
      routes({
        'GET /api/v1/owner/meal-sets': () => ok({ sets: [set] }),
        'PUT /api/v1/owner/meal-sets/4': () => ok({ ...set, is_active: false }),
        'DELETE /api/v1/owner/meal-sets/4': () => ok(null),
      }),
    );
    renderPage(<DailyMenuView />);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('tab', { name: 'Komplekslar' }));
    await user.click(await screen.findByRole('switch', { name: 'Kompleks yoqilgan' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ is_active: false });

    await user.click(screen.getByRole('button', { name: /^O'chirish/ }));
    const dialog = await screen.findByRole('alertdialog');
    await user.click(within(dialog).getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(calls.some((call) => call.method === 'DELETE')).toBe(true));
  });
});
