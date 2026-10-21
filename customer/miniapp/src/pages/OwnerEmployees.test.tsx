import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { showAlert, showConfirm } from '../lib/dialogs';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import OwnerEmployees from './OwnerEmployees';

// The owner's staff screen on a fake API (GET/POST /owner/staff, PATCH /owner/staff/{id}, the PIN endpoints): who works
// where, how their phone signs in, and the PIN

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined), showConfirm: vi.fn() }));

// Several turns: the queries, their fetches and the re-renders after them
const flush = () =>
  act(async () => {
    for (let turn = 0; turn < 10; turn++) await new Promise((resolve) => setTimeout(resolve, 0));
  });

const member = (overrides: Record<string, unknown> = {}) => ({
  id: 11,
  name: 'Bobur',
  role: 'manager',
  role_label: 'Menejer',
  branch: { id: 3, name: 'Chilonzor' },
  courier_type: null,
  phone: null,
  login_phone_enabled: false,
  has_pin: false,
  staff_code: null,
  telegram_linked: false,
  invite_pending: false,
  can_edit: true,
  created_at: null,
  ...overrides,
});

const branch = (id: number, name: string) => ({ id, name });

let calls: FakeCall[];
let staff: ReturnType<typeof member>[];
let writeAnswer: unknown;

beforeEach(() => {
  staff = [member()];
  writeAnswer = success({});
  ({ calls } = stubStaffApi((call) => {
    if (call.path === '/owner/staff' && call.method === 'GET') return success(staff);
    if (call.path === '/owner/branches') return success([branch(3, 'Chilonzor'), branch(4, 'Yunusobod')]);
    return writeAnswer;
  }));
  vi.mocked(showAlert).mockClear();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  vi.restoreAllMocks();
});

function renderStaff() {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter>
        <OwnerEmployees />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const manage = (name: string) => fireEvent.click(screen.getByRole('button', { name: `${name}: boshqarish` }));

describe('OwnerEmployees: the list', () => {
  it('names the role and the branch of each member, and "Filialsiz" only for one that has none', async () => {
    staff = [member(), member({ id: 12, name: 'Sardor', role: 'courier', role_label: 'Kuryer', branch: null })];
    renderStaff();
    await flush();

    expect(screen.getByText('Menejer • Chilonzor')).toBeInTheDocument();
    expect(screen.getByText('Kuryer • Filialsiz')).toBeInTheDocument();
    expect(screen.getByText('Jami 2 ta xodim')).toBeInTheDocument();
  });

  it('shows every role of the new API (a cook, a cashier), by the label the backend gives', async () => {
    staff = [member({ id: 12, name: 'Aziza', role: 'cook', role_label: 'Oshpaz' }), member({ id: 13, name: 'Dilnoza', role: 'cashier', role_label: 'Kassir' })];
    renderStaff();
    await flush();

    expect(screen.getByText('Oshpaz • Chilonzor')).toBeInTheDocument();
    expect(screen.getByText('Kassir • Chilonzor')).toBeInTheDocument();
  });

  it('marks who has not joined yet, who is on Telegram and who has a PIN', async () => {
    staff = [member({ invite_pending: true }), member({ id: 12, name: 'Sardor', telegram_linked: true, has_pin: true })];
    renderStaff();
    await flush();

    expect(screen.getByText('Taklif kutilmoqda')).toBeInTheDocument();
    expect(screen.getByText('Telegram ulangan')).toBeInTheDocument();
    expect(screen.getByText("PIN o'rnatilgan")).toBeInTheDocument();
    expect(screen.getByText("PIN yo'q")).toBeInTheDocument();
  });

  it('says so when the list cannot be loaded', async () => {
    ({ calls } = stubStaffApi(() => reply(502)));
    renderStaff();
    await flush();

    expect(screen.getByRole('alert')).toBeInTheDocument();
  });
});

describe('OwnerEmployees: the login phone', () => {
  it('tells whether the phone signs in by SMS, in words', async () => {
    staff = [
      member({ id: 11, name: 'Bobur', phone: '+998901112233', login_phone_enabled: true }),
      member({ id: 12, name: 'Sardor', phone: '+998909998877', login_phone_enabled: false }),
      member({ id: 13, name: 'Aziza', phone: null }),
    ];
    renderStaff();
    await flush();

    manage('Bobur');
    expect(screen.getByText('SMS bilan kirish yoqilgan')).toBeInTheDocument();
    expect(screen.getByText(/o'zi SMS bilan tasdiqlagan bo'lsa/)).toBeInTheDocument();
    manage('Sardor');
    expect(screen.getByText('SMS bilan kirish yoqilmagan')).toBeInTheDocument();
    manage('Aziza');
    expect(screen.getByText("Raqam yo'q")).toBeInTheDocument();
  });

  it('writes a login phone with PATCH and nothing else', async () => {
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "Raqam qo'shish: Bobur" }));
    const dialog = screen.getByRole('dialog');
    fireEvent.change(within(dialog).getByLabelText(/Telefon/), { target: { value: ' +998901112233 ' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    const [patch] = callsTo(calls, '/owner/staff/11', 'PATCH');
    expect(patch!.body).toEqual({ phone: '+998901112233' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('clears the phone by sending null', async () => {
    staff = [member({ phone: '+998901112233', login_phone_enabled: true })];
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "Raqamni o'zgartirish: Bobur" }));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: "Raqamni o'chirish" }));
    await flush();

    expect(callsTo(calls, '/owner/staff/11', 'PATCH')[0]!.body).toEqual({ phone: null });
  });

  it('shows the words of the backend when it refuses a phone the member proved by SMS', async () => {
    staff = [member({ phone: '+998901112233', login_phone_enabled: true })];
    renderStaff();
    await flush();
    manage('Bobur');
    fireEvent.click(screen.getByRole('button', { name: "Raqamni o'zgartirish: Bobur" }));
    writeAnswer = reply(422, { success: false, message: 'The given data was invalid.', errors: { phone: ["Bu raqamni xodimning o'zi SMS kod bilan tasdiqlagan, uni o'zgartirib bo'lmaydi."] } });

    fireEvent.change(within(screen.getByRole('dialog')).getByLabelText(/Telefon/), { target: { value: '+998909998877' } });
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(within(screen.getByRole('dialog')).getByRole('alert')).toHaveTextContent("xodimning o'zi SMS kod bilan tasdiqlagan");
  });
});

describe('OwnerEmployees: the PIN', () => {
  it('sets a PIN with PUT /owner/staff/{id}/pin and closes', async () => {
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "PIN o'rnatish: Bobur" }));
    const dialog = screen.getByRole('dialog');
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled(); // nothing typed
    fireEvent.change(within(dialog).getByLabelText('Yangi PIN kod'), { target: { value: '48x21' } }); // digits only
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    const [put] = callsTo(calls, '/owner/staff/11/pin', 'PUT');
    expect(put!.body).toEqual({ pin: '4821' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('keeps the dialog open and says why when the PIN is refused', async () => {
    renderStaff();
    await flush();
    manage('Bobur');
    fireEvent.click(screen.getByRole('button', { name: "PIN o'rnatish: Bobur" }));
    writeAnswer = reply(422, { success: false, message: 'PIN juda oddiy.', errors: { pin: ['PIN juda oddiy: boshqasini tanlang.'] } });

    fireEvent.change(within(screen.getByRole('dialog')).getByLabelText('Yangi PIN kod'), { target: { value: '1234' } });
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(within(screen.getByRole('dialog')).getByRole('alert')).toHaveTextContent('PIN juda oddiy: boshqasini tanlang.');
  });

  it('replaces or resets a PIN that is set, after asking', async () => {
    staff = [member({ has_pin: true, staff_code: 'K-12' })];
    vi.mocked(showConfirm).mockResolvedValue(true);
    renderStaff();
    await flush();
    manage('Bobur');

    expect(screen.getByText('Kod: K-12')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'PIN almashtirish: Bobur' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: "PIN ni o'chirish: Bobur" }));
    await flush();

    expect(showConfirm).toHaveBeenCalled();
    expect(callsTo(calls, '/owner/staff/11/pin', 'DELETE')).toHaveLength(1);
  });

  it('does not reset a PIN that was not confirmed', async () => {
    staff = [member({ has_pin: true })];
    vi.mocked(showConfirm).mockResolvedValue(false);
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "PIN ni o'chirish: Bobur" }));
    await flush();

    expect(callsTo(calls, '/owner/staff/11/pin', 'DELETE')).toHaveLength(0);
  });

  it('offers nothing to change on a member the signed-in person may not edit', async () => {
    staff = [member({ can_edit: false, has_pin: true })];
    renderStaff();
    await flush();
    manage('Bobur');

    expect(screen.getByText("Bu xodimni faqat ko'rishingiz mumkin.")).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /PIN/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Raqam/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /o'chirish/ })).not.toBeInTheDocument();
  });
});

describe('OwnerEmployees: adding, moving and removing', () => {
  it('adds a member with a login phone and shows the invite link', async () => {
    writeAnswer = success({ staff: member({ id: 20, name: 'Aziza' }), invite_link: 'https://t.me/bot?start=invite_abc' }, {});
    renderStaff();
    await flush();

    fireEvent.change(screen.getByLabelText('Lavozim'), { target: { value: 'cook' } });
    fireEvent.change(screen.getByLabelText('Ism-familiya'), { target: { value: ' Aziza ' } });
    fireEvent.change(screen.getByLabelText('Filialga biriktirish'), { target: { value: '4' } });
    fireEvent.change(screen.getByLabelText(/Kirish telefoni/), { target: { value: '+998901112233' } });
    fireEvent.click(screen.getByRole('button', { name: "Xodim qo'shish" }));
    await flush();

    const [post] = callsTo(calls, '/owner/staff', 'POST');
    expect(post!.body).toEqual({ name: 'Aziza', role: 'cook', branch_id: 4, phone: '+998901112233' });
    expect(screen.getByRole('button', { name: 'Nusxa olish' })).toBeInTheDocument();
  });

  it('sends no phone when none was typed, and the first branch when none was picked', async () => {
    writeAnswer = success({ staff: member({ id: 20 }), invite_link: 'https://t.me/bot?start=invite_abc' });
    renderStaff();
    await flush();

    fireEvent.change(screen.getByLabelText('Ism-familiya'), { target: { value: 'Vali' } });
    fireEvent.click(screen.getByRole('button', { name: "Xodim qo'shish" }));
    await flush();

    expect(callsTo(calls, '/owner/staff', 'POST')[0]!.body).toEqual({ name: 'Vali', role: 'manager', branch_id: 3 });
  });

  it('shows the 422 messages under the fields', async () => {
    writeAnswer = reply(422, { success: false, message: 'The given data was invalid.', errors: { phone: ["Bu raqam do'koningizning boshqa xodimiga biriktirilgan."] } });
    renderStaff();
    await flush();

    fireEvent.change(screen.getByLabelText('Ism-familiya'), { target: { value: 'Vali' } });
    fireEvent.change(screen.getByLabelText(/Kirish telefoni/), { target: { value: '+998901112233' } });
    fireEvent.click(screen.getByRole('button', { name: "Xodim qo'shish" }));
    await flush();

    expect(screen.getByText("Bu raqam do'koningizning boshqa xodimiga biriktirilgan.")).toBeInTheDocument();
  });

  it('moves a member to another branch with PATCH', async () => {
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.change(screen.getByLabelText("Filialni o'zgartirish"), { target: { value: '4' } });
    await flush();

    expect(callsTo(calls, '/owner/staff/11', 'PATCH')[0]!.body).toEqual({ branch_id: 4 });
  });

  it('removes a member after the owner confirms', async () => {
    vi.mocked(showConfirm).mockResolvedValue(true);
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "Bobur xodimini o'chirish" }));
    await flush();

    expect(callsTo(calls, '/owner/employees/11', 'DELETE')).toHaveLength(1);
  });

  it('does not remove a member that was not confirmed', async () => {
    vi.mocked(showConfirm).mockResolvedValue(false);
    renderStaff();
    await flush();
    manage('Bobur');

    fireEvent.click(screen.getByRole('button', { name: "Bobur xodimini o'chirish" }));
    await flush();

    expect(callsTo(calls, '/owner/employees/11', 'DELETE')).toHaveLength(0);
  });
});
