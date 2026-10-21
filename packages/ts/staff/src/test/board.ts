import type { StaffOrder, StaffOrderDetail } from '../api/board';

let nextId = 1;

// An order as GET /staff/orders lists it (P2-21 tests)
export function makeStaffOrder(over: Partial<StaffOrder> = {}): StaffOrder {
  const id = nextId++;
  return {
    uuid: `bbbbbbbb-0000-4000-8000-${String(id).padStart(12, '0')}`,
    short_code: `B${100 + id}`,
    status: 'new',
    status_label: 'Yangi',
    type: 'delivery',
    channel: 'telegram',
    store_id: 1,
    branch_id: 1,
    branch_name: 'Chilonzor',
    payment_method: 'cash',
    payment_status: 'pending',
    subtotal: '40000.00',
    discount: '0.00',
    delivery_price: '5000.00',
    service_charge_amount: '0.00',
    place_fee_amount: '0.00',
    total_price: '45000.00',
    notes: null,
    guests_count: null,
    hall_ref: null,
    table_ref: null,
    cancel_reason: null,
    cancel_note: null,
    customer: { name: 'Aziz', phone: '+998901234567' },
    delivery: { address: 'Chilonzor 9', latitude: null, longitude: null, zone_name: 'Markaz' },
    courier: null,
    items_total: 1,
    items_ready: 0,
    items: [
      {
        id: id * 10,
        product_name: 'Osh',
        variant_name: null,
        quantity: 1.5,
        unit: 'kg',
        quantity_label: '1.5 kg',
        modifiers: [{ name: 'Qazi', quantity: 1, price_delta: '8000.00' }],
        kitchen_status: 'pending',
        ready_at: null,
        unit_price: '25000.00',
        total_price: '45500.00',
      },
    ],
    created_at: '2026-10-20T10:00:00+05:00',
    updated_at: '2026-10-20T10:00:00+05:00',
    accepted_at: null,
    preparing_at: null,
    ready_at: null,
    closed_at: null,
    waiting_seconds: 0,
    ...over,
  };
}

export function makeStaffOrderDetail(over: Partial<StaffOrderDetail> = {}): StaffOrderDetail {
  return { ...makeStaffOrder(), status_history: [{ from_status: null, status: 'new', created_at: '2026-10-20T10:00:00+05:00' }], ...over };
}
