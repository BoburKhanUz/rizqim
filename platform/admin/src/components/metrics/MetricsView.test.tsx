import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { funnel, overview } from './fixtures';
import { MetricsView } from './MetricsView';

function setup(routes: Parameters<typeof mockFetch>[0] = {}, permissions = ['admin.finance.view', 'admin.stores.view']) {
  const mocks = mockFetch({
    'GET /auth/me': () => json(meBody({ permissions })),
    'GET /metrics': () => ok(overview()),
    'GET /metrics/funnel': () => ok(funnel()),
    ...routes,
  });
  renderPage(<MetricsView />);
  return { ...mocks, user: userEvent.setup() };
}

const card = (name: string) => screen.getByRole('heading', { level: 3, name }).closest('li') as HTMLElement;
const heights = (buttons: HTMLElement[]) => buttons.map((bar) => Math.round(parseFloat((bar.firstElementChild as HTMLElement).style.height) * 10) / 10);

describe('MetricsView', () => {
  it('shows the cards with the server\'s figures, the change since the month began and the latest finished month\'s churn', async () => {
    setup();
    const cards = await screen.findByRole('list', { name: "Asosiy ko'rsatkichlar" });

    expect(within(card('MRR (oylik obuna daromadi)')).getByText(/831 167/)).toBeInTheDocument();
    expect(within(card('MRR (oylik obuna daromadi)')).getByText(/Oy boshidan 100 000.so'm ko'p \(13,7%\)/)).toBeInTheDocument();
    expect(within(card('ARR (yillik)')).getByText(/9 974 000/)).toBeInTheDocument();
    expect(within(card('Pullik ovqatxonalar')).getByText('3')).toBeInTheDocument();
    expect(within(card('Pullik ovqatxonalar')).getByText('Sinovda: 1 ta')).toBeInTheDocument();
    expect(within(card("Bitta ovqatxonadan o'rtacha (ARPA)")).getByText(/277 056/)).toBeInTheDocument();
    expect(within(card('Faol ovqatxonalar')).getByText('4')).toBeInTheDocument();
    expect(within(card('Ketish (oxirgi oy)')).getByText('50,0%')).toBeInTheDocument();
    expect(within(card('Ketish (oxirgi oy)')).getByText('sen 2026 oyi')).toBeInTheDocument();
    expect(within(card('GMV (shu oy)')).getByText(/1 500 000/)).toBeInTheDocument();
    expect(within(card('Komissiya (shu oy)')).getByText(/150 000/)).toBeInTheDocument();
    // The current month lacks computed days: the card says so
    expect(within(card('Komissiya (shu oy)')).getByText("Oyning 27 kuni hisoblanmagan: raqamlar to'liq emas.")).toBeInTheDocument();
    expect(cards.querySelectorAll('li')).toHaveLength(8);
  });

  it('scales the MRR bars to the largest month and reads each one out; the table has the same figures', async () => {
    const { user } = setup();
    const chart = await screen.findByRole('group', { name: 'MRR oylar bo\'yicha: 4 oy' });
    const bars = within(chart).getAllByRole('button');
    expect(heights(bars)).toEqual([83.8, 100, 79.4, 90.2]);
    expect(bars[1]).toHaveAccessibleName(/^avg 2026: MRR 921\s167\sso'm, pullik ovqatxona 4 ta$/);

    await user.hover(bars[2]!);
    expect(screen.getAllByText(/sen 2026: MRR 731 167/).length).toBeGreaterThan(0);

    const section = chart.closest('div.rounded-card') as HTMLElement;
    await user.click(within(section).getByRole('button', { name: 'Jadval' }));
    const table = within(section).getByRole('table', { name: 'MRR oylar bo\'yicha' });
    expect(within(table).getByRole('row', { name: /okt 2026 · oy tugamagan/ })).toHaveTextContent(/831 167/);
    expect(within(table).getAllByRole('row')).toHaveLength(5);
  });

  it('draws the MRR movement above and below the baseline and keeps the sum in the table', async () => {
    const { user } = setup();
    const heading = await screen.findByRole('heading', { name: 'MRR harakati' });
    const section = heading.closest('div.rounded-card') as HTMLElement;

    const part = (month: number, key: string) => parseFloat((section.querySelectorAll(`[data-part="${key}"]`)[month] as HTMLElement).style.height);
    // July has the largest "up" (772 166.67); September lost the most (249 000)
    expect(part(0, 'new')).toBe(100);
    expect(part(2, 'churned')).toBe(100);
    expect(part(2, 'expansion')).toBeCloseTo((59000 / 772166.67) * 100, 1);
    expect(part(1, 'contraction')).toBe(0);

    expect(within(section).getByRole('button', { name: /^sen 2026: yangi 0\sso'm, o'sish 59\s000\sso'm, kamayish 0\sso'm, ketgan 249\s000\sso'm; sof -190\s000\sso'm$/ })).toBeInTheDocument();
    for (const name of ['Yangi', "O'sish", 'Kamayish', 'Ketgan']) expect(within(section).getAllByText(name).length).toBeGreaterThan(0);

    await user.click(within(section).getByRole('button', { name: 'Jadval' }));
    const row = within(section).getByRole('row', { name: /sen 2026/ });
    expect(row).toHaveTextContent(/921 167/);
    expect(row).toHaveTextContent(/249 000.*1 ta/);
    expect(row).toHaveTextContent(/731 167/);
  });

  it('shows churn as a rate per month with the counts behind it', async () => {
    const { user } = setup();
    const chart = await screen.findByRole('group', { name: 'Ketish (churn): 4 oy' });
    const bars = within(chart).getAllByRole('button');
    expect(heights(bars)).toEqual([0, 0, 100, 0]);
    expect(bars[2]).toHaveAccessibleName('sen 2026: ketish 50,0%, ketgan 2 / 4');

    const section = chart.closest('div.rounded-card') as HTMLElement;
    await user.click(within(section).getByRole('button', { name: 'Jadval' }));
    const row = within(section).getByRole('row', { name: /sen 2026/ });
    expect(row).toHaveTextContent(/4.*1.*1.*2.*50,0.*27,0/);
  });

  it('explains a month with no stores to measure and lists the months whose revenue is incomplete', async () => {
    setup({
      'GET /metrics': () => ok(overview({ churn: overview().churn.map((row, index) => (index === 0 ? { ...row, active_at_start: 0, rate: null, churned: 0 } : row)) })),
    });
    const chart = await screen.findByRole('group', { name: 'Ketish (churn): 4 oy' });
    expect(within(chart).getAllByRole('button')[0]).toHaveAccessibleName("iyl 2026: oy boshida faol ovqatxona yo'q");
    expect(screen.getByText(/To'liq bo'lmagan oylar: iyl 2026, avg 2026, okt 2026/)).toBeInTheDocument();
  });

  it('asks for another number of months and drops the cache on refresh', async () => {
    const { user, calls } = setup({ 'GET /metrics': () => ok(overview()) });
    await screen.findByRole('list', { name: "Asosiy ko'rsatkichlar" });
    expect(calls.find((call) => call.path === '/metrics')?.query.get('months')).toBe('12');

    await user.selectOptions(screen.getByLabelText('Davr'), '24');
    await waitFor(() => expect(calls.some((call) => call.path === '/metrics' && call.query.get('months') === '24')).toBe(true));
    await waitFor(() => expect(calls.some((call) => call.path === '/metrics/funnel' && call.query.get('months') === '24')).toBe(true));

    await user.click(screen.getByRole('button', { name: 'Yangilash' }));
    await waitFor(() => expect(calls.some((call) => call.path === '/metrics' && call.query.get('refresh') === '1')).toBe(true));
  });

  it('shows the application funnel: stages, the share of all, the median days and the conversion', async () => {
    setup();
    const heading = await screen.findByRole('heading', { name: 'Arizadan faol ovqatxonagacha' });
    const panel = heading.closest('div.rounded-card') as HTMLElement;

    expect(await within(panel).findByText('25,0%')).toBeInTheDocument();
    expect(within(panel).getByText("1 / 4 ariza faol bo'ldi")).toBeInTheDocument();
    expect(within(panel).getByText('14 kun')).toBeInTheDocument();
    const stages = within(within(panel).getByRole('list', { name: 'Ariza bosqichlari' })).getAllByRole('listitem');
    expect(stages).toHaveLength(7);
    expect(stages[0]).toHaveTextContent('Ariza4 ta · 100,0%');
    expect(stages[0]).toHaveTextContent("o'rtacha 2 kun");
    expect(stages[3]).toHaveTextContent("o'rtacha 2,5 kun");
    expect(stages[4]).toHaveTextContent("hali chiqqan yo'q");
    expect(stages[6]).toHaveTextContent('Faol1 ta · 25,0%');
    expect(within(panel).getByRole('link', { name: /Ariza va ovqatxonalar/ })).toHaveAttribute('href', '/stores/applications');
  });

  it('says so when no application came in the period', async () => {
    setup({ 'GET /metrics/funnel': () => ok(funnel({ applications: 0, active: 0, rejected: 0, open: 0, conversion: null, median_days_to_active: null, stages: [] })) });
    expect(await screen.findByText("Bu davrda ariza yo'q")).toBeInTheDocument();
  });

  it('keeps the funnel when the numbers fail, and offers a retry', async () => {
    setup({ 'GET /metrics': () => refusal(500, 'Server xatosi') });
    expect(await screen.findByText("Ko'rsatkichlar yuklanmadi")).toBeInTheDocument();
    expect(await screen.findByText('25,0%')).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /Qayta urinish/ }).length).toBeGreaterThan(0);
  });

  it('keeps the numbers when the funnel fails', async () => {
    setup({ 'GET /metrics/funnel': () => refusal(500, 'Server xatosi') });
    expect(await screen.findByRole('list', { name: "Asosiy ko'rsatkichlar" })).toBeInTheDocument();
    expect(await screen.findByText('Konversiya yuklanmadi')).toBeInTheDocument();
  });

  it('is open to finance.view alone and to stores.view alone, and shut to anyone else without asking the server', async () => {
    setup({}, ['admin.finance.view']);
    expect(await screen.findByRole('list', { name: "Asosiy ko'rsatkichlar" })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Ariza va ovqatxonalar/ })).not.toBeInTheDocument();
  });

  it('refuses a member without either permission', async () => {
    const { callsTo } = setup({}, ['admin.orders.view']);
    expect(await screen.findByText(/moliya yoki ovqatxonalarni ko'rish ruxsati kerak/)).toBeInTheDocument();
    expect(callsTo('GET', '/metrics')).toHaveLength(0);
  });
});
