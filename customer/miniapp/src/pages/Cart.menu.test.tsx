import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { useDialogQueue } from '../lib/dialogs';
import api from '../services/api';
import { useAddressStore } from '../store/useAddressStore';
import { useAuthStore } from '../store/authStore';
import { useCartStore, type CartItem } from '../store/cartStore';
import { failedRequest } from '../test/httpErrors';
import { makeProduct, makeStore, makeUser, makeVariant } from '../test/factories';
import type { BranchAvailability, ModifierGroup, StoreListItem } from '../types';
import Cart from './Cart';

// The cart on the new menu and delivery model (P1-31): modifier lines, items sold by weight, dishes that sold out, the
// delivery quote of the server and the Idempotency-Key of the order.

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const get = vi.mocked(api.get);
const post = vi.mocked(api.post);

const BRANCH = { id: 5, store_id: 1, name: 'Asosiy filial', latitude: 41.31, longitude: 69.28, radius_km: 10, address: "Amir Temur ko'chasi 1", phone: null };
const STORE: StoreListItem = { ...makeStore({ id: 1, payment_settings: { cash: { enabled: true } }, delivery_fee: 9000, min_order: 20000 }), branches: [BRANCH] };

const sauce: ModifierGroup = {
  id: 1, uuid: 'g-1', name: 'Sous', min_select: 1, max_select: 1, is_required: true, sort_order: 0,
  modifiers: [{ id: 5, uuid: 'm-5', modifier_group_id: 1, name: 'Pishloqli', price_delta: '3000.00', max_quantity: 1, sort_order: 0, is_active: true }],
};
const PRODUCTS = [
  makeProduct({ id: 1, name: 'Burger', variants: [makeVariant({ id: 1, uuid: 'v-burger', product_id: 1, price: '30000.00', stock: 20 })], modifier_groups: [sauce] }),
  makeProduct({ id: 2, name: 'Qazi', variants: [makeVariant({ id: 2, uuid: 'v-meat', product_id: 2, price: '120000.00', unit: 'kg', quantity_step: '0.500', min_quantity: '0.500', stock: 10 })] }),
  makeProduct({ id: 3, name: 'Cola', variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price: '8000.00', stock: 20 })] }),
];

const burger: CartItem = {
  id: '1-v-burger+m-5x1', productId: 1, variantId: 'v-burger', variantDbId: 1, storeId: '1', name: 'Burger (Standart)', price: 30000, quantity: 2,
  modifiers: [{ id: 5, uuid: 'm-5', groupId: 1, name: 'Pishloqli', priceDelta: 3000, quantity: 1 }],
};
const meat: CartItem = {
  id: '2-v-meat', productId: 2, variantId: 'v-meat', variantDbId: 2, storeId: '1', name: 'Qazi (Standart)', price: 120000, quantity: 1.5,
  unit: 'kg', quantityStep: 0.5, minQuantity: 0.5,
};
const cola: CartItem = { id: '3-v-cola', productId: 3, variantId: 'v-cola', variantDbId: 3, storeId: '1', name: 'Cola (Standart)', price: 8000, quantity: 1 };

function quoteOf(overrides: Record<string, unknown> = {}) {
  return {
    zone: { id: 7, name: 'Markaz', delivery_price: '12000.00', min_order_amount: '30000.00', free_delivery_from: null, eta_minutes: 40 },
    branch: { id: 5, name: 'Asosiy filial', distance_km: 1.2 },
    delivery_price: '12000.00',
    min_order_amount: '30000.00',
    free_delivery_applies: false,
    meets_min_order: true,
    eta: { minutes: 40 },
    ...overrides,
  };
}

let availability: BranchAvailability[];
let quote: () => Promise<unknown>;
let products = PRODUCTS;

function serve() {
  get.mockImplementation((url) => {
    if (url === '/stores') return Promise.resolve({ data: { success: true, data: [STORE] } });
    if (url.startsWith('/catalog/availability')) return Promise.resolve({ data: { success: true, data: { store_id: 1, branches: availability } } });
    if (url.startsWith('/catalog')) return Promise.resolve({ data: { success: true, data: { products } } });
    if (url === '/delivery/quote') return quote();
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });
let queryClient: QueryClient;

async function renderCart(items: CartItem[]) {
  useCartStore.setState({ items, storeName: 'Evos', storeAddress: 'Asosiy filial' });
  serve();
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <Cart />
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  await flush();
}

const orderButton = () => screen.getByRole('button', { name: /^(Buyurtma berish|Tugaganlarni olib tashlang|Yangi narxlarni tasdiqlang|Bu manzilga yetkazib berilmaydi)/ }) as HTMLButtonElement;
const lastAlert = () => useDialogQueue.getState().queue.at(-1)?.message;
// The list item of a dish by its exact name (the sold-out banner repeats the names inside longer sentences)
const line = (name: RegExp) => screen.getByText(new RegExp(`^${name.source}$`)).closest('li') as HTMLElement;
const settle = async () => { await act(async () => { await new Promise((resolve) => setTimeout(resolve, 600)); }); await flush(); };

beforeEach(() => {
  queryClient = new QueryClient();
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
  useDialogQueue.setState({ queue: [] });
  useAuthStore.getState().setAuth('token', makeUser({ balance: '0.00' }), makeStore(), false);
  useAddressStore.setState({ addresses: [{ id: 1, title: 'Uy', address: 'Chilonzor 12', lat: 41.27, lng: 69.21, is_default: true }], selectedAddressId: 1 });
  availability = [{ branch_id: 5, stopped: { products: [], variants: [], modifiers: [] }, sold_out: { variants: [] } }];
  quote = () => Promise.resolve({ data: { success: true, data: quoteOf() } });
  products = PRODUCTS;
  useCartStore.setState({ checkoutAttempt: null });
  post.mockReset();
  post.mockResolvedValue({ data: { success: true, data: { order_number: 'A100' } } });
});

describe('Cart: lines of the new menu', () => {
  it('shows the chosen modifiers, the price of the line with them, and the weight as "1,5 kg"', async () => {
    await renderCart([burger, meat]);

    const burgerLine = line(/Burger \(Standart\)/);
    expect(within(burgerLine).getByText(/Pishloqli · \+3\s000\sso'm/)).toBeInTheDocument();
    expect(within(burgerLine).getByText("66 000 so'm")).toBeInTheDocument(); // (30 000 + 3 000) × 2

    const meatLine = line(/Qazi \(Standart\)/);
    expect(within(meatLine).getByText("180 000 so'm")).toBeInTheDocument(); // 120 000 × 1,5
    expect(within(meatLine).getByText(/120\s000\sso'm \/ kg · 1,5\skg/)).toBeInTheDocument();
  });

  it('moves a weighed line in the step of the item and removes it at the minimum', async () => {
    await renderCart([cola, { ...meat, quantity: 1 }]);
    const meatLine = () => line(/Qazi \(Standart\)/);

    fireEvent.click(within(meatLine()).getByRole('button', { name: "Qazi (Standart): ko'paytirish" }));
    expect(useCartStore.getState().items.find((item) => item.id === '2-v-meat')?.quantity).toBe(1.5);

    fireEvent.click(within(meatLine()).getByRole('button', { name: 'Qazi (Standart): kamaytirish' }));
    fireEvent.click(within(meatLine()).getByRole('button', { name: 'Qazi (Standart): kamaytirish' }));
    expect(useCartStore.getState().items.find((item) => item.id === '2-v-meat')?.quantity).toBe(0.5);

    fireEvent.click(within(meatLine()).getByRole('button', { name: 'Qazi (Standart): savatdan olib tashlash' }));
    expect(useCartStore.getState().items.map((item) => item.id)).toEqual(['3-v-cola']);
  });

  it('sends the modifiers and the fractional quantity exactly as the backend reads them, and never a price', async () => {
    await renderCart([burger, meat]);

    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(1);
    const body = post.mock.calls[0][1] as { items: unknown[] };
    expect(body.items).toEqual([
      { variant_id: 'v-burger', quantity: 2, modifiers: [{ id: 'm-5', quantity: 1 }] },
      { variant_id: 'v-meat', quantity: 1.5 },
    ]);
    expect(JSON.stringify(body)).not.toMatch(/price|delivery_fee/);
  });
});

describe('Cart: dishes that sold out', () => {
  it('flags the line "Tugadi", explains it and does not let the order go', async () => {
    availability = [{ branch_id: 5, stopped: { products: [], variants: [3], modifiers: [] }, sold_out: { variants: [] } }];
    await renderCart([burger, cola]);

    expect(within(line(/Cola \(Standart\)/)).getByText('Tugadi')).toBeInTheDocument();
    expect(within(line(/Burger \(Standart\)/)).queryByText('Tugadi')).not.toBeInTheDocument();
    expect(screen.getAllByRole('alert').map((alert) => alert.textContent).join(' ')).toContain("Savatdagi ba'zi taomlar hozir buyurtma qilinmaydi");
    expect(orderButton()).toBeDisabled();
    expect(orderButton()).toHaveTextContent('Tugaganlarni olib tashlang');
    expect(post).not.toHaveBeenCalled();
  });

  it('takes the sold-out lines out with one tap, and then the order can go', async () => {
    availability = [{ branch_id: 5, stopped: { products: [], variants: [], modifiers: [] }, sold_out: { variants: [3] } }];
    await renderCart([burger, cola]);

    fireEvent.click(screen.getByRole('button', { name: 'Tugaganlarni olib tashlash' }));
    await settle();

    expect(useCartStore.getState().items.map((item) => item.id)).toEqual([burger.id]);
    expect(orderButton()).toBeEnabled();
  });

  it('flags a stopped modifier and names it', async () => {
    availability = [{ branch_id: 5, stopped: { products: [], variants: [], modifiers: [5] }, sold_out: { variants: [] } }];
    await renderCart([burger]);

    expect(within(line(/Burger \(Standart\)/)).getByText(/"Pishloqli" tugadi/)).toBeInTheDocument();
    expect(orderButton()).toBeDisabled();
  });

  it('flags a dish the store took off its menu', async () => {
    await renderCart([{ ...cola, id: '9-gone', productId: 9, variantId: 'gone', name: 'Eski taom' }]);

    expect(within(line(/Eski taom/)).getByText(/menyudan olib tashlangan/)).toBeInTheDocument();
    expect(orderButton()).toBeDisabled();
  });

  it('judges by the branch that will cook it: sold out on another branch only is fine', async () => {
    availability = [
      { branch_id: 5, stopped: { products: [], variants: [], modifiers: [] }, sold_out: { variants: [] } },
      { branch_id: 6, stopped: { products: [], variants: [3], modifiers: [] }, sold_out: { variants: [] } },
    ];
    await renderCart([cola]);

    expect(screen.queryByText('Tugadi')).not.toBeInTheDocument();
    expect(orderButton()).toBeEnabled();
  });

  it('tells the customer clearly when the server refuses a dish that sold out meanwhile, instead of the raw 422', async () => {
    await renderCart([cola]);
    post.mockRejectedValue(
      failedRequest(422, {
        message: 'The given data was invalid.',
        errors: { items: ["Uzr, 'Cola' tugadi. Iltimos, savatni yangilang."] },
      }, '/orders'),
    );

    fireEvent.click(orderButton());
    await flush();

    expect(lastAlert()).toBe("Uzr, 'Cola' tugadi. Iltimos, savatni yangilang.");
    expect(lastAlert()).not.toMatch(/given data/);
    // ... and the menu is asked for again so the cart shows what sold out
    const availabilityCalls = get.mock.calls.filter(([url]) => url.startsWith('/catalog/availability')).length;
    expect(availabilityCalls).toBeGreaterThanOrEqual(2);
  });
});

describe('Cart: the delivery quote of the server', () => {
  it("shows the zone's fee and total instead of calculating one", async () => {
    await renderCart([burger]);

    expect(screen.getByText(/Yetkazib berish: 12\s000\sso'm/)).toBeInTheDocument();
    const totals = screen.getByText('Kuryer xizmati').closest('div') as HTMLElement;
    expect(totals).toHaveTextContent("12 000 so'm");
    expect(screen.getByRole('button', { name: /^Buyurtma berish · 78\s000\sso'm/ })).toBeInTheDocument(); // 66 000 + 12 000, not the store's 9 000
    expect(get).toHaveBeenCalledWith('/delivery/quote', { params: { store: '1', lat: 41.27, lng: 69.21, subtotal: 66000 } });
  });

  it('says "Bepul" past the free-delivery threshold', async () => {
    quote = () => Promise.resolve({ data: { success: true, data: quoteOf({ delivery_price: '0.00', free_delivery_applies: true }) } });
    await renderCart([burger]);

    expect(screen.getByText('Yetkazib berish: Bepul')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Buyurtma berish · 66\s000\sso'm/ })).toBeInTheDocument();
  });

  it('refuses an address nobody delivers to, in Uzbek, and does not let the order go', async () => {
    quote = () => Promise.reject(failedRequest(422, { success: false, message: 'Manzil yetkazib berish hududidan tashqarida', data: { reason: 'outside_zone' } }, '/delivery/quote'));
    await renderCart([burger]);

    expect(screen.getAllByText('Bu manzilga yetkazib berilmaydi')).toHaveLength(2); // the note, and the checkout button
    expect(orderButton()).toBeDisabled();
    expect(orderButton()).toHaveTextContent('Bu manzilga yetkazib berilmaydi');
    expect(post).not.toHaveBeenCalled();
  });

  it('moves to pickup from the refusal', async () => {
    quote = () => Promise.reject(failedRequest(422, { success: false, message: 'x', data: { reason: 'outside_zone' } }, '/delivery/quote'));
    await renderCart([burger]);

    fireEvent.click(screen.getByRole('button', { name: 'Olib ketishni tanlash' }));
    await flush();

    expect(screen.getByRole('button', { name: /^Olib ketish/ })).toHaveAttribute('aria-pressed', 'true');
    expect(orderButton()).toBeEnabled();
  });

  it("blocks an order below the zone's minimum, with the minimum from the quote", async () => {
    quote = () => Promise.resolve({ data: { success: true, data: quoteOf({ min_order_amount: '100000.00', meets_min_order: false }) } });
    await renderCart([burger]);

    expect(screen.getByText('Minimal buyurtma miqdori yetarli emas')).toBeInTheDocument();
    expect(screen.getByText(/kamida 100\s000\sso'm/)).toBeInTheDocument();
    expect(orderButton()).toBeDisabled();
  });

  it('keeps the store fee and minimum for a store that has drawn no zones', async () => {
    quote = () => Promise.reject(failedRequest(422, { success: false, message: 'x', data: { reason: 'zones_not_configured' } }, '/delivery/quote'));
    await renderCart([burger]);

    expect(screen.queryByText('Bu manzilga yetkazib berilmaydi')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Buyurtma berish · 75\s000\sso'm/ })).toBeInTheDocument(); // 66 000 + the store's 9 000
  });

  it('waits for the quote before the order can go, and offers a retry when it cannot be asked', async () => {
    quote = () => Promise.reject(failedRequest(500, { message: 'Server Error' }, '/delivery/quote'));
    await renderCart([burger]);
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 1700)); });

    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
    expect(orderButton()).toBeDisabled();
  }, 10_000);

  it('shows the server\'s refusal of the order itself (below the minimum) in Uzbek', async () => {
    await renderCart([burger]);
    post.mockRejectedValue(
      failedRequest(422, { success: false, message: 'Bu hududda eng kam buyurtma summasi: 30 000 UZS.', data: { reason: 'below_minimum_order', min_order_amount: '30000.00', subtotal: '20000.00' } }, '/orders'),
    );

    fireEvent.click(orderButton());
    await flush();

    expect(lastAlert()).toMatch(/Bu hududda eng kam buyurtma summasi 30\s000\sso'm/);
  });

  it('shows the refusal of an address the server rejects at checkout (outside_zone)', async () => {
    await renderCart([burger]);
    post.mockRejectedValue(failedRequest(422, { success: false, message: 'x', data: { reason: 'outside_zone' } }, '/orders'));

    fireEvent.click(orderButton());
    await flush();

    expect(lastAlert()).toMatch(/^Bu manzilga yetkazib berilmaydi/);
  });
});

describe('Cart: one Idempotency-Key per checkout attempt', () => {
  const keyOf = (call: number) => (post.mock.calls[call][2] as { headers: Record<string, string> }).headers['Idempotency-Key'];

  it('sends the key as a header of POST /orders', async () => {
    await renderCart([burger]);

    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledWith('/orders', expect.objectContaining({ type: 'delivery' }), { headers: { 'Idempotency-Key': expect.stringMatching(/^[0-9a-f-]{36}$/) } });
  });

  it('reuses the key when the same order is sent again after a failure', async () => {
    await renderCart([burger]);
    post.mockRejectedValueOnce(failedRequest(undefined, undefined, '/orders'));

    fireEvent.click(orderButton());
    await flush();
    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(2);
    expect(keyOf(1)).toBe(keyOf(0));
  });

  it('takes a new key when the order changed in between', async () => {
    await renderCart([burger]);
    post.mockRejectedValueOnce(failedRequest(422, { message: 'x', errors: { items: ['Uzr, tugadi.'] } }, '/orders'));

    fireEvent.click(orderButton());
    await flush();
    act(() => useCartStore.getState().updateQuantity(burger.id, 3));
    await settle();
    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(2);
    expect(keyOf(1)).not.toBe(keyOf(0));
  });
});

describe('Cart: the checkout attempt outlives the cart page', () => {
  const keyOf = (call: number) => (post.mock.calls[call][2] as { headers: Record<string, string> }).headers['Idempotency-Key'];

  it('retries an unchanged order with the same key after the cart was left and opened again (the answer was lost)', async () => {
    // The first POST reached the server (the order exists) but its answer was lost on the way back
    post.mockRejectedValueOnce(failedRequest(undefined, undefined, '/orders'));
    await renderCart([cola, { ...burger }]);
    await settle();
    fireEvent.click(orderButton());
    await flush();
    expect(lastAlert()).toMatch(/ikki marta yaratilmaydi/);

    cleanup(); // the customer looks at "Buyurtmalarim", then comes back to the cart (or Telegram reloads the Mini App)
    await renderCart(useCartStore.getState().items);
    await settle();
    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(2);
    expect(post.mock.calls[1][1]).toEqual(post.mock.calls[0][1]);
    expect(keyOf(1)).toBe(keyOf(0)); // the server answers with the first order instead of placing a second
  });

  it('starts a new attempt once the order was placed', async () => {
    await renderCart([cola]);
    await settle();
    fireEvent.click(orderButton());
    await flush();
    expect(useCartStore.getState().checkoutAttempt).toBeNull();

    cleanup();
    await renderCart([cola]);
    await settle();
    fireEvent.click(orderButton());
    await flush();

    expect(post).toHaveBeenCalledTimes(2);
    expect(keyOf(1)).not.toBe(keyOf(0));
  });
});

describe('Cart: prices of the menu as it is now', () => {
  const dearerCola = () => [PRODUCTS[0], PRODUCTS[1], makeProduct({ id: 3, name: 'Cola', variants: [makeVariant({ id: 3, uuid: 'v-cola', product_id: 3, price: '12000.00', stock: 20 })] })];

  it('totals a line at the current price and flags the change before the order can go', async () => {
    products = dearerCola();
    await renderCart([burger, { ...cola, quantity: 2 }]); // cola was added at 8 000
    await settle();

    const colaLine = line(/Cola \(Standart\)/);
    expect(within(colaLine).getByText("24 000 so'm")).toBeInTheDocument(); // 12 000 × 2, what the server charges
    expect(within(colaLine).getByText(/Narxi o'zgardi: 16\s000\sso'm → 24\s000\sso'm/)).toBeInTheDocument();
    expect(screen.getByText("Ba'zi taomlarning narxi o'zgardi")).toBeInTheDocument();
    // The quote is asked for the subtotal the server will see: 66 000 + 24 000
    expect(get).toHaveBeenCalledWith('/delivery/quote', { params: { store: '1', lat: 41.27, lng: 69.21, subtotal: 90000 } });
    expect(orderButton()).toHaveTextContent('Yangi narxlarni tasdiqlang');
    expect(orderButton()).toBeDisabled();
    expect(post).not.toHaveBeenCalled();
  });

  it('lets the customer accept the new prices, and then the order goes with the right total', async () => {
    products = dearerCola();
    await renderCart([burger, { ...cola, quantity: 2 }]);
    await settle();

    fireEvent.click(screen.getByRole('button', { name: 'Yangi narxlarni qabul qilish' }));
    await settle();

    expect(useCartStore.getState().items.find((item) => item.id === cola.id)?.price).toBe(12000);
    expect(screen.queryByText("Ba'zi taomlarning narxi o'zgardi")).not.toBeInTheDocument();
    expect(orderButton()).toBeEnabled();
    expect(orderButton()).toHaveTextContent(/^Buyurtma berish · 102\s000\sso'm/); // 90 000 + delivery 12 000
  });

  it('prices modifiers at their current delta', async () => {
    products = [makeProduct({ ...PRODUCTS[0], modifier_groups: [{ ...sauce, modifiers: [{ ...sauce.modifiers[0], price_delta: '5000.00' }] }] }), PRODUCTS[1], PRODUCTS[2]];
    await renderCart([burger]);
    await settle();

    const burgerLine = line(/Burger \(Standart\)/);
    expect(within(burgerLine).getByText(/Pishloqli · \+5\s000\sso'm/)).toBeInTheDocument();
    expect(within(burgerLine).getByText("70 000 so'm")).toBeInTheDocument(); // (30 000 + 5 000) × 2
    expect(orderButton()).toHaveTextContent('Yangi narxlarni tasdiqlang');
  });

  it('asks for the stores and the menu again once the +/- taps settle, not on every tap', async () => {
    await renderCart([cola]);
    await settle();
    const fetches = () => get.mock.calls.filter(([url]) => url === '/stores' || /^\/catalog\?/.test(url)).length;
    const before = fetches();

    fireEvent.click(within(line(/Cola \(Standart\)/)).getByRole('button', { name: "Cola (Standart): ko'paytirish" }));
    fireEvent.click(within(line(/Cola \(Standart\)/)).getByRole('button', { name: "Cola (Standart): ko'paytirish" }));
    await settle();

    expect(useCartStore.getState().items[0].quantity).toBe(3);
    expect(fetches()).toBe(before + 2); // one /stores and one /catalog for the two taps
  });
});
