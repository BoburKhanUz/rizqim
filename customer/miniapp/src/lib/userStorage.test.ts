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

describe('data saved before per-user storage (the legacy keys)', () => {
  it('moves all three stores to the user whose session they hold, as they were', () => {
    const device = legacyDevice(B);

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('auth-storage', B))).toBe(authOf(B));
    expect(device.getItem(userStorageKey('cart-storage', B))).toBe(CART);
    expect(device.getItem(userStorageKey('address-storage', B))).toBe(ADDRESSES);
    for (const name of ['auth-storage', 'cart-storage', 'address-storage']) expect(device.getItem(name)).toBeNull();
  });

  it.each([
    ['another Telegram user', A],
    ['nobody (signed out: the owner is unknown)', null],
  ])('removes them when the session is of %s, without moving anything', (_name, owner) => {
    const device = legacyDevice(owner);

    prepareUserStorage(device, B, NOW);

    const left = device.snapshot();
    expect(Object.keys(left).sort()).toEqual([LAST_USED_KEY, 'restaurant-language-store', 'rz_order_sound'].sort());
    expect(JSON.stringify(left)).not.toMatch(/Lavash|Yunusobod|domofon|Aziza/);
  });

  it('a store already saved per user is the current one: the legacy copy does not replace it', () => {
    const device = legacyDevice(B);
    device.setItem(userStorageKey('cart-storage', B), 'newer cart');

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('cart-storage', B))).toBe('newer cart');
    expect(device.getItem('cart-storage')).toBeNull();
  });

  it('runs once: on the next load there is nothing left to move', () => {
    const device = legacyDevice(B);
    prepareUserStorage(device, B, NOW);
    device.setItem(userStorageKey('cart-storage', B), 'changed since');

    prepareUserStorage(device, B, NOW + 1000);

    expect(device.getItem(userStorageKey('cart-storage', B))).toBe('changed since');
  });

  it('with no Telegram user nothing is moved or deleted', () => {
    const device = legacyDevice(A);
    const before = device.snapshot();

    prepareUserStorage(device, null, NOW);

    expect(device.snapshot()).toEqual(before);
  });
});

describe('the storage of accounts that stopped using the device', () => {
  it('is removed after 90 days without a launch; recent ones and the device settings stay', () => {
    const device = new MemoryStorage();
    for (const name of ['auth-storage', 'cart-storage', 'address-storage']) {
      device.setItem(userStorageKey(name, A), `${name} of A`);
      device.setItem(userStorageKey(name, '300'), `${name} of 300`);
    }
    device.setItem('restaurant-language-store', 'ru');
    device.setItem(LAST_USED_KEY, JSON.stringify({ [A]: NOW - UNUSED_STORAGE_TTL_MS - DAY, 300: NOW - 10 * DAY }));

    prepareUserStorage(device, B, NOW);

    const left = device.snapshot();
    expect(Object.keys(left).filter((key) => key.endsWith(`:${A}`))).toEqual([]);
    expect(left[userStorageKey('cart-storage', '300')]).toBe('cart-storage of 300');
    expect(left['restaurant-language-store']).toBe('ru');
    expect(JSON.parse(left[LAST_USED_KEY])).toEqual({ 300: NOW - 10 * DAY, [B]: NOW });
  });

  it('a storage with no date yet is dated now, not removed', () => {
    const device = new MemoryStorage();
    device.setItem(userStorageKey('cart-storage', A), 'cart of A');

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('cart-storage', A))).toBe('cart of A');
    expect(JSON.parse(device.getItem(LAST_USED_KEY) ?? '{}')).toEqual({ [A]: NOW, [B]: NOW });

    // ... and removed once it has gone unused for long enough
    prepareUserStorage(device, B, NOW + UNUSED_STORAGE_TTL_MS + DAY);
    expect(device.getItem(userStorageKey('cart-storage', A))).toBeNull();
  });

  it('the current user\'s own storage is never removed, however old its date', () => {
    const device = new MemoryStorage();
    device.setItem(userStorageKey('cart-storage', B), 'cart of B');
    device.setItem(LAST_USED_KEY, JSON.stringify({ [B]: 0 }));

    prepareUserStorage(device, B, NOW);

    expect(device.getItem(userStorageKey('cart-storage', B))).toBe('cart of B');
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
