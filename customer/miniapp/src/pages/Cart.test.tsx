import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { useAddressStore } from '../store/useAddressStore';
import { useAuthStore } from '../store/authStore';
import { useCartStore } from '../store/cartStore';
import { makeOrder, makeProduct, makeStore, makeUser, makeVariant } from '../test/factories';
import type { BranchSummary, PaymentSettings, StoreListItem } from '../types';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import Cart from './Cart';

// The cart on a fake API: which payment methods and ways of getting the order it offers must follow what the server
// accepts at checkout (CreateOrderAction), so that a customer is never offered a choice the order is refused for.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

const BRANCH: BranchSummary = {
  id: 5,
  store_id: 1,
  name: 'Asosiy filial',
  latitude: 41.31,
  longitude: 69.28,
  radius_km: 10,
  address: 'Amir Temur ko\'chasi 1',
  phone: null,
};

// What GET /stores sends for the store of the cart. `payment_settings` is the JSON column, whatever shape it holds.
function storeWith(paymentSettings: unknown): StoreListItem {
  return { ...makeStore({ id: 1, payment_settings: paymentSettings as PaymentSettings | null, delivery_fee: 10000 }), branches: [BRANCH] };
}

// The dish of the cart in the store's menu (a line whose dish is not on the menu any more cannot be ordered)
const LAVASH = makeProduct({ id: 1, variants: [makeVariant({ id: 1, uuid: 'lavash', product_id: 1, price: '25000.00', stock: 50 })] });

// What GET /delivery/quote answers: the zone's fee and minimum for the cart
function quoteOf(deliveryPrice: string, minOrder = '0.00') {
  return {
    zone: { id: 7, name: 'Markaz', delivery_price: deliveryPrice, min_order_amount: minOrder, free_delivery_from: null, eta_minutes: 40 },
    branch: { id: 5, name: 'Asosiy filial', distance_km: 1.2 },
    delivery_price: deliveryPrice,
    min_order_amount: minOrder,
    free_delivery_applies: false,
    meets_min_order: true,
    eta: { minutes: 40 },
  };
}

function serveStore(store: StoreListItem, quote: unknown = quoteOf('10000.00')) {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: [store] } });
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 1, branches: [] } } });
    if (url.startsWith('/catalog')) return Promise.resolve({ data: { success: true, data: { products: [LAVASH] } } });
    if (url === '/delivery/quote') return Promise.resolve({ data: { success: true, data: quote } });
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

// Lets the (fake) network answer and React render what it brought
const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
// ... and waits out the moment the quote is asked after the cart changed (useDeliveryQuote settles a change first)
const settle = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 600)); });

let queryClient: QueryClient;

async function renderCart(paymentSettings: unknown) {
  serveStore(storeWith(paymentSettings));
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <Cart />
        {/* Outside Telegram the checkout button (the MainButton) is drawn by the dock's host */}
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flush();
}

const tab = (name: RegExp) => screen.getByRole('button', { name });
const paymentTile = (name: RegExp) => screen.getByRole('radio', { name });
const queryPaymentTile = (name: RegExp) => screen.queryByRole('radio', { name });
const orderButton = () => screen.getByRole('button', { name: /^Buyurtma berish/ }) as HTMLButtonElement;
// A tab is pressed, a payment method (a radio) is checked
const isSelected = (element: HTMLElement) => element.getAttribute('aria-pressed') === 'true' || element.getAttribute('aria-checked') === 'true';

const DELIVERY = /^Yetkazib berish/;
const PICKUP = /^Olib ketish/;

beforeEach(() => {
  queryClient = new QueryClient();
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});

  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  useCartStore.setState({
    items: [{ id: '1-lavash', productId: 1, variantId: 'lavash', storeId: '1', name: 'Lavash (Standart)', price: 25000, quantity: 1 }],
    storeName: 'Evos',
    storeAddress: 'Asosiy filial',
  });
  useAddressStore.setState({
    addresses: [{ id: 1, title: 'Uy', address: 'Chilonzor 12', lat: 41.27, lng: 69.21, is_default: true }],
    selectedAddressId: 1,
  });
  post.mockResolvedValue({ data: { success: true, data: { order_number: 'A100' } } });
});

describe('Cart: payment methods follow the store settings the server enforces', () => {
  it.each([
    ['null (the store never saved any)', null],
    ['empty', {}],
    ['cash only', { cash: { enabled: true } }],
    ['without Click and Payme (older stores)', { delivery_fee: 10000, working_hours: { open: '09:00', close: '22:00' } }],
  ])('offers cash alone when the payment settings are %s: Click and Payme missing means off', async (_label, settings) => {
    await renderCart(settings);

    expect(queryPaymentTile(/click/i)).not.toBeInTheDocument();
    expect(queryPaymentTile(/payme/i)).not.toBeInTheDocument();
    expect(isSelected(paymentTile(/naqd/i))).toBe(true);

    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(1);
    expect(post.mock.calls[0][1]).toMatchObject({ payment_method: 'cash', type: 'delivery' });
  });

  it('does not preselect Click when the store has it switched off, whatever else it takes', async () => {
    await renderCart({ cash: { enabled: true }, click: { enabled: false, merchant_id: '1' }, payme: { enabled: true } });

    expect(queryPaymentTile(/click/i)).not.toBeInTheDocument();
    expect(isSelected(paymentTile(/naqd/i))).toBe(true);
    expect(isSelected(paymentTile(/payme/i))).toBe(false);
  });

  it('offers Click and Payme once the store switched them on, with Click preselected and the customer free to change it', async () => {
    await renderCart({ cash: { enabled: true }, click: { enabled: true }, payme: { enabled: true } });

    expect(isSelected(paymentTile(/click/i))).toBe(true);
    expect(isSelected(paymentTile(/naqd/i))).toBe(false);

    fireEvent.click(paymentTile(/payme/i));
    expect(isSelected(paymentTile(/payme/i))).toBe(true);
    expect(isSelected(paymentTile(/click/i))).toBe(false);

    fireEvent.click(orderButton());
    await flush();

    expect(post.mock.calls[0][1]).toMatchObject({ payment_method: 'payme' });
  });

  it('does not offer cash to a store that switched it off, and pays with what is left', async () => {
    await renderCart({ cash: { enabled: false }, click: { enabled: true } });

    expect(queryPaymentTile(/naqd/i)).not.toBeInTheDocument();
    expect(isSelected(paymentTile(/click/i))).toBe(true);
  });

  it('moves to the first method on offer when the one the customer picked is switched off meanwhile', async () => {
    await renderCart({ cash: { enabled: true }, click: { enabled: true }, payme: { enabled: true } });
    fireEvent.click(paymentTile(/payme/i));
    expect(isSelected(paymentTile(/payme/i))).toBe(true);

    // The store switches Payme off; the cart reloads the store once its items changed (and settled)
    serveStore(storeWith({ cash: { enabled: true }, click: { enabled: true }, payme: { enabled: false } }));
    act(() => useCartStore.getState().updateQuantity('1-lavash', 2));
    await settle();

    expect(queryPaymentTile(/payme/i)).not.toBeInTheDocument();
    expect(isSelected(paymentTile(/naqd/i))).toBe(true);

    await settle();
    await flush();
    fireEvent.click(orderButton());
    await flush();

    expect(post.mock.calls[0][1]).toMatchObject({ payment_method: 'cash' });
  });

  it('blocks the order, with a reason, when the store takes no payment at all', async () => {
    await renderCart({ cash: { enabled: false }, click: { enabled: false }, payme: { enabled: false } });

    expect(screen.getByText("Do'kon hozircha to'lov qabul qilmayapti. Keyinroq urinib ko'ring.")).toBeInTheDocument();
    expect(orderButton()).toBeDisabled();

    fireEvent.click(orderButton());
    expect(post).not.toHaveBeenCalled();
  });

  it('offers cashback next to the store methods, but only lets it pay for an order it covers', async () => {
    useAuthStore.getState().setAuth('token', makeUser({ balance: '12000.00' }), makeStore(), false);
    await renderCart({ cash: { enabled: true } });

    const keshbek = paymentTile(/keshbek/i) as HTMLButtonElement;
    expect(keshbek).toBeDisabled(); // 25 000 + 10 000 delivery is more than the 12 000 balance
    expect(isSelected(paymentTile(/naqd/i))).toBe(true);
  });
});

describe('Cart: delivery and pickup follow the store switches the server enforces', () => {
  it('offers both for a store that never switched either off', async () => {
    await renderCart({ cash: { enabled: true } });

    expect(tab(DELIVERY)).toBeEnabled();
    expect(tab(PICKUP)).toBeEnabled();
    expect(isSelected(tab(DELIVERY))).toBe(true);
  });

  it('does not offer delivery when the store switched it off: pickup is selected and the order is a pickup order', async () => {
    await renderCart({ cash: { enabled: true }, delivery_enabled: false, pickup_enabled: true });

    expect(tab(DELIVERY)).toBeDisabled();
    expect(tab(PICKUP)).toBeEnabled();
    expect(isSelected(tab(PICKUP))).toBe(true);
    expect(screen.queryByText('Yetkazib berish manzili')).not.toBeInTheDocument();

    expect(orderButton()).toBeEnabled();
    fireEvent.click(orderButton());
    await flush();

    expect(post.mock.calls[0][1]).toMatchObject({ type: 'pickup', branch_id: BRANCH.id, address: null, latitude: null, longitude: null });
  });

  it('does not offer pickup when the store switched it off, and the customer cannot switch to it', async () => {
    await renderCart({ cash: { enabled: true }, delivery_enabled: true, pickup_enabled: false });

    expect(tab(PICKUP)).toBeDisabled();
    expect(isSelected(tab(DELIVERY))).toBe(true);

    fireEvent.click(tab(PICKUP));
    expect(isSelected(tab(DELIVERY))).toBe(true);
    expect(screen.getByText('Yetkazib berish manzili')).toBeInTheDocument();

    fireEvent.click(orderButton());
    await flush();

    expect(post.mock.calls[0][1]).toMatchObject({ type: 'delivery', address: 'Chilonzor 12' });
  });

  it('moves a customer who had picked pickup to delivery when the store stops taking pickup', async () => {
    await renderCart({ cash: { enabled: true } });
    fireEvent.click(tab(PICKUP));
    expect(isSelected(tab(PICKUP))).toBe(true);

    serveStore(storeWith({ cash: { enabled: true }, pickup_enabled: false }));
    act(() => useCartStore.getState().updateQuantity('1-lavash', 2));
    await settle();

    expect(tab(PICKUP)).toBeDisabled();
    expect(isSelected(tab(DELIVERY))).toBe(true);
  });

  it('blocks the order, with a reason, when the store takes neither', async () => {
    await renderCart({ cash: { enabled: true }, delivery_enabled: false, pickup_enabled: false });

    expect(tab(DELIVERY)).toBeDisabled();
    expect(tab(PICKUP)).toBeDisabled();
    expect(screen.getAllByText(/Do'kon hozircha yetkazib berishni ham, olib ketishni ham qabul qilmayapti/)).not.toHaveLength(0);
    expect(orderButton()).toBeDisabled();

    fireEvent.click(orderButton());
    expect(post).not.toHaveBeenCalled();
  });
});

describe('Cart: placing the order', () => {
  it('marks the cached orders stale, so the orders screen shows the new one at once', async () => {
    queryClient.setQueryData(['orders', 'active', 'customer'], { orders: [makeOrder({ id: 1, status: 'new' })], truncated: false });
    await renderCart(null);
    expect(queryClient.getQueryState(['orders', 'active', 'customer'])?.isInvalidated).toBe(false);

    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(1);
    expect(queryClient.getQueryState(['orders', 'active', 'customer'])?.isInvalidated).toBe(true);
  });

  it('leaves them alone when the order was refused', async () => {
    queryClient.setQueryData(['orders', 'active', 'customer'], { orders: [], truncated: false });
    vi.spyOn(console, 'error').mockImplementation(() => {});
    post.mockRejectedValueOnce(new Error('network'));
    await renderCart(null);

    fireEvent.click(orderButton());
    await flush();

    expect(queryClient.getQueryState(['orders', 'active', 'customer'])?.isInvalidated).toBe(false);
  });
});

describe('Cart: one order per press', () => {
  it('sends the order once when the checkout button is pressed again while it is being sent', async () => {
    let answer: (value: unknown) => void = () => {};
    post.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)));
    await renderCart(null);

    const button = orderButton();
    fireEvent.click(button);
    fireEvent.click(button);
    await flush();
    fireEvent.click(orderButton());
    expect(post).toHaveBeenCalledTimes(1);

    await act(async () => answer({ data: { success: true, data: { order_number: 'A100' } } }));
    expect(post).toHaveBeenCalledTimes(1);
  });
});

describe('Cart: the screen', () => {
  it('shows the total in the checkout button (the MainButton stand-in)', async () => {
    await renderCart(null);

    expect(orderButton()).toHaveTextContent("Buyurtma berish · 35 000 so'm"); // 25 000 + 10 000 delivery
  });

  it('offers the catalog when the cart is empty, and has no checkout button', async () => {
    useCartStore.setState({ items: [], storeName: '', storeAddress: '' });
    await renderCart(null);

    expect(screen.getByRole('heading', { name: "Savat bo'sh" })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "Katalogga o'tish" })).toHaveAttribute('href', '/');
    expect(screen.queryByRole('button', { name: /^Buyurtma berish/ })).not.toBeInTheDocument();
  });
});
