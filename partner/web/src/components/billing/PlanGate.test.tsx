import { screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { mockFetch, renderPage } from '@/test/helpers';
import { signIn } from '../management/testSupport';
import { PlanButton, PlanGate, PlanNotice } from './PlanGate';
import { asosiyPlan, ok, startPlan } from './testSupport';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

const entitlements = (data: unknown) => mockFetch({ 'GET /api/v1/owner/billing/entitlements': () => ok(data) });

describe('PlanButton', () => {
  it('is a working button while the plan has the feature', async () => {
    entitlements(asosiyPlan());
    renderPage(<PlanButton feature="bookings">Yangi bron</PlanButton>);
    await vi.waitFor(() => expect(screen.getByRole('button', { name: 'Yangi bron' })).toBeEnabled());
    expect(screen.queryByText('Asosiy tarifda bor')).not.toBeInTheDocument();
  });

  it('is open while the entitlements are on their way (the server judges)', () => {
    entitlements(startPlan());
    renderPage(<PlanButton feature="bookings">Yangi bron</PlanButton>);
    expect(screen.getByRole('button', { name: 'Yangi bron' })).toBeEnabled();
  });

  it('is disabled and says which plan has the feature when the plan does not', async () => {
    entitlements(startPlan());
    renderPage(<PlanButton feature="bookings">Yangi bron</PlanButton>);
    expect(await screen.findByText('Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Yangi bron' })).toBeDisabled();
  });

  it('names Pro for a Pro feature', async () => {
    entitlements(startPlan());
    renderPage(<PlanButton feature="banquet">Banket</PlanButton>);
    expect(await screen.findByText('Pro tarifda bor')).toBeInTheDocument();
  });

  it('is open for a store on a legacy plan, whatever the features say', async () => {
    const { fetchMock } = entitlements(startPlan({ legacy: true }));
    renderPage(<PlanButton feature="bookings">Yangi bron</PlanButton>);
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(screen.getByRole('button', { name: 'Yangi bron' })).toBeEnabled();
  });
});

describe('PlanNotice and PlanGate', () => {
  it('explains the lock above a page and leaves the page alone', async () => {
    entitlements(startPlan());
    renderPage(
      <>
        <PlanNotice feature="customer_accounts" />
        <p>list of accounts</p>
      </>,
    );
    expect(await screen.findByText('Nasiya — Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: "Tarif va to'lov" })).toHaveAttribute('href', '/billing');
    expect(screen.getByText('list of accounts')).toBeInTheDocument();
  });

  it('shows nothing when the plan has it', async () => {
    const { fetchMock } = entitlements(asosiyPlan());
    renderPage(<PlanNotice feature="customer_accounts" />);
    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('replaces a whole section the plan does not have', async () => {
    entitlements(startPlan());
    renderPage(
      <PlanGate feature="kds">
        <p>kds section</p>
      </PlanGate>,
    );
    expect(await screen.findByText('Oshxona ekrani (KDS) — Asosiy tarifda bor')).toBeInTheDocument();
    expect(screen.queryByText('kds section')).not.toBeInTheDocument();
  });

  it('says "not in your plan" when no plan has the feature', async () => {
    entitlements(startPlan({ required_plans: {} }));
    renderPage(
      <PlanGate feature="banquet">
        <p>banquet section</p>
      </PlanGate>,
    );
    expect(await screen.findByText("Banket — tarifingizda yo'q")).toBeInTheDocument();
  });

  it('is in Russian for a Russian panel', async () => {
    entitlements(startPlan());
    renderPage(<PlanNotice feature="bookings" />, 'ru');
    expect(await screen.findByText('Бронь — есть в тарифе Asosiy')).toBeInTheDocument();
  });
});
