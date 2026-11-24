import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.tsx';
import { initSentry, isSentryConfigured, reportCaughtError } from './lib/sentry';
import { clearForeignSession } from './lib/session';
// Registers the API the staff screens' hooks (@rizqim/staff) read
import './services/staffApi';

// Sentry starts only when VITE_SENTRY_DSN is set (src/lib/sentry.ts); otherwise this is a plain React root.
void initSentry();

// The per-user stores were opened under the key of the Telegram user who has the app open (lib/userStorage.ts). A
// session in them whose user is not that Telegram user is dropped before anything renders or is requested with it
// (useTelegramAuth then signs the user in again; the cart and addresses stay)
clearForeignSession();

createRoot(document.getElementById('root')!, isSentryConfigured() ? { onCaughtError: reportCaughtError } : undefined).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
