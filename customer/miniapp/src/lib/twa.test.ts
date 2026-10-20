import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// A fake Telegram WebApp: what lib/telegram exports as `tg`
const handlers = new Map<string, () => void>();
const fake = {
  initData: '',
  colorScheme: 'light' as 'light' | 'dark',
  themeParams: {} as Record<string, string>,
  version: '6.0',
  isVersionAtLeast(version: string) {
    const [a, b] = this.version.split('.').map(Number);
    const [c, d] = version.split('.').map(Number);
    return a > c || (a === c && b >= d);
  },
  setHeaderColor: vi.fn(),
  setBackgroundColor: vi.fn(),
  setBottomBarColor: vi.fn(),
  disableVerticalSwipes: vi.fn(),
  onEvent: vi.fn((name: string, handler: () => void) => handlers.set(name, handler)),
  offEvent: vi.fn((name: string) => handlers.delete(name)),
  HapticFeedback: { selectionChanged: vi.fn(), notificationOccurred: vi.fn(), impactOccurred: vi.fn() },
  BackButton: { show: vi.fn(), hide: vi.fn(), onClick: vi.fn(), offClick: vi.fn() },
  showPopup: vi.fn(),
};
vi.mock('./telegram', () => ({ tg: fake }));

const twa = await import('./twa');

function openInTelegram(version: string, scheme: 'light' | 'dark' = 'light') {
  fake.initData = 'query_id=AAH&hash=abc';
  fake.version = version;
  fake.colorScheme = scheme;
  fake.themeParams = { bg_color: scheme === 'dark' ? '#212d3b' : '#ffffff' };
  Object.defineProperty(window, 'TelegramWebviewProxy', { configurable: true, value: { postEvent: () => {} } });
}

beforeEach(() => {
  vi.clearAllMocks();
  handlers.clear();
  fake.initData = '';
  fake.themeParams = {};
});

afterEach(() => {
  Reflect.deleteProperty(window, 'TelegramWebviewProxy');
  delete document.documentElement.dataset.theme;
});

describe('outside Telegram', () => {
  it('is not hosted, even with launch data (the e2e tests and a browser with test parameters)', () => {
    fake.initData = 'query_id=AAH&hash=abc';
    expect(twa.isTelegramHost()).toBe(false);
    expect(twa.supports('6.0')).toBe(false);
  });

  it('calls nothing of Telegram and leaves the theme to the system', () => {
    const stop = twa.startTelegramChrome();
    twa.selectionChanged();
    twa.notificationOccurred('success');
    expect(twa.showTelegramBackButton(() => {})).toBe(false);
    expect(twa.nativePopup(undefined, 'Salom', [{ id: 'ok', text: 'OK' }])).toBe(false);
    expect(twa.nativeMainButton()).toBeNull();
    expect(fake.setHeaderColor).not.toHaveBeenCalled();
    expect(fake.HapticFeedback.selectionChanged).not.toHaveBeenCalled();
    expect(document.documentElement.dataset.theme).toBeUndefined();
    stop();
  });
});

describe('inside Telegram', () => {
  it("follows Telegram's colour scheme and its changes", () => {
    openInTelegram('8.0', 'dark');
    const stop = twa.startTelegramChrome();
    expect(document.documentElement.dataset.theme).toBe('dark');

    fake.colorScheme = 'light';
    fake.themeParams = { bg_color: '#ffffff' };
    handlers.get('themeChanged')?.();
    expect(document.documentElement.dataset.theme).toBe('light');

    stop();
    expect(fake.offEvent).toHaveBeenCalledWith('themeChanged', expect.any(Function));
  });

  it('paints the header, background and bottom bar in the page colour and stops swipe-to-close on a new client', () => {
    openInTelegram('8.0');
    // jsdom does not resolve CSS variables: the page colour is what the browser would compute
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({ color: 'rgb(244, 245, 247)' } as CSSStyleDeclaration);
    twa.startTelegramChrome();
    expect(fake.setHeaderColor).toHaveBeenCalledWith('#f4f5f7');
    expect(fake.setBackgroundColor).toHaveBeenCalledWith('#f4f5f7');
    expect(fake.setBottomBarColor).toHaveBeenCalledWith('#f4f5f7');
    expect(fake.disableVerticalSwipes).toHaveBeenCalledTimes(1);
  });

  it('asks an old client only for what it has: no bottom bar colour, no swipe setting, the header by theme key', () => {
    openInTelegram('6.1');
    twa.startTelegramChrome();
    expect(fake.setHeaderColor).toHaveBeenCalledWith('secondary_bg_color');
    expect(fake.setBottomBarColor).not.toHaveBeenCalled();
    expect(fake.disableVerticalSwipes).not.toHaveBeenCalled();
    // popups need 6.2
    expect(twa.nativePopup(undefined, 'Salom', [{ id: 'ok', text: 'OK' }])).toBe(false);
  });

  it('never throws when the client refuses a call', () => {
    openInTelegram('8.0');
    fake.setHeaderColor.mockImplementationOnce(() => {
      throw new Error('WebAppMethodUnsupported');
    });
    fake.showPopup.mockImplementationOnce(() => {
      throw new Error('WebAppPopupOpened');
    });
    expect(() => twa.startTelegramChrome()).not.toThrow();
    // A popup that cannot be opened falls back to the in-app one
    expect(twa.nativePopup(undefined, 'Salom', [{ id: 'ok', text: 'OK' }])).toBe(false);
  });

  it('gives haptic feedback', () => {
    openInTelegram('7.0');
    twa.selectionChanged();
    twa.notificationOccurred('error');
    expect(fake.HapticFeedback.selectionChanged).toHaveBeenCalledTimes(1);
    expect(fake.HapticFeedback.notificationOccurred).toHaveBeenCalledWith('error');
  });

  it('wires the BackButton and removes it', () => {
    openInTelegram('7.0');
    const onBack = vi.fn();
    const remove = twa.showTelegramBackButton(onBack);
    expect(fake.BackButton.onClick).toHaveBeenCalledWith(onBack);
    expect(fake.BackButton.show).toHaveBeenCalled();
    if (remove) remove();
    expect(fake.BackButton.offClick).toHaveBeenCalledWith(onBack);
    expect(fake.BackButton.hide).toHaveBeenCalled();
  });

  it('answers a native popup with the pressed button, and leaves long texts to the in-app dialog', async () => {
    openInTelegram('7.0');
    fake.showPopup.mockImplementationOnce((_params: unknown, callback: (id?: string) => void) => callback('ok'));
    const answer = twa.nativePopup('Savol', 'Davom etamizmi?', [
      { id: 'cancel', text: 'Bekor', type: 'cancel' },
      { id: 'ok', text: 'Ha' },
    ]);
    expect(answer).not.toBe(false);
    await expect(answer).resolves.toBe('ok');
    expect(twa.nativePopup(undefined, 'x'.repeat(300), [{ id: 'ok', text: 'OK' }])).toBe(false);
  });
});
