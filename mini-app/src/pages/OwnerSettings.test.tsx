import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import api from '../services/api';
import type { OwnerStoreSettings, PaymentSettings } from '../types';
import OwnerSettings from './OwnerSettings';

// The owner's settings screen on a fake API. What matters here is the contract of the request it saves:
// payment_settings.delivery_enabled / pickup_enabled, plain booleans, which checkout reads back as
// `(bool) ($settings['delivery_enabled'] ?? true)` (App\Actions\Order\CreateOrderAction).

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined) }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

let client: QueryClient;

function ownerSettings(paymentSettings: PaymentSettings): OwnerStoreSettings {
  return {
    id: 1,
    name: 'Evos',
    logo: null,
    logo_url: null,
    banner: null,
    banner_url: null,
    description: null,
    address: 'Amir Temur ko\'chasi 1',
    location_url: null,
    is_open: true,
    min_order_amount: 0,
    payment_settings: paymentSettings,
    payment_account_type: 'platform_account',
    status: 'active',
    is_order_locked: false,
    allowed_methods: ['cash', 'click', 'payme', 'uzum', 'paynet'],
  };
}

async function renderSettings(paymentSettings: PaymentSettings) {
  get.mockResolvedValue({ data: { success: true, data: ownerSettings(paymentSettings) } });
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <OwnerSettings />
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await screen.findByRole('button', { name: /Sozlamalarni saqlash/ });
}

const deliverySwitch = () => screen.getByRole('switch', { name: 'Yetkazib berish' });
const pickupSwitch = () => screen.getByRole('switch', { name: 'Olib ketish' });

// Saves the form and returns the `payment_settings` it sent
async function save(): Promise<Record<string, unknown>> {
  fireEvent.click(screen.getByRole('button', { name: /Sozlamalarni saqlash/ }));
  await vi.waitFor(() => expect(post).toHaveBeenCalledTimes(1));
  const [url, body] = post.mock.calls[0];
  expect(url).toBe('/owner/settings');
  return (body as { payment_settings: Record<string, unknown> }).payment_settings;
}

beforeEach(() => {
  client = new QueryClient();
  post.mockResolvedValue({ data: { success: true } });
});

afterEach(() => {
  client.clear();
});

describe('OwnerSettings: delivery and pickup switches', () => {
  it('shows both on for a store that never saved them, as checkout treats a missing switch', async () => {
    await renderSettings({ cash: { enabled: true } });

    expect(deliverySwitch()).toHaveAttribute('aria-checked', 'true');
    expect(pickupSwitch()).toHaveAttribute('aria-checked', 'true');
  });

  it('shows what the store saved', async () => {
    await renderSettings({ delivery_enabled: false, pickup_enabled: true });

    expect(deliverySwitch()).toHaveAttribute('aria-checked', 'false');
    expect(pickupSwitch()).toHaveAttribute('aria-checked', 'true');
  });

  it('sends them as booleans inside payment_settings, unchanged when the owner touched nothing', async () => {
    await renderSettings({ cash: { enabled: true } });

    const sent = await save();

    expect(sent.delivery_enabled).toBe(true);
    expect(sent.pickup_enabled).toBe(true);
  });

  it('sends the switch the owner turned off as false and the other as true', async () => {
    await renderSettings({ cash: { enabled: true }, delivery_enabled: true, pickup_enabled: true });

    fireEvent.click(deliverySwitch());
    expect(deliverySwitch()).toHaveAttribute('aria-checked', 'false');
    const sent = await save();

    expect(sent.delivery_enabled).toBe(false);
    expect(sent.pickup_enabled).toBe(true);
  });

  it('sends a pickup switch the owner turned off as false', async () => {
    await renderSettings({ delivery_enabled: true, pickup_enabled: true });

    fireEvent.click(pickupSwitch());
    const sent = await save();

    expect(sent.pickup_enabled).toBe(false);
    expect(sent.delivery_enabled).toBe(true);
  });

  it('sends a switch the owner turned back on as true', async () => {
    await renderSettings({ delivery_enabled: false, pickup_enabled: false });

    fireEvent.click(deliverySwitch());
    const sent = await save();

    expect(sent.delivery_enabled).toBe(true);
    expect(sent.pickup_enabled).toBe(false);
  });
});
