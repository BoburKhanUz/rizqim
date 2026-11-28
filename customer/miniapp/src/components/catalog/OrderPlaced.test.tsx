import { Suspense, lazy } from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { loadOrders } from '../../lib/customerPages';
import { OrderPlaced } from './OrderPlaced';

// The orders page's chunk on a slow network: it never arrives during the test
vi.mock('../../lib/customerPages', () => ({ loadOrders: vi.fn(() => new Promise(() => {})) }));
const SlowOrders = lazy(() => new Promise<{ default: () => null }>(() => {}));

function renderPlaced() {
  return render(
    <MemoryRouter initialEntries={['/cart']}>
      <Suspense fallback={<p>route loading</p>}>
        <Routes>
          <Route path="/cart" element={<OrderPlaced orderNumber="A1B2" />} />
          <Route path="/orders" element={<SlowOrders />} />
        </Routes>
      </Suspense>
    </MemoryRouter>,
  );
}

describe('OrderPlaced', () => {
  beforeEach(() => {
    vi.mocked(loadOrders).mockClear();
  });

  it('starts downloading the orders page as soon as it is shown', () => {
    renderPlaced();
    expect(loadOrders).toHaveBeenCalledTimes(1);
  });

  it('shows at once that "Buyurtmani kuzatish" was tapped while the orders page is still loading', async () => {
    renderPlaced();
    const track = screen.getByRole('link', { name: 'Buyurtmani kuzatish' });
    expect(track).not.toHaveAttribute('aria-busy');

    await act(async () => {
      fireEvent.click(track);
    });

    // The router navigates in a transition, so the confirmation stays on screen until the page arrives: the link itself
    // must say that it is opening
    const pending = screen.getByRole('link', { name: 'Buyurtmani kuzatish' });
    expect(pending).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('link', { name: 'Katalogga qaytish' })).not.toHaveAttribute('aria-busy');
  });
});
