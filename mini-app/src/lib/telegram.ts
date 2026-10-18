import WebApp from '@twa-dev/sdk';

export type TelegramWebApp = typeof WebApp;

// The WebApp object Telegram injects (telegram-web-app.js in index.html), else the one the SDK exposes.
export const tg: TelegramWebApp = window.Telegram?.WebApp || WebApp;

export const initTelegramApp = () => {
  if (typeof tg.ready === 'function') {
    tg.ready();
  }
  if (typeof tg.expand === 'function') {
    tg.expand(); // Use maximum screen height
  }
  
  // Configure theme
  if (tg.themeParams?.bg_color) {
    document.body.style.backgroundColor = tg.themeParams.bg_color;
  }
};

export const getTelegramUser = () => {
  return tg.initDataUnsafe?.user;
};

export const getInitData = () => {
  return tg.initData || '';
};

type NotificationStyle = 'success' | 'error' | 'warning';
type ImpactStyle = 'light' | 'medium' | 'heavy' | 'rigid' | 'soft';

const isNotificationStyle = (style: ImpactStyle | NotificationStyle): style is NotificationStyle =>
  style === 'success' || style === 'error' || style === 'warning';

export const hapticFeedback = (style: ImpactStyle | NotificationStyle = 'light') => {
  if (!tg.HapticFeedback) return;

  if (isNotificationStyle(style)) {
    if (typeof tg.HapticFeedback.notificationOccurred === 'function') {
      tg.HapticFeedback.notificationOccurred(style);
    }
  } else {
    if (typeof tg.HapticFeedback.impactOccurred === 'function') {
      tg.HapticFeedback.impactOccurred(style);
    }
  }
};
