import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { createTranslator } from '@/i18n';
import { mockFetch, renderPage } from '@/test/helpers';
import { ok, permissions, signIn } from '../management/testSupport';
import { actionLabel, AuditView, describeChange } from './AuditView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const ENTRIES = [
  {
    id: 2,
    action: 'order.cancelled',
    actor: { id: 7, name: 'Aziz', role: 'owner' },
    device: null,
    subject_type: 'Order',
    subject_id: 41,
    branch_id: 1,
    before: { status: 'new' },
    after: { status: 'canceled' },
    meta: { reason: 'out_of_stock' },
    ip: '10.0.0.5',
    created_at: '2026-10-28T10:00:00+05:00',
  },
  { id: 1, action: 'something.new', actor: null, device: null, subject_type: null, subject_id: null, branch_id: null, before: null, after: null, meta: null, ip: null, created_at: '2026-10-27T09:00:00+05:00' },
];

const page = (data: unknown[], current = 1, last = 1) => ok({ current_page: current, last_page: last, per_page: 25, total: data.length, data });

describe('AuditView', () => {
  it('lists the store log with the words for each action and the change', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissions(['audit.view']), 'GET /api/v1/owner/audit-logs': () => page(ENTRIES) });
    renderPage(<AuditView />);
    const table = await screen.findByRole('table', { name: 'Harakatlar jurnali' });
    expect(within(table).getByText('Buyurtma bekor qilindi')).toBeInTheDocument();
    expect(within(table).getByText('status: new → canceled; reason: out_of_stock')).toBeInTheDocument();
    expect(within(table).getByText('Order #41')).toBeInTheDocument();
    // An action the panel does not know is shown as the server wrote it; no actor is the system
    expect(within(table).getByText('something.new')).toBeInTheDocument();
    expect(within(table).getByText('Tizim')).toBeInTheDocument();
  });

  it('filters by the kind of action and dates, from the first page', async () => {
    const user = userEvent.setup();
    const { calls } = mockFetch({ 'GET /api/v1/me/permissions': () => permissions(['audit.view']), 'GET /api/v1/owner/audit-logs': () => page(ENTRIES, 1, 3) });
    const logCalls = () => calls.filter((request) => new URL(request.url).pathname === '/api/v1/owner/audit-logs').map((request) => new URL(request.url).searchParams);
    renderPage(<AuditView />);
    await screen.findByRole('table', { name: 'Harakatlar jurnali' });
    await user.selectOptions(screen.getByLabelText('Harakat turi'), 'staff');
    await vi.waitFor(() => expect(logCalls().at(-1)?.get('action')).toBe('staff.*'));
    expect(logCalls().at(-1)?.get('page')).toBe('1');
    await user.type(screen.getByLabelText('Boshlanish sanasi'), '2026-10-01');
    await vi.waitFor(() => expect(logCalls().at(-1)?.get('from')).toBe('2026-10-01'));
  });

  it('says so when the person may not see the log', async () => {
    mockFetch({ 'GET /api/v1/me/permissions': () => permissions(['orders.view']) });
    renderPage(<AuditView />);
    expect(await screen.findByText(/Jurnalni ko'rish uchun ruxsat yo'q/)).toBeInTheDocument();
  });

  it('knows the words in every language', () => {
    for (const language of ['uz', 'ru', 'en'] as const) {
      const t = createTranslator(language);
      expect(actionLabel(t, 'staff.login_phone_changed')).not.toBe('staff.login_phone_changed');
      expect(describeChange({ before: { is_open: false }, after: { is_open: true }, meta: null }, t)).toContain('→');
    }
  });
});
