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

// The storage of an account that has not opened the app on this device for this long is removed
export const UNUSED_STORAGE_TTL_MS = 90 * 24 * 60 * 60 * 1000;

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

function readJson(storage: Storage, key: string): unknown {
  try {
    const raw = storage.getItem(key);
    return raw === null ? null : JSON.parse(raw);
  } catch {
    return null;
  }
}

// The Telegram user a legacy (un-namespaced) auth store belongs to, or null when it holds no user (signed out, empty)
function legacyOwner(storage: Storage): string | null {
  const stored = readJson(storage, 'auth-storage') as { state?: { user?: { telegram_id?: unknown } | null } } | null;
  const owner = stored?.state?.user?.telegram_id;
  return owner === undefined || owner === null || String(owner) === '' ? null : String(owner);
}

// The stores as the app saved them before per-user storage ("auth-storage", "cart-storage", "address-storage"). They
// move to the current user only when the saved session is provably theirs (its user has their telegram_id). Otherwise
// (another user's, or no user at all, so the owner is unknown) they are removed without being shown to anyone.
export function migrateLegacyStores(storage: Storage, telegramId: string): void {
  if (!USER_STORE_NAMES.some((name) => storage.getItem(name) !== null)) return;
  const isOwn = legacyOwner(storage) === telegramId;
  for (const name of USER_STORE_NAMES) {
    const legacy = storage.getItem(name);
    if (legacy === null) continue;
    const key = userStorageKey(name, telegramId);
    // Already saved per user (written by a newer version of the app): that copy is the current one
    if (isOwn && storage.getItem(key) === null) storage.setItem(key, legacy);
    storage.removeItem(name);
  }
}

// Notes that this user opened the app now, and removes the storage of accounts that have not opened it on this device
// for UNUSED_STORAGE_TTL_MS (a storage that has no date yet is dated now, so it is removed only after that much time).
export function pruneUnusedStorage(storage: Storage, telegramId: string, now: number): void {
  const recorded = readJson(storage, LAST_USED_KEY);
  const lastUsed: Record<string, number> = recorded && typeof recorded === 'object' ? { ...(recorded as Record<string, number>) } : {};
  const owners = new Set<string>();
  for (let i = 0; i < storage.length; i++) {
    const match = USER_KEY.exec(storage.key(i) ?? '');
    if (match) owners.add(match[2]);
  }
  for (const owner of owners) {
    if (typeof lastUsed[owner] !== 'number') lastUsed[owner] = now;
  }
  lastUsed[telegramId] = now;
  for (const [owner, at] of Object.entries(lastUsed)) {
    if (owner === telegramId || now - at <= UNUSED_STORAGE_TTL_MS) continue;
    for (const name of USER_STORE_NAMES) storage.removeItem(userStorageKey(name, owner));
    delete lastUsed[owner];
  }
  storage.setItem(LAST_USED_KEY, JSON.stringify(lastUsed));
}

// Gets the device's storage ready for this page load, before any store reads it: the legacy keys, the last-used date,
// the unused accounts. With no Telegram user nothing on the device is read or changed.
export function prepareUserStorage(storage: Storage | null, telegramId: string | null, now: number = Date.now()): void {
  if (!storage || telegramId === null) return;
  try {
    migrateLegacyStores(storage, telegramId);
    pruneUnusedStorage(storage, telegramId, now);
  } catch {
    // A full or blocked storage: the stores still work, they just start empty
  }
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
