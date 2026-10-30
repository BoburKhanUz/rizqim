import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Product } from '@rizqim/staff';
import { ProductPosService, ProductPosServiceForm, posServiceOf } from './ProductPosService';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, ok, product } from './menuFixtures';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }) }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => setSession(null));

const shashlik = (over: Record<string, unknown> = {}) => product({ id: 7, name: 'Shashlik', ...over }) as unknown as Product;

const format = (modules: string[]) => ({
  branch_id: 1,
  profile: 'kabobxona',
  profile_label: 'Kabobxona',
  mode: 'table',
  first_screen: 'hall_map',
  modules,
  locks: { service_charge_zero: false },
  module_rows: [],
  profiles: [],
  template: { stations: [], products: [] },
});

describe("a dish's skewer buttons, grill time and weight rule (P6-25)", () => {
  it('reads what the product has', () => {
    expect(posServiceOf(shashlik({ quick_multipliers: [2, 5], wait_minutes: 15, weight_mode: 'cooked' }))).toEqual({ quick_multipliers: [2, 5], wait_minutes: 15, weight_mode: 'cooked' });
    expect(posServiceOf(shashlik())).toEqual({ quick_multipliers: null, wait_minutes: null, weight_mode: null });
  });

  it('sends the chosen ×N buttons, the grill time and the weight rule', async () => {
    const { calls } = mockFetch({
      'PATCH /api/v1/owner/products/7/pos-service': () => ok({ product_id: 7, quick_multipliers: [2, 5], wait_minutes: 15, weight_mode: 'raw' }),
    });
    renderPage(<ProductPosServiceForm product={shashlik({ quick_multipliers: [5] })} canManage skewer weight />);
    const user = userEvent.setup();

    expect(screen.getByRole('switch', { name: '×5 tugmasi' })).toBeChecked();
    await user.click(screen.getByRole('switch', { name: '×2 tugmasi' }));
    await user.type(screen.getByLabelText('Mangal vaqti (daqiqa)'), '15');
    await user.selectOptions(screen.getByLabelText("Og'irlik"), 'raw');
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Saqlandi.')).toBeInTheDocument();
    const call = calls.find((request) => request.method === 'PATCH')!;
    expect(await call.json()).toEqual({ quick_multipliers: [2, 5], wait_minutes: 15, weight_mode: 'raw' });
  });

  it('checks the grill time and shows the server refusal in its own words', async () => {
    const { calls } = mockFetch({
      'PATCH /api/v1/owner/products/7/pos-service': () =>
        json({ success: false, message: "\"Og'irlikni keyin kiritish\" moduli bu filialda o'chirilgan.", code: 'module_disabled', data: { reason: 'module_disabled', module: 'weight_later' } }, 403),
    });
    renderPage(<ProductPosServiceForm product={shashlik()} canManage skewer weight={false} />);
    const user = userEvent.setup();

    expect(screen.queryByLabelText("Og'irlik")).not.toBeInTheDocument();
    await user.type(screen.getByLabelText('Mangal vaqti (daqiqa)'), '999');
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('0 dan 240 gacha butun son kiriting.')).toBeInTheDocument();
    expect(calls).toHaveLength(0);

    await user.clear(screen.getByLabelText('Mangal vaqti (daqiqa)'));
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText("\"Og'irlikni keyin kiritish\" moduli bu filialda o'chirilgan.")).toBeInTheDocument();
    expect(await calls[0]!.json()).toEqual({ quick_multipliers: null, wait_minutes: null });
  });

  it("shows only where the branch's format sells that way", async () => {
    mockFetch({
      'GET /api/v1/owner/branches': () => ok([branch()]),
      'GET /api/v1/owner/pos/format': () => ok(format(['service_charge', 'kds', 'weight_later'])),
    });
    renderPage(<ProductPosService product={shashlik()} canManage />);

    expect(await screen.findByLabelText("Og'irlik")).toBeInTheDocument();
    expect(screen.queryByLabelText('Mangal vaqti (daqiqa)')).not.toBeInTheDocument();
  });

  it('shows nothing for a format without them', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches': () => ok([branch()]),
      'GET /api/v1/owner/pos/format': () => ok(format(['rooms', 'room_fee'])),
    });
    const { container } = renderPage(<ProductPosService product={shashlik()} canManage />);

    await waitFor(() => expect(calls.some((call) => call.url.includes('/owner/pos/format'))).toBe(true));
    expect(container).toBeEmptyDOMElement();
  });
});
