import { fetchRealtimeStatus, toApiRoot, type RealtimeStatus } from '@rizqim/staff';

// GET /api/v1/realtime (public): whether the server's real-time path works, its poll intervals, the heartbeat silence
// limit and the Reverb settings to connect with when the build has no key. Read on start and after every reconnect,
// never per poll: screens that open within REUSE_MS share one answer (the server keeps it 5 s anyway). null when it
// cannot be read (an older backend, offline): the build's settings and the old behaviour then apply.
const REUSE_MS = 30_000;

let last: { at: number; promise: Promise<RealtimeStatus | null> } | null = null;

export function loadRealtimeStatus(fresh = false): Promise<RealtimeStatus | null> {
  const now = Date.now();
  if (!fresh && last && now - last.at < REUSE_MS) return last.promise;
  const promise = fetchRealtimeStatus(toApiRoot(import.meta.env.VITE_API_URL || '/api/v1'));
  last = { at: now, promise };
  return promise;
}
