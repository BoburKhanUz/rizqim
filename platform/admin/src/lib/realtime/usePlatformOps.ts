'use client';

import { useEffect, useRef, useState } from 'react';
import { getPlatformOps, type OpsStatus } from './platformOps';

export interface PlatformOpsOptions {
  // How often the handler is called with null while the channel is not live (the polling fallback). 0: never
  pollMs?: number;
  enabled?: boolean;
}

export const OPS_POLL_MS = 30_000;

// Listens for `event` (one or several) on platform.ops: `handler(payload)` for each event, `handler(null)` after a
// reconnect (events may have been missed) and every `pollMs` while real-time is not live (off, connecting, down), so a
// page refetches either way and a Reverb outage never breaks it. Returns whether the channel is live (pages show it, and
// may poll slower then).
export function usePlatformOps(
  event: string | readonly string[],
  handler: (payload: unknown | null) => void,
  { pollMs = OPS_POLL_MS, enabled = true }: PlatformOpsOptions = {},
): { live: boolean; status: OpsStatus } {
  const handlerRef = useRef(handler);
  useEffect(() => {
    handlerRef.current = handler;
  });
  const [status, setStatus] = useState<OpsStatus>('off');
  const events = (Array.isArray(event) ? event : [event]).join('|');

  useEffect(() => {
    if (!enabled) return;
    const ops = getPlatformOps();
    const off = events.split('|').map((name) => ops.listen(name, (payload) => handlerRef.current(payload)));
    const offStatus = ops.onStatus(setStatus);
    const offResync = ops.onResync(() => handlerRef.current(null));
    return () => {
      off.forEach((stop) => stop());
      offStatus();
      offResync();
    };
  }, [events, enabled]);

  const live = enabled && status === 'live';
  useEffect(() => {
    if (!enabled || live || pollMs <= 0) return;
    const timer = setInterval(() => handlerRef.current(null), pollMs);
    return () => clearInterval(timer);
  }, [enabled, live, pollMs]);

  return { live, status: enabled ? status : 'off' };
}
