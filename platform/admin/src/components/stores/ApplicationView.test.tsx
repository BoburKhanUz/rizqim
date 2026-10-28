import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { ApplicationView } from './ApplicationView';
import { applicationCard } from './fixtures';

const PERMS = ['stores.view', 'stores.manage'].map((name) => `admin.${name}`);
const UUID = '7a7a7a7a-0000-4000-8000-000000000007';

function setup(routes: Parameters<typeof mockFetch>[0] = {}) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions: PERMS })),
    [`GET /store-applications/${UUID}`]: () => ok(applicationCard()),
    ...routes,
  });
  renderPage(<ApplicationView uuid={UUID} />);
  return { ...mocks, user: userEvent.setup() };
}

describe('ApplicationView', () => {
  it('ticks a checklist item and moves to the next stage, showing the refusal in words', async () => {
    const ticked = applicationCard({ checklist: applicationCard().checklist.map((stage) => (stage.stage === 'application' ? { ...stage, items: stage.items.map((item) => ({ ...item, done: true, ticked: true })) } : stage)) });
    let move = 0;
    const { user, callsTo } = setup({
      [`POST /store-applications/${UUID}/checklist`]: () => ok(ticked),
      [`POST /store-applications/${UUID}/stage`]: () =>
        ++move === 1
          ? refusal(422, "«Ariza» bosqichining ro'yxati tugallanmagan: Egasi bilan bog'lanildi.", { reason: 'checklist_incomplete', stage: 'application', missing: ['contacted'] })
          : ok(applicationCard({ stage: 'review', stage_label: 'Tekshiruv' })),
    });

    await user.click(await screen.findByRole('button', { name: 'Keyingi bosqich: Tekshiruv' }));
    expect(await screen.findByText("«Ariza» bosqichining ro'yxati tugallanmagan: Egasi bilan bog'lanildi.")).toBeInTheDocument();

    await user.click(within(screen.getByRole('list', { name: 'Ariza' })).getByRole('checkbox', { name: /Egasi bilan bog'lanildi/ }));
    expect(callsTo('POST', `/store-applications/${UUID}/checklist`)[0]?.body).toEqual({ item: 'contacted', done: true });
    await user.click(screen.getByRole('button', { name: 'Keyingi bosqich: Tekshiruv' }));
    expect(await screen.findByText("Ariza «Tekshiruv» bosqichiga o'tdi.")).toBeInTheDocument();
  });

  it('converts to a store and shows the owner link once', async () => {
    const { user, callsTo } = setup({
      'GET /stores/lookup': () => ok({ stores: [], regions: [{ id: 1, name: 'Toshkent shahri' }], districts: [{ id: 3, name: 'Chilonzor tumani' }] }),
      [`POST /store-applications/${UUID}/convert`]: () => ok({ application: applicationCard({ store: { id: 40, name: "Choyxona Navro'z", status: 'pending' }, actions: { manage: true, convert: false, reject: true } }), store_id: 40, invite_link: 'https://t.me/rizqim_bot?start=invite_xyz' }),
    });
    await user.click(await screen.findByRole('button', { name: 'Ovqatxonani yaratish' }));
    const dialog = screen.getByRole('dialog', { name: 'Arizadan ovqatxona yaratish' });
    await user.selectOptions(await within(dialog).findByLabelText('Tuman'), 'Chilonzor tumani');
    await user.type(within(dialog).getByLabelText('Filial manzili'), 'Chilonzor 9');
    await user.type(within(dialog).getByLabelText('Kenglik (lat)'), '41.28');
    await user.type(within(dialog).getByLabelText('Uzunlik (lng)'), '69.2');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Hujjatlar tekshirildi');
    await user.click(within(dialog).getByRole('button', { name: 'Ovqatxonani yaratish' }));

    const link = await screen.findByRole('dialog', { name: 'Havola tayyor' });
    expect(within(link).getByDisplayValue('https://t.me/rizqim_bot?start=invite_xyz')).toBeInTheDocument();
    expect(within(link).getByRole('link', { name: 'Ovqatxona kartasi' })).toHaveAttribute('href', '/stores/40');
    expect(callsTo('POST', `/store-applications/${UUID}/convert`)[0]?.body).toEqual({
      branch_region_id: 1,
      branch_district_id: 3,
      branch_address: 'Chilonzor 9',
      branch_lat: '41.28',
      branch_lng: '69.2',
      branch_radius: '5',
      reason: 'Hujjatlar tekshirildi',
    });
  });

  it('rejects with a reason', async () => {
    const rejected = applicationCard({ stage: 'rejected', stage_label: 'Rad etildi', rejected_reason: 'Hujjat yoq', actions: { manage: true, convert: false, reject: false } });
    let current = applicationCard();
    const { user } = setup({
      [`GET /store-applications/${UUID}`]: () => ok(current),
      [`POST /store-applications/${UUID}/reject`]: () => {
        current = rejected;
        return ok(rejected);
      },
    });
    await user.click(await screen.findByRole('button', { name: 'Rad etish' }));
    const dialog = screen.getByRole('dialog', { name: 'Arizani rad etish' });
    await user.type(within(dialog).getByLabelText('Sabab'), 'Hujjat yoq');
    await user.click(within(dialog).getByRole('button', { name: 'Rad etish' }));
    expect(await screen.findByText('Ariza rad etildi.')).toBeInTheDocument();
    expect(screen.getByText('Rad etildi: Hujjat yoq')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Qayta ochish' })).toBeInTheDocument();
  });
});
