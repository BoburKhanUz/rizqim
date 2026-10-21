import type { StaffOrder, StaffOrderItem } from '@rizqim/staff';
import type { Order, OrderItem } from '../types';

// A fake of GET /staff/orders (the staff board's list) for the screens' tests, and the fixtures it serves.

const FINISHED = new Set(['delivered', 'canceled', 'rejected', 'refunded']);

export function makeStaffItem(overrides: Partial<StaffOrderItem> = {}): StaffOrderItem {
  return {
    id: 1,
    product_name: 'Lavash',
    variant_name: 'Standart',
    quantity: 1,
    unit: 'piece',
    quantity_label: '1',
    modifiers: [],
    kitchen_status: 'pending',
    ready_at: null,
    unit_price: '25000.00',
    modifiers_total: '0.00',
    total_price: '25000.00',
    ...overrides,
  };
}

export function makeStaffOrder(overrides: Partial<StaffOrder> = {}): StaffOrder {
  return {
    uuid: '55555555-5555-4555-8555-555555555555',
    short_code: 'A1B2',
    status: 'new',
    status_label: 'Yangi',
    type: 'delivery',
    channel: 'telegram',
    store_id: 1,
    branch_id: 1,
    branch_name: 'Chilonzor',
    payment_method: 'cash',
    payment_status: 'pending',
    subtotal: '25000.00',
    discount: '0.00',
    delivery_price: '5000.00',
    service_charge_amount: '0.00',
    place_fee_amount: '0.00',
    total_price: '30000.00',
    notes: null,
    guests_count: null,
    hall_ref: null,
    table_ref: null,
    cancel_reason: null,
    cancel_note: null,
    customer: { name: 'Mijoz Test', phone: '+998909998877' },
    delivery: null,
    courier: null,
    items_total: 1,
    items_ready: 0,
    items: [makeStaffItem()],
    created_at: '2026-10-14T09:50:00+05:00',
    updated_at: '2026-10-14T09:50:00+05:00',
    accepted_at: null,
    preparing_at: null,
    ready_at: null,
    closed_at: null,
    waiting_seconds: 0,
    ...overrides,
  };
}

const staffItemOf = (item: OrderItem): StaffOrderItem =>
  makeStaffItem({
    id: item.id,
    product_name: item.product_name,
    variant_name: item.variant_name,
    quantity: item.quantity,
    unit: item.unit ?? 'piece',
    modifiers: (item.modifiers ?? []).map((modifier) => ({ name: modifier.name, group_name: modifier.group_name, quantity: modifier.quantity, price_delta: modifier.price_delta })),
    total_price: item.total_price,
  });

// The staff view of an order of the app's own fixtures (the same order the old GET /orders served)
export function toStaffOrder(order: Order): StaffOrder {
  const items = (order.items ?? []).map(staffItemOf);
  return makeStaffOrder({
    uuid: order.uuid,
    short_code: order.short_code,
    status: order.status,
    type: order.order_type,
    branch_id: order.branch_id,
    payment_method: order.payment_method,
    payment_status: order.payment_status,
    total_price: order.total_price,
    notes: order.notes,
    cancel_reason: order.cancel_reason,
    cancel_note: order.cancel_note,
    delivery: order.address ? { address: order.address, latitude: null, longitude: null, zone_name: null } : null,
    items,
    items_total: items.length,
    items_ready: items.filter((item) => item.kitchen_status === 'ready').length,
    created_at: order.created_at,
    updated_at: order.updated_at,
  });
}

// What GET /staff/orders answers for `query` over `orders` (newest first): the open ones for scope=active, a page of the
// finished ones for scope=history (the cursor is the offset of the page, as a string).
export function staffOrdersResponse(orders: readonly StaffOrder[], query: Record<string, string>) {
  const perPage = Number(query.per_page ?? 20);
  const offset = query.cursor ? Number(query.cursor) : 0;
  const branchId = query.branch_id ? Number(query.branch_id) : null;
  const inBranch = orders.filter((order) => branchId === null || order.branch_id === branchId);
  const scoped = inBranch.filter((order) => (query.scope === 'history' ? FINISHED.has(order.status) : !FINISHED.has(order.status)));
  const end = offset + perPage;
  return {
    success: true,
    message: '',
    data: scoped.slice(offset, end),
    meta: { next_cursor: end < scoped.length ? String(end) : null, per_page: perPage },
  };
}
