import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import type { Voucher } from './api';
import { VouchersView } from './VouchersView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));
const saveBlob = vi.hoisted(() => vi.fn());
vi.mock('../reports/download', () => ({ saveBlob }));

beforeEach(() => {
  signIn();
  saveBlob.mockReset();
});
afterEach(() => setSession(null));

const UUID = '33333333-3333-4333-8333-333333333333';

const voucher = (over: Partial<Voucher> = {}): Voucher => ({
  uuid: UUID,
  code: 'K7QM-4XPA-9HTR',
  kind: 'gift',
  face_value: '200000.00',
  balance: '80000.00',
  currency: 'UZS',
  valid_from: '2026-11-13',
  valid_until: '2027-11-13',
  status: 'active',
  status_label: 'Faol',
  branch: { id: 1, name: 'Chilonzor' },
  sale: { tender: 'cash', card_type: null, transaction_id: null, reference: null, shift_id: 5, sold_at: '2026-11-13T10:00:00+05:00', sold_by: { id: 3, name: 'Kassir' } },
  buyer: { name: 'Aziz', phone: '+99890*****67' },
  note: null,
  fiscal: { receipt_uuid: null, status: 'issued' },
  blocked: null,
  created_at: '2026-11-13T10:00:00+05:00',
  ...over,
});

const report = {
  from: '2026-10-15',
  to: '2026-11-13',
  branch_id: null,
  sold: { count: 2, amount: '250000.00', by_tender: [] },
  redeemed: { count: 1, amount: '120000.00' },
  refunded: { count: 0, amount: '0.00' },
  net_change: '130000.00',
  outstanding: { count: 1, amount: '80000.00' },
  blocked: { count: 1, amount: '50000.00' },
  expired: { count: 0, amount: '0.00' },
  as_of: '2026-11-13',
};

const page = (vouchers: Voucher[]) => ok({ vouchers, meta: { current_page: 1, last_page: 1, per_page: 30, total: vouchers.length } });

const open = (over: Parameters<typeof mockFetch>[0] = {}, keys: string[] = ['vouchers.manage', 'reports.view']) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(keys),
    'GET /api/v1/owner/pos/vouchers': () => page([voucher()]),
    'GET /api/v1/owner/pos/vouchers/report': () => ok(report),
    [`GET /api/v1/owner/pos/vouchers/${UUID}`]: () =>
      ok({
        voucher: voucher(),
        entries: [
          { uuid: 'e2', type: 'redemption', type_label: "To'lovda ishlatildi", amount: '120000.00', balance_after: '80000.00', business_date: '2026-11-13', bill_uuid: 'b1', refund_uuid: null, tender: null, branch_id: 1, shift_id: 5, user: { id: 3, name: 'Kassir' }, note: null, created_at: '2026-11-13T12:00:00+05:00' },
          { uuid: 'e1', type: 'sale', type_label: 'Sotildi', amount: '200000.00', balance_after: '200000.00', business_date: '2026-11-13', bill_uuid: null, refund_uuid: null, tender: 'cash', branch_id: 1, shift_id: 5, user: { id: 3, name: 'Kassir' }, note: null, created_at: '2026-11-13T10:00:00+05:00' },
        ],
      }),
    ...over,
  });
  renderPage(<VouchersView />);
  return { ...mocked, typing: userEvent.setup() };
};

describe('Sertifikatlar', () => {
  it('shows the period report, the liability and the list', async () => {
    open();
    expect(await screen.findByText('Qolgan majburiyat')).toBeInTheDocument();
    const table = await screen.findByRole('table', { name: 'Sertifikatlar' });
    expect(table).toHaveTextContent('K7QM-4XPA-9HTR');
    expect(table).toHaveTextContent('Faol');
    expect(table).toHaveTextContent('+99890*****67');
  });

  it('filters by status and search', async () => {
    const { calls, typing } = open();
    await screen.findByRole('table', { name: 'Sertifikatlar' });
    await typing.click(screen.getByRole('button', { name: 'Bloklangan' }));
    await typing.type(screen.getByLabelText('Kod, xaridor yoki telefon'), 'K7QM');
    await waitFor(() => {
      const url = new URL(calls.filter((call) => call.url.includes('/owner/pos/vouchers?')).at(-1)!.url);
      expect(url.searchParams.get('status')).toBe('blocked');
      expect(url.searchParams.get('q')).toBe('K7QM');
    });
  });

  it('shows one certificate with its history', async () => {
    const { typing } = open();
    await typing.click(await screen.findByRole('button', { name: 'K7QM-4XPA-9HTR tarixi' }));
    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findByRole('table', { name: 'Harakatlar' })).toHaveTextContent("To'lovda ishlatildi");
    expect(within(dialog).getByText(/Kassir/, { selector: 'dd' })).toBeInTheDocument();
  });

  it('blocks a certificate with a reason', async () => {
    const { calls, typing } = open({ [`POST /api/v1/owner/pos/vouchers/${UUID}/block`]: () => ok(voucher({ status: 'blocked', status_label: 'Bloklangan' })) });
    await typing.click(await screen.findByRole('button', { name: 'K7QM-4XPA-9HTR ni bloklash' }));
    const dialog = await screen.findByRole('dialog');
    await typing.click(within(dialog).getByRole('button', { name: 'Bloklash' }));
    expect(await within(dialog).findByText('Sababni yozing (kamida 3 harf).')).toBeInTheDocument();
    await typing.type(within(dialog).getByLabelText('Sabab'), "Yo'qolgan");
    await typing.click(within(dialog).getByRole('button', { name: 'Bloklash' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(await screen.findByText('K7QM-4XPA-9HTR bloklandi.')).toBeInTheDocument();
    const post = calls.filter((call) => call.method === 'POST').at(-1)!;
    expect(await post.json()).toEqual({ reason: "Yo'qolgan" });
  });

  it('downloads the Excel file', async () => {
    const { typing } = open({
      'GET /api/v1/owner/pos/vouchers/export': () => new Response('xlsx', { status: 200, headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition': 'attachment; filename=sertifikatlar-2026-10-15-2026-11-13.xlsx' } }),
    });
    await screen.findByRole('table', { name: 'Sertifikatlar' });
    await typing.click(screen.getByRole('button', { name: 'Excel yuklab olish' }));
    await waitFor(() => expect(saveBlob).toHaveBeenCalledTimes(1));
    expect(saveBlob.mock.calls[0]![1]).toBe('sertifikatlar-2026-10-15-2026-11-13.xlsx');
  });

  it('reads without managing: no block button; without either permission no page', async () => {
    open({}, ['reports.view']);
    await screen.findByRole('table', { name: 'Sertifikatlar' });
    expect(screen.queryByRole('button', { name: 'K7QM-4XPA-9HTR ni bloklash' })).toBeNull();
  });

  it('refuses a person who may not read it', async () => {
    open({}, ['orders.view']);
    expect(await screen.findByText("Bu bo'limni ko'rishga ruxsatingiz yo'q.")).toBeInTheDocument();
  });
});
