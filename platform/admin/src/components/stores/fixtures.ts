import type { ApplicationCard, StoreCard, StorePage, StoreRow } from '@/lib/api/stores';

// Shapes the Ovqatxonalar tests answer with (as App\Services\Admin\Stores\StorePresenter sends them)

export function storeRow(overrides: Partial<StoreRow> = {}): StoreRow {
  return {
    id: 12,
    uuid: '0b6c1f9e-0000-4000-8000-000000000012',
    name: 'Lazzat Oshxona',
    status: 'active',
    status_label: 'Faol',
    is_active: true,
    is_premium: false,
    is_featured: false,
    region: { id: 1, name: 'Toshkent shahri' },
    branches_count: 2,
    owner: { id: 40, name: 'Aziz', telegram_linked: true },
    plan: { code: 'asosiy', name: 'Asosiy', status: 'active', status_label: 'Faol' },
    debt_balance: '0.00',
    subscription_debt: '0.00',
    commission_debt: '0.00',
    credit_limit: '100000.00',
    over_limit: false,
    health: { level: 'good', label: 'Yaxshi', score: 100, reasons: 0, top_reason: null, computed_at: '2026-11-10T09:00:00+05:00' },
    next_contact_on: null,
    created_at: '2026-09-01T10:00:00+05:00',
    ...overrides,
  };
}

export function storePage(rows: StoreRow[] = [storeRow()]): StorePage {
  return {
    data: rows,
    current_page: 1,
    last_page: 1,
    per_page: 30,
    total: rows.length,
    health_counts: { all: rows.length, good: 1, attention: 0, churn_risk: rows.length - 1, none: 0 },
    statuses: { active: 'Faol', suspended: "To'xtatilgan", pending: 'Ulanmoqda' },
  };
}

export function storeCard(overrides: Partial<StoreCard> = {}): StoreCard {
  return {
    profile: {
      id: 12,
      uuid: '0b6c1f9e-0000-4000-8000-000000000012',
      name: 'Lazzat Oshxona',
      status: 'active',
      status_label: 'Faol',
      is_active: true,
      is_open: true,
      is_premium: false,
      is_featured: false,
      is_order_locked: false,
      phone: '+998 90 *** ** 67',
      address: 'Chilonzor 1',
      description: null,
      logo_url: null,
      tin: '305000111',
      legal_name: 'Lazzat MChJ',
      tax_regime: null,
      payment_account_type: 'platform_account',
      commission_rate: '10',
      rating: '4.50',
      review_count: 31,
      created_at: '2026-09-01T10:00:00+05:00',
      telegram: { store_chat_linked: true, owner_linked: true, owner_invite_pending: false },
    },
    application: null,
    contacts: [{ kind: 'staff', user_id: 40, name: 'Aziz', role: 'owner', role_label: 'Ega', phone: '+998 90 *** ** 21', telegram_linked: true, branch_id: null }],
    branches: [{ id: 5, name: 'Asosiy Filial', address: 'Chilonzor 1', phone: null, region: { id: 1, name: 'Toshkent shahri' }, district: null, is_active: true, open_time: '09:00:00', close_time: '23:00:00' }],
    status_history: [{ from_status: 'pending', from_label: 'Ulanmoqda', to_status: 'active', to_label: 'Faol', by: { id: 1, name: 'Aziza Karimova' }, comment: 'Ulash jarayoni yakunlandi', created_at: '2026-09-02T10:00:00+05:00' }],
    plan: {
      source: 'billing_v2',
      subscription: null,
      entitlements: { plan: null, status: null, settings_locked: false, locked_since: null, features: {}, devices: { pos: { included: null, active: 1, extra: 0 } } },
      debt: { debt_balance: '250000.00', subscription_debt: '50000.00', commission_debt: '200000.00', credit_limit: '100000.00', over_credit_limit: true },
      commission: {
        rates: { rizqim_courier: { rate: '15.00', source: 'default', default: '15.00' }, own_delivery: { rate: '10.00', source: 'default', default: '10.00' }, pickup: { rate: '5.00', source: 'default', default: '5.00' }, pos: { rate: '0.00', source: 'default', default: '0.00' } },
        overrides: {},
        flat_rate: null,
        delivery_courier: 'own',
      },
      history: [],
      invoices: [{ uuid: 'inv-1', number: 'OB-202610-000012', kind: 'monthly', period: '2026-10', amount: '249000.00', paid_amount: '199000.00', outstanding: '50000.00', status: 'issued', due_date: '2026-11-10' }],
    },
    devices: {
      terminals: 1,
      terminals_online: 0,
      by_type: { pos: 1, kds: 0 },
      items: [{ uuid: 'd-1', name: 'Kassa 1', type: 'pos', type_label: 'Kassa', terminal: true, branch: { id: 5, name: 'Asosiy Filial' }, platform: 'android', app_version: '1.4.0', last_seen_at: '2026-11-05T10:00:00+05:00', online: false, paired_at: null }],
    },
    fiscal: { enabled: false, driver: null, tax_regime: null, failed_7d: 0, pending: 0, missing_mxik: 0, last_failed: [] },
    finance: { debt_balance: '250000.00', subscription_debt: '50000.00', commission_debt: '200000.00', wallet_balance: '0.00', credit_limit: '100000.00', over_limit: true, min_payout_reserve: '100000.00', available_for_payout: '0.00', payouts: [], ledger: [] },
    orders: { quality: null, last_30_days: [], recent: [] },
    complaints: { open: 0, last_30_days: 0, items: [] },
    health: {
      level: 'churn_risk',
      label: 'Ketish xavfi',
      score: 55,
      reasons: [
        { key: 'debt_over_limit', level: 'churn_risk', points: 25, label: "Qarz kredit limitidan oshgan: 250 000 so'm (limit 100 000 so'm)" },
        { key: 'devices_silent', level: 'churn_risk', points: 25, label: 'Kassa 3 kundan beri aloqa qilmagan' },
      ],
      metrics: {},
      computed_at: '2026-11-10T09:00:00+05:00',
    },
    crm: { next_contact_on: null, notes: [], tasks: [] },
    audit: [],
    actions: { manage: true, suspend: true, activate: false, credit_limit: true, display: true, telegram_link: true, message_owner: true, commission_override: true },
    links: { orders: '/orders?store_id=12', audit: '/audit?store_id=12', subscription: '/pos?store=12' },
    ...overrides,
  };
}

export function applicationCard(overrides: Partial<ApplicationCard> = {}): ApplicationCard {
  return {
    id: 7,
    uuid: '7a7a7a7a-0000-4000-8000-000000000007',
    name: 'Choyxona Navro\'z',
    format: 'teahouse',
    format_label: 'Choyxona',
    region: { id: 1, name: 'Toshkent shahri' },
    city: null,
    owner_name: 'Bobur',
    source: 'website',
    stage: 'application',
    stage_label: 'Ariza',
    stage_changed_at: '2026-11-10T09:00:00+05:00',
    assignee: null,
    due_on: '2026-11-11',
    overdue: false,
    store: null,
    created_at: '2026-11-10T09:00:00+05:00',
    phone: '+998 90 *** ** 21',
    tin: null,
    comment: 'Ikki filial bor',
    rejected_reason: null,
    rejected_at: null,
    converted_at: null,
    activated_at: null,
    checklist: [
      { stage: 'application', label: 'Ariza', current: true, passed: false, items: [{ key: 'contacted', label: "Egasi bilan bog'lanildi", required: true, detect: false, done: false, ticked: false, detected: false, done_at: null, done_by: null }] },
      {
        stage: 'review',
        label: 'Tekshiruv',
        current: false,
        passed: false,
        items: [{ key: 'tin_checked', label: 'STIR tekshirildi', required: true, detect: false, done: false, ticked: false, detected: false, done_at: null, done_by: null }],
      },
      { stage: 'menu', label: 'Menyu', current: false, passed: false, items: [{ key: 'menu_entered', label: 'Menyu kiritildi', required: true, detect: true, done: false, ticked: false, detected: false, done_at: null, done_by: null }] },
    ],
    crm: { next_contact_on: null, notes: [], tasks: [] },
    audit: [],
    actions: { manage: true, convert: true, reject: true },
    ...overrides,
  };
}
