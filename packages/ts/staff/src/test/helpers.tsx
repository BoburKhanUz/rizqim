import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi } from 'vitest';
import { createStaffApi, type StaffApi } from '../api/client';
import { StaffApiProvider } from '../hooks/context';
import type { Order } from '../types';

export const API_ROOT = 'http://api.test/api';

export const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

// {success, message, data} like App\Traits\ApiResponse
export const ok = (data: unknown, extra: Record<string, unknown> = {}) => json({ success: true, message: 'ok', data, ...extra });

type Handler = (request: Request) => Response | Promise<Response>;

// Replaces fetch with a router: "METHOD /path" -> handler. An unlisted call fails the test loudly.
export function mockFetch(routes: Record<string, Handler>) {
  const calls: Request[] = [];
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const request = input instanceof Request ? input : new Request(input, init);
    calls.push(request.clone());
    const key = `${request.method} ${new URL(request.url).pathname}`;
    const handler = routes[key];
    if (!handler) throw new Error(`Unexpected request: ${key}`);
    return handler(request);
  });
  vi.stubGlobal('fetch', fetchMock);
  return { fetchMock, calls };
}

// An API whose requests go through the (stubbed) global fetch, unsigned
export const testApi = (): StaffApi => createStaffApi({ baseUrl: API_ROOT, fetch: (request) => globalThis.fetch(request) });

export function makeQueryClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } } });
}

export function wrapperFor(queryClient: QueryClient, api: StaffApi = testApi()) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <StaffApiProvider api={api}>
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </StaffApiProvider>
    );
  };
}

let nextId = 1;

// An order as GET /orders sends it (only the fields the package reads are meaningful)
export function makeOrder(over: Partial<Order> = {}): Order {
  const id = nextId++;
  return {
    id,
    uuid: `aaaaaaaa-0000-0000-0000-${String(id).padStart(12, '0')}`,
    short_code: `A${100 + id}`,
    store_id: 1,
    user_id: 7,
    status: 'new',
    payment_status: 'pending',
    order_type: 'delivery',
    subtotal: '40000.00',
    delivery_price: '5000.00',
    total_price: '45000.00',
    currency: 'UZS',
    notes: null,
    shipping_address: null,
    created_at: '2026-10-20T10:00:00+05:00',
    updated_at: '2026-10-20T10:00:00+05:00',
    deleted_at: null,
    payment_method: 'cash',
    courier_id: null,
    commission_amount: '0.00',
    branch_id: 1,
    address_id: null,
    manager_id: null,
    payment_owner: 'STORE',
    discount: '0.00',
    promo_code_id: null,
    settlement_failed_at: null,
    settlement_error: null,
    settlement_attempts: 0,
    cancel_reason: null,
    cancel_note: null,
    cancelled_by: null,
    channel: 'telegram',
    external_id: null,
    service_charge_amount: '0.00',
    place_fee_amount: '0.00',
    guests_count: null,
    hall_ref: null,
    table_ref: null,
    delivery_zone_id: null,
    delivery_zone_name: null,
    delivery_zone_price: null,
    delivery_min_order_amount: null,
    type: 'delivery',
    address: null,
    // POS columns (phase 3): empty on online orders
    hall_id: null,
    table_id: null,
    waiter_id: null,
    device_id: null,
    version: 0,
    room_fee_amount: '0.00',
    min_spend_topup_amount: '0.00',
    booking_id: null,
    due_at: null,
    shift_id: null,
    business_date: null,
    queue_number: null,
    bill_locked_at: null,
    closed_at: null,
    long_open_alerted_at: null,
    bill_requested_at: null,
    bill_requested_by: null,
    ...over,
  };
}
