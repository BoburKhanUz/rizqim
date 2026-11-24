import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  createUserStateStorage,
  LAST_USED_KEY,
  MOCK_TELEGRAM_ID_KEY,
  prepareUserStorage,
  resolveLaunchTelegramId,
  UNUSED_STORAGE_TTL_MS,
  userStorageKey,
} from './userStorage';

// A device's localStorage of its own for each test
class MemoryStorage implements Storage {
  private items = new Map<string, string>();
  get length() {
    return this.items.size;
  }
  clear() {
    this.items.clear();
  }
  getItem(key: string) {
    return this.items.get(key) ?? null;
  }
  key(index: number) {
    return [...this.items.keys()][index] ?? null;
  }
  removeItem(key: string) {
    this.items.delete(key);
  }
  setItem(key: string, value: string) {
    this.items.set(key, String(value));
  }
  snapshot() {
    return Object.fromEntries(this.items);
  }
}

const A = '4200042';
const B = '7100001';
const NOW = Date.parse('2026-11-24T10:00:00Z');
const DAY = 24 * 60 * 60 * 1000;

const authOf = (telegramId: string | null, token: string | null = 'token') =>
  JSON.stringify({
    state: { token, user: telegramId === null ? null : { id: 1, telegram_id: telegramId, name: 'Aziza' }, store: null, isAuthenticated: telegramId !== null, smsVerificationRequired: false },
    version: 0,
  });
const CART = JSON.stringify({ state: { items: [{ id: 'v1', name: 'Lavash' }], specialInstructions: 'domofon 42', checkoutAttempt: { key: 'k', fingerprint: '{}' } }, version: 0 });
const ADDRESSES = JSON.stringify({ state: { addresses: [{ id: 9, address: 'Yunusobod 4', intercom: '42' }], selectedAddressId: 9 }, version: 1 });

function legacyDevice(telegramIdOfSession: string | null) {
  const storage = new MemoryStorage();
  storage.setItem('auth-storage', authOf(telegramIdOfSession, telegramIdOfSession === null ? null : 'token'));
  storage.setItem('cart-storage', CART);
  storage.setItem('address-storage', ADDRESSES);
  storage.setItem('restaurant-language-store', '{"state":{"language":"ru"},"version":0}');
  storage.setItem('rz_order_sound', 'off');
  return storage;
}

function setLaunchUser(id: number | undefined) {
  Object.defineProperty(window, 'Telegram', { configurable: true, value: { WebApp: { initDataUnsafe: id === undefined ? {} : { user: { id } } } } });
}

afterEach(() => {
  Reflect.deleteProperty(window, 'Telegram');
});

describe('whose storage a page load uses', () => {
  it('the Telegram user of initData', () => {
    setLaunchUser(Number(B));
    expect(resolveLaunchTelegramId(new MemoryStorage())).toBe(B);
  });

  it('the dev server outside Telegram: the mock account, created on the first load', () => {
    setLaunchUser(undefined);
    const storage = new MemoryStorage();

    const first = resolveLaunchTelegramId(storage);

    expect(first).toMatch(/^\d{8}$/);
    expect(storage.getItem(MOCK_TELEGRAM_ID_KEY)).toBe(first);
    expect(resolveLaunchTelegramId(storage)).toBe(first);
  });

  it('a production build with no Telegram user: nobody (and nothing is written)', () => {
    vi.stubEnv('DEV', false);
    setLaunchUser(undefined);
    const storage = new MemoryStorage();

    expect(resolveLaunchTelegramId(storage)).toBeNull();
    expect(storage.length).toBe(0);
  });
});

describe('the storage of a per-user store', () => {
  it('keeps each Telegram user under keys of their own: one never reads or overwrites the other', () => {
    const device = new MemoryStorage();
    const ofA = createUserStateStorage(A, device);
    const ofB = createUserStateStorage(B, device);

    ofA.setItem('cart-storage', 'cart of A');
    ofB.setItem('cart-storage', 'cart of B');

    expect(ofA.getItem('cart-storage')).toBe('cart of A');
    expect(ofB.getItem('cart-storage')).toBe('cart of B');
    expect(device.snapshot()).toEqual({ [`cart-storage:${A}`]: 'cart of A', [`cart-storage:${B}`]: 'cart of B' });

    ofB.removeItem('cart-storage');
    expect(ofA.getItem('cart-storage')).toBe('cart of A');
  });

  it('with no Telegram user: memory only, the device is neither read nor written', () => {
    const device = legacyDevice(A);
    const before = device.snapshot();
    const memory = createUserStateStorage(null, device);

    expect(memory.getItem('auth-storage')).toBeNull();
    memory.setItem('cart-storage', 'cart');
    expect(memory.getItem('cart-storage')).toBe('cart');
    expect(device.snapshot()).toEqual(before);
  });
});

// A device whose localStorage fails the way real ones do: a full quota (setItem throws), a broken key (getItem /
// removeItem throw for it). `failOn` names the keys that fail; '*' fails every key.
class FailingStorage extends MemoryStorage {
  private readonly fail: { set?: string[]; get?: string[]; remove?: string[] };
  constructor(fail: { set?: string[]; get?: string[]; remove?: string[] }) {
    super();
    this.fail = fail;
  }
  private fails(list: string[] | undefined, key: string) {
    return Boolean(list && (list.includes('*') || list.includes(key)));
  }
  getItem(key: string) {
    if (this.fails(this.fail.get, key)) throw new DOMException('broken', 'SecurityError');
    return super.getItem(key);
  }
  setItem(key: string, value: string) {
    if (this.fails(this.fail.set, key)) throw new DOMException('full', 'QuotaExceededError');
    super.setItem(key, value);
  }
  removeItem(key: string) {
    if (this.fails(this.fail.remove, key)) throw new DOMException('broken', 'SecurityError');
    super.removeItem(key);
  }
  seed(key: string, value: string) {
    super.setItem(key, value);
  }
}

function seedLegacy(storage: FailingStorage, owner: string | null) {
  storage.seed('auth-storage', authOf(owner, owner === null ? null : 'token'));
  storage.seed('cart-storage', CART);
  storage.seed('address-storage', ADDRESSES);
}

const LEGACY = ['auth-storage', 'cart-storage', 'address-storage'];

// The old app never cleared the cart and the addresses when the account changed, so whose they are is unknown: they are
// always removed. The saved session names its own user (telegram_id): it moves to that user's storage, whoever launches.
describe('data saved before per-user storage (the legacy keys)', () => {
  it.each([
    ['its own user', B],
    ['another Telegram user', A],
  ])('the cart and the addresses are removed, never moved, even when the app is opened by %s', (_name, launcher) => {
    const device = legacyDevice(B);

    prepareUserStorage(device, launcher, NOW);

    for (const owner of [A, B]) {
      expect(device.getItem(userStorageKey('cart-storage', owner))).toBeNull();
      expect(device.getItem(userStorageKey('address-storage', owner))).toBeNull();
    }
    for (const name of LEGACY) expect(device.getItem(name)).toBeNull();
    expect(JSON.stringify(device.snapshot())).not.toMatch(/Lavash|Yunusobod|domofon/);
  });

  it.each([
    ['its own user', B],
    ['another Telegram user', A],
  ])("the saved session moves to its user's storage when the app is opened by %s", (_name, launcher) => {
    const device = legacyDevice(B);

    prepareUserStorage(device, launcher, NOW);

    expect(device.getItem(userStorageKey('auth-storage', B))).toBe(authOf(B));
    if (launcher !== B) expect(device.getItem(userStorageKey('auth-storage', launcher))).toBeNull();
    expect(device.getItem('auth-storage')).toBeNull();
    expect(device.getItem('restaurant-language-store')).toBe('{"state":{"language":"ru"},"version":0}');
  });

  it('a saved session with no user (signed out: the owner is unknown) is removed', () => {
    const device = legacyDevice(null);

    prepareUserStorage(device, B, NOW);

    const left = device.snapshot();
    expect(Object.keys(left).sort()).toEqual([LAST_USED_KEY, 'restaurant-language-store', 'rz_order_sound'].sort());
  });

  it("a session already saved in its user's storage (by a newer version) is the current one: the legacy one is dropped", () => {
    const device = legacyDevice(B);
    const newer = authOf(B, 'newer-token');
    device.setItem(userStorageKey('auth-storage', B), newer);

    prepareUserStorage(device, A, NOW);

    expect(device.getItem(userStorageKey('auth-storage', B))).toBe(newer);
    expect(device.getItem('auth-storage')).toBeNull();
  });

  it("a signed-out storage of that user holds no session: the legacy session moves in", () => {
    const device = legacyDevice(B);
    device.setItem(userStorageKey('auth-storage', B), authOf(null, null));

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('auth-storage', B))).toBe(authOf(B));
  });

  it('runs once: on the next load there is nothing left to move', () => {
    const device = legacyDevice(B);
    prepareUserStorage(device, B, NOW);
    device.setItem(userStorageKey('auth-storage', B), 'changed since');

    prepareUserStorage(device, B, NOW + 1000);

    expect(device.getItem(userStorageKey('auth-storage', B))).toBe('changed since');
  });

  it('with no Telegram user nothing is moved or deleted', () => {
    const device = legacyDevice(A);
    const before = device.snapshot();

    prepareUserStorage(device, null, NOW);

    expect(device.snapshot()).toEqual(before);
  });

  describe('a storage that throws midway', () => {
    it('a full storage (the copy fails): every legacy key is still removed, nothing is thrown', () => {
      const device = new FailingStorage({ set: ['*'] });
      seedLegacy(device, B);

      expect(() => prepareUserStorage(device, B, NOW)).not.toThrow();

      expect(device.snapshot()).toEqual({});
    });

    it('one key cannot be removed: the others are removed all the same', () => {
      const device = new FailingStorage({ remove: ['cart-storage'] });
      seedLegacy(device, A);

      expect(() => prepareUserStorage(device, B, NOW)).not.toThrow();

      expect(device.getItem('address-storage')).toBeNull();
      expect(device.getItem('auth-storage')).toBeNull();
      expect(device.getItem(userStorageKey('auth-storage', A))).toBe(authOf(A));
      // What is left can never become anyone's: no store reads the legacy keys, and the next launch removes it again
      expect(device.getItem(userStorageKey('cart-storage', A))).toBeNull();
      expect(device.getItem(userStorageKey('cart-storage', B))).toBeNull();
    });

    it("the legacy session cannot be removed: it was copied to its owner, so a later launch only moves it there again", () => {
      const device = new FailingStorage({ remove: ['auth-storage'] });
      seedLegacy(device, A);

      prepareUserStorage(device, B, NOW);
      prepareUserStorage(device, B, NOW + 1000);

      expect(device.getItem(userStorageKey('auth-storage', A))).toBe(authOf(A));
      expect(device.getItem(userStorageKey('auth-storage', B))).toBeNull();
    });

    it('a storage that cannot even be read: nothing is thrown, the stores still work', () => {
      const device = new FailingStorage({ get: ['*'] });
      seedLegacy(device, B);

      expect(() => prepareUserStorage(device, B, NOW)).not.toThrow();

      const storage = createUserStateStorage(B, new MemoryStorage());
      storage.setItem('cart-storage', 'cart');
      expect(storage.getItem('cart-storage')).toBe('cart');
    });

    it('the date cannot be written (quota): the legacy keys are removed and nothing is pruned', () => {
      const device = new FailingStorage({ set: [LAST_USED_KEY] });
      seedLegacy(device, null);
      device.seed(userStorageKey('cart-storage', A), 'cart of A');
      device.seed(LAST_USED_KEY, JSON.stringify({ [A]: NOW - UNUSED_STORAGE_TTL_MS - 10 * DAY }));

      expect(() => prepareUserStorage(device, B, NOW)).not.toThrow();

      for (const name of LEGACY) expect(device.getItem(name)).toBeNull();
      expect(device.getItem(userStorageKey('cart-storage', A))).toBe('cart of A');
    });
  });
});

// An account's storage is removed only once it is seen unused for 90 days on two launches at least a day apart (by the
// dates recorded on the device), so one launch with a clock set far ahead cannot wipe the other accounts.
describe('the storage of accounts that stopped using the device', () => {
  function deviceWith(stamps: Record<string, number>) {
    const device = new MemoryStorage();
    for (const owner of Object.keys(stamps)) {
      for (const name of ['auth-storage', 'cart-storage', 'address-storage']) device.setItem(userStorageKey(name, owner), `${name} of ${owner}`);
    }
    device.setItem('restaurant-language-store', 'ru');
    device.setItem(LAST_USED_KEY, JSON.stringify(stamps));
    return device;
  }
  const keysOf = (device: MemoryStorage, owner: string) => Object.keys(device.snapshot()).filter((key) => key.endsWith(`:${owner}`));
  const stamps = (device: MemoryStorage) => JSON.parse(device.getItem(LAST_USED_KEY) ?? '{}') as Record<string, number>;

  it('is removed once seen unused for 90 days on two launches a day apart; recent ones and the device settings stay', () => {
    const device = deviceWith({ [A]: NOW - UNUSED_STORAGE_TTL_MS - DAY, 300: NOW - 10 * DAY });

    prepareUserStorage(device, B, NOW);
    expect(keysOf(device, A)).toHaveLength(3); // seen expired once: kept

    prepareUserStorage(device, B, NOW + DAY);

    const left = device.snapshot();
    expect(keysOf(device, A)).toEqual([]);
    expect(left[userStorageKey('cart-storage', '300')]).toBe('cart-storage of 300');
    expect(left['restaurant-language-store']).toBe('ru');
    expect(stamps(device)).toEqual({ 300: NOW - 10 * DAY, [B]: NOW + DAY });
  });

  it('two launches less than a day apart are not enough', () => {
    const device = deviceWith({ [A]: NOW - UNUSED_STORAGE_TTL_MS - DAY });

    prepareUserStorage(device, B, NOW);
    prepareUserStorage(device, B, NOW + DAY - 1000);

    expect(keysOf(device, A)).toHaveLength(3);
  });

  it('one launch with the clock set far ahead wipes nobody, and the next launch with the right time keeps them all', () => {
    const device = deviceWith({ [A]: NOW - 10 * DAY, 300: NOW - DAY });
    const FAR_AHEAD = NOW + 5 * 365 * DAY;

    prepareUserStorage(device, B, FAR_AHEAD);
    expect(keysOf(device, A)).toHaveLength(3);
    expect(keysOf(device, '300')).toHaveLength(3);

    // The clock is right again: B's date from the future is brought back to now, and nothing is removed then or later
    prepareUserStorage(device, B, NOW);
    expect(stamps(device)[B]).toBe(NOW);
    prepareUserStorage(device, B, NOW + 2 * DAY);
    expect(keysOf(device, A)).toHaveLength(3);
    expect(keysOf(device, '300')).toHaveLength(3);
  });

  it('a launch whose clock is earlier than the newest recorded date (set back) prunes nothing', () => {
    const device = deviceWith({ [A]: NOW - UNUSED_STORAGE_TTL_MS - 30 * DAY, 300: NOW - DAY });
    prepareUserStorage(device, B, NOW); // A seen unused once
    // 300 then opened the app (in another tab) with a clock running ahead: its date is later than the next launch's now
    device.setItem(LAST_USED_KEY, JSON.stringify({ ...stamps(device), 300: NOW + 2 * DAY + 60 * 60 * 1000 }));

    // Two days after A was first seen unused, but earlier than 300's date: this clock is not trusted to prune
    prepareUserStorage(device, B, NOW + 2 * DAY);
    expect(keysOf(device, A)).toHaveLength(3);

    prepareUserStorage(device, B, NOW + 3 * DAY);
    expect(keysOf(device, A)).toEqual([]);
  });

  it('dates in the future are brought back to now', () => {
    const device = deviceWith({ [A]: NOW + 400 * DAY, 300: NOW - DAY });

    prepareUserStorage(device, B, NOW);

    expect(stamps(device)).toEqual({ [A]: NOW, 300: NOW - DAY, [B]: NOW });
    // ... so A is pruned 90 days after now, not 90 days after the date from the future
    prepareUserStorage(device, B, NOW + UNUSED_STORAGE_TTL_MS + DAY);
    prepareUserStorage(device, B, NOW + UNUSED_STORAGE_TTL_MS + 2 * DAY);
    expect(keysOf(device, A)).toEqual([]);
  });

  it('a storage with no date yet is dated now, not removed', () => {
    const device = new MemoryStorage();
    device.setItem(userStorageKey('cart-storage', A), 'cart of A');

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('cart-storage', A))).toBe('cart of A');
    expect(stamps(device)).toEqual({ [A]: NOW, [B]: NOW });

    // ... and removed once it has gone unused for long enough (seen so twice, a day apart)
    prepareUserStorage(device, B, NOW + UNUSED_STORAGE_TTL_MS + DAY);
    prepareUserStorage(device, B, NOW + UNUSED_STORAGE_TTL_MS + 2 * DAY);
    expect(device.getItem(userStorageKey('cart-storage', A))).toBeNull();
  });

  it('an account that comes back before the second sighting is kept', () => {
    const device = deviceWith({ [A]: NOW - UNUSED_STORAGE_TTL_MS - DAY });

    prepareUserStorage(device, B, NOW);
    prepareUserStorage(device, A, NOW + 1000);
    prepareUserStorage(device, B, NOW + 2 * DAY);

    expect(keysOf(device, A)).toHaveLength(3);
  });

  it("the current user's own storage is never removed, however old its date", () => {
    const device = deviceWith({ [B]: 0 });

    prepareUserStorage(device, B, NOW);
    prepareUserStorage(device, B, NOW + 2 * DAY);

    expect(device.getItem(userStorageKey('cart-storage', B))).toBe(`cart-storage of ${B}`);
  });
});

describe('the stores of a page load', () => {
  it('are opened under the keys of the Telegram user of the launch, chosen before any store exists', async () => {
    vi.resetModules();
    setLaunchUser(Number(A));
    localStorage.setItem(userStorageKey('cart-storage', A), JSON.stringify({ state: { items: [], specialInstructions: 'note of A' }, version: 0 }));
    localStorage.setItem(userStorageKey('cart-storage', B), JSON.stringify({ state: { items: [], specialInstructions: 'note of B' }, version: 0 }));

    const { useCartStore } = await import('../store/cartStore');
    const { launchTelegramId } = await import('./userStorage');

    expect(launchTelegramId).toBe(A);
    expect(useCartStore.getState().specialInstructions).toBe('note of A');
    useCartStore.getState().setSpecialInstructions('changed by A');
    expect(JSON.parse(localStorage.getItem(userStorageKey('cart-storage', B)) ?? '{}').state.specialInstructions).toBe('note of B');
  });
});
