import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { IntegrationsView } from './IntegrationsView';

const VIEW = ['admin.integrations.view'];
const MANAGE = ['admin.integrations.view', 'admin.integrations.manage'];
const CONNECTION = 'c0000000-0000-4000-8000-000000000001';
const EVENT = 'e0000000-0000-4000-8000-000000000001';

const empty = { data: [], current_page: 1, last_page: 1, per_page: 30, total: 0, counts: { total: 0, configuring: 0, active: 0, paused: 0, disconnected: 0, attention: 0 }, stuck_orders: 0 };

const row = (overrides: Record<string, unknown> = {}) => ({
  uuid: CONNECTION,
  store: { id: 12, name: 'Lazzat Oshxona' },
  branch: null,
  provider: 'iiko',
  provider_label: 'iiko',
  direction: 'rizqim_to_pos',
  direction_label: "RIZQIM → joyning POS'i",
  mode: 'sandbox',
  mode_label: 'Sinov (sandbox)',
  status: 'active',
  status_label: 'Faol',
  health: { score: 62, verdict: 'attention', last_success_at: '2026-10-28T10:00:00+05:00', last_webhook_at: null, last_error_at: null, last_error: null, errors_today: 3, checked_at: null },
  paused: null,
  created_at: '2026-10-20T10:00:00+05:00',
  ...overrides,
});

const event = (overrides: Record<string, unknown> = {}) => ({
  uuid: EVENT,
  connection: { uuid: CONNECTION, provider: 'iiko', provider_label: 'iiko', store: { id: 12, name: 'Lazzat Oshxona' } },
  direction: 'outbound',
  direction_label: 'Chiquvchi',
  type: 'order.push',
  status: 'failed',
  status_label: 'Xato',
  http_status: 502,
  attempts: 3,
  next_attempt_at: null,
  error: 'Timeout for +99890*****33',
  subject: { type: 'order', id: 5 },
  duration_ms: 900,
  created_at: '2026-10-28T10:00:00+05:00',
  ...overrides,
});

const card = (overrides: Record<string, unknown> = {}) => ({
  ...row(overrides),
  credentials: [{ key: 'api_login', configured: true, last4: '4321' }],
  settings: { terminal_id: 'T-9', webhook_secret: '[redacted]' },
  recent_events: [event()],
  menu_map: { total: 0, by_type: {} },
  stuck_orders: 0,
});

afterEach(() => {
  vi.unstubAllGlobals();
  window.history.replaceState(null, '', '/');
});

const me = (permissions: string[]) => () => json(meBody({ permissions }));
const noStuck = () => ok({ count: 0, data: [], stuck_after_minutes: 2 });

describe('IntegrationsView', () => {
  it('is calm and empty on every tab before phase 5', async () => {
    const user = userEvent.setup();
    mockFetch({
      'GET /auth/me': me(VIEW),
      'GET /integrations/connections': () => ok(empty),
      'GET /integrations/stuck-orders': noStuck,
      'GET /integrations/events': () => ok({ data: [], next_cursor: null, per_page: 25 }),
    });
    renderPage(<IntegrationsView />);

    expect(await screen.findByText("Hali hech qanday ulanish yo'q")).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Hodisalar jurnali' }));
    expect(await screen.findByText("Jurnal bo'sh")).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Menyu xaritasi' }));
    expect(screen.getByText('Menyu xaritasi 5-bosqichda')).toBeInTheDocument();
    await user.click(screen.getByRole('tab', { name: 'Qotib qolganlar' }));
    expect(await screen.findByText("Qotib qolgan buyurtma yo'q")).toBeInTheDocument();
    expect(window.location.search).toBe('?tab=stuck');
  });

  it('refuses a member without integrations.view', async () => {
    mockFetch({ 'GET /auth/me': me(['admin.today.view']) });
    renderPage(<IntegrationsView />);
    expect(await screen.findByText("Integratsiyalarni ko'rish uchun ruxsat kerak.")).toBeInTheDocument();
  });

  it('opens a connection card with keys by name only, and a viewer cannot pause it', async () => {
    const user = userEvent.setup();
    mockFetch({
      'GET /auth/me': me(VIEW),
      'GET /integrations/connections': () => ok({ ...empty, data: [row()], total: 1, counts: { ...empty.counts, total: 1, active: 1, attention: 1 } }),
      'GET /integrations/stuck-orders': noStuck,
      [`GET /integrations/connections/${CONNECTION}`]: () => ok(card()),
    });
    renderPage(<IntegrationsView />);

    expect(await screen.findByText("E'tibor kerak · 62")).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'iiko · Lazzat Oshxona: ochish' }));
    expect(await screen.findByText('kiritilgan, oxiri ••••4321')).toBeInTheDocument();
    expect(screen.getByText('[redacted]')).toBeInTheDocument();
    expect(screen.getByText("Hali birorta taom bog'lanmagan.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "To'xtatish" })).not.toBeInTheDocument();
    expect(window.location.search).toBe(`?connection=${CONNECTION}`);
  });

  it('pauses a connection with a reason', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({
      'GET /auth/me': me(MANAGE),
      'GET /integrations/connections': () => ok(empty),
      'GET /integrations/stuck-orders': noStuck,
      [`GET /integrations/connections/${CONNECTION}`]: () => ok(card()),
      [`POST /integrations/connections/${CONNECTION}/pause`]: () => ok(card({ status: 'paused', status_label: "To'xtatilgan", paused: { at: '2026-10-28T11:00:00+05:00', by: { id: 1, name: 'Aziza Karimova' }, reason: "Kassa o'chiq" } })),
    });
    renderPage(<IntegrationsView initial={{ connection: CONNECTION }} />);

    await user.click(await screen.findByRole('button', { name: "To'xtatish" }));
    const dialog = await screen.findByRole('dialog', { name: "iiko · Lazzat Oshxona: to'xtatilsinmi?" });
    await user.type(within(dialog).getByLabelText('Sabab'), "Kassa o'chiq");
    await user.click(within(dialog).getByRole('button', { name: "To'xtatish" }));

    await waitFor(() => expect(callsTo('POST', `/integrations/connections/${CONNECTION}/pause`)).toHaveLength(1));
    expect(callsTo('POST', `/integrations/connections/${CONNECTION}/pause`)[0]!.body).toEqual({ reason: "Kassa o'chiq" });
    expect(await screen.findByText("Ulanish to'xtatildi.")).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Qayta yoqish' })).toBeInTheDocument();
  });

  it('filters the journal and shows an event with its redacted bodies', async () => {
    const user = userEvent.setup();
    const { callsTo } = mockFetch({
      'GET /auth/me': me(VIEW),
      'GET /integrations/stuck-orders': noStuck,
      'GET /integrations/events': () => ok({ data: [event()], next_cursor: null, per_page: 25 }),
      [`GET /integrations/events/${EVENT}`]: () => ok({ ...event(), idempotency_key: 'eats-1', signature_valid: true, last_attempt_at: null, request_body: { phone: '+99890*****33', access_token: '[redacted]' }, response_body: null }),
    });
    renderPage(<IntegrationsView initial={{ tab: 'events' }} />);

    expect(await screen.findByText('Timeout for +99890*****33')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Xato' }));
    await waitFor(() => expect(callsTo('GET', '/integrations/events').some((call) => call.query.get('status') === 'failed')).toBe(true));

    await user.click(screen.getByRole('button', { name: /order\.push.*ochish/ }));
    expect(await screen.findByText(/"access_token": "\[redacted\]"/)).toBeInTheDocument();
    expect(screen.getByText("Tanasi yo'q.")).toBeInTheDocument();
    expect(screen.getByText("To'g'ri")).toBeInTheDocument();
  });

  it('lists stuck orders with a link to the order and their count on the tab', async () => {
    mockFetch({
      'GET /auth/me': me(VIEW),
      'GET /integrations/stuck-orders': () =>
        ok({
          count: 1,
          stuck_after_minutes: 2,
          data: [{ event_uuid: EVENT, connection: { uuid: CONNECTION, provider: 'alipos', provider_label: 'Alipos', store: { id: 12, name: 'Lazzat Oshxona' } }, order: { uuid: 'ab12cd34-1111-4222-8333-444455556666', code: 'AB12CD', status: 'new' }, status: 'sent', status_label: 'Yuborildi', attempts: 1, error: null, since: '2026-10-28T10:00:00+05:00', waiting_minutes: 7 }],
        }),
    });
    renderPage(<IntegrationsView initial={{ tab: 'stuck' }} />);

    expect(await screen.findByRole('link', { name: 'Buyurtma AB12CD' })).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666');
    expect(screen.getByText('7 daqiqa kutmoqda')).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Qotib qolganlar (1)' })).toBeInTheDocument();
  });
});
