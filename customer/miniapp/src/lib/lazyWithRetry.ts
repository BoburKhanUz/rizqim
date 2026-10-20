import { lazy, type ComponentType } from 'react';

const RETRY_DELAY_MS = 600;

// React.lazy for a route page that tries the dynamic import twice: on a flaky mobile connection the first
// request often fails while the second succeeds. A second failure is thrown to the nearest error boundary.
export function lazyWithRetry(load: () => Promise<{ default: ComponentType }>) {
  return lazy(async () => {
    try {
      return await load();
    } catch {
      await new Promise((resolve) => setTimeout(resolve, RETRY_DELAY_MS));
      return load();
    }
  });
}
