'use client';

import { useEffect, useState } from 'react';

// The current time, renewed every `intervalMs` (the SLA words count between refetches)
export function useNow(intervalMs: number): number {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), intervalMs);
    return () => clearInterval(timer);
  }, [intervalMs]);
  return now;
}
