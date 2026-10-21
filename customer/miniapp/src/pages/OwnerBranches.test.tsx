import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { resetMainButtonForTests } from '../hooks/useMainButton';
import { showAlert } from '../lib/dialogs';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import OwnerBranches from './OwnerBranches';

// The branches' working hours on the phone (GET /owner/branches, PATCH /owner/branches/{id})

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined), showConfirm: vi.fn() }));

const flush = () =>
  act(async () => {
    for (let turn = 0; turn < 10; turn++) await new Promise((resolve) => setTimeout(resolve, 0));
  });

const branch = (overrides: Record<string, unknown> = {}) => ({
  id: 1,
  name: 'Chilonzor',
  phone: '+998712001122',
  address: 'Chilonzor, 9-kvartal',
  region_id: 1,
  district_id: 2,
  latitude: 41.2,
  longitude: 69.2,
  radius_km: 10,
  status: 'active',
  is_active: true,
  working_hours: { open: '09:00', close: '22:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' },
  is_open_now: true,
  created_at: null,
  updated_at: null,
  ...overrides,
});

let calls: FakeCall[];
let branches: ReturnType<typeof branch>[];
let writeAnswer: unknown;

beforeEach(() => {
  branches = [branch()];
  writeAnswer = undefined;
  ({ calls } = stubStaffApi((call) => (call.method === 'GET' ? success(branches) : (writeAnswer ?? success(branch())))));
  vi.mocked(showAlert).mockClear();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  resetMainButtonForTests();
  vi.restoreAllMocks();
});

function renderBranches() {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter>
        <OwnerBranches />
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const edit = (name: string) => fireEvent.click(screen.getByRole('button', { name: `${name}: ish vaqtini tahrirlash` }));

describe('OwnerBranches', () => {
  it('shows each branch with its hours and whether it is open now', async () => {
    branches = [
      branch(),
      branch({ id: 2, name: 'Yunusobod', is_open_now: false, working_hours: { open: '22:00', close: '04:00', all_day: false, overnight: true, timezone: 'Asia/Tashkent' } }),
      branch({ id: 3, name: 'Sergeli', working_hours: { open: null, close: null, all_day: true, overnight: false, timezone: 'Asia/Tashkent' } }),
      branch({ id: 4, name: 'Mirzo', status: 'temporarily_closed' }),
      branch({ id: 5, name: 'Eski', status: 'inactive', is_active: false }),
    ];
    renderBranches();
    await flush();

    expect(screen.getAllByText('09:00 – 22:00')).toHaveLength(3); // Chilonzor, Mirzo, Eski
    expect(screen.getAllByText('Hozir ochiq')).toHaveLength(2); // Chilonzor, Sergeli
    expect(screen.getByText('Hozir yopiq')).toBeInTheDocument();
    expect(screen.getByText('22:00 – 04:00')).toBeInTheDocument();
    expect(screen.getByText('Tungi smena')).toBeInTheDocument();
    expect(screen.getByText("Sutka bo'yi")).toBeInTheDocument();
    expect(screen.getByText('Vaqtincha yopiq', { selector: 'span' })).toBeInTheDocument();
    expect(screen.getByText('Faol emas')).toBeInTheDocument();
  });

  it('saves new hours with PATCH on the main button: both times, and the status', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();

    fireEvent.change(screen.getByLabelText('Ochilish vaqti'), { target: { value: '08:30' } });
    fireEvent.change(screen.getByLabelText('Yopilish vaqti'), { target: { value: '23:00' } });
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    const [patch] = callsTo(calls, '/owner/branches/1', 'PATCH');
    expect(patch!.body).toEqual({ open_time: '08:30', close_time: '23:00', status: 'active' });
    expect(screen.queryByLabelText('Ochilish vaqti')).not.toBeInTheDocument(); // the editor closed
  });

  it('opens all day with both times null', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();

    fireEvent.click(screen.getByRole('switch', { name: "Sutka bo'yi ochiq" }));
    expect(screen.queryByLabelText('Ochilish vaqti')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(callsTo(calls, '/owner/branches/1', 'PATCH')[0]!.body).toEqual({ open_time: null, close_time: null, status: 'active' });
  });

  it('closes a branch for a while with the status', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();

    fireEvent.click(screen.getByRole('switch', { name: 'Vaqtincha yopiq' }));
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(callsTo(calls, '/owner/branches/1', 'PATCH')[0]!.body).toMatchObject({ status: 'temporarily_closed' });
  });

  it('leaves the status of an inactive branch alone (that is the web panel\'s)', async () => {
    branches = [branch({ status: 'inactive', is_active: false })];
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();

    expect(screen.queryByRole('switch', { name: 'Vaqtincha yopiq' })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(callsTo(calls, '/owner/branches/1', 'PATCH')[0]!.body).toEqual({ open_time: '09:00', close_time: '22:00' });
  });

  it('shows the 422 of a time under its field and keeps the editor open', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();
    writeAnswer = reply(422, { success: false, message: 'The given data was invalid.', errors: { close_time: ['Yopilish vaqti noto‘g‘ri.'] } });

    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(screen.getByText('Yopilish vaqti noto‘g‘ri.')).toBeInTheDocument();
    expect(screen.getByLabelText('Yopilish vaqti')).toBeInTheDocument();
    expect(showAlert).not.toHaveBeenCalled();
  });

  it('says what went wrong when the answer names no field', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();
    writeAnswer = reply(403, { success: false, message: "Bu filialni o'zgartirishga ruxsat yo'q." });

    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(showAlert).toHaveBeenCalledWith("Bu filialni o'zgartirishga ruxsat yo'q.");
  });

  it('closes the editor without saving on "Bekor qilish"', async () => {
    renderBranches();
    await flush();
    edit('Chilonzor');
    await flush();

    fireEvent.click(within(screen.getByText('Chilonzor', { selector: 'h3' }).closest('div')!).getByRole('button', { name: 'Bekor qilish' }));

    expect(callsTo(calls, '/owner/branches/1', 'PATCH')).toHaveLength(0);
    expect(screen.queryByLabelText('Ochilish vaqti')).not.toBeInTheDocument();
  });

  it('says so when the branches cannot be loaded', async () => {
    ({ calls } = stubStaffApi(() => reply(502)));
    renderBranches();
    await flush();

    expect(screen.getByRole('alert')).toBeInTheDocument();
  });
});
