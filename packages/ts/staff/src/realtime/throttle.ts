// Wave M2 fix round 2: pacing of the re-reads a real-time event asks for (docs/scale/RESULTS_M2.md, sections 4 and 8).
//
// With real-time on, every committed write of a busy branch made every screen of the branch re-read within ~300 ms
// (one debounce everywhere, no spread): the tills queued behind the burst. A screen now paces the reads that events and
// heartbeats ask for (leading + trailing throttle, timers only, no clock):
//  - a request with nothing pending fires after a random U(0, spread): the screens of a branch spread out;
//  - the window W starts when it fires. Requests during W collapse into ONE read at the end of W, plus a new
//    U(0, spread);
//  - requests while a read is already scheduled (in its spread, or at the end of W) add nothing;
//  - `flush()` reads at once and is never paced (the person's own action, a resync or reconnect, the silence watch,
//    the page coming back): it takes over whatever was scheduled. It does not open a window, so the next event after
//    it is prompt again.
// The safety polls of the screens are their own and are never paced.

export interface ThrottleTimers {
  setTimeout: (callback: () => void, ms: number) => unknown;
  clearTimeout: (handle: unknown) => void;
}

export interface RefreshPacing {
  // The window W (ms) after a paced read in which further requests collapse into one read at its end
  windowMs: number;
  // Each paced read waits a random 0..spreadMs first
  spreadMs: number;
}

export interface RefreshThrottleOptions extends RefreshPacing {
  // 0 <= random() < 1; Math.random by default (tests pass a fixed one)
  random?: () => number;
  timers?: ThrottleTimers;
}

export interface RefreshThrottle {
  // An event or heartbeat asks for a read: paced
  request(): void;
  // Read now, unpaced (an own action, resync, silence, page return); whatever was scheduled is dropped
  flush(): void;
  // Drop whatever is scheduled (the screen is going away)
  cancel(): void;
  // A paced read is scheduled
  readonly pending: boolean;
}

const defaultTimers: ThrottleTimers = {
  setTimeout: (callback, ms) => globalThis.setTimeout(callback, ms),
  clearTimeout: (handle) => globalThis.clearTimeout(handle as ReturnType<typeof setTimeout>),
};

// A random delay in [0, spreadMs]; a broken random() (NaN, out of range) counts as 0 or spreadMs, never beyond
export function spreadDelay(spreadMs: number, random: () => number = Math.random): number {
  if (!(spreadMs > 0)) return 0;
  const value = random();
  const unit = Number.isFinite(value) ? Math.min(Math.max(value, 0), 1) : 0;
  return Math.round(unit * spreadMs);
}

export function createRefreshThrottle(run: () => void, options: RefreshThrottleOptions): RefreshThrottle {
  const { windowMs, spreadMs, random = Math.random, timers = defaultTimers } = options;
  // The scheduled paced read (in its spread delay)
  let fireTimer: unknown;
  // The open window after a paced read, and whether a request came in during it
  let windowTimer: unknown;
  let trailing = false;

  const clearFire = () => {
    if (fireTimer !== undefined) timers.clearTimeout(fireTimer);
    fireTimer = undefined;
  };
  const clearWindow = () => {
    if (windowTimer !== undefined) timers.clearTimeout(windowTimer);
    windowTimer = undefined;
    trailing = false;
  };

  const schedule = () => {
    fireTimer = timers.setTimeout(fire, spreadDelay(spreadMs, random));
  };

  function fire() {
    fireTimer = undefined;
    windowTimer = timers.setTimeout(endWindow, Math.max(0, windowMs));
    run();
  }

  function endWindow() {
    windowTimer = undefined;
    if (!trailing) return;
    trailing = false;
    schedule();
  }

  return {
    request() {
      if (fireTimer !== undefined) return; // already on its way
      if (windowTimer !== undefined) {
        trailing = true;
        return;
      }
      schedule();
    },
    flush() {
      clearFire();
      clearWindow();
      run();
    },
    cancel() {
      clearFire();
      clearWindow();
    },
    get pending() {
      return fireTimer !== undefined || trailing;
    },
  };
}
