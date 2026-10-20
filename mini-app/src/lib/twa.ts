import { create } from 'zustand';
import { tg } from './telegram';

// The Telegram Mini App chrome: theme sync, header/background colours, swipes, haptics, BackButton, MainButton and
// native popups. Every call is guarded by the Bot API version it needs (tg.isVersionAtLeast), so an old client
// never throws, and does nothing outside a real Telegram client (a browser, the e2e tests), where the in-app
// fallbacks take over (components/ui/DialogHost.tsx, components/ui/MainButtonHost.tsx).

type WebApp = typeof tg;

// The WebApp object, when there is one with the given method (tests mock lib/telegram with a bare object).
const webApp = (): Partial<WebApp> | undefined => (tg ?? undefined) as Partial<WebApp> | undefined;

// True when a Telegram client hosts the page: launch data is there AND there is a client to talk to (the native
// webview proxy, the Windows host, or the web client's parent frame). A page that only has launch data (a browser
// with test parameters, the e2e tests) is not hosted: Telegram's buttons and popups would never answer there.
export function isTelegramHost(): boolean {
  const app = webApp();
  if (!app?.initData) return false;
  const w = window as Window & { TelegramWebviewProxy?: unknown; external?: unknown };
  if (w.TelegramWebviewProxy !== undefined) return true;
  if (w.external && typeof w.external === 'object' && 'notify' in w.external) return true;
  try {
    return window.parent != null && window.parent !== window;
  } catch {
    return true; // a cross-origin parent: an iframe inside web.telegram.org
  }
}

// The client runs Bot API `version` or newer (and hosts the page).
export function supports(version: string): boolean {
  const app = webApp();
  if (!isTelegramHost() || typeof app?.isVersionAtLeast !== 'function') return false;
  try {
    return app.isVersionAtLeast(version);
  } catch {
    return false;
  }
}

// Runs `call`, swallowing what an unsupported method throws (WebAppMethodUnsupported, WebAppPopupOpened ...).
function safely(call: () => void): boolean {
  try {
    call();
    return true;
  } catch {
    return false;
  }
}

/* ------------------------------------------------------------------ haptics */

// A light tick for a changed selection: a tab, a bottom-nav item, a segmented control, a stepper.
export function selectionChanged(): void {
  const haptics = webApp()?.HapticFeedback;
  if (!supports('6.1') || typeof haptics?.selectionChanged !== 'function') return;
  safely(() => haptics.selectionChanged());
}

// The outcome of an action: 'success' after it went through, 'error' when it failed, 'warning' before a risky one.
export function notificationOccurred(type: 'success' | 'error' | 'warning'): void {
  const haptics = webApp()?.HapticFeedback;
  if (!supports('6.1') || typeof haptics?.notificationOccurred !== 'function') return;
  safely(() => haptics.notificationOccurred(type));
}

/* -------------------------------------------------------------------- theme */

export type ColorScheme = 'light' | 'dark';

interface ThemeState {
  // The scheme the app shows; null: Telegram gave none, the system setting decides (CSS media query)
  scheme: ColorScheme | null;
  // Bumped whenever the theme changes, so colours read from CSS (MainButton) are read again
  revision: number;
}

export const useThemeStore = create<ThemeState>(() => ({ scheme: null, revision: 0 }));

// '#rrggbb' of a CSS colour expression (e.g. 'var(--rz-page)') as the page currently resolves it, or null.
export function resolveCssColor(expression: string): `#${string}` | null {
  if (typeof document === 'undefined' || !document.body) return null;
  const probe = document.createElement('div');
  probe.style.cssText = `position:absolute;width:0;height:0;visibility:hidden;color:${expression}`;
  document.body.appendChild(probe);
  const value = getComputedStyle(probe).color;
  probe.remove();
  const match = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(value);
  if (!match) return null;
  const hex = match.slice(1, 4).map((part) => Number(part).toString(16).padStart(2, '0')).join('');
  return `#${hex}`;
}

// The scheme Telegram asks for, or null when it gave no theme (not opened from Telegram).
function telegramScheme(): ColorScheme | null {
  const app = webApp();
  if (!app?.initData || !app.themeParams?.bg_color) return null;
  return app.colorScheme === 'dark' ? 'dark' : app.colorScheme === 'light' ? 'light' : null;
}

// <html data-theme>, then Telegram's header, background and bottom bar in the page colour.
function applyTheme(): void {
  const scheme = telegramScheme();
  const root = document.documentElement;
  if (scheme) root.dataset.theme = scheme;
  else delete root.dataset.theme;
  useThemeStore.setState((state) => ({ scheme, revision: state.revision + 1 }));

  const app = webApp();
  if (!app || !supports('6.1')) return;
  const page = resolveCssColor('var(--rz-page)');
  // Custom header colours need 6.9; before, only the theme keys are allowed (the page is the secondary bg)
  if (typeof app.setHeaderColor === 'function') {
    safely(() => app.setHeaderColor!(supports('6.9') && page ? page : 'secondary_bg_color'));
  }
  if (page && typeof app.setBackgroundColor === 'function') {
    safely(() => app.setBackgroundColor!(page));
  }
  if (page && supports('7.10') && typeof app.setBottomBarColor === 'function') {
    safely(() => app.setBottomBarColor!(page));
  }
}

// Starts following Telegram's theme (and sets up the chrome once). Returns the function that stops it.
export function startTelegramChrome(): () => void {
  applyTheme();
  const app = webApp();
  if (supports('7.7') && typeof app?.disableVerticalSwipes === 'function') {
    // Scrolling a long list must not pull the whole Mini App down and close it
    safely(() => app.disableVerticalSwipes!());
  }
  if (typeof app?.onEvent !== 'function') return () => {};
  const onThemeChanged = () => applyTheme();
  safely(() => app.onEvent!('themeChanged', onThemeChanged));
  return () => {
    if (typeof app.offEvent === 'function') safely(() => app.offEvent!('themeChanged', onThemeChanged));
  };
}

/* -------------------------------------------------------------- back button */

// Shows Telegram's BackButton and calls `onBack` when it is pressed; returns the cleanup. False: not available
// (the page keeps its own back control).
export function showTelegramBackButton(onBack: () => void): (() => void) | false {
  const button = webApp()?.BackButton;
  if (!supports('6.1') || !button) return false;
  safely(() => {
    button.onClick(onBack);
    button.show();
  });
  return () => {
    safely(() => {
      button.offClick(onBack);
      button.hide();
    });
  };
}

export function hideTelegramBackButton(): void {
  const button = webApp()?.BackButton;
  if (!supports('6.1') || !button) return;
  safely(() => button.hide());
}

/* -------------------------------------------------------------- main button */

export interface NativeMainButtonParams {
  text: string;
  enabled: boolean;
  progress: boolean;
  visible: boolean;
  color: string | null;
  textColor: string | null;
}

// Telegram's MainButton, or null when the client has none (then the in-page fallback is drawn).
export function nativeMainButton() {
  const button = webApp()?.MainButton;
  if (!isTelegramHost() || !button || typeof button.setParams !== 'function') return null;
  return {
    update({ text, enabled, progress, visible, color, textColor }: NativeMainButtonParams) {
      safely(() => {
        button.setParams({
          text,
          is_active: enabled && !progress,
          is_visible: visible,
          ...(color ? { color } : {}),
          ...(textColor ? { text_color: textColor } : {}),
        });
        if (progress) button.showProgress(false);
        else button.hideProgress();
      });
    },
    onClick(handler: () => void) {
      safely(() => button.onClick(handler));
      return () => safely(() => button.offClick(handler));
    },
    hide() {
      safely(() => {
        button.hideProgress();
        button.hide();
      });
    },
  };
}

/* ------------------------------------------------------------------ popups */

export interface PopupButtonSpec {
  id: string;
  text: string;
  type?: 'default' | 'destructive' | 'cancel';
}

// Telegram's own limits: a longer popup is drawn in-app instead
const POPUP_TITLE_MAX = 64;
const POPUP_MESSAGE_MAX = 256;

// Shows a native popup; resolves with the id of the pressed button (null: dismissed). False: not available.
export function nativePopup(title: string | undefined, message: string, buttons: PopupButtonSpec[]): Promise<string | null> | false {
  const app = webApp();
  if (!supports('6.2') || typeof app?.showPopup !== 'function') return false;
  if ((title?.length ?? 0) > POPUP_TITLE_MAX || message.length > POPUP_MESSAGE_MAX || buttons.length < 1 || buttons.length > 3) return false;
  let answer: (id: string | null) => void = () => {};
  const pressed = new Promise<string | null>((resolve) => {
    answer = resolve;
  });
  const shown = safely(() =>
    app.showPopup!(
      {
        ...(title ? { title } : {}),
        message,
        buttons: buttons.map((button) =>
          button.type === 'cancel'
            ? { id: button.id, type: 'cancel' as const }
            : { id: button.id, type: button.type ?? 'default', text: button.text },
        ),
      },
      (id) => answer(id ? id : null),
    ),
  );
  // Throws when another popup is open, among others: then the caller draws its own
  return shown ? pressed : false;
}
