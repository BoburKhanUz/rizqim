import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
// The API the staff screens' hooks (@rizqim/staff) read; main.tsx registers it in the app
import '../services/staffApi';
import { resetSessionStatus } from '../lib/session';
import { TEST_TELEGRAM_ID } from './telegramId';

// Testing Library only cleans up on its own when the test globals are enabled; they are not.
afterEach(() => {
  cleanup();
  localStorage.clear();
  // The tests' Telegram account stays chosen (src/test/launch.ts), as on a dev server
  localStorage.setItem('mock_telegram_id', TEST_TELEGRAM_ID);
  resetSessionStatus();
});
