import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, ok, refusal, renderPage } from '@/test/helpers';
import { category, categoryList, mockMarketingFetch } from './fixtures';
import { CategoriesTab } from './CategoriesTab';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const MANAGE = ['marketing.view', 'marketing.manage'];
const png = (name = 'icon.png', size = 1024, type = 'image/png') => new File([new Uint8Array(size)], name, { type });

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  const button = within(dialog).getByRole('button', { name: confirm });
  expect(button).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  expect(button).toBeEnabled();
  await user.click(button);
  return dialog;
}

describe('CategoriesTab', () => {
  it('lists the categories in the Mini App\'s order with where they sit and how full they are', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/categories': () => ok({ data: categoryList() }) });
    renderPage(<CategoriesTab />);

    const list = await screen.findByRole('list', { name: "Kategoriyalar ro'yxati" });
    const rows = within(list).getAllByRole('listitem');
    expect(rows.map((row) => within(row).getByRole('heading', { level: 3 }).textContent)).toEqual(['Taomlar', 'Burgerlar', 'Ichimliklar']);
    expect(within(rows[0]!).getByText('Taomlar: 14')).toBeInTheDocument();
    expect(within(rows[0]!).getByText('Ichki kategoriyalar: 1')).toBeInTheDocument();
    expect(within(rows[1]!).getByText('Taomlar ichida')).toBeInTheDocument();
    expect(within(rows[2]!).getByText('Yashirin')).toBeInTheDocument();
    // Only an empty category can be deleted (the other two have products or sub-categories)
    expect(within(rows[0]!).queryByRole('button', { name: /kategoriyasini o'chirish/ })).not.toBeInTheDocument();
    expect(within(rows[1]!).queryByRole('button', { name: /kategoriyasini o'chirish/ })).not.toBeInTheDocument();
    expect(within(rows[2]!).getByRole('button', { name: "Ichimliklar kategoriyasini o'chirish" })).toBeInTheDocument();
  });

  it('shows a member who only reads no buttons', async () => {
    mockMarketingFetch({ ...ME(['marketing.view']), 'GET /marketing/categories': () => ok({ data: categoryList() }) });
    renderPage(<CategoriesTab />);

    await screen.findByRole('list', { name: "Kategoriyalar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'Yangi kategoriya' })).not.toBeInTheDocument();
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /ko'chirish/ })).not.toBeInTheDocument();
  });

  it('orders with the arrows, can undo, and saves the whole list with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'PUT /marketing/categories/order': () => ok({ data: categoryList() }),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await screen.findByRole('list', { name: "Kategoriyalar ro'yxati" });
    expect(screen.getByRole('button', { name: "Yuqoriga ko'chirish: Taomlar" })).toBeDisabled();
    expect(screen.getByRole('button', { name: "Pastga ko'chirish: Ichimliklar" })).toBeDisabled();

    await user.click(screen.getByRole('button', { name: "Yuqoriga ko'chirish: Ichimliklar" }));
    expect(screen.getByText("Tartib o'zgartirildi, lekin hali saqlanmadi.")).toBeInTheDocument();
    const names = () => within(screen.getByRole('list', { name: "Kategoriyalar ro'yxati" })).getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent);
    expect(names()).toEqual(['Taomlar', 'Ichimliklar', 'Burgerlar']);

    await user.click(screen.getByRole('button', { name: 'Bekor qilish' }));
    expect(names()).toEqual(['Taomlar', 'Burgerlar', 'Ichimliklar']);
    expect(screen.queryByText("Tartib o'zgartirildi, lekin hali saqlanmadi.")).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: "Pastga ko'chirish: Taomlar" }));
    await user.click(screen.getByRole('button', { name: 'Tartibni saqlash' }));
    await reasonDialog(user, 'Kategoriyalar tartibini saqlash', 'Burgerlar birinchi', 'Saqlash');
    await waitFor(() => expect(callsTo('PUT', '/marketing/categories/order')).toHaveLength(1));
    expect(callsTo('PUT', '/marketing/categories/order')[0]?.body).toEqual({ ids: [2, 1, 3], reason: 'Burgerlar birinchi' });
    expect(await screen.findByText('Tartib saqlandi.')).toBeInTheDocument();
  });

  it('tells the person to reload when the list changed under them', async () => {
    mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'PUT /marketing/categories/order': () => refusal(409, "Ro'yxat eskirgan: sahifani yangilang va qayta urining.", { reason: 'order_stale' }, { code: 'order_stale' }),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await screen.findByRole('list', { name: "Kategoriyalar ro'yxati" });
    await user.click(screen.getByRole('button', { name: "Pastga ko'chirish: Taomlar" }));
    await user.click(screen.getByRole('button', { name: 'Tartibni saqlash' }));
    const dialog = await reasonDialog(user, 'Kategoriyalar tartibini saqlash', 'Tartib', 'Saqlash');
    expect(await within(dialog).findByText("Ro'yxat eskirgan: sahifani yangilang va qayta urining.")).toBeInTheDocument();
  });

  it('hides and shows a category with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'POST /marketing/categories/3': () => ok(category({ id: 3, is_active: true })),
      'POST /marketing/categories/1': () => ok(category({ is_active: false })),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await user.click(await screen.findByRole('switch', { name: 'Ichimliklar kategoriyasi ko\'rinadi' }));
    await reasonDialog(user, "Kategoriyani ko'rsatish", 'Mavsum boshlandi', "Ko'rsatish");
    await waitFor(() => expect(callsTo('POST', '/marketing/categories/3')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/categories/3')[0]?.body).toEqual({ is_active: true, reason: 'Mavsum boshlandi' });
    expect(await screen.findByText("Kategoriya ko'rsatildi.")).toBeInTheDocument();

    await user.click(screen.getByRole('switch', { name: "Taomlar kategoriyasi ko'rinadi" }));
    await reasonDialog(user, 'Kategoriyani yashirish', 'Tugadi', 'Yashirish');
    await waitFor(() => expect(callsTo('POST', '/marketing/categories/1')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/categories/1')[0]?.body).toEqual({ is_active: false, reason: 'Tugadi' });
  });

  it('adds a category with an icon as a form, and without one as JSON', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'POST /marketing/categories': () => ok(category({ id: 9, name: 'Desertlar' })),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await user.click(await screen.findByRole('button', { name: 'Yangi kategoriya' }));
    let dialog = await screen.findByRole('dialog', { name: 'Yangi kategoriya' });
    // Only top-level categories can be a parent
    const parent = within(dialog).getByLabelText('Asosiy kategoriya');
    expect(within(parent).getAllByRole('option').map((option) => option.textContent)).toEqual(['Asosiy (eng yuqori daraja)', 'Taomlar', 'Ichimliklar']);
    await user.type(within(dialog).getByLabelText('Nomi'), 'Desertlar');
    await user.upload(within(dialog).getByLabelText('Ikonka'), png());
    await user.selectOptions(parent, 'Taomlar');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Yangi bo\'lim');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('POST', '/marketing/categories')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/categories')[0]?.body).toEqual({ name: 'Desertlar', parent_id: '1', is_active: '1', image: expect.objectContaining({ type: 'image/png' }), reason: "Yangi bo'lim" });
    expect(await screen.findByText("Kategoriya qo'shildi.")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Yangi kategoriya' }));
    dialog = await screen.findByRole('dialog', { name: 'Yangi kategoriya' });
    await user.type(within(dialog).getByLabelText('Nomi'), 'Salatlar');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Yana bir bo\'lim');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    await waitFor(() => expect(callsTo('POST', '/marketing/categories')).toHaveLength(2));
    expect(callsTo('POST', '/marketing/categories')[1]?.body).toEqual({ name: 'Salatlar', parent_id: null, is_active: true, reason: "Yana bir bo'lim" });
  });

  it('does not let a category with sub-categories move inside another, and sends only what changed', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'POST /marketing/categories/1': () => ok(category({ name: 'Ovqatlar' })),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await user.click(await screen.findByRole('button', { name: 'Taomlar kategoriyasini tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Kategoriyani tahrirlash' });
    expect(within(dialog).getByLabelText('Asosiy kategoriya')).toBeDisabled();
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    const name = within(dialog).getByLabelText('Nomi');
    await user.clear(name);
    await user.type(name, 'Ovqatlar');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Nomi yangilandi');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('POST', '/marketing/categories/1')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/categories/1')[0]?.body).toEqual({ name: 'Ovqatlar', reason: 'Nomi yangilandi' });
  });

  it('deletes an empty category with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/categories': () => ok({ data: categoryList() }),
      'DELETE /marketing/categories/3': () => ok({ id: 3 }),
    });
    const user = userEvent.setup();
    renderPage(<CategoriesTab />);

    await user.click(await screen.findByRole('button', { name: "Ichimliklar kategoriyasini o'chirish" }));
    await reasonDialog(user, "Kategoriyani o'chirish", 'Keraksiz', "O'chirish");
    await waitFor(() => expect(callsTo('DELETE', '/marketing/categories/3')).toHaveLength(1));
    expect(callsTo('DELETE', '/marketing/categories/3')[0]?.body).toEqual({ reason: 'Keraksiz' });
    expect(await screen.findByText("Kategoriya o'chirildi.")).toBeInTheDocument();
  });
});
