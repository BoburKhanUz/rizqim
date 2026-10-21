// Typed fixtures shaped like the API's JSON. Each takes the fields a test cares about.
import type {
  ApiUser,
  Employee,
  LedgerTransaction,
  Order,
  OrderItem,
  OwnerAnalytics,
  OwnerFinance,
  PayoutRequest,
  Product,
  ProductVariant,
  Store,
} from '../types';

const TIMESTAMP = '2026-10-18T09:00:00.000000Z';

export function makeUser(overrides: Partial<ApiUser> = {}): ApiUser {
  return {
    id: 1,
    uuid: '11111111-1111-4111-8111-111111111111',
    name: 'Ali Valiyev',
    email: null,
    telegram_id: '555000111',
    telegram_username: 'ali',
    telegram_photo_url: null,
    phone: null,
    phone_verified_at: null,
    address: null,
    role: 'customer',
    store_id: 1,
    branch_id: null,
    balance: '0.00',
    referred_by: null,
    telegram_invite_token: null,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makeStore(overrides: Partial<Store> = {}): Store {
  return {
    id: 1,
    uuid: '22222222-2222-4222-8222-222222222222',
    name: 'Evos',
    slug: 'evos-1234',
    is_active: true,
    status: 'active',
    timezone: 'Asia/Tashkent',
    currency: 'UZS',
    language: 'uz',
    theme_color: '#000000',
    telegram_bot_username: null,
    payment_settings: null,
    address: null,
    location_url: null,
    logo: null,
    logo_url: null,
    banner: null,
    banner_url: null,
    description: null,
    is_open: true,
    open_time: '09:00:00',
    close_time: '23:00:00',
    min_order_amount: '0.00',
    min_order: 0,
    delivery_fee: 0,
    is_premium: false,
    is_featured: false,
    is_order_locked: false,
    review_count: 0,
    rating: '0.00',
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makeVariant(overrides: Partial<ProductVariant> = {}): ProductVariant {
  return {
    id: 1,
    uuid: '33333333-3333-4333-8333-333333333333',
    product_id: 1,
    name: 'Standart',
    sku: null,
    price: '25000.00',
    stock: 10,
    attributes_json: null,
    is_active: true,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makeProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    uuid: '44444444-4444-4444-8444-444444444444',
    store_id: 1,
    category_id: 1,
    name: 'Lavash',
    slug: 'lavash-abc123',
    description: null,
    image: null,
    image_url: null,
    type: 'physical',
    sku: null,
    status: 'active',
    is_active: true,
    sort_order: 0,
    has_variants: true,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    variants: [makeVariant()],
    ...overrides,
  };
}

export function makeOrderItem(overrides: Partial<OrderItem> = {}): OrderItem {
  return {
    id: 1,
    order_id: 1,
    product_variant_id: 1,
    product_name: 'Lavash',
    variant_name: 'Standart',
    quantity: 1,
    unit_price: '25000.00',
    total_price: '25000.00',
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makeOrder(overrides: Partial<Order> = {}): Order {
  return {
    id: 1,
    uuid: '55555555-5555-4555-8555-555555555555',
    short_code: 'A1B2',
    store_id: 1,
    user_id: 1,
    courier_id: null,
    manager_id: null,
    branch_id: null,
    address_id: null,
    promo_code_id: null,
    status: 'delivered',
    payment_status: 'paid',
    payment_method: 'cash',
    payment_owner: 'STORE',
    order_type: 'delivery',
    type: 'delivery',
    subtotal: '25000.00',
    delivery_price: '5000.00',
    discount: '0.00',
    total_price: '30000.00',
    commission_amount: '0.00',
    currency: 'UZS',
    notes: null,
    cancel_reason: null,
    cancel_note: null,
    shipping_address: null,
    address: null,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    deleted_at: null,
    ...overrides,
  };
}

export function makeEmployee(overrides: Partial<Employee> = {}): Employee {
  return {
    ...makeUser(),
    role: 'manager',
    branch_id: 1,
    branch: { id: 1, name: 'Chilonzor' },
    ...overrides,
  };
}

export function makeLedgerTransaction(overrides: Partial<LedgerTransaction> = {}): LedgerTransaction {
  return {
    id: 1,
    store_id: 1,
    type: 'commission',
    amount: '15000.00',
    balance_before: '150000.00',
    balance_after: '165000.00',
    reference_id: null,
    order_id: null,
    payment_id: null,
    performed_by: null,
    notes: null,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

export function makePayoutRequest(overrides: Partial<PayoutRequest> = {}): PayoutRequest {
  return {
    id: 1,
    store_id: 1,
    amount: '250000.00',
    card: '8600 **** **** 1234',
    card_number: '8600 **** **** 1234',
    status: 'pending',
    processed_by: null,
    processed_at: null,
    notes: null,
    resolution_notes: null,
    created_at: TIMESTAMP,
    updated_at: TIMESTAMP,
    ...overrides,
  };
}

// GET /owner/finance: the balances are floats, the ledger and payout amounts are decimal strings
export function makeFinance(overrides: Partial<OwnerFinance> = {}): OwnerFinance {
  return {
    debt_balance: 150000,
    wallet_balance: 500000,
    credit_limit: 1000000,
    min_payout_reserve: 100000,
    payment_account_type: 'platform_account',
    commission_rate: 10,
    status: 'active',
    is_order_locked: false,
    ledger_transactions: [],
    wallet_transactions: [],
    payout_requests: [],
    ...overrides,
  };
}

// GET /owner/analytics for a day with a few sales: 3 delivered orders (130 000, 90 000 of it cash), 2 canceled, 1 open.
export function makeAnalytics(overrides: Partial<OwnerAnalytics> = {}): OwnerAnalytics {
  return {
    period: { key: 'today', from: '2026-10-18', to: '2026-10-18', days: 1, timezone: 'Asia/Tashkent', basis: 'created_at' },
    summary: { orders_count: 6, delivered_count: 3, canceled_count: 2, refunded_count: 0, active_count: 1, revenue: 130000, average_check: 43333.33 },
    by_status: { delivered: 3, canceled: 1, rejected: 1, preparing: 1 },
    by_payment_method: [
      { method: 'cash', orders: 2, revenue: 90000 },
      { method: 'click', orders: 1, revenue: 40000 },
    ],
    by_order_type: [{ type: 'delivery', orders: 3, revenue: 130000 }],
    top_products: [
      { name: 'Cheeseburger', quantity: 5, revenue: 100000 },
      { name: 'Cola', quantity: 3, revenue: 30000 },
    ],
    couriers: [{ id: 7, name: 'Sardor', delivered_count: 2 }],
    times: { average_prep_seconds: 150, average_delivery_seconds: 1500 },
    daily: [{ date: '2026-10-18', orders: 6, delivered: 3, revenue: 130000 }],
    generated_at: '2026-10-18T07:00:00+00:00',
    ...overrides,
  };
}
