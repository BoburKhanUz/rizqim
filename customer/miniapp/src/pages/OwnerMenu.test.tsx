import { act, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MainButtonHost } from '../components/ui/MainButtonHost';
import { showAlert, showConfirm } from '../lib/dialogs';
import { makeProduct, makeVariant } from '../test/factories';
import { callsTo, reply, stubStaffApi, success, type FakeCall } from '../test/staffFetch';
import OwnerMenuManagement from './OwnerMenuManagement';
import OwnerProductForm from './OwnerProductForm';

// The owner's menu screens on a fake API (@rizqim/staff, over fetch): what they send is the contract with the backend

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../lib/dialogs', () => ({ showAlert: vi.fn().mockResolvedValue(undefined), showConfirm: vi.fn() }));

const flush = () => act(async () => { await new Promise((resolve) => setTimeout(resolve, 0)); });

let calls: FakeCall[];
let products: ReturnType<typeof makeProduct>[];
let saveAnswer: unknown;

beforeEach(() => {
  products = [
    makeProduct({ id: 7, name: 'Lavash', variants: [makeVariant({ price: '25000.00' }), makeVariant({ id: 2, price: '30000.00' })] }),
    makeProduct({ id: 8, name: 'Burger', status: 'inactive', variants: [makeVariant({ id: 3, price: '40000.00' })] }),
  ];
  saveAnswer = success({ id: 9 });
  ({ calls } = stubStaffApi((call) => {
    if (call.path === '/owner/categories') return success([{ id: 3, name: 'Fast food', image: null, image_url: null }]);
    if (call.path === '/owner/products' && call.method === 'GET') return success(products);
    return saveAnswer;
  }));
  vi.mocked(showAlert).mockClear();
});

function renderMenu() {
  return render(
    <MemoryRouter>
      <OwnerMenuManagement />
    </MemoryRouter>,
  );
}

describe('OwnerMenuManagement', () => {
  it('lists the products with their cheapest price and whether they are on sale', async () => {
    renderMenu();
    await flush();

    expect(screen.getByText('Lavash')).toBeInTheDocument();
    expect(screen.getByText(/25\s000\sso'm/)).toBeInTheDocument();
    expect(screen.getByText('Burger')).toBeInTheDocument();
    expect(screen.getByRole('switch', { name: 'Sotuvda: Lavash' })).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByRole('switch', { name: 'Sotuvda: Burger' })).toHaveAttribute('aria-checked', 'false');
  });

  it('switches a product on or off with the toggle_active flag and nothing else', async () => {
    renderMenu();
    await flush();

    fireEvent.click(screen.getByRole('switch', { name: 'Sotuvda: Burger' }));
    await flush();

    const toggle = callsTo(calls, '/owner/products/8', 'POST');
    expect(toggle).toHaveLength(1);
    expect(toggle[0]!.query).toEqual({ toggle_active: '1' });
    expect(toggle[0]!.body).toBeUndefined();
    expect(screen.getByRole('switch', { name: 'Sotuvda: Burger' })).toHaveAttribute('aria-checked', 'true');
  });

  it('puts the switch back when the server refuses', async () => {
    renderMenu();
    await flush();
    saveAnswer = reply(500);

    fireEvent.click(screen.getByRole('switch', { name: 'Sotuvda: Lavash' }));
    await flush();

    expect(screen.getByRole('switch', { name: 'Sotuvda: Lavash' })).toHaveAttribute('aria-checked', 'true');
  });

  it('deletes a product after the owner confirms', async () => {
    vi.mocked(showConfirm).mockResolvedValue(true);
    renderMenu();
    await flush();

    fireEvent.click(screen.getByRole('button', { name: "Burger: o'chirish" }));
    await flush();

    expect(callsTo(calls, '/owner/products/8', 'DELETE')).toHaveLength(1);
    expect(screen.queryByText('Burger')).not.toBeInTheDocument();
  });

  it('does not delete without the confirmation', async () => {
    vi.mocked(showConfirm).mockResolvedValue(false);
    renderMenu();
    await flush();

    fireEvent.click(screen.getByRole('button', { name: "Burger: o'chirish" }));
    await flush();

    expect(callsTo(calls, '/owner/products/8', 'DELETE')).toHaveLength(0);
  });

  it('says so when the list cannot be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    ({ calls } = stubStaffApi(() => reply(502)));
    renderMenu();
    await flush();

    expect(screen.getByRole('alert')).toBeInTheDocument();
  });
});

function renderForm(path: string) {
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/admin/catalog/create" element={<OwnerProductForm />} />
        <Route path="/admin/catalog/edit/:id" element={<OwnerProductForm />} />
        <Route path="/admin/catalog" element={<p>Katalog sahifasi</p>} />
      </Routes>
      <MainButtonHost />
    </MemoryRouter>,
  );
}

describe('OwnerProductForm', () => {
  it('creates a product with a multipart POST: fields as strings, variants as JSON', async () => {
    renderForm('/admin/catalog/create');
    await flush();

    fireEvent.change(screen.getByLabelText(/Kategoriya/), { target: { value: '3' } });
    fireEvent.change(screen.getByPlaceholderText('Masalan: Achchiq lavash'), { target: { value: 'Plov' } });
    fireEvent.change(screen.getByPlaceholderText('0'), { target: { value: '45000' } });
    fireEvent.click(screen.getByRole('button', { name: /Mahsulotni qo'shish/ }));
    await flush();

    const [create] = callsTo(calls, '/owner/products', 'POST');
    expect(create).toBeDefined();
    const form = create!.body as FormData;
    expect(form.get('name')).toBe('Plov');
    expect(form.get('category_id')).toBe('3');
    expect(form.get('is_active')).toBe('1');
    expect(JSON.parse(form.get('variants') as string)).toEqual([{ id: null, name: '1-chi Porsiya', price: '45000' }]);
    expect(screen.getByText('Katalog sahifasi')).toBeInTheDocument();
  });

  it('opens a product for editing and saves it with a POST to its own address', async () => {
    renderForm('/admin/catalog/edit/7');
    await flush();

    expect(screen.getByPlaceholderText('Masalan: Achchiq lavash')).toHaveValue('Lavash');
    fireEvent.change(screen.getByPlaceholderText('Masalan: Achchiq lavash'), { target: { value: 'Katta lavash' } });
    fireEvent.click(screen.getByRole('button', { name: /O'zgarishlarni saqlash/ }));
    await flush();

    const [update] = callsTo(calls, '/owner/products/7', 'POST');
    expect(update).toBeDefined();
    expect((update!.body as FormData).get('name')).toBe('Katta lavash');
  });

  it('shows the server\'s message when saving is refused', async () => {
    renderForm('/admin/catalog/create');
    await flush();
    fireEvent.change(screen.getByLabelText(/Kategoriya/), { target: { value: '3' } });
    fireEvent.change(screen.getByPlaceholderText('Masalan: Achchiq lavash'), { target: { value: 'Plov' } });
    fireEvent.change(screen.getByPlaceholderText('0'), { target: { value: '45000' } });
    vi.spyOn(console, 'error').mockImplementation(() => {});
    saveAnswer = reply(422, { success: false, message: 'Rasm juda katta' });

    fireEvent.click(screen.getByRole('button', { name: /Mahsulotni qo'shish/ }));
    await flush();

    expect(showAlert).toHaveBeenCalledWith('Rasm juda katta');
    expect(screen.queryByText('Katalog sahifasi')).not.toBeInTheDocument();
  });
});
