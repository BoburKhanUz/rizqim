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
  // Bot API 8.0: the device's safe area, the band Telegram's own buttons cover, and the full-screen flag
  safeAreaInset: { top: 0, bottom: 0, left: 0, right: 0 },
  contentSafeAreaInset: { top: 0, bottom: 0, left: 0, right: 0 },
  isFullscreen: false,
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
  delete document.documentElement.dataset.fullscreen;
  document.documentElement.removeAttribute('style');
  fake.safeAreaInset = { top: 0, bottom: 0, left: 0, right: 0 };
  fake.contentSafeAreaInset = { top: 0, bottom: 0, left: 0, right: 0 };
  fake.isFullscreen = false;
});

const rootVar = (name: string) => document.documentElement.style.getPropertyValue(name);

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

describe('safe areas (Bot API 8.0 full-screen)', () => {
  it('publishes nothing outside Telegram, so the CSS falls back to env(safe-area-inset-*)', () => {
    fake.safeAreaInset = { top: 47, bottom: 34, left: 0, right: 0 };
    twa.startTelegramChrome();
    expect(rootVar('--rz-tg-safe-top')).toBe('');
    expect(rootVar('--rz-tg-content-top')).toBe('');
    expect(document.documentElement.dataset.fullscreen).toBeUndefined();
  });

  it('publishes nothing on a client before 8.0', () => {
    openInTelegram('7.10');
    fake.safeAreaInset = { top: 47, bottom: 34, left: 0, right: 0 };
    twa.startTelegramChrome();
    expect(rootVar('--rz-tg-safe-top')).toBe('');
  });

  it('publishes zero insets in the normal mode', () => {
    openInTelegram('8.0');
    twa.startTelegramChrome();
    expect(rootVar('--rz-tg-safe-top')).toBe('0px');
    expect(rootVar('--rz-tg-content-top')).toBe('0px');
    expect(document.documentElement.dataset.fullscreen).toBeUndefined();
  });

  it("follows full-screen: the status bar and Telegram's buttons, then back to normal", () => {
    openInTelegram('8.0');
    const stop = twa.startTelegramChrome();

    fake.isFullscreen = true;
    fake.safeAreaInset = { top: 47, bottom: 34, left: 0, right: 0 };
    fake.contentSafeAreaInset = { top: 46, bottom: 0, left: 0, right: 0 };
    handlers.get('fullscreenChanged')?.();
    expect(rootVar('--rz-tg-safe-top')).toBe('47px');
    expect(rootVar('--rz-tg-content-top')).toBe('46px');
    expect(rootVar('--rz-tg-safe-bottom')).toBe('34px');
    expect(document.documentElement.dataset.fullscreen).toBe('true');

    // Landscape: the notch moves to a side
    fake.safeAreaInset = { top: 0, bottom: 21, left: 59, right: 59 };
    handlers.get('safeAreaChanged')?.();
    expect(rootVar('--rz-tg-safe-left')).toBe('59px');
    expect(rootVar('--rz-tg-safe-right')).toBe('59px');
    fake.contentSafeAreaInset = { top: 40, bottom: 0, left: 0, right: 0 };
    handlers.get('contentSafeAreaChanged')?.();
    expect(rootVar('--rz-tg-content-top')).toBe('40px');

    fake.isFullscreen = false;
    fake.safeAreaInset = { top: 0, bottom: 0, left: 0, right: 0 };
    fake.contentSafeAreaInset = { top: 0, bottom: 0, left: 0, right: 0 };
    handlers.get('fullscreenChanged')?.();
    expect(rootVar('--rz-tg-safe-top')).toBe('0px');
    expect(document.documentElement.dataset.fullscreen).toBeUndefined();

    stop();
    for (const event of ['safeAreaChanged', 'contentSafeAreaChanged', 'fullscreenChanged']) {
      expect(fake.offEvent).toHaveBeenCalledWith(event, expect.any(Function));
    }
  });

  it('ignores missing or broken values', () => {
    openInTelegram('8.0');
    (fake as { safeAreaInset: unknown }).safeAreaInset = undefined;
    (fake.contentSafeAreaInset as Record<string, unknown>).top = Number.NaN;
    twa.startTelegramChrome();
    expect(rootVar('--rz-tg-safe-top')).toBe('0px');
    expect(rootVar('--rz-tg-content-top')).toBe('0px');
  });

  it('never asks for full-screen itself', () => {
    openInTelegram('8.0');
    const requestFullscreen = vi.fn();
    Object.assign(fake, { requestFullscreen });
    twa.startTelegramChrome();
    expect(requestFullscreen).not.toHaveBeenCalled();
  });
});
