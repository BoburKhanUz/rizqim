import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { CommandPalette } from './CommandPalette';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push, replace: vi.fn() }), usePathname: () => '/today' }));

afterEach(() => push.mockReset());

const RESULTS = [
  { type: 'store', id: '12', title: 'Lazzat Oshxona', subtitle: 'STIR 123456789 · +998 90 *** ** 67', href: '/stores/12' },
  { type: 'store', id: '13', title: 'Lazzat Express', subtitle: null, href: '/stores/13' },
  { type: 'order', id: 'ab12cd34', title: 'Buyurtma #AB12CD', subtitle: 'Lazzat Oshxona', href: '/orders/ab12cd34-0000' },
  { type: 'user', id: '7', title: 'Olim Egamov', subtitle: '+998 90 *** ** 67', href: '/users/7' },
  { type: 'member', id: '3', title: 'Sardor Operator', subtitle: 'Operator', href: '/team/3' },
];

function open(search: (q: string) => Response) {
  const onClose = vi.fn();
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody()), 'GET /search': (request) => search(new URL(request.url).searchParams.get('q') ?? '') });
  renderPage(<CommandPalette onClose={onClose} />);
  return { onClose, ...mocks };
}

describe('Ctrl+K search', () => {
  it('asks for two characters first, then searches once the typing pauses', async () => {
    const { callsTo } = open(() => ok(RESULTS));
    const user = userEvent.setup();
    expect(screen.getByText('Kamida 2 ta belgi yozing')).toBeInTheDocument();
    await user.type(screen.getByRole('combobox'), 'l');
    await new Promise((resolve) => setTimeout(resolve, 400));
    expect(callsTo('GET', '/search')).toHaveLength(0);

    await user.type(screen.getByRole('combobox'), 'azzat');
    await screen.findByRole('option', { name: /^Lazzat Oshxona/ });
    // One request for the finished word, not one per key
    expect(callsTo('GET', '/search')).toHaveLength(1);
    expect(callsTo('GET', '/search')[0]?.query.get('q')).toBe('lazzat');
  });

  it('groups the results by type, in the order stores, orders, users, team', async () => {
    open(() => ok(RESULTS));
    const user = userEvent.setup();
    await user.type(screen.getByRole('combobox'), 'lazzat');
    await screen.findByRole('option', { name: /^Lazzat Oshxona/ });

    const groups = screen.getAllByRole('group');
    expect(groups.map((group) => group.getAttribute('aria-labelledby') && document.getElementById(group.getAttribute('aria-labelledby')!)?.textContent)).toEqual(['Ovqatxonalar', 'Buyurtmalar', 'Foydalanuvchilar', 'Jamoa']);
    expect(within(groups[0]!).getAllByRole('option')).toHaveLength(2);
    // The phones come masked from the backend and are shown as they are
    expect(screen.getAllByText('+998 90 *** ** 67').length).toBeGreaterThan(0);
  });

  it('walks the list with the arrow keys and opens the chosen one with Enter', async () => {
    const { onClose } = open(() => ok(RESULTS));
    const user = userEvent.setup();
    const box = screen.getByRole('combobox');
    await user.type(box, 'lazzat');
    await screen.findByRole('option', { name: /^Lazzat Oshxona/ });
    expect(screen.getAllByRole('option')[0]).toHaveAttribute('aria-selected', 'true');
    expect(box).toHaveAttribute('aria-activedescendant', screen.getAllByRole('option')[0]!.id);

    await user.keyboard('{ArrowDown}{ArrowDown}');
    expect(screen.getAllByRole('option')[2]).toHaveAttribute('aria-selected', 'true');
    await user.keyboard('{ArrowUp}{ArrowUp}{ArrowUp}');
    // Wraps to the last
    expect(screen.getAllByRole('option')[4]).toHaveAttribute('aria-selected', 'true');
    await user.keyboard('{Enter}');
    expect(push).toHaveBeenCalledWith('/team/3');
    expect(onClose).toHaveBeenCalled();
  });

  it('opens a result with a click', async () => {
    open(() => ok(RESULTS));
    const user = userEvent.setup();
    await user.type(screen.getByRole('combobox'), 'olim');
    await user.click(await screen.findByRole('option', { name: /Olim Egamov/ }));
    expect(push).toHaveBeenCalledWith('/users/7');
  });

  it('says when nothing is found and when the search fails', async () => {
    let fail = false;
    open(() => (fail ? refusal(500, 'Server xatosi') : ok([])));
    const user = userEvent.setup();
    await user.type(screen.getByRole('combobox'), 'zzz');
    expect(await screen.findByText('Hech narsa topilmadi')).toBeInTheDocument();

    fail = true;
    await user.clear(screen.getByRole('combobox'));
    await user.type(screen.getByRole('combobox'), 'yyy');
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Server xatosi'));
  });

  it('ignores a result whose link leaves the app', async () => {
    open(() => ok([{ type: 'store', id: '1', title: 'Evil', subtitle: null, href: 'https://evil.example/' }, { type: 'store', id: '2', title: 'Fine', subtitle: null, href: '/stores/2' }]));
    const user = userEvent.setup();
    await user.type(screen.getByRole('combobox'), 'abc');
    await screen.findByRole('option', { name: /Fine/ });
    expect(screen.queryByRole('option', { name: /Evil/ })).toBeNull();
  });

  it('closes with Escape', async () => {
    const { onClose } = open(() => ok([]));
    const user = userEvent.setup();
    await user.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalled();
  });
});
