import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { changedFields } from './AuditDetail';
import { AuditView } from './AuditView';

const entry = (id: number, overrides: Record<string, unknown> = {}) => ({
  id,
  action: 'admin.team.role_changed',
  actor: { id: 1, name: 'Aziza Karimova' },
  subject_type: 'platform_member',
  subject_id: 2,
  store: null,
  reason: null,
  before: { platform_role: 'operator', note: 'same' },
  after: { platform_role: 'finance', note: 'same' },
  ip: '10.0.0.5',
  user_agent: 'Mozilla/5.0 Chrome/120',
  created_at: '2026-10-27T10:15:00+05:00',
  ...overrides,
});

// The backend's cursor page: the list and `next_cursor` inside `data`
const page = (rows: unknown[], next: string | null = null) => ok({ data: rows, next_cursor: next, prev_cursor: null, per_page: 30 });

function setup(audit: (query: URLSearchParams) => Response, permissions?: string[]) {
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody(permissions ? { permissions } : {})), 'GET /audit-logs': (request) => audit(new URL(request.url).searchParams) });
  renderPage(<AuditView />);
  return { ...mocks, user: userEvent.setup() };
}

describe('Harakatlar jurnali', () => {
  it('lists the entries newest first with who, when and what', async () => {
    setup(() => page([entry(2), entry(1, { action: 'admin.auth.login', actor: null, reason: 'navbatchi' })]));
    const list = await screen.findByRole('list', { name: 'Jurnal yozuvlari' });
    const rows = within(list).getAllByRole('listitem');
    expect(rows).toHaveLength(2);
    expect(rows[0]).toHaveTextContent('admin.team.role_changed');
    expect(rows[0]).toHaveTextContent('Aziza Karimova');
    expect(rows[1]).toHaveTextContent('Tizim');
    expect(rows[1]).toHaveTextContent('navbatchi');
    expect(screen.queryByRole('button', { name: 'Yana yuklash' })).toBeNull();
  });

  it('sends the filters together when applied, and clears them', async () => {
    const { callsTo, user } = setup(() => page([]));
    await screen.findByText('Bu filtrlar bo\'yicha yozuv topilmadi');
    expect(callsTo('GET', '/audit-logs')).toHaveLength(1);

    await user.type(screen.getByLabelText('Qidirish'), 'lazzat');
    await user.type(screen.getByLabelText('Amal'), 'admin.team');
    await user.type(screen.getByLabelText('Boshlanish sanasi'), '2026-10-01');
    // Nothing is asked per key
    expect(callsTo('GET', '/audit-logs')).toHaveLength(1);

    await user.click(screen.getByRole('button', { name: "Qo'shimcha filtrlar" }));
    await user.type(screen.getByLabelText('Ovqatxona (ID)'), '12');
    await user.type(screen.getByLabelText('Obyekt turi'), 'order');
    await user.type(screen.getByLabelText('Obyekt ID'), 'ab12');
    await user.type(screen.getByLabelText('Xodim (ID)'), '5');
    await user.click(screen.getByRole('button', { name: "Qo'llash" }));

    await screen.findByText('Bu filtrlar bo\'yicha yozuv topilmadi');
    const last = callsTo('GET', '/audit-logs').at(-1)!;
    expect(Object.fromEntries(last.query)).toMatchObject({ q: 'lazzat', action: 'admin.team', from: '2026-10-01', store_id: '12', subject_type: 'order', subject_id: 'ab12', actor_id: '5', per_page: '30' });

    await user.click(screen.getByRole('button', { name: 'Tozalash' }));
    await screen.findByText('Bu filtrlar bo\'yicha yozuv topilmadi');
    const cleared = callsTo('GET', '/audit-logs').at(-1)!;
    expect(cleared.query.get('q')).toBeNull();
    expect(cleared.query.get('store_id')).toBeNull();
  });

  it('loads the next page by cursor and keeps the first page', async () => {
    const { callsTo, user } = setup((query) => (query.get('cursor') === 'c2' ? page([entry(1, { action: 'admin.auth.logout' })], null) : page([entry(2)], 'c2')));
    await screen.findByText('admin.team.role_changed');
    await user.click(screen.getByRole('button', { name: 'Yana yuklash' }));
    expect(await screen.findByText('admin.auth.logout')).toBeInTheDocument();
    expect(screen.getByText('admin.team.role_changed')).toBeInTheDocument();
    expect(callsTo('GET', '/audit-logs').at(-1)?.query.get('cursor')).toBe('c2');
    expect(screen.queryByRole('button', { name: 'Yana yuklash' })).toBeNull();
  });

  it('shows the store of an entry by name', async () => {
    setup(() => page([entry(3, { action: 'admin.stores.suspended', store: { id: 12, name: 'Lazzat Oshxona' } })]));
    const row = await screen.findByRole('button', { name: 'admin.stores.suspended: batafsil' });
    expect(row).toHaveTextContent('Ovqatxona: Lazzat Oshxona');
  });

  it('opens an entry with what changed: before and after, and who, from where', async () => {
    const { user } = setup(() => page([entry(2, { reason: 'rol almashdi' })]));
    await user.click(await screen.findByRole('button', { name: 'admin.team.role_changed: batafsil' }));
    const detail = await screen.findByRole('dialog', { name: 'Yozuv tafsiloti' });
    expect(detail).toHaveTextContent('Aziza Karimova');
    expect(detail).toHaveTextContent('rol almashdi');
    expect(detail).toHaveTextContent('10.0.0.5');
    const rows = within(within(detail).getByRole('table')).getAllByRole('row');
    // Header and the one field that changed (note is the same in both)
    expect(rows).toHaveLength(2);
    expect(rows[1]).toHaveTextContent('platform_role');
    expect(rows[1]).toHaveTextContent('operator');
    expect(rows[1]).toHaveTextContent('finance');
    await user.click(within(detail).getAllByRole('button', { name: 'Yopish' }).at(-1)!);
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('says nothing changed when the entry has no snapshots', () => {
    expect(changedFields(null, null)).toEqual([]);
    expect(changedFields({ a: 1 }, { a: 1, b: { c: 2 } })).toEqual([{ field: 'b', before: undefined, after: { c: 2 } }]);
  });

  it('shows the failure with a way to retry', async () => {
    setup(() => refusal(500, 'Server xatosi'));
    expect(await screen.findByText('Jurnal yuklanmadi')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Qayta urinish' })).toBeInTheDocument();
  });

  it('is closed to a member without audit.view', async () => {
    const { callsTo } = setup(() => page([]), ['admin.today.view']);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(callsTo('GET', '/audit-logs')).toHaveLength(0);
  });
});
