import WebApp from '@twa-dev/sdk';

export const tg = (window as any).Telegram?.WebApp || WebApp || {};

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

export const hapticFeedback = (style: 'light' | 'medium' | 'heavy' | 'rigid' | 'soft' | 'success' | 'error' | 'warning' = 'light') => {
  if (!tg.HapticFeedback) return;
  
  if (['success', 'error', 'warning'].includes(style)) {
    if (typeof tg.HapticFeedback.notificationOccurred === 'function') {
      tg.HapticFeedback.notificationOccurred(style as 'success' | 'error' | 'warning');
    }
  } else {
    if (typeof tg.HapticFeedback.impactOccurred === 'function') {
      tg.HapticFeedback.impactOccurred(style as 'light' | 'medium' | 'heavy' | 'rigid' | 'soft');
    }
  }
};
