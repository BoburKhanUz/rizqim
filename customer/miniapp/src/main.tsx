import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.tsx';
import { initSentry, isSentryConfigured, reportCaughtError } from './lib/sentry';

// Sentry starts only when VITE_SENTRY_DSN is set (src/lib/sentry.ts); otherwise this is a plain React root.
void initSentry();

createRoot(document.getElementById('root')!, isSentryConfigured() ? { onCaughtError: reportCaughtError } : undefined).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
