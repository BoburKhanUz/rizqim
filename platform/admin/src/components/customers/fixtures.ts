import type { CannedReply, ComplaintCard, ComplaintPage, ComplaintRow, Suspicious, UserCard, UserPage, UserRow } from '@/lib/api/customers';

// Answers of the Mijozlar va yordam endpoints for the unit tests, shaped like App\Services\Admin\Customers

export function userRow(overrides: Partial<UserRow> = {}): UserRow {
  return {
    id: 7,
    name: 'Olim Egamov',
    role: 'customer',
    role_label: 'Mijoz',
    phone: '+998 90 *** ** 67',
    telegram_linked: true,
    store: null,
    branch: null,
    orders_count: 12,
    blocked: false,
    blocked_at: null,
    created_at: '2026-10-01T10:00:00+05:00',
    ...overrides,
  };
}

export function userPage(rows: UserRow[] = [userRow(), userRow({ id: 8, name: 'Kuryer Bek', role: 'courier', role_label: 'Kuryer', store: { id: 12, name: 'Lazzat Oshxona' }, blocked: true })], next: string | null = null): UserPage {
  return { data: rows, next_cursor: next, per_page: 30, roles: { customer: 'Mijoz', courier: 'Kuryer', staff: 'Ovqatxona xodimlari (barchasi)' } };
}

export function userCard(overrides: Partial<UserCard> = {}): UserCard {
  return {
    profile: {
      id: 7,
      uuid: '7f7f7f7f-1111-4222-8333-444455556666',
      name: 'Olim Egamov',
      role: 'customer',
      role_label: 'Mijoz',
      phone: '+998 90 *** ** 67',
      phone_verified: true,
      telegram: { linked: true, username: 'olim' },
      courier_type: null,
      referred_by: null,
      referrals: 2,
      created_at: '2026-10-01T10:00:00+05:00',
    },
    block: { blocked: false, at: null, reason: null, by: null },
    stats: { orders: 12, delivered: 10, cancelled: 2, delivered_sum: '850000.00', avg_check: '85000.00', last_order_at: '2026-10-27T13:00:00+05:00', segment: { key: 'vip', label: 'Doimiy mijoz (VIP)' } },
    orders: [{ uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD', store: { id: 12, name: 'Lazzat Oshxona' }, status: 'delivered', status_label: 'Yetkazildi', total: '85000.00', payment_method: 'cash', created_at: '2026-10-27T13:00:00+05:00', href: '/orders/ab12cd34-1111-4222-8333-444455556666' }],
    refunds: [{ uuid: 'rf000000-1111-4222-8333-444455556666', order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD' }, amount: '20000.00', status: 'approved', status_label: 'Tasdiqlangan', reason: 'Bir taom kelmadi', created_at: '2026-10-27T14:00:00+05:00', decided_at: null }],
    keshbek: {
      balance: '15000.00',
      entries: [
        { kind: 'referral', label: "Do'st taklifi uchun bonus (Ali)", amount: '5000.00', order: null, at: '2026-10-26T10:00:00+05:00' },
        { kind: 'spent', label: "Buyurtma uchun to'landi #AB12CD", amount: '-30000.00', order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD' }, at: '2026-10-25T10:00:00+05:00' },
      ],
    },
    complaints: [{ uuid: 'cc000000-1111-4222-8333-444455556666', type: 'QUALITY_ISSUE', type_label: 'Sifat muammosi', support_status: 'new', support_status_label: 'Yangi', description: 'Ovqat sovuq keldi', order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD' }, created_at: '2026-10-27T15:00:00+05:00' }],
    sessions: {
      active_tokens: 2,
      refresh_sessions: 1,
      last_used_at: '2026-10-28T09:00:00+05:00',
      tokens: [{ id: 1, kind: 'mini_app', kind_label: 'Mini App / Telegram', device: null, created_at: '2026-10-20T10:00:00+05:00', last_used_at: '2026-10-28T09:00:00+05:00', expires_at: null }],
      devices: [{ uuid: 'dd000000-1111-4222-8333-444455556666', type: 'customer_mobile', type_label: 'Mijoz ilovasi', name: 'Pixel 8', platform: 'android', app_version: '1.2.0', last_seen_at: '2026-10-28T09:00:00+05:00' }],
    },
    membership: null,
    linked_accounts: [{ id: 9, name: 'Olim (xodim)', role: 'courier', role_label: 'Kuryer', store: { id: 12, name: 'Lazzat Oshxona' }, same: ['phone'], blocked: false, href: '/users/9' }],
    audit: [],
    actions: { block: true, unblock: false, close_sessions: true },
    links: { audit: '/audit?subject_type=User&subject_id=7' },
    ...overrides,
  };
}

export function complaintRow(overrides: Partial<ComplaintRow> = {}): ComplaintRow {
  return {
    uuid: 'cc000000-1111-4222-8333-444455556666',
    type: 'QUALITY_ISSUE',
    type_label: 'Sifat muammosi',
    description: 'Ovqat sovuq keldi',
    support_status: 'new',
    support_status_label: 'Yangi',
    store_flow: 'in_review',
    store_flow_label: 'Mijoz RIZQIMga yubordi',
    escalated: true,
    store_replied: true,
    waiting: true,
    customer: { id: 7, name: 'Olim Egamov' },
    order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD' },
    store: { id: 12, name: 'Lazzat Oshxona' },
    assignee: null,
    created_at: '2026-10-27T15:00:00+05:00',
    answered_at: null,
    resolved_at: null,
    ...overrides,
  };
}

export function complaintPage(rows: ComplaintRow[] = [complaintRow()]): ComplaintPage {
  return {
    data: rows,
    next_cursor: null,
    per_page: 30,
    counts: { new: 1, in_progress: 0, answered: 0, resolved: 3, open: 1, waiting: 1 },
    waiting_hours: 4,
    assignees: [{ id: 1, name: 'Aziza Karimova' }, { id: 3, name: 'Madina Yordam' }],
    types: { QUALITY_ISSUE: 'Sifat muammosi' },
    statuses: { new: 'Yangi', in_progress: "Ko'rib chiqilmoqda", answered: 'Javob berildi', resolved: 'Hal qilindi' },
  };
}

export function complaintCard(overrides: Partial<ComplaintCard> = {}): ComplaintCard {
  const row = complaintRow();
  return {
    ...row,
    description: 'Ovqat sovuq keldi, kuryer 1 soat kechikdi.',
    customer: { id: 7, name: 'Olim Egamov', phone: '+998 90 *** ** 67', telegram_linked: true, blocked: false, href: '/users/7' },
    order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', short_code: 'AB12CD', status: 'delivered', status_label: 'Yetkazildi', total: '85000.00', created_at: '2026-10-27T13:00:00+05:00', href: '/orders/ab12cd34-1111-4222-8333-444455556666' },
    store_reply: 'Uzr, kuryer adashib qoldi.',
    replies: [],
    resolution: null,
    history: [],
    actions: { manage: true, reply: true, resolve: true, reopen: false },
    ...overrides,
  };
}

export const cannedReplies = (): CannedReply[] => [
  { id: 1, title: 'Kechikish uchun uzr', body: "Buyurtmangiz kechikkani uchun uzr so'raymiz.", sort_order: 0, updated_by: { id: 1, name: 'Aziza Karimova' }, updated_at: '2026-10-20T10:00:00+05:00' },
];

export function suspicious(): Suspicious {
  return {
    generated_at: '2026-10-28T10:00:00+05:00',
    groups: [
      {
        kind: 'cancellations',
        label: "Ko'p bekor qilish",
        description: "Oxirgi 7 kunda kamida 3 ta buyurtmasi mijoz tufayli bekor qilingan mijozlar.",
        rows: [{ kind: 'cancellations', user: { id: 7, name: 'Olim Egamov', role_label: 'Mijoz', blocked: false }, accounts: [], reason: "7 kunda 4 ta buyurtma bekor qilindi: 3 tasini o'zi bekor qildi, 1 tasini olib ketmadi.", metric: 4, since: '2026-10-27T18:00:00+05:00', href: '/users/7' }],
      },
      {
        kind: 'promo_sharing',
        label: "Promo-kodni ko'p hisobdan ishlatish",
        description: 'Bitta promo-kod bir xil telefon yoki Telegramli hisoblardan.',
        rows: [{ kind: 'promo_sharing', user: { id: 21, name: 'Birinchi hisob', role_label: 'Mijoz', blocked: false }, accounts: [{ id: 21, name: 'Birinchi hisob', role_label: 'Mijoz', blocked: false }, { id: 22, name: 'Ikkinchi hisob', role_label: 'Mijoz', blocked: true }], reason: 'BIRINCHI kodi 30 kunda bir xil telefon raqamli 2 ta hisobdan 3 marta ishlatildi.', metric: 2, since: '2026-10-26T18:00:00+05:00', href: '/users/21', promo_code: 'BIRINCHI', shared_by: 'phone' }],
      },
      { kind: 'refunds', label: "O'rtachadan ko'p qaytarish", description: "Qaytarishlar o'rtachadan 3 baravar ko'p.", rows: [] },
    ],
  };
}
