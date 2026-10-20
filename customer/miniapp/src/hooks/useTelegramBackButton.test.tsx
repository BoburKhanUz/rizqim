import { useEffect } from 'react';
import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BrowserRouter, Route, Routes, useNavigate } from 'react-router-dom';

// A fake Telegram WebApp (what lib/telegram exports as `tg`) with a BackButton
type Handler = () => void;
const backHandlers = new Set<Handler>();
const backButton = {
  isVisible: false,
  show: vi.fn(() => {
    backButton.isVisible = true;
  }),
  hide: vi.fn(() => {
    backButton.isVisible = false;
  }),
  onClick: vi.fn((handler: Handler) => backHandlers.add(handler)),
  offClick: vi.fn((handler: Handler) => backHandlers.delete(handler)),
};
const fake = {
  initData: 'query_id=AAH&hash=abc',
  version: '8.0',
  isVersionAtLeast(version: string) {
    const [a, b] = this.version.split('.').map(Number);
    const [c, d] = version.split('.').map(Number);
    return a > c || (a === c && b >= d);
  },
  HapticFeedback: { selectionChanged: vi.fn(), notificationOccurred: vi.fn(), impactOccurred: vi.fn() },
  BackButton: backButton,
};
vi.mock('../lib/telegram', () => ({ tg: fake }));

const { useTelegramBackButton } = await import('./useTelegramBackButton');

const ROOTS = new Set(['/', '/orders']);

let navigateTo: (to: string) => void = () => {};
function Shell() {
  useTelegramBackButton(ROOTS);
  const navigate = useNavigate();
  useEffect(() => {
    navigateTo = (to) => navigate(to);
  }, [navigate]);
  return (
    <Routes>
      <Route path="/" element={<p>Katalog</p>} />
      <Route path="/orders" element={<p>Buyurtmalar</p>} />
      <Route path="/store/:id" element={<p>Oshxona</p>} />
      <Route path="/cart" element={<p>Savat</p>} />
    </Routes>
  );
}

// The app runs on BrowserRouter: the BackButton reads React Router's position from window.history
function startAt(path: string) {
  window.history.replaceState(null, '', path);
}

const pressBack = () => act(() => backHandlers.forEach((handler) => handler()));

beforeEach(() => {
  vi.clearAllMocks();
  backHandlers.clear();
  backButton.isVisible = false;
  fake.version = '8.0';
  Object.defineProperty(window, 'TelegramWebviewProxy', { configurable: true, value: { postEvent: () => {} } });
});

afterEach(() => {
  Reflect.deleteProperty(window, 'TelegramWebviewProxy');
});

describe.each(['6.1', '8.0'])('useTelegramBackButton on Telegram %s', (version) => {
  beforeEach(() => {
    fake.version = version;
  });

  it('is hidden on a root screen and shown on the others', () => {
    startAt('/');
    render(
      <BrowserRouter>
        <Shell />
      </BrowserRouter>,
    );
    expect(backButton.isVisible).toBe(false);
    act(() => navigateTo('/store/1'));
    expect(backButton.isVisible).toBe(true);
    expect(backHandlers.size).toBe(1);
    act(() => navigateTo('/orders'));
    expect(backButton.isVisible).toBe(false);
    expect(backHandlers.size).toBe(0);
  });

  it('goes back in the app history, and keeps one handler from screen to screen', async () => {
    startAt('/');
    render(
      <BrowserRouter>
        <Shell />
      </BrowserRouter>,
    );
    act(() => navigateTo('/store/1'));
    act(() => navigateTo('/cart'));
    expect(backHandlers.size).toBe(1);
    pressBack();
    // history.back() answers with a popstate a moment later
    expect(await screen.findByText('Oshxona')).toBeInTheDocument();
    expect(fake.HapticFeedback.selectionChanged).toHaveBeenCalled();
  });

  it('goes to the catalog from a screen that was opened directly', () => {
    startAt('/cart');
    render(
      <BrowserRouter>
        <Shell />
      </BrowserRouter>,
    );
    expect(backButton.isVisible).toBe(true);
    pressBack();
    expect(screen.getByText('Katalog')).toBeInTheDocument();
    expect(backButton.isVisible).toBe(false);
  });
});

describe('useTelegramBackButton where it is not available', () => {
  it('leaves the BackButton alone on a client older than 6.1', () => {
    fake.version = '6.0';
    startAt('/cart');
    render(
      <BrowserRouter>
        <Shell />
      </BrowserRouter>,
    );
    expect(backButton.show).not.toHaveBeenCalled();
    expect(backButton.onClick).not.toHaveBeenCalled();
  });

  it('does nothing outside Telegram', () => {
    Reflect.deleteProperty(window, 'TelegramWebviewProxy');
    startAt('/cart');
    render(
      <BrowserRouter>
        <Shell />
      </BrowserRouter>,
    );
    expect(backButton.show).not.toHaveBeenCalled();
  });
});
