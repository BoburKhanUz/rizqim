import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { DeliveryQuoteState } from '../../hooks/useDeliveryQuote';
import { DeliveryQuoteNote } from './DeliveryQuoteNote';

vi.mock('../../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));

const retry = vi.fn();
const note = (state: DeliveryQuoteState, onChoosePickup?: () => void) => render(<DeliveryQuoteNote quote={{ ...state, retry }} onChoosePickup={onChoosePickup} />);

const quote = (overrides: Record<string, unknown> = {}) =>
  ({
    zone: { id: 7, name: 'Markaz', delivery_price: '12000.00', min_order_amount: '30000.00', free_delivery_from: null, eta_minutes: 40 },
    branch: { id: 3, name: 'Markaz filial', distance_km: 0.3 },
    delivery_price: '12000.00',
    min_order_amount: '30000.00',
    free_delivery_applies: false,
    meets_min_order: true,
    eta: { minutes: 40 },
    ...overrides,
  }) as never;

describe('DeliveryQuoteNote', () => {
  it('shows the fee, the time and the minimum order the server quoted', () => {
    note({ status: 'ready', quote: quote() });

    expect(screen.getByText(/Yetkazib berish: 12\s000\sso'm/)).toBeInTheDocument();
    expect(screen.getByText(/~40 daqiqa/)).toBeInTheDocument();
    expect(screen.getByText(/Minimal buyurtma: 30\s000\sso'm/)).toBeInTheDocument();
  });

  it('says "Bepul" when the free-delivery rule applied', () => {
    note({ status: 'ready', quote: quote({ delivery_price: '0.00', free_delivery_applies: true, min_order_amount: '0.00' }) });

    expect(screen.getByText('Yetkazib berish: Bepul')).toBeInTheDocument();
    expect(screen.queryByText(/Minimal buyurtma/)).not.toBeInTheDocument();
  });

  it('says that delivery is not possible there, and offers pickup', () => {
    const onChoosePickup = vi.fn();
    note({ status: 'refused', reason: 'outside_zone', message: 'Bu manzilga yetkazib berilmaydi. Boshqa manzilni tanlang.' }, onChoosePickup);

    expect(screen.getByRole('alert')).toHaveTextContent('Bu manzilga yetkazib berilmaydi');
    fireEvent.click(screen.getByRole('button', { name: 'Olib ketishni tanlash' }));
    expect(onChoosePickup).toHaveBeenCalledTimes(1);
  });

  it('offers no pickup when the store takes none', () => {
    note({ status: 'refused', reason: 'outside_zone', message: 'x' });

    expect(screen.queryByRole('button', { name: 'Olib ketishni tanlash' })).not.toBeInTheDocument();
  });

  it('lets the customer ask again after an error', () => {
    note({ status: 'error' });

    fireEvent.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(retry).toHaveBeenCalledTimes(1);
  });

  it('says it is calculating, and is silent for pickup and for a store without zones', () => {
    const { rerender } = note({ status: 'loading' });
    expect(screen.getByRole('status')).toHaveTextContent('hisoblanmoqda');

    for (const status of ['off', 'unconfigured'] as const) {
      rerender(<DeliveryQuoteNote quote={{ status, retry }} />);
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    }
  });
});
