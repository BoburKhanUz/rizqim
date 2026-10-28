import { screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { signIn } from '../management/testSupport';
import { BillingBanner } from './BillingBanner';
import { asosiyPlan, locked, ok, reminder, startPlan } from './testSupport';

let pathname = '/dashboard';
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }), usePathname: () => pathname }));

beforeEach(() => {
  signIn();
  pathname = '/dashboard';
});
afterEach(() => setSession(null));

describe('the unpaid-invoice banner', () => {
  it('says nothing for a store that has paid', async () => {
    const { fetchMock } = mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(asosiyPlan()) });
    renderPage(<BillingBanner canPay />);
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('counts the days to the lock for an overdue invoice and says the till keeps working', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(startPlan({ status: 'past_due', notice: reminder(), lock_at: reminder().lock_at })) });
    renderPage(<BillingBanner canPay />);
    const banner = await screen.findByRole('status', { name: "To'lanmagan hisob-faktura haqida" });
    expect(banner).toHaveTextContent("To'lov muddati o'tdi — 5 kundan keyin sozlamalar yopiladi");
    expect(banner).toHaveTextContent("OB-202610-1 hisob-faktura, qoldiq 249 000 so'm");
    expect(banner).toHaveTextContent('Kassa ishlashda davom etadi');
    expect(screen.getByRole('link', { name: "To'lash" })).toHaveAttribute('href', '/billing');
  });

  it('turns into the locked message a week past the due date', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(startPlan({ status: 'locked', settings_locked: true, locked_since: '2026-11-19T00:00:00+05:00', notice: locked() })) });
    renderPage(<BillingBanner canPay />);
    const banner = await screen.findByRole('alert');
    expect(banner).toHaveTextContent("Sozlamalar yopiq — to'lov qiling");
    expect(banner).toHaveTextContent("To'lov qilinishi bilan darhol ochiladi");
    expect(banner).toHaveTextContent("Kassa to'lov qabul qilishda davom etadi");
  });

  it('speaks the panel language', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(startPlan({ status: 'locked', settings_locked: true, notice: locked() })) });
    renderPage(<BillingBanner canPay />, 'ru');
    expect(await screen.findByRole('alert')).toHaveTextContent('Настройки закрыты — оплатите счёт');
  });

  it('hides the pay link from people without finance.view and on the billing page itself', async () => {
    mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(startPlan({ status: 'past_due', notice: reminder(), lock_at: reminder().lock_at })) });
    const view = renderPage(<BillingBanner canPay={false} />);
    await screen.findByRole('status');
    expect(screen.queryByRole('link', { name: "To'lash" })).not.toBeInTheDocument();
    view.unmount();

    pathname = '/billing';
    renderPage(<BillingBanner canPay />);
    await screen.findByRole('status');
    expect(screen.queryByRole('link', { name: "To'lash" })).not.toBeInTheDocument();
  });

  it('stays out of the way when the entitlements cannot be read', async () => {
    const { fetchMock } = mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => new Response('{}', { status: 500 }) });
    renderPage(<BillingBanner canPay />);
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
