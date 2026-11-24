import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.tsx';
import { initSentry, isSentryConfigured, reportCaughtError } from './lib/sentry';
import { dropForeignSession } from './lib/session';
// Registers the API the staff screens' hooks (@rizqim/staff) read
import './services/staffApi';

// Sentry starts only when VITE_SENTRY_DSN is set (src/lib/sentry.ts); otherwise this is a plain React root.
void initSentry();

// A session stored on this device for another Telegram user is dropped before anything renders or is requested with it
// (useTelegramAuth then signs in the user who has the app open)
dropForeignSession();

createRoot(document.getElementById('root')!, isSentryConfigured() ? { onCaughtError: reportCaughtError } : undefined).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
