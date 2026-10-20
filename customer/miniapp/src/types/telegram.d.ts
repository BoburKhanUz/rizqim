import type WebApp from '@twa-dev/sdk';

declare global {
  interface Window {
    // Set by telegram-web-app.js (loaded in index.html) and by @twa-dev/sdk
    Telegram?: {
      WebApp: typeof WebApp;
    };
  }
}
