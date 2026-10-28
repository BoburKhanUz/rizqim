import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { RolesView } from './RolesView';

const MATRIX = {
  roles: [
    { key: 'superadmin', label: 'Superadmin', editable: false },
    { key: 'operator', label: 'Operator', editable: true },
    { key: 'support', label: 'Yordam', editable: true },
    { key: 'viewer', label: "Faqat ko'rish", editable: true },
  ],
  permissions: [
    { key: 'admin.today.view', label: "Bugun'ni ko'rish", group: 'Kundalik' },
    { key: 'admin.orders.intervene', label: 'Buyurtmaga aralashish', group: 'Kundalik' },
    { key: 'admin.payouts.approve', label: 'Payoutni tasdiqlash', group: 'Moliya' },
  ],
  grants: { superadmin: ['admin.today.view', 'admin.orders.intervene', 'admin.payouts.approve'], operator: ['admin.today.view', 'admin.orders.intervene'], support: [], viewer: ['admin.today.view'] },
  // The built-in table: support has Bugun by default (the superadmin took it away), operator has no payouts
  defaults: { superadmin: ['admin.today.view', 'admin.orders.intervene', 'admin.payouts.approve'], operator: ['admin.today.view', 'admin.orders.intervene'], support: ['admin.today.view'], viewer: ['admin.today.view'] },
};

function setup(extra: Parameters<typeof mockFetch>[0] = {}) {
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody()), 'GET /roles': () => ok(MATRIX), ...extra });
  renderPage(<RolesView />);
  return { ...mocks, user: userEvent.setup() };
}

describe('Rollar', () => {
  it('draws the matrix of roles and permissions grouped, with the superadmin column locked', async () => {
    setup();
    const table = await screen.findByRole('table', { name: 'Rollar va ruxsatlar jadvali' });
    expect(within(table).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual(['Ruxsat', 'SuperadminQulflangan', 'Operator', 'Yordam', "Faqat ko'rish"]);
    expect(within(table).getByRole('rowheader', { name: 'Kundalik' })).toBeInTheDocument();
    expect(within(table).getByRole('rowheader', { name: 'Moliya' })).toBeInTheDocument();

    const superadmin = within(table).getByRole('switch', { name: 'Superadmin: Payoutni tasdiqlash' });
    expect(superadmin).toBeChecked();
    expect(superadmin).toBeDisabled();
    expect(within(table).getByRole('switch', { name: 'Operator: Buyurtmaga aralashish' })).toBeChecked();
    expect(within(table).getByRole('switch', { name: 'Yordam: Buyurtmaga aralashish' })).not.toBeChecked();
    expect(within(table).getByRole('switch', { name: 'Operator: Bugun\'ni ko\'rish' })).toBeEnabled();
  });

  it('marks a cell that differs from the default "O\'zgartirilgan" and puts it back with one tap (allowed: null)', async () => {
    const { callsTo, user } = setup({ 'PUT /roles': () => ok(MATRIX) });
    const table = await screen.findByRole('table', { name: 'Rollar va ruxsatlar jadvali' });
    // Support: Bugun is a default the superadmin took away
    const cell = within(table).getByRole('switch', { name: "Yordam: Bugun'ni ko'rish" }).closest('td')!;
    expect(within(cell).getByText("O'zgartirilgan")).toBeInTheDocument();
    // Operator: Bugun is as the default says: no marker, no reset button
    const plain = within(table).getByRole('switch', { name: "Operator: Bugun'ni ko'rish" }).closest('td')!;
    expect(within(plain).queryByText("O'zgartirilgan")).toBeNull();
    // Only that one cell differs
    expect(within(table).getAllByText("O'zgartirilgan")).toHaveLength(1);

    await user.click(within(cell).getByRole('button', { name: "Standartga qaytarish: Yordam, Bugun'ni ko'rish (ruxsat bor)" }));
    expect(await screen.findByText("«Yordam» roli uchun «Bugun'ni ko'rish» ruxsati standartga qaytarildi.")).toBeInTheDocument();
    expect(callsTo('PUT', '/roles')[0]?.body).toEqual({ role: 'support', permission: 'admin.today.view', allowed: null });
  });

  it('shows the whole matrix of the answer without asking again', async () => {
    const { callsTo, user } = setup({ 'PUT /roles': () => ok({ ...MATRIX, grants: { ...MATRIX.grants, support: ['admin.today.view'] } }) });
    await user.click(await screen.findByRole('switch', { name: "Yordam: Bugun'ni ko'rish" }));
    await screen.findByText(/ruxsati berildi/);
    // Now equal to its default: the marker is gone, and the matrix was not fetched a second time
    expect(screen.queryByText("O'zgartirilgan")).toBeNull();
    expect(callsTo('GET', '/roles')).toHaveLength(1);
  });

  it('saves a switch at once and says what changed', async () => {
    const { callsTo, user } = setup({ 'PUT /roles': () => ok(MATRIX) });
    await user.click(await screen.findByRole('switch', { name: "Yordam: Bugun'ni ko'rish" }));
    expect(await screen.findByText("«Yordam» roliga «Bugun'ni ko'rish» ruxsati berildi.")).toBeInTheDocument();
    expect(callsTo('PUT', '/roles')[0]?.body).toEqual({ role: 'support', permission: 'admin.today.view', allowed: true });

    await user.click(screen.getByRole('switch', { name: 'Operator: Bugun\'ni ko\'rish' }));
    expect(await screen.findByText('«Operator» rolidan «Bugun\'ni ko\'rish» ruxsati olindi.')).toBeInTheDocument();
    expect(callsTo('PUT', '/roles')[1]?.body).toEqual({ role: 'operator', permission: 'admin.today.view', allowed: false });
  });

  it('asks before giving a risky permission, and sends nothing when backing out', async () => {
    const { callsTo, user } = setup({ 'PUT /roles': () => ok(MATRIX) });
    await user.click(await screen.findByRole('switch', { name: 'Operator: Payoutni tasdiqlash' }));
    const ask = await screen.findByRole('alertdialog', { name: '«Payoutni tasdiqlash» ruxsatini berasizmi?' });
    await user.click(within(ask).getByRole('button', { name: 'Bekor qilish' }));
    expect(callsTo('PUT', '/roles')).toHaveLength(0);

    await user.click(screen.getByRole('switch', { name: 'Operator: Payoutni tasdiqlash' }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Ruxsat berish' }));
    expect(await screen.findByText(/ruxsati berildi/)).toBeInTheDocument();
    expect(callsTo('PUT', '/roles')).toHaveLength(1);
  });

  it('shows the backend\'s refusal above the table', async () => {
    const { user } = setup({ 'PUT /roles': () => refusal(422, 'Superadmin ruxsatlari o\'zgarmaydi.') });
    await user.click(await screen.findByRole('switch', { name: "Yordam: Bugun'ni ko'rish" }));
    expect(await screen.findByRole('alert')).toHaveTextContent("Superadmin ruxsatlari o'zgarmaydi.");
  });

  it('is closed to a member without team.manage', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody({ permissions: ['admin.audit.view'] })) });
    renderPage(<RolesView />);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});
