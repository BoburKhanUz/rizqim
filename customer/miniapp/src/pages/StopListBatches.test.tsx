import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { resetMainButtonForTests } from '../hooks/useMainButton';
import { showAlert, showConfirm } from '../lib/dialogs';
import { useAuthStore } from '../store/authStore';
import { useLanguageStore } from '../store/languageStore';
import { makeStore, makeUser } from '../test/factories';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import StopListBatches from './StopListBatches';

// Stop-list and batches ("qozon") on the phone, on a fake API: what a tap sends, and what the backend answers

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined), showConfirm: vi.fn() }));

// Several turns: the queries, their fetches and the re-renders after them
const flush = (turns = 10) =>
  act(async () => {
    for (let turn = 0; turn < turns; turn++) await new Promise((resolve) => setTimeout(resolve, 0));
  });
const wait = (ms: number) => act(async () => { await new Promise((resolve) => setTimeout(resolve, ms)); });

const branch = (id: number, name: string) => ({ id, name });
const variant = (id: number, name: string | null) => ({ variant_id: id, variant_uuid: `v${id}`, name, price: '25000.00', is_active: true, hidden_reason: null, sort_order: 0 });
const entry = (overrides: Record<string, unknown>) => ({ id: 70, branch_id: 1, type: 'product', subject_id: 1, subject_uuid: null, name: null, reason: null, until: null, created_by: 1, created_at: null, ...overrides });
const batch = (overrides: Record<string, unknown> = {}) => ({
  id: 5,
  uuid: 'b5',
  branch_id: 1,
  product_id: 9,
  product_name: 'Osh',
  product_variant_id: null,
  variant_name: null,
  unit: 'piece',
  label: 'Tushlik',
  quantity_produced: 40,
  quantity_left: 12,
  is_open: true,
  started_at: '2026-10-14T05:00:00+05:00',
  sold_out_at: null,
  closed_at: null,
  ...overrides,
});

let calls: FakeCall[];
let stopList: ReturnType<typeof entry>[];
let batches: ReturnType<typeof batch>[];
let writeAnswer: unknown;
let client: QueryClient;

beforeEach(() => {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  useLanguageStore.setState({ language: 'uz' });
  stopList = [];
  batches = [];
  writeAnswer = undefined;
  ({ calls } = stubStaffApi((call) => {
    if (call.method !== 'GET') return writeAnswer ?? success(entry({ id: 99 }));
    switch (call.path) {
      case '/owner/branches':
        return success([branch(1, 'Chilonzor'), branch(2, 'Yunusobod')]);
      case '/owner/branch-variants':
        return success([
          { product_id: 1, name: 'Lavash', description: null, image_url: null, variants: [variant(11, 'Standart')] },
          { product_id: 2, name: 'Burger', description: null, image_url: null, variants: [variant(21, 'Kichik'), variant(22, 'Katta')] },
          { product_id: 9, name: 'Osh', description: null, image_url: null, variants: [variant(91, 'Porsiya')] },
        ]);
      case '/owner/modifier-groups':
        return success([
          {
            id: 1, uuid: 'g1', name: 'Sous', name_i18n: null, min_select: 0, max_select: 1, is_required: false, sort_order: 0, is_active: true, product_ids: [1],
            modifiers: [{ id: 31, uuid: 'm31', name: 'Achchiq sous', name_i18n: null, price_delta: '0.00', max_quantity: 1, product_variant_id: null, sort_order: 0, is_active: true }],
          },
        ]);
      case '/owner/stop-list':
        return success({ branch_id: Number(call.query.branch_id ?? 1), entries: stopList });
      case '/owner/batches':
        return success({ branch_id: Number(call.query.branch_id ?? 1), batches });
      default:
        return reply(404, { success: false, message: 'yo\'q' });
    }
  }));
  vi.mocked(showAlert).mockClear();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  client.clear();
  resetMainButtonForTests();
  useAuthStore.getState().logout();
  vi.restoreAllMocks();
});

function renderPage() {
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <StopListBatches />
        <MainButtonHost />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const owner = () => useAuthStore.getState().setAuth('token', makeUser({ role: 'owner', store_id: 1 }), makeStore(), false);
const manager = () => useAuthStore.getState().setAuth('token', makeUser({ role: 'manager', store_id: 1, branch_id: 1 }), makeStore(), false);
const sellSwitch = (name: string) => screen.getByRole('switch', { name: `Sotuvda: ${name}` });
const stopListCalls = () => callsTo(calls, '/owner/stop-list', 'GET');

describe('StopListBatches: the branch', () => {
  it('works with the first branch of an owner, and with the one they pick', async () => {
    owner();
    renderPage();
    await flush();

    expect(stopListCalls().at(-1)!.query.branch_id).toBe('1');
    expect(callsTo(calls, '/owner/branch-variants').at(-1)!.query.branch_id).toBe('1');

    fireEvent.change(screen.getByRole('combobox', { name: 'Filial' }), { target: { value: '2' } });
    await flush();

    expect(stopListCalls().at(-1)!.query.branch_id).toBe('2');
  });

  it('asks a manager for no branch: the backend binds them to their own', async () => {
    manager();
    renderPage();
    await flush();

    expect(callsTo(calls, '/owner/branches')).toHaveLength(0);
    expect(screen.queryByRole('combobox', { name: 'Filial' })).not.toBeInTheDocument();
    expect(stopListCalls().at(-1)!.query.branch_id).toBeUndefined();
    expect(screen.getByText('Lavash')).toBeInTheDocument();
  });
});

describe('StopListBatches: the stop-list', () => {
  beforeEach(() => {
    owner();
  });

  it('lists the dishes, their variants and the extras, all on sale at first', async () => {
    renderPage();
    await flush();

    for (const name of ['Lavash', 'Burger', 'Osh', 'Kichik', 'Katta', 'Achchiq sous']) expect(sellSwitch(name)).toHaveAttribute('aria-checked', 'true');
    expect(screen.queryByRole('switch', { name: 'Sotuvda: Standart' })).not.toBeInTheDocument(); // a single variant is the dish itself
  });

  it('stops a dish with one tap: POST type product, no reason', async () => {
    renderPage();
    await flush();

    fireEvent.click(sellSwitch('Lavash'));
    await flush();

    const [post] = callsTo(calls, '/owner/stop-list', 'POST');
    expect(post!.body).toEqual({ type: 'product', id: 1, branch_id: 1 });
  });

  it('stops a variant and an extra on their own', async () => {
    renderPage();
    await flush();

    fireEvent.click(sellSwitch('Katta'));
    await flush();
    fireEvent.click(sellSwitch('Achchiq sous'));
    await flush();

    const bodies = callsTo(calls, '/owner/stop-list', 'POST').map((call) => call.body);
    expect(bodies).toEqual([
      { type: 'variant', id: 22, branch_id: 1 },
      { type: 'modifier', id: 31, branch_id: 1 },
    ]);
  });

  it('sends no branch for a manager', async () => {
    useAuthStore.getState().logout();
    manager();
    renderPage();
    await flush();

    fireEvent.click(sellSwitch('Lavash'));
    await flush();

    expect(callsTo(calls, '/owner/stop-list', 'POST')[0]!.body).toEqual({ type: 'product', id: 1 });
  });

  it('puts a stopped dish back with DELETE of its entry, and shows until when and why it is stopped', async () => {
    stopList = [entry({ id: 70, type: 'product', subject_id: 1, reason: 'Go\'sht tugadi', until: '2026-10-14T18:00:00+05:00' })];
    renderPage();
    await flush();

    expect(sellSwitch('Lavash')).toHaveAttribute('aria-checked', 'false');
    expect(screen.getByText('Tugadi')).toBeInTheDocument();
    expect(screen.getByText("Go'sht tugadi")).toBeInTheDocument();
    expect(screen.getByText(/gacha/)).toBeInTheDocument();

    fireEvent.click(sellSwitch('Lavash'));
    await flush();

    expect(callsTo(calls, '/owner/stop-list/70', 'DELETE')).toHaveLength(1);
  });

  it('does not let the variants of a stopped dish be switched alone', async () => {
    stopList = [entry({ id: 71, type: 'product', subject_id: 2 })];
    renderPage();
    await flush();

    expect(sellSwitch('Kichik')).toBeDisabled();
    expect(screen.getAllByText("Taom to'xtatilgan")).toHaveLength(2);
  });

  it('searches dishes, variants and extras by name', async () => {
    renderPage();
    await flush();

    fireEvent.change(screen.getByRole('searchbox', { name: "Taom yoki qo'shimcha qidirish" }), { target: { value: 'katta' } });
    await wait(200);

    expect(screen.getByText('Burger')).toBeInTheDocument();
    expect(screen.queryByText('Lavash')).not.toBeInTheDocument();
    expect(screen.queryByText('Achchiq sous')).not.toBeInTheDocument();

    fireEvent.change(screen.getByRole('searchbox', { name: "Taom yoki qo'shimcha qidirish" }), { target: { value: 'zzz' } });
    await wait(200);

    expect(screen.getByText('Hech narsa topilmadi')).toBeInTheDocument();
  });

  it('shows only what is stopped when asked, and says so when nothing is', async () => {
    stopList = [entry({ id: 70, type: 'product', subject_id: 1 })];
    renderPage();
    await flush();

    fireEvent.click(screen.getByRole('button', { name: "To'xtatilgan (1)" }));

    expect(screen.getByText('Lavash')).toBeInTheDocument();
    expect(screen.queryByText('Burger')).not.toBeInTheDocument();

    fireEvent.click(sellSwitch('Lavash'));
    await flush();
    stopList = [];
    await act(async () => { await client.invalidateQueries(); });
    await flush();

    expect(screen.getByText("Hozir hech narsa to'xtatilmagan")).toBeInTheDocument();
  });

  it('shows the words of the backend when a change is refused', async () => {
    writeAnswer = reply(403, { success: false, message: "Sizda stop-listni o'zgartirish huquqi yo'q." });
    renderPage();
    await flush();

    fireEvent.click(sellSwitch('Lavash'));
    await flush();

    expect(showAlert).toHaveBeenCalledWith("Sizda stop-listni o'zgartirish huquqi yo'q.");
    expect(sellSwitch('Lavash')).toHaveAttribute('aria-checked', 'true'); // it is not stopped
  });

  it('says so when the stop-list cannot be loaded', async () => {
    ({ calls } = stubStaffApi((call) => (call.path === '/owner/branches' ? success([branch(1, 'Chilonzor')]) : reply(502))));
    renderPage();
    await wait(1500); // the poll retries once before it gives up

    expect(screen.getByRole('alert')).toBeInTheDocument();
  });
});

describe('StopListBatches: the batches', () => {
  beforeEach(() => {
    owner();
    batches = [batch(), batch({ id: 6, uuid: 'b6', product_id: 2, product_name: 'Burger', variant_name: 'Katta', label: null, quantity_produced: 10, quantity_left: 0, sold_out_at: '2026-10-14T08:00:00+05:00' })];
  });

  async function openBatches() {
    renderPage();
    await flush();
    fireEvent.click(screen.getByRole('button', { name: 'Qozon' }));
    await flush();
  }

  it('lists the open batches with what is left, and marks a sold-out one', async () => {
    await openBatches();

    const osh = screen.getByRole('article', { name: 'Osh' });
    expect(within(osh).getByText('12 / 40 dona')).toBeInTheDocument();
    expect(within(osh).getByText(/Tushlik/)).toBeInTheDocument();
    expect(within(osh).getByRole('progressbar')).toHaveAttribute('aria-valuenow', '30');
    const burger = screen.getByRole('article', { name: 'Burger (Katta)' });
    expect(within(burger).getByText('Tugadi')).toBeInTheDocument();
  });

  it('reads the open batches of the picked branch', async () => {
    await openBatches();
    expect(callsTo(calls, '/owner/batches').at(-1)!.query.branch_id).toBe('1');
  });

  it('adds to a batch', async () => {
    await openBatches();

    fireEvent.click(screen.getByRole('button', { name: "To'ldirish: Osh" }));
    const dialog = screen.getByRole('dialog');
    fireEvent.change(within(dialog).getByLabelText("Qancha qo'shiladi"), { target: { value: '12,5' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(callsTo(calls, '/owner/batches/5/adjust', 'POST')[0]!.body).toEqual({ add: 12.5 });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('sets what is left', async () => {
    await openBatches();

    fireEvent.click(screen.getByRole('button', { name: "To'ldirish: Osh" }));
    const dialog = screen.getByRole('dialog');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Qoldiq' }));
    fireEvent.change(within(dialog).getByLabelText('Hozirgi qoldiq'), { target: { value: '0' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(callsTo(calls, '/owner/batches/5/adjust', 'POST')[0]!.body).toEqual({ quantity_left: 0 });
  });

  it('does not send an amount that is not a number', async () => {
    await openBatches();

    fireEvent.click(screen.getByRole('button', { name: "To'ldirish: Osh" }));
    const dialog = screen.getByRole('dialog');
    fireEvent.change(within(dialog).getByLabelText("Qancha qo'shiladi"), { target: { value: 'ko\'p' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(within(dialog).getByRole('alert')).toHaveTextContent('Miqdorni raqam bilan kiriting');
    expect(callsTo(calls, '/owner/batches/5/adjust', 'POST')).toHaveLength(0);
  });

  it('shows the backend\'s refusal inside the dialog', async () => {
    await openBatches();
    writeAnswer = reply(422, { success: false, message: 'The given data was invalid.', errors: { quantity_left: ["Qoldiq ishlab chiqarilgandan ko'p bo'lmasin."] } });

    fireEvent.click(screen.getByRole('button', { name: "To'ldirish: Osh" }));
    const dialog = screen.getByRole('dialog');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Qoldiq' }));
    fireEvent.change(within(dialog).getByLabelText('Hozirgi qoldiq'), { target: { value: '99' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await flush();

    expect(within(screen.getByRole('dialog')).getByRole('alert')).toHaveTextContent("Qoldiq ishlab chiqarilgandan ko'p bo'lmasin.");
  });

  it('closes a batch only after the question is answered yes', async () => {
    await openBatches();
    vi.mocked(showConfirm).mockResolvedValueOnce(false).mockResolvedValueOnce(true);

    fireEvent.click(screen.getByRole('button', { name: 'Yopish: Osh' }));
    await flush();
    expect(callsTo(calls, '/owner/batches/5/close', 'POST')).toHaveLength(0);

    fireEvent.click(screen.getByRole('button', { name: 'Yopish: Osh' }));
    await flush();
    expect(callsTo(calls, '/owner/batches/5/close', 'POST')).toHaveLength(1);
  });

  it('opens a new batch from the main button', async () => {
    await openBatches();

    fireEvent.click(screen.getByRole('button', { name: 'Yangi qozon ochish' }));
    await flush();
    const start = screen.getByRole('button', { name: 'Qozonni ochish' }); // the main button (its stand-in in the page)
    expect(start).toBeDisabled(); // nothing chosen yet

    fireEvent.change(screen.getByLabelText('Taom'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Variant'), { target: { value: '22' } });
    fireEvent.change(screen.getByLabelText(/Necha porsiya/), { target: { value: '30' } });
    fireEvent.change(screen.getByLabelText(/Izoh/), { target: { value: ' Kechki ' } });
    expect(screen.getByRole('button', { name: 'Qozonni ochish' })).toBeEnabled();
    fireEvent.click(screen.getByRole('button', { name: 'Qozonni ochish' }));
    await flush();

    expect(callsTo(calls, '/owner/batches', 'POST')[0]!.body).toEqual({ branch_id: 1, product_id: 2, product_variant_id: 22, quantity: 30, label: 'Kechki' });
    expect(screen.queryByLabelText('Taom')).not.toBeInTheDocument(); // the form closed
  });

  it('opens a batch of all the variants when none is picked, for a manager without a branch id', async () => {
    useAuthStore.getState().logout();
    manager();
    renderPage();
    await flush();
    fireEvent.click(screen.getByRole('button', { name: 'Qozon' }));
    await flush();

    fireEvent.click(screen.getByRole('button', { name: 'Yangi qozon ochish' }));
    await flush();
    fireEvent.change(screen.getByLabelText('Taom'), { target: { value: '9' } });
    fireEvent.change(screen.getByLabelText(/Necha porsiya/), { target: { value: '40' } });
    fireEvent.click(screen.getByRole('button', { name: 'Qozonni ochish' }));
    await flush();

    expect(callsTo(calls, '/owner/batches', 'POST')[0]!.body).toEqual({ product_id: 9, quantity: 40 });
  });

  it('shows the 422 of a field under it and keeps the form', async () => {
    await openBatches();
    writeAnswer = reply(422, { success: false, message: 'The given data was invalid.', errors: { product_id: ['Bu taom uchun qozon allaqachon ochiq.'] } });

    fireEvent.click(screen.getByRole('button', { name: 'Yangi qozon ochish' }));
    await flush();
    fireEvent.change(screen.getByLabelText('Taom'), { target: { value: '9' } });
    fireEvent.change(screen.getByLabelText(/Necha porsiya/), { target: { value: '40' } });
    fireEvent.click(screen.getByRole('button', { name: 'Qozonni ochish' }));
    await flush();

    expect(screen.getByText('Bu taom uchun qozon allaqachon ochiq.')).toBeInTheDocument();
    expect(screen.getByLabelText('Taom')).toBeInTheDocument();
  });

  it('says so when no batch is open', async () => {
    batches = [];
    await openBatches();

    expect(screen.getByText("Ochiq qozon yo'q")).toBeInTheDocument();
  });
});
