// Fills window.Telegram.WebApp from Telegram's launch parameters before anything below reads it. (index.html loads
// telegram-web-app.js as a blocking script too; the bundled copy covers the tests and a page where that script did not
// load.) Importing it here, and this module from every per-user store, means the Telegram user is known before any
// store is created, in the app, the unit tests and the e2e tests alike.
import '@twa-dev/sdk';
import { createJSONStorage, type StateStorage } from 'zustand/middleware';

// Per-user storage. The Mini App's localStorage belongs to the device (the Telegram app), not to the Telegram account:
// every account on the phone, every open Telegram Web tab and every Telegram Desktop account shares it. So every
// store that holds one person's data is saved under a key of its own per Telegram user ("cart-storage:<telegram id>"):
// one account can never read or overwrite another's, nothing needs deleting when accounts take turns, and each person
// finds their own cart and addresses again when they come back. The Telegram user is chosen once per page load, here.
// Device settings (the language, the order-sound switch, the dev server's mock account) are not per user.

// The stores that hold one person's data: the session (token, user, store), the cart (lines, the note, the open
// checkout attempt with its body) and the saved addresses (device-only: home address, intercom, ...)
export const USER_STORE_NAMES = ['auth-storage', 'cart-storage', 'address-storage'] as const;
export type UserStoreName = (typeof USER_STORE_NAMES)[number];

// The dev server's stand-in for a Telegram account outside Telegram (useTelegramAuth signs in with it)
export const MOCK_TELEGRAM_ID_KEY = 'mock_telegram_id';

// When each Telegram user's storage was last opened on this device ({ "<telegram id>": epoch ms })
export const LAST_USED_KEY = 'user-storage-last-used';

// When each account was first seen unused for UNUSED_STORAGE_TTL_MS ({ "<telegram id>": epoch ms }): it is removed only
// when a launch at least PRUNE_CONFIRM_MS later sees it unused again (see pruneUnusedStorage)
export const EXPIRING_KEY = 'user-storage-expiring';

// The storage of an account that has not opened the app on this device for this long is removed
export const UNUSED_STORAGE_TTL_MS = 90 * 24 * 60 * 60 * 1000;

// How far apart (by the dates recorded on the device) the two launches that see an account unused must be
export const PRUNE_CONFIRM_MS = 24 * 60 * 60 * 1000;

const USER_KEY = new RegExp(`^(${USER_STORE_NAMES.join('|')}):(.+)$`);

function deviceStorage(): Storage | null {
  try {
    return window.localStorage;
  } catch {
    return null; // storage blocked (some in-app browsers, private modes)
  }
}

// The Telegram user who has the app open, from this launch's initData (window.Telegram, filled by the SDK import above).
export function telegramUserIdOfLaunch(): string | null {
  const id = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
  return id === undefined || id === null || String(id) === '' ? null : String(id);
}

// Whose storage this page load uses: the Telegram user of initData; on the dev server outside Telegram, the mock account
// (created here when there is none yet, so the sign-in and the storage agree from the first load); else (a production
// build with no Telegram user) nobody: the per-user stores then live in memory only and nothing on the device is touched.
export function resolveLaunchTelegramId(storage: Storage | null = deviceStorage()): string | null {
  const id = telegramUserIdOfLaunch();
  if (id !== null) return id;
  if (!import.meta.env.DEV) return null;
  try {
    let mockId = storage?.getItem(MOCK_TELEGRAM_ID_KEY) ?? null;
    if (!mockId && storage) {
      mockId = String(Math.floor(10000000 + Math.random() * 90000000));
      storage.setItem(MOCK_TELEGRAM_ID_KEY, mockId);
    }
    return mockId;
  } catch {
    return null;
  }
}

// The device key of a per-user store for a Telegram user
export function userStorageKey(name: string, telegramId: string): string {
  return `${name}:${telegramId}`;
}

// Each storage call on its own: a storage that throws (full, blocked, broken) fails that one call, never the next
function attempt<T>(run: () => T, fallback: T): T {
  try {
    return run();
  } catch {
    return fallback;
  }
}

function parse(raw: string | null): unknown {
  return raw === null ? null : attempt(() => JSON.parse(raw) as unknown, null);
}

function readJson(storage: Storage, key: string): unknown {
  return parse(attempt(() => storage.getItem(key), null));
}

// The Telegram user a saved session (auth store) belongs to, or null when it holds no user (signed out, empty, broken)
function sessionOwner(raw: string | null): string | null {
  const stored = parse(raw) as { state?: { user?: { telegram_id?: unknown } | null } } | null;
  const owner = stored?.state?.user?.telegram_id;
  return owner === undefined || owner === null || String(owner) === '' ? null : String(owner);
}

// A saved auth store holds a session (or a part of one): a token, a user or the signed-in flag
function holdsSession(raw: string | null): boolean {
  const state = (parse(raw) as { state?: { token?: unknown; user?: unknown; isAuthenticated?: unknown } } | null)?.state;
  return Boolean(state && (state.token || state.user || state.isAuthenticated));
}

// The stores as the app saved them before per-user storage ("auth-storage", "cart-storage", "address-storage").
//  - The cart and the addresses are always removed, never shown or moved: the old app did not clear them when the
//    account changed, so they may be someone else's than the session's user (and the product had no real users yet).
//  - The session names its own user: it moves to that user's storage ("auth-storage:<its telegram_id>"), whoever has
//    the app open now, unless that storage already holds a session (a newer version wrote it: that one is current).
//    A session with no user (signed out) is removed.
// Every step stands on its own against a storage that throws (full, private mode): each key is removed whether or not
// anything else (the copy included) worked. What a failed removal leaves can never become anyone's: no store reads the
// legacy keys, and the next launch removes the cart and addresses again and moves the session to the same owner again.
export function migrateLegacyStores(storage: Storage): void {
  for (const name of ['cart-storage', 'address-storage']) attempt(() => storage.removeItem(name), undefined);
  const legacy = attempt(() => storage.getItem('auth-storage'), null);
  if (legacy === null) return;
  const owner = sessionOwner(legacy);
  if (owner !== null) {
    const key = userStorageKey('auth-storage', owner);
    if (!holdsSession(attempt(() => storage.getItem(key), null))) attempt(() => storage.setItem(key, legacy), undefined);
  }
  attempt(() => storage.removeItem('auth-storage'), undefined);
}

function readStamps(storage: Storage, key: string): Record<string, number> {
  const recorded = readJson(storage, key);
  const stamps: Record<string, number> = {};
  if (recorded && typeof recorded === 'object') {
    for (const [owner, at] of Object.entries(recorded as Record<string, unknown>)) {
      if (typeof at === 'number' && Number.isFinite(at)) stamps[owner] = at;
    }
  }
  return stamps;
}

// Notes that this user opened the app now, and removes the storage of accounts that have not opened it on this device
// for UNUSED_STORAGE_TTL_MS. The device clock can be wrong, so:
//  - a date later than now (recorded by a clock that was ahead) is brought back to now;
//  - a launch whose clock is earlier than the newest recorded date (the clock was set back) prunes nothing;
//  - an account is removed only when it is seen unused on two launches at least PRUNE_CONFIRM_MS apart by the dates
//    recorded here (the first sighting is noted under EXPIRING_KEY): one launch with a clock set far ahead marks the
//    others, but removes nobody, and a launch with the right time then finds them in use again and drops the marks;
//  - the current user is never removed, and a storage that has no date yet is dated now.
export function pruneUnusedStorage(storage: Storage, telegramId: string, now: number): void {
  const lastUsed = readStamps(storage, LAST_USED_KEY);
  const expiring = readStamps(storage, EXPIRING_KEY);
  const newest = Math.max(-Infinity, ...Object.values(lastUsed), ...Object.values(expiring));
  const clockSetBack = newest > now;
  for (const stamps of [lastUsed, expiring]) {
    for (const [owner, at] of Object.entries(stamps)) if (at > now) stamps[owner] = now;
  }

  const owners = new Set<string>();
  const count = attempt(() => storage.length, 0);
  for (let i = 0; i < count; i++) {
    const match = USER_KEY.exec(attempt(() => storage.key(i), null) ?? '');
    if (match) owners.add(match[2]);
  }
  for (const owner of owners) {
    if (lastUsed[owner] === undefined) lastUsed[owner] = now;
  }
  lastUsed[telegramId] = now;
  delete expiring[telegramId];

  const removed: string[] = [];
  if (!clockSetBack) {
    for (const [owner, at] of Object.entries(lastUsed)) {
      if (owner === telegramId) continue;
      if (now - at <= UNUSED_STORAGE_TTL_MS) {
        delete expiring[owner];
      } else if (expiring[owner] === undefined) {
        expiring[owner] = now;
      } else if (now - expiring[owner] >= PRUNE_CONFIRM_MS) {
        removed.push(owner);
      }
    }
  }

  // The dates first: if they cannot be written (a full storage), nothing is removed on the strength of them
  for (const owner of removed) {
    delete lastUsed[owner];
    delete expiring[owner];
  }
  storage.setItem(LAST_USED_KEY, JSON.stringify(lastUsed));
  if (Object.keys(expiring).length > 0) storage.setItem(EXPIRING_KEY, JSON.stringify(expiring));
  else storage.removeItem(EXPIRING_KEY);
  for (const owner of removed) {
    for (const name of USER_STORE_NAMES) attempt(() => storage.removeItem(userStorageKey(name, owner)), undefined);
  }
}

// Gets the device's storage ready for this page load, before any store reads it: the legacy keys, the last-used date,
// the unused accounts. With no Telegram user nothing on the device is read or changed. Each step on its own: a storage
// that throws in one (full, private mode) does not stop the other, and the stores still work (they just start empty).
export function prepareUserStorage(storage: Storage | null, telegramId: string | null, now: number = Date.now()): void {
  if (!storage || telegramId === null) return;
  attempt(() => migrateLegacyStores(storage), undefined);
  attempt(() => pruneUnusedStorage(storage, telegramId, now), undefined);
}

function memoryStorage(): StateStorage {
  const items = new Map<string, string>();
  return {
    getItem: (name) => items.get(name) ?? null,
    setItem: (name, value) => {
      items.set(name, value);
    },
    removeItem: (name) => {
      items.delete(name);
    },
  };
}

// The zustand storage of a per-user store: the device's localStorage under the user's own keys, or, with no Telegram
// user (or no usable localStorage), memory only for this page load.
export function createUserStateStorage(telegramId: string | null, storage: Storage | null): StateStorage {
  if (telegramId === null || !storage) return memoryStorage();
  return {
    getItem: (name) => storage.getItem(userStorageKey(name, telegramId)),
    setItem: (name, value) => storage.setItem(userStorageKey(name, telegramId), value),
    removeItem: (name) => storage.removeItem(userStorageKey(name, telegramId)),
  };
}

// --- This page load -------------------------------------------------------------------------------------------------

// The Telegram user whose storage this page load uses (null: none, memory only). Fixed for the page load: Telegram
// launches a new page for another account.
export const launchTelegramId: string | null = resolveLaunchTelegramId();

prepareUserStorage(deviceStorage(), launchTelegramId);

const userStateStorage = createUserStateStorage(launchTelegramId, deviceStorage());

// The `storage` option of every per-user persisted store
export function userPersistStorage<S>() {
  return createJSONStorage<S>(() => userStateStorage);
}
