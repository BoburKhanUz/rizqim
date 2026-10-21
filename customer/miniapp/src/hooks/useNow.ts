import { useState } from 'react';
import { useVisibleInterval } from './useVisibleInterval';

// The current time, refreshed every `intervalMs` while the page is visible: a waiting time on a card stays true on a
// screen left open. Returns epoch milliseconds.
export function useNow(intervalMs = 30_000): number {
  const [now, setNow] = useState(() => Date.now());
  useVisibleInterval(() => setNow(Date.now()), intervalMs);
  return now;
}
