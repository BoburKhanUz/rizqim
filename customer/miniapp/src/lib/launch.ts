// How old this launch's Telegram data (initData) is, and whether it can still sign anyone in.
//
// The backend accepts initData for 10 minutes after Telegram created it (its auth_date; backend
// app/Actions/Auth/ValidateTelegramInitDataAction.php) and answers 401 after that. A reload of the page keeps the same
// initData, so once a launch is that old no sign-in of this page can succeed: only closing the Mini App and opening it
// again from Telegram brings new launch data. The app then says so (the "close and reopen" screen, components/Feedback.tsx)
// instead of offering a reload that cannot work.
//
// Two signals, either one is enough:
//  - the server refused this launch's initData (a 401 to POST /auth/telegram): it will refuse it again, so it is never
//    posted again in this page (lib/telegramSignIn.ts);
//  - the age by the device clock (now - auth_date) is past LAUNCH_STALE_AFTER_S, a little under the server's limit. The
//    device clock can be off, so this only chooses what a failed sign-in screen offers; a sign-in is still tried (the
//    server decides).

// The server's limit, and the age from which the app treats a failed sign-in as "too old" (a minute of margin)
export const LAUNCH_MAX_AGE_S = 600;
export const LAUNCH_STALE_AFTER_S = 540;

let refused = false;

// The auth_date (epoch seconds) of an initData query string, or null when it has none
export function authDateOf(initData: string | null | undefined): number | null {
  if (!initData) return null;
  try {
    const value = Number(new URLSearchParams(initData).get('auth_date') ?? NaN);
    return Number.isFinite(value) && value > 0 ? value : null;
  } catch {
    return null;
  }
}

// The launch data Telegram handed this page (window.Telegram.WebApp is filled by the SDK, lib/userStorage.ts)
function launchInitData(): string | null {
  try {
    return window.Telegram?.WebApp?.initData || null;
  } catch {
    return null;
  }
}

// How many seconds ago Telegram created this launch's data, by the device clock (null: unknown, no auth_date)
export function launchAgeSeconds(now: number = Date.now(), initData: string | null = launchInitData()): number | null {
  const authDate = authDateOf(initData);
  return authDate === null ? null : now / 1000 - authDate;
}

// The server refused this launch's initData (401 to POST /auth/telegram)
export function noteLaunchRefused(): void {
  refused = true;
}

export function isLaunchRefused(): boolean {
  return refused;
}

// No sign-in with this launch's data can succeed any more: refused by the server, or older than LAUNCH_STALE_AFTER_S
export function isLaunchTooOld(now: number = Date.now()): boolean {
  if (refused) return true;
  const age = launchAgeSeconds(now);
  return age !== null && age > LAUNCH_STALE_AFTER_S;
}

// A new page load, as far as this module knows (tests)
export function resetLaunch(): void {
  refused = false;
}
