// The backend accepts a 2FA code once, and no older one after it (also inside the same 30 s window). A person who signs in and, a
// moment later, is asked to confirm a sensitive action types the same code from the app again: it can only be refused. The last
// accepted code is remembered here, in memory only and for 90 seconds (a code is good for about that long), so the screens can say
// "that code was just used, wait for the next one" without asking the server.

const KEEP_MS = 90_000;
let last: { code: string; at: number } | null = null;

export function rememberAcceptedCode(code: string, at = Date.now()): void {
  last = { code, at };
}

export function wasJustUsed(code: string, at = Date.now()): boolean {
  return last !== null && last.code === code && at - last.at < KEEP_MS;
}

export function forgetAcceptedCode(): void {
  last = null;
}
