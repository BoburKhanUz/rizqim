// Wave M2: change tokens of the real-time channels (docs/API.md §8 "M2: rev, heartbeat, holat" and §7.1).
//
// Every broadcast event carries `rev`, the current change token of each channel it went to ({"pos-branch.5": "4711"}),
// and the heartbeat of a staff channel carries the same every 30 s. A single-scope read (GET /orders of a customer,
// GET /staff/orders of one branch) names its token in `X-Revision` / `X-Revision-Scope`. Tokens are decimal strings that
// grow by one per committed change; they are compared only for equality and "+1" (the base is random and jumps after a
// Redis flush), as big integers, never through a 32-bit or float parse.
//
// The tracker decides what an event or a heartbeat means for a screen:
//  - next:      the change right after what the screen holds (or one more event of the burst already being handled):
//               do what the app always did (its debounced refetch);
//  - same:      every read the screen holds was taken after this change: nothing to fetch (the app may mark its caches
//               stale so a screen opened later reads again);
//  - missed:    a gap (an event was lost, the token jumped): re-read everything the channel feeds;
//  - untracked: the channel has no single-scope read (a store-wide list, or a server that does not expose the headers):
//               as before, re-read on any event.
// A read older than `readTtlMs` no longer counts (its screen is gone or stopped polling).

export type RevisionVerdict = 'next' | 'same' | 'missed' | 'untracked';
export type HeartbeatVerdict = 'fine' | 'reread';

const TOKEN = /^\d{1,30}$/;

export function isToken(value: unknown): value is string {
  return typeof value === 'string' && TOKEN.test(value);
}

// b is the token right after a
export function isNextToken(a: string, b: string): boolean {
  return isToken(a) && isToken(b) && BigInt(a) + BigInt(1) === BigInt(b);
}

// The channel names (without the "private-" prefix, as `rev` keys them) a read scope feeds: a branch read
// ("branch:5") feeds both branch channels, "user:9" the customer's channel, "store:3" the store's.
export function channelsOfScope(scope: string): string[] {
  const match = /^(branch|store|user):(\d+)$/.exec(scope.trim());
  if (!match) return [];
  const [, kind, id] = match;
  if (kind === 'branch') return [`pos-branch.${id}`, `branch.${id}`];
  return [`${kind}.${id}`];
}

// The token an event or heartbeat payload carries for `channel` (null when it carries none)
export function revOf(payload: unknown, channel: string): string | null {
  // The data arrives as an object; a JSON text (a payload encoded twice on the way) is read too
  if (typeof payload === 'string') {
    try {
      payload = JSON.parse(payload) as unknown;
    } catch {
      return null;
    }
  }
  if (typeof payload !== 'object' || payload === null) return null;
  const rev = (payload as { rev?: unknown }).rev;
  if (typeof rev !== 'object' || rev === null) return null;
  const token = (rev as Record<string, unknown>)[channel.replace(/^(private-encrypted-|private-|presence-)/, '')];
  return isToken(token) ? token : null;
}

export interface RevisionTrackerOptions {
  // How long a read keeps counting (ms); the polled screens read at least every minute
  readTtlMs?: number;
  now?: () => number;
}

interface ChannelState {
  reads: Map<string, { token: string; at: number }>;
  everRead: boolean;
  lastEvent: string | undefined;
  lastHeard: string | undefined;
}

export const DEFAULT_READ_TTL_MS = 150_000;

export class RevisionTracker {
  private readonly channels = new Map<string, ChannelState>();
  private readonly readTtlMs: number;
  private readonly now: () => number;

  constructor({ readTtlMs = DEFAULT_READ_TTL_MS, now = () => Date.now() }: RevisionTrackerOptions = {}) {
    this.readTtlMs = readTtlMs;
    this.now = now;
  }

  private state(channel: string): ChannelState {
    let state = this.channels.get(channel);
    if (!state) {
      state = { reads: new Map(), everRead: false, lastEvent: undefined, lastHeard: undefined };
      this.channels.set(channel, state);
    }
    return state;
  }

  private freshReads(state: ChannelState): string[] {
    const oldest = this.now() - this.readTtlMs;
    const tokens: string[] = [];
    for (const [key, read] of state.reads) {
      if (read.at < oldest) state.reads.delete(key);
      else tokens.push(read.token);
    }
    return tokens;
  }

  // A 200 (or a 304 the browser turned into one) of a single-scope read: `key` names the read (method and URL), so a
  // screen that reads again replaces its own entry
  noteRead(scope: string | null | undefined, token: string | null | undefined, key: string): void {
    if (!scope || !isToken(token)) return;
    for (const channel of channelsOfScope(scope)) {
      const state = this.state(channel);
      state.everRead = true;
      state.reads.set(key, { token, at: this.now() });
    }
  }

  // The token a read holds now for `channel` (all fresh reads agree), else null. For tests and diagnostics.
  heldToken(channel: string): string | null {
    const state = this.channels.get(channel);
    if (!state) return null;
    const tokens = this.freshReads(state);
    return tokens.length > 0 && tokens.every((token) => token === tokens[0]) ? (tokens[0] ?? null) : null;
  }

  onEvent(channel: string, rev: string | null): RevisionVerdict {
    const state = this.state(channel);
    const verdict = this.classify(state, rev);
    if (rev !== null) {
      state.lastEvent = rev;
      state.lastHeard = rev;
    }
    return verdict;
  }

  private classify(state: ChannelState, rev: string | null): RevisionVerdict {
    if (!state.everRead || rev === null) return 'untracked';
    const reads = this.freshReads(state);
    if (reads.length > 0 && reads.every((token) => token === rev)) return 'same';
    const last = state.lastEvent;
    if (last !== undefined && (last === rev || isNextToken(last, rev))) return 'next';
    if (reads.length > 0 && reads.every((token) => token === rev || isNextToken(token, rev))) return 'next';
    if (reads.length === 0 && last === undefined) return 'untracked';
    return 'missed';
  }

  onHeartbeat(channel: string, rev: string | null): HeartbeatVerdict {
    if (rev === null) return 'fine';
    const state = this.state(channel);
    const heard = state.lastHeard;
    state.lastHeard = rev;
    if (!state.everRead) {
      // A store-wide channel: only a change of its own token since the last thing heard says something was missed
      return heard !== undefined && heard !== rev ? 'reread' : 'fine';
    }
    const reads = this.freshReads(state);
    if (reads.length > 0 && reads.every((token) => token === rev)) return 'fine';
    if (state.lastEvent === rev) return 'fine';
    if (reads.length === 0 && state.lastEvent === undefined) return heard !== undefined && heard !== rev ? 'reread' : 'fine';
    return 'reread';
  }

  // After a full re-read was asked for: the events heard so far no longer vouch for anything
  resetEvents(channel?: string): void {
    for (const [name, state] of this.channels) {
      if (channel === undefined || name === channel) state.lastEvent = undefined;
    }
  }

  // Sign-out, another account, another API: nothing known any more
  clear(): void {
    this.channels.clear();
  }
}

// The fetch of an API client, recording X-Revision of every single-scope GET it answers. A 304 the browser revalidated
// arrives here as the stored 200 with the stored headers (the server's 304 says the tokens did not change).
export function withRevisionCapture(send: (request: Request) => Promise<Response>, tracker: RevisionTracker) {
  return async (request: Request): Promise<Response> => {
    const method = request.method;
    const url = request.url;
    const response = await send(request);
    if (method === 'GET' && response.ok) noteResponseRevision(tracker, `${method} ${url}`, (name) => response.headers.get(name));
    return response;
  };
}

// Shared by the fetch wrapper above and an axios interceptor: `header` reads a response header by its lower-case name
export function noteResponseRevision(tracker: RevisionTracker, key: string, header: (name: string) => string | null | undefined): void {
  const scope = header('x-revision-scope');
  const token = header('x-revision');
  if (scope && token) tracker.noteRead(scope, token, key);
}
