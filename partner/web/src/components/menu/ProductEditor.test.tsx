import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ProductEditor } from './ProductEditor';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { branch, category, group, ok, permissionsAnswer, product, variant } from './menuFixtures';

const router = vi.hoisted(() => ({ replace: vi.fn(), refresh: vi.fn(), push: vi.fn() }));
vi.mock('next/navigation', () => ({ useRouter: () => router }));

beforeEach(() => setSession({ accessToken: 'token', expiresAt: Date.now() + 600_000, user: null }));
afterEach(() => {
  setSession(null);
  router.replace.mockReset();
});

const base = {
  'GET /api/v1/me/permissions': () => permissionsAnswer(),
  'GET /api/v1/owner/categories': () => json({ success: true, data: [category(), category({ id: 5, name: 'Ichimliklar' })] }),
};

describe('a new product', () => {
  it('checks the form lightly and sends nothing while it is incomplete', async () => {
    const { calls } = mockFetch({ ...base });
    renderPage(<ProductEditor id="new" />);
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: 'Yaratish' }));

    expect(await screen.findAllByText("Maydonni to'ldiring.")).not.toHaveLength(0);
    expect(screen.getByText('Maydonlarni tekshiring.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'POST')).toBe(false);
    expect(screen.getByText('Modifikatorlar, kanal narxlari va filial sozlamalari taom saqlangach paydo bo\'ladi.')).toBeInTheDocument();
  });

  it('sends a dish sold by weight and shows the server\'s step/minimum message under the field, then saves', async () => {
    const message = "Eng kam miqdor (0,3) qadamga (0,5) karrali bo'lishi kerak, aks holda bu taomni buyurtma qilib bo'lmaydi. Masalan: 0,5.";
    let attempt = 0;
    const { calls } = mockFetch({
      ...base,
      'POST /api/v1/owner/products': () => {
        attempt += 1;
        return attempt === 1
          ? json({ message, errors: { 'variants.0.min_quantity': [message] } }, 422)
          : json({ success: true, message: 'Product created successfully', data: product({ id: 9, name: 'Qazi' }) });
      },
    });
    renderPage(<ProductEditor id="new" />);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Nomi'), 'Qazi');
    await user.selectOptions(screen.getByLabelText('Kategoriya'), 'Issiq taomlar');
    await user.clear(screen.getByLabelText('Variant nomi'));
    await user.type(screen.getByLabelText('Variant nomi'), 'Kilogramm');
    await user.selectOptions(screen.getByLabelText("O'lchov birligi"), 'kg');
    await user.type(screen.getByLabelText('1 kg narxi (so\'m)'), '120000');
    await user.type(screen.getByLabelText('Qadam (kg)'), '0,5');
    await user.type(screen.getByLabelText('Eng kam miqdor (kg)'), '0,3');
    await user.click(screen.getByRole('button', { name: 'Yaratish' }));

    // the server's own Uzbek message, next to the field
    const field = await screen.findByLabelText('Eng kam miqdor (kg)');
    await waitFor(() => expect(field).toHaveAttribute('aria-invalid', 'true'));
    expect(screen.getAllByText(message).length).toBeGreaterThan(0);
    expect(router.replace).not.toHaveBeenCalled();

    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('name')).toBe('Qazi');
    expect(form.get('category_id')).toBe('2');
    expect(form.get('is_active')).toBe('1');
    expect(JSON.parse(String(form.get('variants')))).toEqual([{ name: 'Kilogramm', price: 120000, unit: 'kg', quantity_step: 0.5, min_quantity: 0.3 }]);

    await user.clear(screen.getByLabelText('Eng kam miqdor (kg)'));
    await user.type(screen.getByLabelText('Eng kam miqdor (kg)'), '0,5');
    await user.click(screen.getByRole('button', { name: 'Yaratish' }));
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith('/menu/9'));
  });

  it("shows the server's Uzbek field messages, and a general Uzbek one for a message that came in English", async () => {
    mockFetch({
      ...base,
      'POST /api/v1/owner/products': () =>
        json({ message: 'Nomi majburiy. (va yana 1 ta xato)', errors: { name: ['Nomi majburiy.'], image: ['The image field must be an image.'] } }, 422),
    });
    renderPage(<ProductEditor id="new" />);
    const user = userEvent.setup();
    await user.type(await screen.findByLabelText('Nomi'), 'Osh');
    await user.selectOptions(screen.getByLabelText('Kategoriya'), 'Issiq taomlar');
    await user.type(screen.getByLabelText('Narxi (so\'m)'), '25000');
    await user.click(screen.getByRole('button', { name: 'Yaratish' }));

    expect(await screen.findByText('Nomi majburiy.')).toBeInTheDocument();
    expect(screen.getByText("Qiymat noto'g'ri.")).toBeInTheDocument();
    expect(screen.queryByText(/The image field/)).not.toBeInTheDocument();
  });

  it('previews a picked picture and refuses one that is too big or not a picture', async () => {
    const createObjectURL = vi.fn(() => 'blob:preview');
    const revokeObjectURL = vi.fn();
    Object.assign(URL, { createObjectURL, revokeObjectURL });
    mockFetch({ ...base });
    renderPage(<ProductEditor id="new" />);
    const user = userEvent.setup({ applyAccept: false });
    await screen.findByLabelText('Nomi');
    const input = screen.getByLabelText('Rasm tanlash', { selector: 'input' });

    await user.upload(input, new File(['x'], 'osh.png', { type: 'image/png' }));
    expect(await screen.findByAltText('Tanlangan rasm')).toHaveAttribute('src', 'blob:preview');
    expect(screen.getByText('osh.png')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Bekor qilish' }));
    expect(screen.queryByAltText('Tanlangan rasm')).not.toBeInTheDocument();
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:preview');

    const big = new File(['x'], 'big.png', { type: 'image/png' });
    Object.defineProperty(big, 'size', { value: 6 * 1024 * 1024 });
    await user.upload(input, big);
    expect(await screen.findByText('Rasm 5 MB dan katta bo\'lmasligi kerak.')).toBeInTheDocument();
    await user.upload(input, new File(['x'], 'osh.pdf', { type: 'application/pdf' }));
    expect(await screen.findByText('Rasm tanlang (JPG, PNG yoki WebP, 5 MB gacha).')).toBeInTheDocument();
  });

  it('sends the picked picture with the product', async () => {
    Object.assign(URL, { createObjectURL: () => 'blob:preview', revokeObjectURL: () => {} });
    const { calls } = mockFetch({ ...base, 'POST /api/v1/owner/products': () => json({ success: true, message: 'ok', data: product({ id: 12 }) }) });
    renderPage(<ProductEditor id="new" />);
    const user = userEvent.setup();
    await user.type(await screen.findByLabelText('Nomi'), 'Osh');
    await user.selectOptions(screen.getByLabelText('Kategoriya'), 'Issiq taomlar');
    await user.type(screen.getByLabelText('Narxi (so\'m)'), '25000');
    await user.upload(screen.getByLabelText('Rasm tanlash', { selector: 'input' }), new File(['pixels'], 'osh.png', { type: 'image/png' }));
    await user.click(screen.getByRole('button', { name: 'Yaratish' }));
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith('/menu/12'));
    const file = (await calls.find((call) => call.method === 'POST')!.formData()).get('image') as File;
    expect(await file.text()).toBe('pixels');
  });
});

describe('an existing product', () => {
  const osh = product({ variants: [variant({ stock: 18 })] });
  const loaded = {
    ...base,
    'GET /api/v1/owner/products': () => json({ success: true, data: [osh] }),
    'GET /api/v1/owner/modifier-groups': () => ok([group(), group({ id: 4, name: "Qo'shimchalar", product_ids: [] })]),
    'GET /api/v1/owner/products/7/channel-prices': () =>
      ok({
        product_id: 7,
        channels: ['telegram', 'mobile', 'pos_hall'],
        prices: [
          { channel: 'mobile', product_variant_id: 11, price: '37000.00', is_hidden: false },
          { channel: 'pos_hall', product_variant_id: 11, price: '33000.00', is_hidden: false },
        ],
      }),
    'GET /api/v1/owner/branches': () => ok([branch(), branch({ id: 2, name: 'Yunusobod' })]),
    'GET /api/v1/owner/branch-variants': (request: Request) =>
      ok([{ product_id: 7, name: 'Osh', image_url: null, variants: [{ variant_id: 11, variant_uuid: 'v', name: '1 porsiya', price: '35000.00', is_active: new URL(request.url).searchParams.get('branch_id') === '1', hidden_reason: null, sort_order: 0 }] }]),
  };

  it('fills the form from the product and sends the variant ids back when saving', async () => {
    const { calls } = mockFetch({ ...loaded, 'POST /api/v1/owner/products/7': () => json({ success: true, message: 'ok', data: { ...osh, name: 'Osh (katta)' } }) });
    renderPage(<ProductEditor id="7" />);
    const user = userEvent.setup();

    const name = await screen.findByLabelText('Nomi');
    expect(name).toHaveValue('Osh');
    expect(screen.getByLabelText('Narxi (so\'m)')).toHaveValue('35000');
    expect(screen.getByLabelText('Tartib raqami')).toHaveValue('3');
    expect(screen.getByRole('switch', { name: "Qozon bo'yicha sotiladi" })).toHaveAttribute('aria-checked', 'true');
    // stock is shown, not editable
    expect(screen.getByText('18')).toBeInTheDocument();
    // a batch-sold dish asks how much of a batch one unit takes
    expect(screen.getByLabelText('Qozondan sarfi')).toHaveValue('');

    await user.clear(name);
    await user.type(name, 'Osh (katta)');
    await user.click(screen.getByRole('button', { name: 'Saqlash' }));

    expect(await screen.findByText('Saqlandi.')).toBeInTheDocument();
    const form = await calls.find((call) => call.method === 'POST')!.formData();
    expect(form.get('name')).toBe('Osh (katta)');
    expect(form.get('sort_order')).toBe('3');
    expect(form.get('batch_tracked')).toBe('1');
    expect(JSON.parse(String(form.get('variants')))).toEqual([{ id: 11, name: '1 porsiya', price: 35000, unit: 'piece', quantity_step: null, min_quantity: null }]);
  });

  it('attaches modifier groups to the product', async () => {
    const { calls } = mockFetch({ ...loaded, 'PUT /api/v1/owner/products/7/modifier-groups': () => ok({ product_id: 7, modifier_groups: [] }) });
    renderPage(<ProductEditor id="7" />);
    const user = userEvent.setup();
    const first = await screen.findByRole('switch', { name: 'Taomda taklif qilinadi: Sous tanlang' });
    const second = screen.getByRole('switch', { name: "Taomda taklif qilinadi: Qo'shimchalar" });
    expect(first).toHaveAttribute('aria-checked', 'true');
    expect(second).toHaveAttribute('aria-checked', 'false');

    await user.click(second);
    await user.click(screen.getByRole('button', { name: 'Guruhlarni saqlash' }));
    await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
    expect(await calls.find((call) => call.method === 'PUT')!.json()).toEqual({
      groups: [
        { modifier_group_id: 3, sort_order: 0 },
        { modifier_group_id: 4, sort_order: 1 },
      ],
    });
    // The groups are fetched again after the save (the switches remount with what the server stored): the note stays
    await waitFor(() => expect(calls.filter((call) => call.method === 'GET' && call.url.endsWith('/owner/modifier-groups')).length).toBeGreaterThan(1));
    expect(await screen.findByText('Saqlandi.')).toBeInTheDocument();
  });

  it('sets channel prices and visibility, and sends the rows of other channels back unchanged', async () => {
    const { calls } = mockFetch({ ...loaded, 'PUT /api/v1/owner/products/7/channel-prices': () => ok({ product_id: 7, prices: [] }) });
    renderPage(<ProductEditor id="7" />);
    const user = userEvent.setup();

    const mobile = await screen.findByRole('textbox', { name: 'Kanal narxi: 1 porsiya (Mobil ilova)' });
    expect(mobile).toHaveValue('37000');
    await user.type(screen.getByRole('textbox', { name: 'Kanal narxi: 1 porsiya (Telegram)' }), '36000');
    await user.click(screen.getByRole('switch', { name: 'Yashirilgan: 1 porsiya (Mobil ilova)' }));
    await user.click(screen.getByRole('switch', { name: 'Taomni butunlay yashirish (Telegram)' }));
    await user.click(screen.getByRole('button', { name: 'Kanal narxlarini saqlash' }));

    await waitFor(() => expect(calls.some((call) => call.method === 'PUT' && call.url.includes('channel-prices'))).toBe(true));
    const body = (await calls.find((call) => call.method === 'PUT')!.json()) as { prices: unknown[] };
    expect(body.prices).toEqual([
      { channel: 'pos_hall', product_variant_id: 11, price: 33000, is_hidden: false },
      { channel: 'telegram', product_variant_id: null, is_hidden: true },
      { channel: 'telegram', product_variant_id: 11, price: 36000, is_hidden: false },
      { channel: 'mobile', product_variant_id: 11, price: 37000, is_hidden: true },
    ]);
  });

  it('refuses a channel price that is not a number before asking the server', async () => {
    const { calls } = mockFetch({ ...loaded });
    renderPage(<ProductEditor id="7" />);
    const user = userEvent.setup();
    await user.type(await screen.findByRole('textbox', { name: 'Kanal narxi: 1 porsiya (Telegram)' }), 'ko\'p');
    await user.click(screen.getByRole('button', { name: 'Kanal narxlarini saqlash' }));
    expect(await screen.findByText('Son kiriting.')).toBeInTheDocument();
    expect(calls.some((call) => call.method === 'PUT')).toBe(false);
  });

  it('switches a variant off in a branch', async () => {
    const { calls } = mockFetch({ ...loaded, 'POST /api/v1/owner/branch-variants': () => ok({ id: 1 }) });
    renderPage(<ProductEditor id="7" />);
    const user = userEvent.setup();
    const section = await screen.findByRole('region', { name: 'Filiallar' });
    const toggle = await within(section).findByRole('switch', { name: "1 porsiya: filialda sotiladi" });
    expect(toggle).toHaveAttribute('aria-checked', 'true');
    await user.click(toggle);
    await waitFor(() => expect(calls.some((call) => call.method === 'POST')).toBe(true));
    expect(await calls.find((call) => call.method === 'POST')!.json()).toEqual({ branch_id: 1, product_id: 7, product_variant_id: 11, is_active: false });
  });

  it('says so when the product does not exist', async () => {
    mockFetch({ ...loaded });
    renderPage(<ProductEditor id="999" />);
    expect(await screen.findByText('Taom topilmadi')).toBeInTheDocument();
  });

  it('is read-only without menu.manage', async () => {
    mockFetch({ ...loaded, 'GET /api/v1/me/permissions': () => permissionsAnswer(['menu.view']) });
    renderPage(<ProductEditor id="7" />);
    expect(await screen.findByText(/faqat ko'rish mumkin/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Saqlash' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('Nomi')).toBeDisabled();
  });
});

