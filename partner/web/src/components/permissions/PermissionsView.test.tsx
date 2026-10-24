import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { ok, permissions, signIn } from '../management/testSupport';
import { PermissionsView } from './PermissionsView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const LISTED = [
  { key: 'orders.view', label: "Buyurtmalarni ko'rish", grantable: true },
  { key: 'orders.cancel', label: 'Buyurtmani bekor qilish yoki rad etish', grantable: true },
  { key: 'orders.discount', label: 'Chegirma berish', grantable: true },
  { key: 'pos.reprint_bill', label: 'Schyotni qayta chiqarish', grantable: true },
  { key: 'pos.cash_in_out', label: 'Kassaga pul kiritish va chiqarish', grantable: true },
  { key: 'pos.refund', label: 'Smena ichida pulni qaytarish', grantable: true },
  { key: 'finance.manage', label: "Pul yechish va to'ldirish", grantable: false },
  { key: 'something.new', label: 'Yangi imkoniyat', grantable: true },
];

// The cashier's defaults after P3-37: reprints the bill and moves cash alone, refund and cancel with a manager's PIN
const CASHIER_DEFAULTS: Record<string, boolean> = { 'orders.view': true, 'pos.reprint_bill': true, 'pos.cash_in_out': true };
const MANAGER_DEFAULTS: Record<string, boolean> = { 'orders.view': true, 'orders.cancel': true, 'orders.discount': true, 'pos.refund': true };

const cells = (granted: Record<string, boolean>) => Object.fromEntries(LISTED.map((permission) => [permission.key, granted[permission.key] === true]));

// A stateful stand-in for the endpoint: PUT changes the matrix the next GET answers
function backend(keys = ['permissions.manage'], overrides: Record<string, Record<string, boolean>> = {}) {
  const defaults = { owner: cells({}), manager: cells(MANAGER_DEFAULTS), cashier: cells(CASHIER_DEFAULTS), waiter: cells({ 'orders.view': true }), cook: cells({}), courier: cells({}) };
  const matrix = structuredClone(defaults) as Record<string, Record<string, boolean>>;
  for (const [role, changes] of Object.entries(overrides)) Object.assign(matrix[role]!, changes);
  const table = () => ({
    roles: ['owner', 'manager', 'cashier', 'waiter', 'cook', 'courier'].map((role) => ({ role, label: role, editable: role !== 'owner' })),
    permissions: LISTED,
    matrix,
    defaults,
  });
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => permissions(keys),
    'GET /api/v1/owner/permissions': () => ok(table()),
    'PUT /api/v1/owner/permissions': async (request) => {
      const body = (await request.json()) as { role: string; permission: string; allowed: boolean | null };
      matrix[body.role]![body.permission] = body.allowed ?? defaults[body.role as 'cashier']![body.permission]!;
      return ok({ role: body.role, permission: body.permission, allowed: matrix[body.role]![body.permission] });
    },
  });
  return { ...mocked, puts: () => Promise.all(mocked.calls.filter((call) => call.method === 'PUT').map((call) => call.json())) };
}

describe('Huquqlar: access', () => {
  it('is for the owner: someone without permissions.manage is told so and nothing is loaded', async () => {
    const { calls } = backend(['orders.view', 'staff.view']);
    renderPage(<PermissionsView />);
    expect(await screen.findByText("Huquqlarni faqat do'kon egasi o'zgartira oladi.")).toBeInTheDocument();
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument();
    expect(calls.some((call) => new URL(call.url).pathname === '/api/v1/owner/permissions')).toBe(false);
  });
});

describe('Huquqlar: the checklist of a role', () => {
  it('lists the roles an owner can change, the owner not among them', async () => {
    backend();
    renderPage(<PermissionsView />);
    const tabs = within(await screen.findByRole('tablist')).getAllByRole('tab');
    expect(tabs.map((tab) => tab.textContent)).toEqual(['Menejer', 'Kassir', 'Ofitsiant', 'Oshpaz', 'Kuryer']);
  });

  it('shows the cashier as the owner decided: bill copies and cash alone, refund and cancel with a manager PIN', async () => {
    const typing = userEvent.setup();
    backend();
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    expect(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' })).toBeChecked();
    expect(screen.getByRole('switch', { name: 'Kassaga pul kiritish va chiqarish' })).toBeChecked();
    expect(screen.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked();
    expect(screen.getByRole('switch', { name: 'Buyurtmani bekor qilish' })).not.toBeChecked();
    expect(screen.getByText('Mijozga pulni qaytaradi (smena ichida)')).toBeInTheDocument();
    expect(screen.getByText('3 ta amalga ruxsat. Hammasi standart.')).toBeInTheDocument();
    expect(screen.queryByText("O'zgartirilgan")).not.toBeInTheDocument();
  });

  it('marks what moves money or cancels as risky, and nothing else', async () => {
    backend();
    renderPage(<PermissionsView />);
    const row = (name: string) => screen.getByRole('switch', { name }).closest('li')!;
    await screen.findByRole('tablist');
    for (const name of ['Qaytim qilish', 'Buyurtmani bekor qilish', 'Kassaga pul kiritish va chiqarish', 'Chegirma berish, istalgan foizda']) {
      expect(within(row(name)).getByText("Pul bilan bog'liq")).toBeInTheDocument();
    }
    expect(within(row('Chekni qayta chiqarish')).queryByText("Pul bilan bog'liq")).not.toBeInTheDocument();
    expect(within(row("Buyurtmalarni ko'rish")).queryByText("Pul bilan bog'liq")).not.toBeInTheDocument();
  });

  it('shows a permission the page has no words for with the label the server sent, and keeps owner-only ones off unless held', async () => {
    const typing = userEvent.setup();
    backend(['permissions.manage'], { cashier: { 'finance.manage': true } });
    renderPage(<PermissionsView />);
    await screen.findByRole('tablist');
    expect(screen.getByRole('switch', { name: 'Yangi imkoniyat' })).toBeInTheDocument();
    // The manager does not hold finance.manage and cannot be given it: no switch
    expect(screen.queryByRole('switch', { name: "Pul yechish va to'ldirish" })).not.toBeInTheDocument();
    // A cashier who somehow holds it can lose it, not get it back
    await typing.click(screen.getByRole('tab', { name: 'Kassir' }));
    expect(screen.getByRole('switch', { name: "Pul yechish va to'ldirish" })).toBeChecked();
    expect(screen.getByText('Buni faqat ega bajaradi')).toBeInTheDocument();
  });

  it('marks a changed row with the default beside it', async () => {
    const typing = userEvent.setup();
    backend(['permissions.manage'], { cashier: { 'pos.refund': true, 'pos.reprint_bill': false } });
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    const refund = screen.getByRole('switch', { name: 'Qaytim qilish' }).closest('li')!;
    expect(within(refund).getByText("O'zgartirilgan")).toBeInTheDocument();
    expect(within(refund).getByText("Standart: ruxsat yo'q")).toBeInTheDocument();
    const reprint = screen.getByRole('switch', { name: 'Chekni qayta chiqarish' }).closest('li')!;
    expect(within(reprint).getByText('Standart: ruxsat bor')).toBeInTheDocument();
    expect(screen.getByText('3 ta amalga ruxsat, 2 tasi standartdan farq qiladi.')).toBeInTheDocument();
  });
});

describe('Huquqlar: saving', () => {
  it('turns a safe permission off at once, with the role, the permission and false', async () => {
    const typing = userEvent.setup();
    const { puts } = backend();
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    await typing.click(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' }));
    expect(await screen.findByText('Kassir: "Chekni qayta chiqarish" ruxsati olib tashlandi.')).toBeInTheDocument();
    expect(await puts()).toEqual([{ role: 'cashier', permission: 'pos.reprint_bill', allowed: false }]);
    expect(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' })).not.toBeChecked();
    expect(screen.getByText("O'zgartirilgan")).toBeInTheDocument();
  });

  it('grants a safe permission without asking', async () => {
    const typing = userEvent.setup();
    const { puts } = backend();
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Ofitsiant' }));
    await typing.click(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' }));
    expect(await screen.findByText('Ofitsiant: "Chekni qayta chiqarish" ruxsati berildi.')).toBeInTheDocument();
    expect(await puts()).toEqual([{ role: 'waiter', permission: 'pos.reprint_bill', allowed: true }]);
  });

  it('asks before giving a risky permission, and sends nothing when the owner backs out', async () => {
    const typing = userEvent.setup();
    const { puts } = backend();
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    await typing.click(screen.getByRole('switch', { name: 'Qaytim qilish' }));
    const dialog = await screen.findByRole('alertdialog');
    expect(within(dialog).getByText('"Qaytim qilish" ruxsatini berasizmi?')).toBeInTheDocument();
    expect(within(dialog).getByText(/Kassir buni menejer PIN kodisiz o'zi bajaradi/)).toBeInTheDocument();
    await typing.click(within(dialog).getByRole('button', { name: 'Bekor qilish' }));
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(await puts()).toEqual([]);
    expect(screen.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked();

    await typing.click(screen.getByRole('switch', { name: 'Qaytim qilish' }));
    await typing.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Ruxsat berish' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(await puts()).toEqual([{ role: 'cashier', permission: 'pos.refund', allowed: true }]);
    expect(screen.getByRole('switch', { name: 'Qaytim qilish' })).toBeChecked();
    expect(screen.getByText("Standart: ruxsat yo'q")).toBeInTheDocument();
  });

  it('takes a risky permission away without asking', async () => {
    const typing = userEvent.setup();
    const { puts } = backend();
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    await typing.click(screen.getByRole('switch', { name: 'Kassaga pul kiritish va chiqarish' }));
    await screen.findByText(/ruxsati olib tashlandi/);
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    expect(await puts()).toEqual([{ role: 'cashier', permission: 'pos.cash_in_out', allowed: false }]);
  });

  it('resets a changed row to the default with allowed null', async () => {
    const typing = userEvent.setup();
    const { puts } = backend(['permissions.manage'], { cashier: { 'pos.refund': true } });
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    await typing.click(screen.getByRole('button', { name: 'Qaytim qilish: standartga qaytarish' }));
    expect(await screen.findByText('Kassir: "Qaytim qilish" standartga qaytarildi.')).toBeInTheDocument();
    expect(await puts()).toEqual([{ role: 'cashier', permission: 'pos.refund', allowed: null }]);
    expect(screen.getByRole('switch', { name: 'Qaytim qilish' })).not.toBeChecked();
    expect(screen.queryByRole('button', { name: /standartga qaytarish/ })).not.toBeInTheDocument();
  });

  it('sends null when the switch is put back to the default by hand', async () => {
    const typing = userEvent.setup();
    const { puts } = backend(['permissions.manage'], { cashier: { 'pos.reprint_bill': false } });
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('tab', { name: 'Kassir' }));
    await typing.click(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' }));
    expect(await screen.findByText('Kassir: "Chekni qayta chiqarish" standartga qaytarildi.')).toBeInTheDocument();
    expect(await puts()).toEqual([{ role: 'cashier', permission: 'pos.reprint_bill', allowed: null }]);
  });

  it("shows the server's refusal where the click was and leaves the switch as it was", async () => {
    const typing = userEvent.setup();
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(['permissions.manage']),
      'GET /api/v1/owner/permissions': () => ok({ roles: [{ role: 'cashier', label: 'Kassir', editable: true }], permissions: LISTED, matrix: { cashier: cells({}) }, defaults: { cashier: cells({}) } }),
      'PUT /api/v1/owner/permissions': () => json({ success: false, message: "Bu ruxsatni faqat do'kon egasi bajaradi." }, 422),
    });
    renderPage(<PermissionsView />);
    await typing.click(await screen.findByRole('switch', { name: 'Chekni qayta chiqarish' }));
    expect(await screen.findByText("Bu ruxsatni faqat do'kon egasi bajaradi.")).toBeInTheDocument();
    expect(screen.getByRole('switch', { name: 'Chekni qayta chiqarish' })).not.toBeChecked();
  });

  it('says so when the table cannot be loaded, and loads it again on retry', async () => {
    const typing = userEvent.setup();
    let fail = true;
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(['permissions.manage']),
      'GET /api/v1/owner/permissions': () => (fail ? json({ success: false, message: 'Server xatosi.' }, 500) : ok({ roles: [{ role: 'cashier', label: 'Kassir', editable: true }], permissions: LISTED, matrix: { cashier: cells({}) }, defaults: { cashier: cells({}) } })),
    });
    renderPage(<PermissionsView />);
    expect(await screen.findByText('Server xatosi.')).toBeInTheDocument();
    fail = false;
    await typing.click(screen.getByRole('button', { name: /Qayta urinish|Qayta/ }));
    expect(await screen.findByRole('tab', { name: 'Kassir' })).toBeInTheDocument();
  });
});
