import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, ok, refusal, renderPage } from '@/test/helpers';
import { banner, bannerList, mockMarketingFetch } from './fixtures';
import { BannersTab } from './BannersTab';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const MANAGE = ['marketing.view', 'marketing.manage'];
const png = (name = 'banner.png', size = 2048, type = 'image/png') => new File([new Uint8Array(size)], name, { type });

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  const button = within(dialog).getByRole('button', { name: confirm });
  expect(button).toBeDisabled();
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  expect(button).toBeEnabled();
  await user.click(button);
  return dialog;
}

describe('BannersTab', () => {
  it('lists the banners with their state, target and schedule, and sends the status filter', async () => {
    const { callsTo } = mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promos': () => ok(bannerList()) });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    const list = await screen.findByRole('list', { name: "Bannerlar ro'yxati" });
    const first = within(list).getAllByRole('listitem')[0]!;
    expect(within(first).getByText('Dekabr aksiyasi')).toBeInTheDocument();
    expect(within(first).getByText("Ko'rinmoqda")).toBeInTheDocument();
    expect(within(first).getByText('Ovqatxonani ochadi: Lazzat Oshxona')).toBeInTheDocument();
    const second = within(list).getAllByRole('listitem')[1]!;
    expect(within(second).getByText('Rejalashtirilgan')).toBeInTheDocument();
    expect(within(second).getByText(/dan cheklovsiz gacha/)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Tugagan' }));
    await waitFor(() => expect(callsTo('GET', '/marketing/promos').at(-1)?.query.get('status')).toBe('ended'));
  });

  it('shows no buttons to a member who only reads', async () => {
    mockMarketingFetch({ ...ME(['marketing.view']), 'GET /marketing/promos': () => ok(bannerList()) });
    renderPage(<BannersTab />);

    await screen.findByRole('list', { name: "Bannerlar ro'yxati" });
    expect(screen.queryByRole('button', { name: 'Yangi banner' })).not.toBeInTheDocument();
    expect(screen.queryByRole('switch')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /tahrirlash/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /o'chirish/ })).not.toBeInTheDocument();
    // …but may look at what the Mini App shows
    expect(screen.getByRole('button', { name: "Mini App'da ko'rinishi" })).toBeInTheDocument();
  });

  it('says calmly that there are no banners yet', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promos': () => ok(bannerList([])) });
    renderPage(<BannersTab />);
    expect(await screen.findByRole('heading', { name: "Hali banner yo'q" })).toBeInTheDocument();
  });

  it('shows what the Mini App lists right now, and says so when nothing shows', async () => {
    let live = [{ id: 1, title: 'Dekabr aksiyasi', image_url: null, image_preview: '/marketing/promos/1/image?v=1', store_uuid: null, product_id: null }];
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promos': () => ok(bannerList()), 'GET /marketing/promos/preview': () => ok({ data: live }) });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('button', { name: "Mini App'da ko'rinishi" }));
    const strip = await screen.findByRole('list', { name: 'Mini App bannerlari' });
    expect(within(strip).getByText('Dekabr aksiyasi')).toBeInTheDocument();

    live = [];
    await user.click(screen.getByRole('button', { name: "Mini App'da ko'rinishi" }));
    await user.click(screen.getByRole('button', { name: "Mini App'da ko'rinishi" }));
    expect(await screen.findByText("Hozir bosh sahifada banner ko'rinmayapti.")).toBeInTheDocument();
  });

  it('switches a banner off with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promos': () => ok(bannerList()),
      'POST /marketing/promos/1': () => ok(banner({ is_active: false, status: 'inactive' })),
    });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('switch', { name: 'Dekabr aksiyasi banneri yoqilgan' }));
    await reasonDialog(user, "Bannerni o'chirish", 'Mavsum tugadi', "O'chirish");
    await waitFor(() => expect(callsTo('POST', '/marketing/promos/1')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/promos/1')[0]?.body).toEqual({ is_active: false, reason: 'Mavsum tugadi' });
    expect(await screen.findByText("Banner o'chirildi (yashirildi).")).toBeInTheDocument();
  });

  it('orders with the arrows and saves the whole row with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promos': () => ok(bannerList()),
      'PUT /marketing/promos/order': () => ok(bannerList([banner({ id: 2, title: 'Yangi yil', sort_order: 1 }), banner()])),
    });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    const list = await screen.findByRole('list', { name: "Bannerlar ro'yxati" });
    expect(within(list).getByRole('button', { name: "Yuqoriga ko'chirish: Dekabr aksiyasi" })).toBeDisabled();
    await user.click(within(list).getByRole('button', { name: "Pastga ko'chirish: Dekabr aksiyasi" }));

    expect(screen.getByText("Tartib o'zgartirildi, lekin hali saqlanmadi.")).toBeInTheDocument();
    expect(within(screen.getByRole('list', { name: "Bannerlar ro'yxati" })).getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)).toEqual(['Yangi yil', 'Dekabr aksiyasi']);

    await user.click(screen.getByRole('button', { name: 'Tartibni saqlash' }));
    await reasonDialog(user, 'Bannerlar tartibini saqlash', 'Yangi yil birinchi', 'Saqlash');
    await waitFor(() => expect(callsTo('PUT', '/marketing/promos/order')).toHaveLength(1));
    expect(callsTo('PUT', '/marketing/promos/order')[0]?.body).toEqual({ ids: [2, 1], reason: 'Yangi yil birinchi' });
    expect(await screen.findByText('Tartib saqlandi.')).toBeInTheDocument();
  });

  it('does not offer the arrows while a status filter hides part of the row', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promos': () => ok(bannerList()) });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await screen.findByRole('list', { name: "Bannerlar ro'yxati" });
    await user.click(screen.getByRole('button', { name: 'Tugagan' }));
    await waitFor(() => expect(screen.queryByRole('button', { name: /ko'chirish/ })).not.toBeInTheDocument());
  });

  it('adds a banner: picture, title, store, schedule in Tashkent time and a reason, sent as a form', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promos': () => ok(bannerList([])),
      'GET /marketing/lookup': () => ok({ stores: [{ id: 12, name: 'Lazzat Oshxona' }], products: [] }),
      'POST /marketing/promos': () => ok(banner({ id: 5, title: 'Yangi banner' })),
    });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('button', { name: 'Yangi banner' }));
    const dialog = await screen.findByRole('dialog', { name: 'Yangi banner' });
    const submit = within(dialog).getByRole('button', { name: 'Saqlash' });
    await user.type(within(dialog).getByLabelText('Sarlavha'), 'Yangi banner');
    expect(submit).toBeDisabled();
    await user.upload(within(dialog).getByLabelText('Banner rasmi'), png());
    await user.click(await within(dialog).findByRole('button', { name: 'Lazzat Oshxona' }));
    expect(within(dialog).getByLabelText('Taom (ixtiyoriy)')).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Boshlanish vaqti'), '2026-12-01T09:00');
    await user.type(within(dialog).getByLabelText('Tugash vaqti'), '2026-12-10T21:00');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Dekabr aksiyasi');
    await user.click(submit);

    await waitFor(() => expect(callsTo('POST', '/marketing/promos')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/promos')[0]?.body).toEqual({
      title: 'Yangi banner',
      image: expect.objectContaining({ size: 2048, type: 'image/png' }),
      store_id: '12',
      product_id: '',
      is_active: '1',
      starts_at: '2026-12-01T09:00:00+05:00',
      ends_at: '2026-12-10T21:00:00+05:00',
      reason: 'Dekabr aksiyasi',
    });
    expect(await screen.findByText("Banner qo'shildi.")).toBeInTheDocument();
  });

  it('refuses a picture that is too big or not an image before anything is sent', async () => {
    mockMarketingFetch({ ...ME(MANAGE), 'GET /marketing/promos': () => ok(bannerList([])), 'GET /marketing/lookup': () => ok({ stores: [], products: [] }) });
    const user = userEvent.setup({ applyAccept: false });
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('button', { name: 'Yangi banner' }));
    const dialog = await screen.findByRole('dialog', { name: 'Yangi banner' });
    const input = within(dialog).getByLabelText('Banner rasmi');
    await user.upload(input, png('big.png', 5 * 1024 * 1024));
    expect(await within(dialog).findByText("Rasm 4 MB dan katta bo'lmasligi kerak.")).toBeInTheDocument();
    await user.upload(input, png('doc.svg', 100, 'image/svg+xml'));
    expect(await within(dialog).findByText("Rasm JPG, PNG yoki WebP bo'lishi kerak.")).toBeInTheDocument();
    await user.type(within(dialog).getByLabelText('Sarlavha'), 'Yangi banner');
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
  });

  it('edits only what changed and shows the backend\'s word when it refuses', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promos': () => ok(bannerList()),
      'GET /marketing/lookup': () => ok({ stores: [], products: [] }),
      'POST /marketing/promos/1': () => refusal(422, 'Tugash vaqti boshlanish vaqtidan keyin bo\'lishi kerak.', { reason: 'invalid_schedule' }, { code: 'invalid_schedule' }),
    });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('button', { name: 'Dekabr aksiyasi bannerini tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Bannerni tahrirlash' });
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    const title = within(dialog).getByLabelText('Sarlavha');
    await user.clear(title);
    await user.type(title, 'Dekabr chegirmalari');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Nomi yangilandi');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('POST', '/marketing/promos/1')).toHaveLength(1));
    expect(callsTo('POST', '/marketing/promos/1')[0]?.body).toEqual({ title: 'Dekabr chegirmalari', reason: 'Nomi yangilandi' });
    expect(await within(dialog).findByText("Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.")).toBeInTheDocument();
  });

  it('deletes a banner with a reason', async () => {
    const { callsTo } = mockMarketingFetch({
      ...ME(MANAGE),
      'GET /marketing/promos': () => ok(bannerList()),
      'DELETE /marketing/promos/1': () => ok({ id: 1 }),
    });
    const user = userEvent.setup();
    renderPage(<BannersTab />);

    await user.click(await screen.findByRole('button', { name: "Dekabr aksiyasi bannerini o'chirish" }));
    await reasonDialog(user, "Bannerni o'chirib tashlash", 'Eskirgan', "O'chirish");
    await waitFor(() => expect(callsTo('DELETE', '/marketing/promos/1')).toHaveLength(1));
    expect(callsTo('DELETE', '/marketing/promos/1')[0]?.body).toEqual({ reason: 'Eskirgan' });
    expect(await screen.findByText("Banner o'chirib tashlandi.")).toBeInTheDocument();
  });
});
