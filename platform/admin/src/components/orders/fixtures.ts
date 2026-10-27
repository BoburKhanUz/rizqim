import type { OrderCard, OrderPage, OrderRow, RefundRequest } from '@/lib/api/orders';

// Orders as the backend answers them (AdminOrderController), for the screen tests
export const NOW = new Date('2026-11-10T09:00:00Z').getTime();
const iso = (minutesAgo: number) => new Date(NOW - minutesAgo * 60_000).toISOString();

export function row(overrides: Partial<OrderRow> = {}): OrderRow {
  return {
    uuid: '01a17dcb-0000-7000-8000-000000000001',
    short_code: 'ORD-AB12CD',
    status: 'new',
    status_label: 'Yangi',
    status_group: 'new',
    type: 'delivery',
    channel: 'telegram',
    channel_label: 'Telegram',
    store: { id: 3, name: 'Lazzat Oshxona' },
    branch: { id: 5, name: 'Chilonzor' },
    region: { id: 1, name: 'Toshkent shahri' },
    customer_name: 'Navro\'za Karimova',
    total: '85000.00',
    payment_method: 'cash',
    payment_status: 'pending',
    courier: null,
    sla: { stage: 'accept', stage_label: 'Qabul qilish', state: 'late', limit_minutes: 5, started_at: iso(17), warning_at: iso(13), due_at: iso(12), late_by_minutes: 12, minutes_left: 0 },
    refund_pending: false,
    manual_refund_required: false,
    created_at: iso(17),
    updated_at: iso(17),
    ...overrides,
  };
}

export function page(rows: OrderRow[], overrides: Partial<OrderPage> = {}): OrderPage {
  return {
    data: rows,
    next_cursor: null,
    per_page: 30,
    counts: { active: rows.length, new: 1, kitchen: 0, en_route: 0, done: 4, canceled: 1 },
    sla_limits: { accept: 5, prepare: 30, deliver: 45 },
    server_time: new Date(NOW).toISOString(),
    ...overrides,
  };
}

export function refundRequest(overrides: Partial<RefundRequest> = {}): RefundRequest {
  return {
    id: 7,
    uuid: 'r-7',
    amount: '50000.00',
    status: 'pending',
    status_label: 'Tasdiq kutilmoqda',
    reason: 'Taom sovuq keldi',
    requested_by: { id: 2, name: 'Yordam 1' },
    requested_at: iso(30),
    decided_by: null,
    decided_at: null,
    decision_note: null,
    execution: null,
    mine: false,
    can_decide: true,
    ...overrides,
  };
}

export function card(overrides: Partial<OrderCard> = {}): OrderCard {
  const base = row({ status: 'delivered', status_label: 'Yetkazildi', status_group: 'done', sla: null });
  return {
    ...base,
    notes: 'Eshik oldida qoldiring',
    delivery_address: 'Amir Temur 1',
    customer: { id: 11, name: "Navro'za Karimova", phone: '+998 93 *** ** 77', has_telegram: true },
    courier: { id: 21, name: 'Kuryer 1', phone: '+998 90 *** ** 12' },
    store: { id: 3, name: 'Lazzat Oshxona', phone: '+998 71 *** ** 00' },
    branch: { id: 5, name: 'Chilonzor', address: 'Bunyodkor 5' },
    items: [{ id: 1, name: 'Osh', variant: 'Katta', modifiers: 'Qazi', quantity: '2', unit_price: '40000.00', total: '80000.00', void: false }],
    totals: { subtotal: '80000.00', discount: '0.00', delivery_price: '5000.00', service_charge: '0.00', place_fee: '0.00', total: '85000.00', commission: '8500.00', payment_owner: 'PLATFORM' },
    cancel: null,
    payments: [{ uuid: 'p1', provider: 'payme', amount: '85000.00', status: 'paid', paid_at: iso(60), manual_refund_required: false, manual_refund_approved: [], created_at: iso(61) }],
    refunds: { paid: '85000.00', refunded: '0.00', refundable: '85000.00', requests: [] },
    history: [
      { status: 'new', status_label: 'Yangi', from_status: null, at: iso(60), actor: null, notes: null },
      { status: 'accepted', status_label: 'Qabul qilindi', from_status: 'new', at: iso(58), actor: { id: 30, name: 'Menejer 1', role: 'Menejer', team: false }, notes: null },
      { status: 'delivered', status_label: 'Yetkazildi', from_status: 'on_the_way', at: iso(5), actor: { id: 40, name: 'Operator 1', role: 'Operator', team: true }, notes: 'Boshqaruv markazi: holat tuzatildi. Sabab: Kuryer topshirdi' },
    ],
    sla_timeline: [
      { stage: 'prepare', stage_label: 'Tayyorlash', limit_minutes: 30, started_at: iso(58), ended_at: iso(20), took_minutes: 38, running: false, state: 'late', late_by_minutes: 8 },
    ],
    sla_events: [{ stage: 'prepare', stage_label: 'Tayyorlash', state: 'late', limit_minutes: 30, crossed_at: iso(28) }],
    fiscal_receipts: [{ uuid: 'f1', type: 'sale', status: 'issued', provider: 'payme', total: '85000.00', receipt_number: '77', qr_url: 'https://ofd.soliq.uz/check?t=1', issued_at: iso(59), error: null }],
    audit: [{ id: 9, action: 'admin.orders.status_fixed', actor: { id: 40, name: 'Operator 1', role: 'Operator', team: true }, reason: 'Kuryer topshirdi', before: { status: 'on_the_way' }, after: { status: 'delivered' }, meta: null, created_at: iso(5) }],
    actions: {
      can_intervene: true,
      can_cancel: false,
      cancel_reasons: [],
      transitions: [],
      message_store: true,
      message_customer: true,
      can_request_refund: true,
      can_approve_refund: false,
    },
    ...overrides,
  };
}
