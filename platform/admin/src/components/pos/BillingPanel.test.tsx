import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, renderPage } from '@/test/helpers';
import { catalog, commission, entitlements, plan, storeBilling, subscription } from './fixtures';
import { PlansTab } from './PlansTab';
import { StoreBillingPanel } from './StoreBillingPanel';
import { SubscriptionsTab } from './SubscriptionsTab';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });

async function reasonDialog(user: ReturnType<typeof userEvent.setup>, name: string, reason: string, confirm: string) {
  const dialog = await screen.findByRole('dialog', { name });
  await user.type(within(dialog).getByLabelText('Sabab'), reason);
  await user.click(within(dialog).getByRole('button', { name: confirm }));
  return dialog;
}

describe('SubscriptionsTab', () => {
  it('lists subscriptions with the trial days left and sends the status and trial filters', async () => {
    const trialEnds = new Date(Date.now() + 2 * 86_400_000 - 3600_000).toISOString();
    const { callsTo } = mockFetch({
      ...ME(['subscriptions.manage']),
      'GET /billing/plans': () => ok(catalog()),
      'GET /billing/subscriptions': () =>
        ok({
          data: [
            { subscription: subscription({ status: 'trialing', status_label: 'Sinov davri', trial_ends_at: trialEnds }), store: { id: 13, name: 'Lazzat Express' } },
            { subscription: subscription({ id: 6, status: 'past_due', status_label: "To'lov muddati o'tgan" }), store: { id: 15, name: 'Qarzdor kafe' } },
          ],
          next_cursor: null,
          per_page: 30,
          counts: { trialing: 1, past_due: 1, active: 4 },
        }),
    });
    const user = userEvent.setup();
    renderPage(<SubscriptionsTab selected={null} onSelect={() => {}} onOpenInvoice={() => {}} />);

    const list = await screen.findByRole('list', { name: "Obunalar ro'yxati" });
    const trial = within(list).getByRole('button', { name: 'Lazzat Express obunasini ochish' });
    expect(within(trial).getByText('Sinov davri')).toBeInTheDocument();
    expect(within(trial).getByText('Sinovga 2 kun qoldi')).toBeInTheDocument();
    expect(within(list).getByText("To'lov muddati o'tgan")).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: "To'lov muddati o'tgan (1)" }));
    await waitFor(() => expect(callsTo('GET', '/billing/subscriptions').at(-1)?.query.get('status')).toBe('past_due'));
    await user.click(screen.getByRole('button', { name: 'Sinovi 3 kunda tugaydi' }));
    await waitFor(() => expect(callsTo('GET', '/billing/subscriptions').at(-1)?.query.get('trial_ends_within')).toBe('3'));
    expect(callsTo('GET', '/billing/subscriptions').at(-1)?.query.get('status')).toBeNull();
    await user.selectOptions(screen.getByLabelText('Davr'), 'Yillik');
    await waitFor(() => expect(callsTo('GET', '/billing/subscriptions').at(-1)?.query.get('period')).toBe('yearly'));
  });
});

describe('StoreBillingPanel', () => {
  function setup(permissions: string[], billing = storeBilling()) {
    const mocks = mockFetch({
      ...ME(permissions),
      'GET /billing/stores/12': () => ok(billing),
      'GET /billing/plans': () => ok(catalog()),
      'POST /billing/stores/12/subscription': () => ok(billing),
      'POST /billing/stores/12/subscription/period': () => ok(billing),
      'POST /billing/stores/12/subscription/trial': () => ok(billing),
      'POST /billing/stores/12/subscription/cancel': () => ok(billing),
      'PUT /billing/stores/12/commission': () => ok(billing.commission),
    });
    renderPage(<StoreBillingPanel storeId={12} onBack={() => {}} />);
    return { ...mocks, user: userEvent.setup() };
  }

  it('shows the plan, what it allows, the debt split, the commission and the invoices', async () => {
    setup(['subscriptions.manage']);
    expect(await screen.findByRole('heading', { name: 'Lazzat Oshxona' })).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Tarif imkoniyatlari' })).toHaveTextContent('Bronlar');
    await waitFor(() => expect(screen.getByRole('list', { name: 'Tarif imkoniyatlari' })).toHaveTextContent('(Pro tarifida)'));
    expect(screen.getByText('Cheksiz va bepul')).toBeInTheDocument();
    expect(screen.getByText(/3 \/ 2 ishlayapti/)).toHaveTextContent('+1 ortiqcha');
    expect(screen.getByText('Komissiya qarzi')).toBeInTheDocument();
    expect(screen.getByText('Komissiya qarzi kredit limitidan oshgan: Mini App buyurtmalari yopiq.')).toBeInTheDocument();
    const rates = screen.getByRole('list', { name: 'Komissiya' });
    expect(within(rates).getByText('3%')).toBeInTheDocument();
    expect(within(rates).getByText('ovqatxonaniki')).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Oxirgi hisob-fakturalar' })).toHaveTextContent('OB-202610-000015');
    expect(screen.getByRole('link', { name: 'Ovqatxona kartasi' })).toHaveAttribute('href', '/stores/12');
  });

  it('names every device row of the plan, the free customer display included (P6-14)', async () => {
    const devices = {
      pos: { included: 2, active: 1, extra: 0, extra_price: '59000.00', can_add: true },
      kds: { included: 1, active: 1, extra: 0, extra_price: '29000.00', can_add: true },
      display: { included: null, active: 2, unlimited: true, extra_price: '0.00', can_add: true },
      waiter: { included: null, unlimited: true, extra_price: '0.00', can_add: true },
    };
    setup(['subscriptions.manage'], storeBilling({ entitlements: entitlements({ devices }) }));
    const list = await screen.findByRole('list', { name: 'Qurilmalar' });
    const rows = within(list).getAllByRole('listitem').map((row) => row.querySelector('.font-bold')?.textContent);
    expect(rows).toEqual(['Kassa', 'Oshxona ekrani (KDS)', 'Mijoz ekrani', 'Ofitsiant telefonlari']);
    expect(within(list).getAllByText('Cheksiz va bepul')).toHaveLength(2);
  });

  it('hides the actions from a member who only reads', async () => {
    setup(['finance.view']);
    await screen.findByRole('heading', { name: 'Lazzat Oshxona' });
    expect(screen.queryByRole('button', { name: 'Tarif biriktirish' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Obunani bekor qilish' })).not.toBeInTheDocument();
  });

  it('assigns another plan with a period, "immediately" and a reason', async () => {
    const { user, callsTo } = setup(['subscriptions.manage']);
    await user.click(await screen.findByRole('button', { name: 'Tarif biriktirish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Tarif biriktirish' });
    await within(dialog).findByRole('option', { name: 'Pro' });
    await user.selectOptions(within(dialog).getByLabelText('Tarif'), 'Pro');
    await user.selectOptions(within(dialog).getByLabelText('Davr'), 'Yillik');
    await user.click(within(dialog).getByRole('switch', { name: "Hoziroq o'tkazish (faqat yuqori tarif)" }));
    await user.type(within(dialog).getByLabelText('Sabab'), 'Egasi Pro tarifni tanladi');
    await user.click(within(dialog).getByRole('button', { name: 'Biriktirish' }));

    await waitFor(() => expect(callsTo('POST', '/billing/stores/12/subscription')).toHaveLength(1));
    expect(callsTo('POST', '/billing/stores/12/subscription')[0]?.body).toEqual({ plan: 'pro', period: 'yearly', immediately: true, reason: 'Egasi Pro tarifni tanladi' });
    expect(await screen.findByText('Tarif saqlandi.')).toBeInTheDocument();
  });

  it('extends a trial only while the store is in its trial', async () => {
    const trialing = storeBilling({ subscription: subscription({ status: 'trialing', status_label: 'Sinov davri', trial_ends_at: new Date(Date.now() + 86_400_000).toISOString() }) });
    const { user, callsTo } = setup(['subscriptions.manage'], trialing);
    await user.click(await screen.findByRole('button', { name: 'Sinovni uzaytirish' }));
    const dialog = await screen.findByRole('dialog', { name: 'Sinovni uzaytirish' });
    const days = within(dialog).getByLabelText("Qo'shiladigan kunlar (1–90)");
    await user.clear(days);
    await user.type(days, '120');
    expect(within(dialog).getByText('1 dan 90 gacha butun son kiriting.')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Uzaytirish' })).toBeDisabled();
    await user.clear(days);
    await user.type(days, '10');
    await user.type(within(dialog).getByLabelText('Sabab'), 'Kassa kech keldi');
    await user.click(within(dialog).getByRole('button', { name: 'Uzaytirish' }));
    await waitFor(() => expect(callsTo('POST', '/billing/stores/12/subscription/trial')[0]?.body).toEqual({ days: 10, reason: 'Kassa kech keldi' }));
  });

  it('has no trial button for an active subscription, and cancels at the end of the period by default', async () => {
    const { user, callsTo } = setup(['subscriptions.manage']);
    await screen.findByRole('heading', { name: 'Lazzat Oshxona' });
    expect(screen.queryByRole('button', { name: 'Sinovni uzaytirish' })).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Obunani bekor qilish' }));
    await reasonDialog(user, 'Obunani bekor qilish', 'Ovqatxona yopildi', 'Obunani bekor qilish');
    await waitFor(() => expect(callsTo('POST', '/billing/stores/12/subscription/cancel')[0]?.body).toEqual({ immediately: false, reason: 'Ovqatxona yopildi' }));
    expect(await screen.findByText('Obuna davr oxirida tugaydi.')).toBeInTheDocument();
  });

  it('sets, changes and removes commission overrides, sending only what changed', async () => {
    const { user, callsTo } = setup(['subscriptions.manage']);
    await user.click(await screen.findByRole('button', { name: "Komissiyani o'zgartirish" }));
    const dialog = await screen.findByRole('dialog', { name: 'Komissiyani belgilash' });
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    // pickup has an override of 3%: remove it; own delivery gets 8.5%
    await user.clear(within(dialog).getByLabelText('Olib ketish'));
    await user.type(within(dialog).getByLabelText("O'z yetkazishi"), '8,5');
    await user.type(within(dialog).getByLabelText('Zal (POS)'), '150');
    expect(within(dialog).getByText('0 dan 100 gacha, ko‘pi bilan 2 xona kasr.'.replace('‘', "'"))).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    await user.clear(within(dialog).getByLabelText('Zal (POS)'));
    await user.type(within(dialog).getByLabelText('Sabab'), 'Kelishuv bo‘yicha');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('PUT', '/billing/stores/12/commission')).toHaveLength(1));
    expect(callsTo('PUT', '/billing/stores/12/commission')[0]?.body).toEqual({ overrides: { pickup: null, own_delivery: '8.50' }, delivery_courier: 'own', reason: 'Kelishuv bo‘yicha' });
  });

  it('shows the legacy plan and a locked store in words', async () => {
    setup(
      ['subscriptions.manage'],
      storeBilling({
        subscription: subscription({ status: 'locked', status_label: 'Cheklangan (qarz)', locked_at: '2026-11-12T00:00:00+05:00', plan: { code: 'pos', name: 'POS (eski)', version: 1 } }),
        entitlements: entitlements({ legacy: true, settings_locked: true, notice: { level: 'locked', message: "Sozlamalar yopiq — to'lov qiling", days_overdue: 8, lock_at: null } }),
        commission: commission(),
      }),
    );
    expect(await screen.findByText('Sozlamalar yopiq')).toBeInTheDocument();
    expect(screen.getByText('Bu ovqatxona eski tarifda: hamma imkoniyat yoqilgan.')).toBeInTheDocument();
    expect(screen.getByText("Sozlamalar yopiq — to'lov qiling")).toBeInTheDocument();
    expect(screen.getByText('POS (eski) (eski tarif)')).toBeInTheDocument();
  });
});

describe('PlansTab', () => {
  it('shows the catalog with prices and features; old plans are read-only', async () => {
    mockFetch({ ...ME(['finance.view']), 'GET /billing/plans': () => ok(catalog()) });
    renderPage(<PlansTab />);
    const list = await screen.findByRole('list', { name: 'Tariflar ro‘yxati'.replace('‘', "'") });
    const pro = within(list).getByRole('heading', { name: 'Pro' }).closest('li') as HTMLElement;
    expect(within(pro).getByText('449 000 so\'m')).toBeInTheDocument();
    expect(within(pro).getByText('4 490 000 so\'m')).toBeInTheDocument();
    expect(within(pro).getByText('4 ta kiritilgan, ortig\'i 59 000 so\'m/oy')).toBeInTheDocument();
    expect(pro).toHaveTextContent('Banket');
    expect(screen.getByRole('list', { name: "Eski tariflar (faqat ko'rish)" })).toHaveTextContent('POS (eski)');
    expect(screen.queryByRole('button', { name: /tahrirlash/ })).not.toBeInTheDocument();
  });

  it('edits a plan with system.manage and sends only the changed fields with the reason', async () => {
    const { callsTo } = mockFetch({
      ...ME(['finance.view', 'system.manage']),
      'GET /billing/plans': () => ok(catalog()),
      'PUT /billing/plans/asosiy': () => ok(plan({ monthly_fee: '259000.00' })),
    });
    const user = userEvent.setup();
    renderPage(<PlansTab />);
    await user.click(await screen.findByRole('button', { name: 'Asosiy tarifini tahrirlash' }));
    const dialog = await screen.findByRole('dialog', { name: 'Asosiy tarifini tahrirlash' });
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();

    const monthly = within(dialog).getByLabelText('Oylik narx');
    await user.clear(monthly);
    await user.type(monthly, '259000');
    await user.click(within(dialog).getByRole('switch', { name: 'Banket' }));
    await user.type(within(dialog).getByLabelText('Sabab'), 'Egasi narxni oshirdi');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));

    await waitFor(() => expect(callsTo('PUT', '/billing/plans/asosiy')).toHaveLength(1));
    expect(callsTo('PUT', '/billing/plans/asosiy')[0]?.body).toEqual({ monthly_fee: '259000', features: ['bookings', 'kds', 'banquet'], reason: 'Egasi narxni oshirdi' });
    expect(await screen.findByText('Asosiy tarifi saqlandi.')).toBeInTheDocument();
  });
});
