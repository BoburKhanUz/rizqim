import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { StaffView } from './StaffView';
import { makeBranch, makeMember, OWNER_PERMISSIONS, ok, permissions, refusal, signIn } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const BRANCHES = [makeBranch(), makeBranch({ id: 2, name: 'Yunusobod' })];

// The table and the phone cards are both in the page (CSS shows one): the table is what these tests read
const table = () => within(screen.getByRole('table'));
const ready = async () => {
  await screen.findByRole('table');
  return table();
};

describe('StaffView: the list', () => {
  it('shows role, branch, the login phone state, PIN and Telegram state of each member', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () =>
        ok([
          makeMember({ has_pin: true, staff_code: '4821', telegram_linked: true }),
          makeMember({ id: 2, name: 'Vali Karimov', role: 'courier', phone: null, login_phone_enabled: false, branch: null, invite_pending: true }),
        ]),
    });
    renderPage(<StaffView />);

    expect(await (await ready()).findByText('Ali Valiyev')).toBeInTheDocument();
    expect(table().getByText('Kassir')).toBeInTheDocument();
    expect(table().getByText('+998901234567')).toBeInTheDocument();
    expect(table().getByText('SMS bilan kiradi')).toBeInTheDocument();
    expect(table().getByText('PIN bor')).toBeInTheDocument();
    expect(table().getByText('Telegram ulangan')).toBeInTheDocument();
    expect(table().getByText('Xodim kodi: 4821')).toBeInTheDocument();
    // The second member has no login number and an unused invite
    expect(table().getByText("Kirish raqami yo'q")).toBeInTheDocument();
    expect(table().getByText('Taklif kutilmoqda')).toBeInTheDocument();
    expect(table().getByText("Butun do'kon")).toBeInTheDocument();
  });

  it('filters by name, phone digits and role', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember(), makeMember({ id: 2, name: 'Vali Karimov', role: 'cook', phone: '+998935550000' })]),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await (await ready()).findByText('Ali Valiyev');

    await typing.type(screen.getByRole('searchbox'), '93 555');
    expect(table().queryByText('Ali Valiyev')).not.toBeInTheDocument();
    expect(table().getByText('Vali Karimov')).toBeInTheDocument();

    await typing.clear(screen.getByRole('searchbox'));
    await typing.selectOptions(screen.getByRole('combobox', { name: 'Roli' }), 'cashier');
    expect(table().getByText('Ali Valiyev')).toBeInTheDocument();
    expect(table().queryByText('Vali Karimov')).not.toBeInTheDocument();
  });

  it('says so when the list could not load, and retries', async () => {
    let attempts = 0;
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => (++attempts === 1 ? refusal('Server xatosi', 500) : ok([makeMember()])),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    expect(await screen.findByRole('alert')).toHaveTextContent('Server xatosi');
    await typing.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await (await ready()).findByText('Ali Valiyev')).toBeInTheDocument();
  });
});

describe('StaffView: what the person may do', () => {
  it('offers edit, role, PIN and remove to the owner, and only edit and PIN to a manager', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(['staff.view', 'staff.pin']),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember()]),
    });
    renderPage(<StaffView />);
    await (await ready()).findByText('Ali Valiyev');
    await waitFor(() => expect(table().getByRole('button', { name: 'Ali Valiyev: PIN kod' })).toBeInTheDocument());
    expect(table().getByRole('button', { name: 'Ali Valiyev: tahrirlash' })).toBeInTheDocument();
    expect(table().queryByRole('button', { name: /rolni o'zgartirish/ })).not.toBeInTheDocument();
    expect(table().queryByRole('button', { name: /o'chirish/ })).not.toBeInTheDocument();
  });

  it('shows no buttons on a member the server says cannot be edited', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(OWNER_PERMISSIONS),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember({ name: 'Boshqa menejer', role: 'manager', can_edit: false })]),
    });
    renderPage(<StaffView />);
    await (await ready()).findByText('Boshqa menejer');
    expect(table().getByText("Faqat ko'rish")).toBeInTheDocument();
    expect(table().queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('StaffView: add', () => {
  it('adds a member with the login phone and shows the invite link once', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([]),
      'POST /api/v1/owner/staff': () => ok({ staff: makeMember({ id: 9, name: 'Yangi Xodim', role: 'cook' }), invite_link: 'https://t.me/rizqim_bot?start=invite_abc' }, 201),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await screen.findByRole('button', { name: "Xodim qo'shish" }));

    const dialog = within(await screen.findByRole('dialog'));
    await typing.type(dialog.getByLabelText('Ismi'), 'Yangi Xodim');
    await typing.selectOptions(dialog.getByLabelText('Roli'), 'cook');
    await typing.selectOptions(dialog.getByLabelText('Filial'), 'Yunusobod');
    await typing.type(dialog.getByLabelText('Kirish telefoni'), '935551122');
    await typing.click(dialog.getByRole('button', { name: "Xodim qo'shish" }));

    const post = calls.find((call) => call.method === 'POST')!;
    expect(await post.json()).toEqual({ name: 'Yangi Xodim', role: 'cook', branch_id: 2, phone: '+998935551122' });
    expect(await screen.findByDisplayValue('https://t.me/rizqim_bot?start=invite_abc')).toBeInTheDocument();
    expect(screen.getByText("Havola faqat hozir ko'rsatiladi: keyinroq xodimlar ro'yxatida chiqmaydi.")).toBeInTheDocument();
    await typing.click(screen.getByRole('button', { name: 'Tayyor' }));
    expect(screen.getByRole('status')).toHaveTextContent("Yangi Xodim qo'shildi.");
  });

  it("checks the form before asking the server, and shows the server's own words for the rest", async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([]),
      'POST /api/v1/owner/staff': () => refusal('Xato', 422, { errors: { role: ['Bu rolni bera olmaysiz.'] } }),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await screen.findByRole('button', { name: "Xodim qo'shish" }));
    const dialog = within(await screen.findByRole('dialog'));

    await typing.click(dialog.getByRole('button', { name: "Xodim qo'shish" }));
    expect(dialog.getByText('Ismini kiriting.')).toBeInTheDocument();
    expect(dialog.getByText('Filialni tanlang.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);

    await typing.type(dialog.getByLabelText('Ismi'), 'Ali');
    await typing.selectOptions(dialog.getByLabelText('Filial'), 'Markaz');
    await typing.type(dialog.getByLabelText('Kirish telefoni'), '9012');
    await typing.click(dialog.getByRole('button', { name: "Xodim qo'shish" }));
    expect(dialog.getByText("Raqamni to'liq kiriting: 90 123 45 67")).toBeInTheDocument();

    await typing.clear(dialog.getByLabelText('Kirish telefoni'));
    await typing.click(dialog.getByRole('button', { name: "Xodim qo'shish" }));
    expect(await dialog.findByText('Bu rolni bera olmaysiz.')).toBeInTheDocument();
  });
});

describe('StaffView: edit and the login phone', () => {
  it('sends only the name when only the name changed (the phone is not re-vouched)', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember()]),
      'PATCH /api/v1/owner/staff/1': () => ok(makeMember({ name: 'Ali Yangi' })),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: 'Ali Valiyev: tahrirlash' }));
    const dialog = within(await screen.findByRole('dialog'));
    expect(dialog.getByLabelText('Kirish telefoni')).toHaveValue('90 123 45 67');

    await typing.clear(dialog.getByLabelText('Ismi'));
    await typing.type(dialog.getByLabelText('Ismi'), 'Ali Yangi');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    const patch = calls.find((call) => call.method === 'PATCH')!;
    expect(await patch.json()).toEqual({ name: 'Ali Yangi' });
    expect(screen.getByRole('status')).toHaveTextContent('Ali Yangi saqlandi.');
  });

  it('shows a phone the member proved by SMS as fixed and never sends it', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember({ login_phone_proof: 'sms' })]),
      'PATCH /api/v1/owner/staff/1': () => ok(makeMember({ name: 'Ali Yangi', login_phone_proof: 'sms' })),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: 'Ali Valiyev: tahrirlash' }));
    const dialog = within(await screen.findByRole('dialog'));

    // login_phone_proof: 'sms' (the staff list says it): the number cannot be edited here
    const phone = dialog.getByLabelText('Kirish telefoni');
    expect(phone).toHaveValue('90 123 45 67');
    expect(phone).toHaveAttribute('readonly');
    const name = dialog.getByLabelText('Ismi');
    await typing.clear(name);
    await typing.type(name, 'Ali Yangi');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'PATCH')!.json()).toEqual({ name: 'Ali Yangi' });
  });

  it('lets a plain phone refusal be undone with the old number', async () => {
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember()]),
      'PATCH /api/v1/owner/staff/1': () => refusal('Xato', 422, { errors: { phone: ["Bu raqam do'koningizning boshqa xodimiga biriktirilgan."] } }),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: 'Ali Valiyev: tahrirlash' }));
    const dialog = within(await screen.findByRole('dialog'));
    const phone = dialog.getByLabelText('Kirish telefoni');
    await typing.clear(phone);
    await typing.type(phone, '935559999');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));

    expect(await dialog.findByText("Bu raqam do'koningizning boshqa xodimiga biriktirilgan.")).toBeInTheDocument();
    expect(phone).not.toHaveAttribute('readonly');
    await typing.click(dialog.getByRole('button', { name: 'Eski raqamni qaytarish' }));
    expect(phone).toHaveValue('90 123 45 67');
    expect(dialog.queryByText(/boshqa xodimiga/)).not.toBeInTheDocument();
  });
});

describe('StaffView: role, PIN, remove', () => {
  const routes = (extra: Parameters<typeof mockFetch>[0]) =>
    mockFetch({
      'GET /api/v1/me/permissions': () => permissions(),
      'GET /api/v1/owner/branches': () => ok(BRANCHES),
      'GET /api/v1/owner/staff': () => ok([makeMember({ has_pin: true, staff_code: '4821' })]),
      ...extra,
    });

  it('changes the role and the branch of a member', async () => {
    const { calls } = routes({ 'PUT /api/v1/owner/staff/1/role': () => ok({ id: 1, name: 'Ali Valiyev', role: 'manager', branch_id: 2 }) });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: "Ali Valiyev: rolni o'zgartirish" }));
    const dialog = within(await screen.findByRole('dialog'));
    await typing.selectOptions(dialog.getByLabelText('Roli'), 'manager');
    await typing.selectOptions(dialog.getByLabelText('Filial'), 'Yunusobod');
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({ role: 'manager', branch_id: 2 });
    expect(screen.getByRole('status')).toHaveTextContent("Ali Valiyev roli o'zgartirildi.");
  });

  it('shows the server message when the role is refused', async () => {
    routes({ 'PUT /api/v1/owner/staff/1/role': () => refusal("Ega o'z rolini o'zgartira olmaydi.", 403) });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: "Ali Valiyev: rolni o'zgartirish" }));
    const dialog = within(await screen.findByRole('dialog'));
    await typing.click(dialog.getByRole('button', { name: 'Saqlash' }));
    expect(await dialog.findByRole('alert')).toHaveTextContent("Ega o'z rolini o'zgartira olmaydi.");
  });

  it('sets a PIN and shows the staff code; a weak PIN is refused with the server message', async () => {
    let attempt = 0;
    const { calls } = routes({
      'PUT /api/v1/owner/staff/1/pin': () => (++attempt === 1 ? refusal('Xato', 422, { errors: { pin: ["Bu PIN juda oddiy, boshqasini tanlang."] } }) : ok({ user_id: 1, staff_code: '4821', pin_set_at: '2026-10-21T10:00:00+05:00' })),
    });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: 'Ali Valiyev: PIN kod' }));
    const dialog = within(await screen.findByRole('dialog'));

    await typing.type(dialog.getByLabelText('Yangi PIN kod'), '12');
    await typing.click(dialog.getByRole('button', { name: 'PINni almashtirish' }));
    expect(dialog.getByText("PIN 4 dan 6 gacha raqamdan iborat bo'lsin.")).toBeInTheDocument();

    await typing.clear(dialog.getByLabelText('Yangi PIN kod'));
    await typing.type(dialog.getByLabelText('Yangi PIN kod'), '1234');
    await typing.click(dialog.getByRole('button', { name: 'PINni almashtirish' }));
    expect(await dialog.findByText('Bu PIN juda oddiy, boshqasini tanlang.')).toBeInTheDocument();

    await typing.clear(dialog.getByLabelText('Yangi PIN kod'));
    await typing.type(dialog.getByLabelText('Yangi PIN kod'), '48 21');
    await typing.click(dialog.getByRole('button', { name: 'PINni almashtirish' }));
    expect(await screen.findByText('PIN saqlandi')).toBeInTheDocument();
    expect(screen.getByText('4821', { selector: 'p' })).toBeInTheDocument();
    expect(await calls.filter((call) => call.method === 'PUT')[1]!.json()).toEqual({ pin: '4821' });
  });

  it('clears the PIN after a second click', async () => {
    const { calls } = routes({ 'DELETE /api/v1/owner/staff/1/pin': () => ok(null) });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: 'Ali Valiyev: PIN kod' }));
    const dialog = within(await screen.findByRole('dialog'));
    await typing.click(dialog.getByRole('button', { name: "PINni o'chirish" }));
    expect(calls.some((call) => call.method === 'DELETE')).toBe(false);
    await typing.click(dialog.getByRole('button', { name: "Ha, o'chirish" }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent("Ali Valiyev PIN kodi o'chirildi.");
  });

  it('removes a member only after the confirmation', async () => {
    const { calls } = routes({ 'DELETE /api/v1/owner/employees/1': () => ok(null) });
    const typing = userEvent.setup();
    renderPage(<StaffView />);
    await typing.click(await (await ready()).findByRole('button', { name: "Ali Valiyev: o'chirish" }));
    const dialog = within(await screen.findByRole('alertdialog'));
    expect(calls.some((call) => call.method === 'DELETE')).toBe(false);
    await typing.click(dialog.getByRole('button', { name: "O'chirish" }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(screen.getByRole('status')).toHaveTextContent("Ali Valiyev o'chirildi.");
  });
});
