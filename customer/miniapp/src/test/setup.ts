import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
// The API the staff screens' hooks (@rizqim/staff) read; main.tsx registers it in the app
import '../services/staffApi';

// Testing Library only cleans up on its own when the test globals are enabled; they are not.
afterEach(() => {
  cleanup();
  localStorage.clear();
});
