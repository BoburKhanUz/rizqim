import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// A fake Telegram WebApp (what lib/telegram exports as `tg`), with a MainButton that remembers its state the way the
// client draws it
type Handler = () => void;
const clickHandlers = new Set<Handler>();
const themeHandlers = new Map<string, Handler>();
const mainButton = {
  text: '',
  isVisible: false,
  isActive: true,
  isProgressVisible: false,
  color: '',
  setParams: vi.fn((params: { text?: string; is_visible?: boolean; is_active?: boolean; color?: string }) => {
    if (params.text !== undefined) mainButton.text = params.text;
    if (params.is_visible !== undefined) mainButton.isVisible = params.is_visible;
    if (params.is_active !== undefined) mainButton.isActive = params.is_active;
    if (params.color !== undefined) mainButton.color = params.color;
  }),
  showProgress: vi.fn(() => {
    mainButton.isProgressVisible = true;
  }),
  hideProgress: vi.fn(() => {
    mainButton.isProgressVisible = false;
  }),
  hide: vi.fn(() => {
    mainButton.isVisible = false;
  }),
  onClick: vi.fn((handler: Handler) => clickHandlers.add(handler)),
  offClick: vi.fn((handler: Handler) => clickHandlers.delete(handler)),
};
const fake = {
  initData: '',
  version: '8.0',
  colorScheme: 'light' as 'light' | 'dark',
  themeParams: {} as Record<string, string>,
  isVersionAtLeast(version: string) {
    const [a, b] = this.version.split('.').map(Number);
    const [c, d] = version.split('.').map(Number);
    return a > c || (a === c && b >= d);
  },
  onEvent: vi.fn((name: string, handler: Handler) => themeHandlers.set(name, handler)),
  offEvent: vi.fn(),
  setHeaderColor: vi.fn(),
  setBackgroundColor: vi.fn(),
  MainButton: mainButton as typeof mainButton | undefined,
};
vi.mock('../lib/telegram', () => ({ tg: fake, hapticFeedback: () => {} }));

const { useMainButton, resetMainButtonForTests } = await import('./useMainButton');
const { MainButtonHost } = await import('../components/ui/MainButtonHost');
const { startTelegramChrome } = await import('../lib/twa');

interface OwnerProps {
  text: string;
  onClick?: () => void | Promise<unknown>;
  enabled?: boolean;
  progress?: boolean;
  visible?: boolean;
}

function Owner({ text, onClick = () => {}, enabled, progress, visible }: OwnerProps) {
  useMainButton({ text, onClick, enabled, progress, visible });
  return null;
}

// A page with a main button, and a sheet over it that takes the button over while it is open
function Page({ sheetOpen, onPage = () => {}, onSheet = () => {}, sheetVisible = true }: { sheetOpen: boolean; onPage?: () => void; onSheet?: () => void; sheetVisible?: boolean }) {
  return (
    <>
      <Owner text="Buyurtma berish" onClick={onPage} />
      {sheetOpen && <Owner text="Tasdiqlash" onClick={onSheet} visible={sheetVisible} />}
      <MainButtonHost />
    </>
  );
}

const pressNative = () => act(() => clickHandlers.forEach((handler) => handler()));
const dockButton = () => screen.queryByRole('button');

function openInTelegram(version: string) {
  fake.initData = 'query_id=AAH&hash=abc';
  fake.version = version;
  fake.themeParams = { bg_color: '#ffffff' };
  Object.defineProperty(window, 'TelegramWebviewProxy', { configurable: true, value: { postEvent: () => {} } });
}

let now = 1_000_000;
beforeEach(() => {
  vi.clearAllMocks();
  clickHandlers.clear();
  themeHandlers.clear();
  Object.assign(mainButton, { text: '', isVisible: false, isActive: true, isProgressVisible: false, color: '' });
  fake.MainButton = mainButton;
  fake.initData = '';
  // Presses further apart than the repeat guard, unless a test says otherwise
  now += 10_000;
  vi.spyOn(Date, 'now').mockImplementation(() => now);
});

afterEach(() => {
  resetMainButtonForTests();
  Reflect.deleteProperty(window, 'TelegramWebviewProxy');
  delete document.documentElement.dataset.theme;
});

describe('useMainButton outside Telegram (the button in the bottom dock)', () => {
  it('lets a sheet take the button over and gives it back to the page when the sheet closes', () => {
    const onPage = vi.fn();
    const onSheet = vi.fn();
    const { rerender } = render(<Page sheetOpen={false} onPage={onPage} onSheet={onSheet} />);
    expect(dockButton()).toHaveTextContent('Buyurtma berish');

    rerender(<Page sheetOpen onPage={onPage} onSheet={onSheet} />);
    expect(dockButton()).toHaveTextContent('Tasdiqlash');
    fireEvent.click(dockButton()!);
    expect(onSheet).toHaveBeenCalledTimes(1);
    expect(onPage).not.toHaveBeenCalled();

    now += 10_000;
    rerender(<Page sheetOpen={false} onPage={onPage} onSheet={onSheet} />);
    expect(dockButton()).toHaveTextContent('Buyurtma berish');
    fireEvent.click(dockButton()!);
    expect(onPage).toHaveBeenCalledTimes(1);
  });

  it('shows the owner below while the one on top hides its button', () => {
    render(<Page sheetOpen sheetVisible={false} />);
    expect(dockButton()).toHaveTextContent('Buyurtma berish');
  });

  it('draws nothing when no owner is left', () => {
    const { rerender } = render(<Page sheetOpen />);
    rerender(<MainButtonHost />);
    expect(dockButton()).not.toBeInTheDocument();
  });

  it('does not press a disabled or busy button', () => {
    const onClick = vi.fn();
    const { rerender } = render(
      <>
        <Owner text="Saqlash" onClick={onClick} enabled={false} />
        <MainButtonHost />
      </>,
    );
    expect(dockButton()).toBeDisabled();
    rerender(
      <>
        <Owner text="Saqlash" onClick={onClick} progress />
        <MainButtonHost />
      </>,
    );
    expect(dockButton()).toBeDisabled();
    expect(onClick).not.toHaveBeenCalled();
  });

  it('drops a second press while the first one is still running, and a quick double tap', async () => {
    let finish: () => void = () => {};
    const onClick = vi.fn(() => new Promise<void>((resolve) => (finish = resolve)));
    render(
      <>
        <Owner text="Buyurtma berish" onClick={onClick} />
        <MainButtonHost />
      </>,
    );
    fireEvent.click(dockButton()!);
    now += 10_000;
    fireEvent.click(dockButton()!); // the request of the first press is still running
    expect(onClick).toHaveBeenCalledTimes(1);

    await act(async () => finish());
    now += 10_000;
    fireEvent.click(dockButton()!);
    expect(onClick).toHaveBeenCalledTimes(2);

    // A synchronous action pressed twice within a moment (before the screen could disable it) runs once
    const onSync = vi.fn();
    render(
      <>
        <Owner text="Saqlash" onClick={onSync} />
      </>,
    );
    now += 10_000;
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    now += 100;
    fireEvent.click(screen.getByRole('button', { name: 'Saqlash' }));
    expect(onSync).toHaveBeenCalledTimes(1);
  });
});

describe('useMainButton: the next action of the same button', () => {
  it('is pressed at once, even right after the previous one', () => {
    const pickUp = vi.fn();
    const deliver = vi.fn();
    const { rerender } = render(
      <>
        <Owner text="Olib ketdim" onClick={pickUp} />
        <MainButtonHost />
      </>,
    );
    fireEvent.click(dockButton()!);
    rerender(
      <>
        <Owner text="Yetkazib berdim" onClick={deliver} />
        <MainButtonHost />
      </>,
    );
    now += 50;
    fireEvent.click(dockButton()!);
    expect(pickUp).toHaveBeenCalledTimes(1);
    expect(deliver).toHaveBeenCalledTimes(1);
  });
});

describe.each(['6.0', '8.0'])("useMainButton inside Telegram %s (Telegram's MainButton)", (version) => {
  beforeEach(() => openInTelegram(version));

  it('shows the page action, hands it to the sheet and back, with one click handler throughout', () => {
    const onPage = vi.fn();
    const onSheet = vi.fn();
    const { rerender, unmount } = render(<Page sheetOpen={false} onPage={onPage} onSheet={onSheet} />);
    expect(mainButton).toMatchObject({ text: 'Buyurtma berish', isVisible: true, isActive: true });
    // The in-page stand-in is not drawn while Telegram's button is used
    expect(dockButton()).not.toBeInTheDocument();

    rerender(<Page sheetOpen onPage={onPage} onSheet={onSheet} />);
    expect(mainButton.text).toBe('Tasdiqlash');
    pressNative();
    expect(onSheet).toHaveBeenCalledTimes(1);
    expect(onPage).not.toHaveBeenCalled();

    now += 10_000;
    rerender(<Page sheetOpen={false} onPage={onPage} onSheet={onSheet} />);
    expect(mainButton).toMatchObject({ text: 'Buyurtma berish', isVisible: true });
    pressNative();
    expect(onPage).toHaveBeenCalledTimes(1);
    expect(mainButton.onClick).toHaveBeenCalledTimes(1);

    unmount();
    expect(mainButton.isVisible).toBe(false);
    expect(clickHandlers.size).toBe(0);
  });

  it('shows the spinner and blocks presses while a request runs', () => {
    const onClick = vi.fn();
    render(<Owner text="Saqlash" onClick={onClick} progress />);
    expect(mainButton.isProgressVisible).toBe(true);
    expect(mainButton.isActive).toBe(false);
    pressNative();
    expect(onClick).not.toHaveBeenCalled();
  });

  it('paints the button again when Telegram switches the theme', () => {
    const stop = startTelegramChrome();
    render(<Owner text="Saqlash" />);
    const calls = mainButton.setParams.mock.calls.length;
    fake.colorScheme = 'dark';
    act(() => themeHandlers.get('themeChanged')?.());
    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(mainButton.setParams.mock.calls.length).toBeGreaterThan(calls);
    stop();
  });
});

describe('useMainButton in a Telegram client without a MainButton', () => {
  it('falls back to the button in the dock', () => {
    openInTelegram('8.0');
    fake.MainButton = undefined;
    const onClick = vi.fn();
    render(
      <>
        <Owner text="Buyurtma berish" onClick={onClick} />
        <MainButtonHost />
      </>,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Buyurtma berish' }));
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
