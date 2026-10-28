import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, ok, refusal, renderPage } from '@/test/helpers';
import { code, codeCard, codePage, mockMarketingFetch } from './fixtures';
import { CodesTab } from './CodesTab';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const MANAGE = ['marketing.view', 'marketing.manage'];
// The amounts hold non-breaking spaces
const plain = (text: string | null) => (text ?? '').replace(/ /g, ' ');

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  const button = within(dialog).getByRole('button', { name: confirm });
  expect(button).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  expect(button).toBeEnabled();
  await user.click(button);
  return dialog;
}

describe('CodesTab', () => {
  it('lists the codes with what they give, their limits and how they are used', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promo-codes': () => ok(codePage()) });
    renderPage(<CodesTab />);

    const list = await screen.findByRole('list', { name: "Promo-kodlar ro'yxati" });
    const used = within(list).getAllByRole('listitem')[0]!;
    expect(within(used).getByRole('heading', { name: 'YANGI10' })).toBeInTheDocument();
    expect(plain(used.textContent)).toContain("Chegirma: 10 % (eng ko'pi 8 000 so'm)");
    expect(within(used).getByText('Faol')).toBeInTheDocument();
    const stats = within(used).getByLabelText('YANGI10 ishlatilishi').textContent;
    expect(plain(stats)).toContain('12 / 100');
    expect(plain(stats)).toContain("84 000 so'm");
    expect(plain(stats)).toContain("1 250 000 so'm");
    const fixed = within(list).getAllByRole('listitem')[1]!;
    expect(plain(fixed.textContent)).toContain("Chegirma: 5 000 so'm");
    // A code that was used cannot be deleted; an unused one can
    expect(within(used).queryByRole('button', { name: "YANGI10 promo-kodini o'chirish" })).not.toBeInTheDocument();
    expect(within(fixed).getByRole('button', { name: "BEPUL5000 promo-kodini o'chirish" })).toBeInTheDocument();
  });

  it('sends the search and the status filter', async () => {
    const { callsTo } = mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promo-codes': () => ok(codePage()) });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await screen.findByRole('list', { name: "Promo-kodlar ro'yxati" });
    await user.type(screen.getByLabelText('Promo-kod qidirish'), 'yangi');
    await waitFor(() => expect(callsTo('GET', '/marketing/promo-codes').at(-1)?.query.get('q')).toBe('yangi'));
    await user.click(screen.getByRole('button', { name: 'Limit tugagan' }));
    await waitFor(() => expect(callsTo('GET', '/marketing/promo-codes').at(-1)?.query.get('status')).toBe('exhausted'));
    expect(callsTo('GET', '/marketing/promo-codes').at(-1)?.query.get('page')).toBe('1');
  });

  it('goes to the next page', async () => {
    const { callsTo } = mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promo-codes': () => ok(codePage([code()], { last_page: 3, total: 70 })) });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'Keyingi' }));
    await waitFor(() => expect(callsTo('GET', '/marketing/promo-codes').at(-1)?.query.get('page')).toBe('2'));
  });

  it('shows a member who only reads no buttons', async () => {
    mockMarketingFetch({ ...ME(['marketing.view']), 'GET /marketing/promo-codes': () => ok(codePage()) });
    renderPage(<CodesTab />);

    await screen.findByRole('list', { name: "Promo-kodlar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'Yangi promo-kod' })).not.toBeInTheDocument();
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /tahrirlash/ })).not.toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /haqida batafsil/ })).toHaveLength(2);
  });

  it('opens a code\'s card with its last orders, each linking to Buyurtmalar', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promo-codes': () => ok(codePage()), 'GET /marketing/promo-codes/1': () => ok(codeCard()) });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'YANGI10 promo-kodi haqida batafsil' }));
    const dialog = await screen.findByRole('dialog', { name: 'Promo-kod YANGI10' });
    const orders = await within(dialog).findByRole('list', { name: 'Oxirgi buyurtmalar' });
    expect(within(orders).getByRole('link')).toHaveAttribute('href', '/orders/ab12cd34-1111-4222-8333-444455556666');
    expect(plain(orders.textContent)).toContain("chegirma 8 000 so'm, jami 92 000 so'm");
    expect(within(dialog).getByText(/chegirmasi endi o'zgarmaydi/)).toBeInTheDocument();
  });

  it('adds a code with the order flow\'s rules and sends it as JSON with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promo-codes': () => ok(codePage([])),
      'POST /marketing/promo-codes': () => ok(codeCard()),
    });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'Yangi promo-kod' }));
    const dialog = await screen.findByRole('dialog', { name: 'Yangi promo-kod' });
    const submit = within(dialog).getByRole('button', { name: 'Saqlash' });
    await user.type(within(dialog).getByLabelText('Promo-kod'), 'yangi10');
    await user.type(within(dialog).getByLabelText('Chegirma (%)'), '150');
    expect(within(dialog).getByText("Foiz 0 dan katta va 100 dan oshmasligi kerak.")).toBeInTheDocument();
    await user.clear(within(dialog).getByLabelText('Chegirma (%)'));
    await user.type(within(dialog).getByLabelText('Chegirma (%)'), '10');
    await user.clear(within(dialog).getByLabelText("Minimal buyurtma summasi (so'm)"));
    await user.type(within(dialog).getByLabelText("Minimal buyurtma summasi (so'm)"), '50 000');
    await user.type(within(dialog).getByLabelText("Eng ko'p chegirma (so'm)"), '8000');
    await user.type(within(dialog).getByLabelText('Umumiy limit'), '100');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Bahor aksiyasi');
    expect(submit).toBeEnabled();
    await user.click(submit);

    await waitFor(() => expect(callsTo('POST', '/marketing/promo-codes')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/promo-codes')[0]?.body).toEqual({
      code: 'YANGI10',
      type: 'percentage',
      value: '10',
      min_order_amount: '50000',
      max_discount_amount: '8000',
      usage_limit: 100,
      per_user_limit: 1,
      store_id: null,
      is_active: true,
      expires_at: null,
      reason: 'Bahor aksiyasi',
    });
    expect(await screen.findByText('Promo-kod yaratildi.')).toBeInTheDocument();
  });

  it('asks a whole amount of so\'m for a fixed code and hides the cap', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promo-codes': () => ok(codePage([])) });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'Yangi promo-kod' }));
    const dialog = await screen.findByRole('dialog', { name: 'Yangi promo-kod' });
    expect(within(dialog).getByLabelText("Eng ko'p chegirma (so'm)")).toBeInTheDocument();
    await user.selectOptions(within(dialog).getByLabelText('Turi'), 'Belgilangan summa');
    expect(within(dialog).queryByLabelText("Eng ko'p chegirma (so'm)")).not.toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Promo-kod'), 'FIX5000');
    await user.type(within(dialog).getByLabelText("Chegirma (so'm)"), '5000.50');
    expect(within(dialog).getByText("Summa butun so'm va 0 dan katta bo'lishi kerak.")).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sabab'), 'Sinov');
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
  });

  it('keeps the discount of a used code locked but lets the limit change, sending only that', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promo-codes': () => ok(codePage()),
      'GET /marketing/lookup': () => ok({ stores: [], products: [] }),
      'PATCH /marketing/promo-codes/1': () => ok(codeCard({ usage_limit: 500 })),
    });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'YANGI10 promo-kodini tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'YANGI10 ni tahrirlash' });
    expect(within(dialog).getByText(/Bu promo-koddan buyurtma berilgan, shuning uchun/)).toBeInTheDocument();
    expect(within(dialog).getByLabelText('Promo-kod')).toBeDisabled();
    expect(within(dialog).getByLabelText('Turi')).toBeDisabled();
    expect(within(dialog).getByLabelText('Chegirma (%)')).toBeDisabled();
    expect(within(dialog).getByLabelText("Eng ko'p chegirma (so'm)")).toBeDisabled();
    expect(within(dialog).getByLabelText('Umumiy limit')).toBeEnabled();

    const limit = within(dialog).getByLabelText('Umumiy limit');
    await user.clear(limit);
    await user.type(limit, '500');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Limitni oshirdik');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('PATCH', '/marketing/promo-codes/1')).toHaveLength(1));
    expect(callsTo('PATCH', '/marketing/promo-codes/1')[0]?.body).toEqual({ usage_limit: 500, reason: 'Limitni oshirdik' });
  });

  it('says what the backend refused', async () => {
    mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promo-codes': () => ok(codePage([code({ locked: false, stats: { uses: 0, unique_customers: 0, discount_total: '0.00', gmv: '0.00' } })])),
      'GET /marketing/lookup': () => ok({ stores: [], products: [] }),
      'PATCH /marketing/promo-codes/1': () => refusal(422, "Bu promo-koddan buyurtma berilgan, shuning uchun uning chegirmasini o'zgartirib bo'lmaydi.", { reason: 'promo_code_used', fields: ['value'] }, { code: 'promo_code_used' }),
    });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: 'YANGI10 promo-kodini tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'YANGI10 ni tahrirlash' });
    const value = within(dialog).getByLabelText('Chegirma (%)');
    await user.clear(value);
    await user.type(value, '20');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Oshirdik');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText(/shuning uchun uning chegirmasini o'zgartirib bo'lmaydi/)).toBeInTheDocument();
  });

  it('switches a code off with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promo-codes': () => ok(codePage()),
      'POST /marketing/promo-codes/1/deactivate': () => ok(codeCard({ is_active: false, status: 'inactive' })),
    });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('switch', { name: 'YANGI10 promo-kodi yoqilgan' }));
    await reasonDialog(user, "Promo-kodni o'chirish", "Suiiste'mol", "O'chirish");
    await waitFor(() => expect(callsTo('POST', '/marketing/promo-codes/1/deactivate')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/promo-codes/1/deactivate')[0]?.body).toEqual({ reason: "Suiiste'mol" });
    expect(await screen.findByText("Promo-kod o'chirib qo'yildi.")).toBeInTheDocument();
  });

  it('deletes an unused code with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promo-codes': () => ok(codePage()),
      'DELETE /marketing/promo-codes/2': () => ok({ id: 2 }),
    });
    const user = userEvent.setup();
    renderPage(<CodesTab />);

    await user.click(await screen.findByRole('button', { name: "BEPUL5000 promo-kodini o'chirish" }));
    await reasonDialog(user, "Promo-kodni o'chirib tashlash", 'Keraksiz', "O'chirish");
    await waitFor(() => expect(callsTo('DELETE', '/marketing/promo-codes/2')).toHaveLength(1));
    expect(callsTo('DELETE', '/marketing/promo-codes/2')[0]?.body).toEqual({ reason: 'Keraksiz' });
    expect(await screen.findByText("Promo-kod o'chirib tashlandi.")).toBeInTheDocument();
  });
});
