import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, ok, renderPage } from '@/test/helpers';
import { bannerList, categoryList, codePage, mockMarketingFetch } from './fixtures';
import { MarketingView } from './MarketingView';

const ME = (permissions: string[]) => ({ 'GET /auth/me': () => json(meBody({ permissions: permissions.map((name) => `admin.${name}`) })) });
const DATA = {
  'GET /marketing/promos': () => ok(bannerList()),
  'GET /marketing/promo-codes': () => ok(codePage()),
  'GET /marketing/categories': () => ok({ data: categoryList() }),
};

describe('MarketingView', () => {
  it('opens on the banners and switches between the three tabs', async () => {
    mockMarketingFetch({ ...ME(['marketing.view', 'marketing.manage']), ...DATA });
    const user = userEvent.setup();
    renderPage(<MarketingView />);

    expect(await screen.findByRole('heading', { name: 'Marketing' })).toBeInTheDocument();
    const tabs = screen.getByRole('tablist', { name: "Marketing bo'limi" });
    expect(within(tabs).getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Bannerlar va aksiyalar', 'Promo-kodlar', 'Kategoriyalar']);
    expect(await screen.findByRole('list', { name: "Bannerlar ro'yxati" })).toBeInTheDocument();

    await user.click(within(tabs).getByRole('tab', { name: 'Promo-kodlar' }));
    expect(await screen.findByRole('list', { name: "Promo-kodlar ro'yxati" })).toBeInTheDocument();
    await user.click(within(tabs).getByRole('tab', { name: 'Kategoriyalar' }));
    expect(await screen.findByRole('list', { name: "Kategoriyalar ro'yxati" })).toBeInTheDocument();
    expect(window.location.search).toBe('?tab=categories');
  });

  it('opens the tab a link points at', async () => {
    mockMarketingFetch({ ...ME(['marketing.view']), ...DATA });
    renderPage(<MarketingView initialTab="codes" />);
    expect(await screen.findByRole('list', { name: "Promo-kodlar ro'yxati" })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Promo-kodlar' })).toHaveAttribute('aria-selected', 'true');
  });

  it('says calmly that a member without marketing.view may not look, and loads nothing', async () => {
    const { calls } = mockMarketingFetch({ ...ME(['orders.view']) });
    renderPage(<MarketingView />);

    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
    await waitFor(() => expect(calls.filter((call) => call.path.startsWith('/marketing'))).toHaveLength(0));
  });

  it('shows the error of a list that did not load, with a retry', async () => {
    let fail = true;
    mockMarketingFetch({
      ...ME(['marketing.view']),
      ...DATA,
      'GET /marketing/promos': () => (fail ? json({ success: false, message: 'Server xatosi.' }, 500) : ok(bannerList())),
    });
    const user = userEvent.setup();
    renderPage(<MarketingView />);

    expect(await screen.findByRole('alert')).toHaveTextContent("Ma'lumot yuklanmadi");
    fail = false;
    await user.click(screen.getByRole('button', { name: 'Qayta urinish' }));
    expect(await screen.findByRole('list', { name: "Bannerlar ro'yxati" })).toBeInTheDocument();
  });
});
