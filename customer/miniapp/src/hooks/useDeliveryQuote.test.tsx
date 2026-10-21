import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { failedRequest } from '../test/httpErrors';
import { useDeliveryQuote } from './useDeliveryQuote';

// GET /delivery/quote at checkout: the fee, the minimum and "bu manzilga yetkazib berilmaydi" come from the server.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn() } }));

const get = vi.mocked(api.get);
let client: QueryClient;

const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
const advance = (ms: number) => act(() => vi.advanceTimersByTimeAsync(ms));

const POINT = { lat: 41.2856, lng: 69.2037 };
const QUOTE = {
  zone: { id: 7, name: 'Markaz', delivery_price: '12000.00', min_order_amount: '30000.00', free_delivery_from: null, eta_minutes: 40 },
  branch: { id: 3, name: 'Markaz filial', distance_km: 0.31 },
  delivery_price: '12000.00',
  min_order_amount: '30000.00',
  free_delivery_applies: false,
  meets_min_order: true,
  eta: { minutes: 40 },
};

const render = (props: Partial<Parameters<typeof useDeliveryQuote>[0]> = {}) =>
  renderHook((p) => useDeliveryQuote(p), { wrapper, initialProps: { storeId: '1', point: POINT, subtotal: 50000, enabled: true, ...props } });

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
  client = new QueryClient();
  get.mockReset();
  get.mockResolvedValue({ data: { success: true, data: QUOTE } });
});

afterEach(() => {
  client.clear();
  vi.useRealTimers();
});

describe('useDeliveryQuote', () => {
  it('asks the server with the store, the pin and the cart total', async () => {
    const { result } = render();
    expect(result.current.status).toBe('loading');
    await advance(0);

    expect(get).toHaveBeenCalledWith('/delivery/quote', { params: { store: '1', lat: 41.2856, lng: 69.2037, subtotal: 50000 } });
    expect(result.current).toMatchObject({ status: 'ready', quote: { delivery_price: '12000.00', meets_min_order: true } });
  });

  it('does not ask for pickup, without a pin or for an empty cart', async () => {
    for (const props of [{ enabled: false }, { point: null }, { subtotal: 0 }]) {
      const { result } = render(props);
      await advance(0);
      expect(result.current.status).toBe('off');
    }
    expect(get).not.toHaveBeenCalled();
  });

  it('reads outside_zone as a refusal with the sentence to show', async () => {
    get.mockRejectedValue(failedRequest(422, { success: false, message: 'Manzil yetkazib berish hududidan tashqarida', data: { reason: 'outside_zone' } }, '/delivery/quote'));
    const { result } = render();
    await advance(0);

    expect(result.current).toMatchObject({ status: 'refused', reason: 'outside_zone' });
    expect((result.current as { message: string }).message).toMatch(/^Bu manzilga yetkazib berilmaydi/);
  });

  it('reads branch_closed as a refusal too', async () => {
    get.mockRejectedValue(failedRequest(422, { success: false, message: 'x', data: { reason: 'branch_closed' } }, '/delivery/quote'));
    const { result } = render();
    await advance(0);

    expect(result.current).toMatchObject({ status: 'refused', reason: 'branch_closed' });
  });

  it('a store without zones is not a refusal: it keeps its own fee', async () => {
    get.mockRejectedValue(failedRequest(422, { success: false, message: 'x', data: { reason: 'zones_not_configured' } }, '/delivery/quote'));
    const { result } = render();
    await advance(0);

    expect(result.current.status).toBe('unconfigured');
  });

  it('is an error when the quote could not be asked for (after one retry), and can be asked again', async () => {
    get.mockRejectedValue(failedRequest(500, { message: 'Server Error' }, '/delivery/quote'));
    const { result } = render();
    await advance(0);
    await advance(1600);

    expect(result.current.status).toBe('error');
    expect(get).toHaveBeenCalledTimes(2);

    get.mockResolvedValue({ data: { success: true, data: QUOTE } });
    act(() => result.current.retry());
    await advance(0);
    expect(result.current.status).toBe('ready');
  });

  it('asks once for a cart that changed several times in a row, after it settled', async () => {
    const { result, rerender } = render();
    await advance(0);
    get.mockClear();

    for (const subtotal of [75000, 100000, 125000]) {
      rerender({ storeId: '1', point: POINT, subtotal, enabled: true });
      await advance(100);
      expect(result.current.status).toBe('loading'); // not ready for a total that is about to change
    }
    expect(get).not.toHaveBeenCalled();

    await advance(400);
    await advance(0);

    expect(get).toHaveBeenCalledTimes(1);
    expect(get).toHaveBeenCalledWith('/delivery/quote', { params: expect.objectContaining({ subtotal: 125000 }) });
    expect(result.current.status).toBe('ready');
  });
});
