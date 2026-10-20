import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The Telegram WebApp object, replaced per test
const { telegram } = vi.hoisted(() => ({ telegram: { tg: {} as Record<string, unknown> } }));
vi.mock('../../lib/telegram', () => telegram);

import { GeolocationError, getCurrentPosition } from './geolocation';
import type { GeolocationFailure } from './geolocation';

interface LocationManagerMock {
  readonly isInited: boolean;
  readonly isLocationAvailable: boolean;
  init: ReturnType<typeof vi.fn>;
  getLocation: ReturnType<typeof vi.fn>;
}

// A LocationManager that behaves like the one of Telegram's SDK (telegram-web-apps.js):
// - init() calls back only the first time. Once initialised it returns at once and never calls back again.
// - isLocationAvailable is only true after init, and getLocation() throws before it or without location services.
// - getLocation() calls back with `location` (null when the customer refused).
function locationManager(location: { latitude: number; longitude: number } | null, available = true): LocationManagerMock {
  let inited = false;
  return {
    get isInited() {
      return inited;
    },
    get isLocationAvailable() {
      return inited && available;
    },
    init: vi.fn((callback?: () => void) => {
      if (inited) return;
      inited = true;
      callback?.();
    }),
    getLocation: vi.fn((callback: (data: typeof location) => void) => {
      if (!inited) throw new Error('WebAppLocationManagerNotInited');
      if (!available) throw new Error('WebAppLocationManagerLocationNotAvailable');
      callback(location);
    }),
  };
}

function useTelegram(version: string, manager?: LocationManagerMock) {
  telegram.tg = {
    isVersionAtLeast: (required: string) => parseFloat(required) <= parseFloat(version),
    LocationManager: manager,
  };
}

// navigator.geolocation: jsdom has none
function browserLocation(outcome: { latitude: number; longitude: number } | { code: number }) {
  const getCurrentPositionMock = vi.fn((success: (position: unknown) => void, failure: (error: unknown) => void) => {
    if ('code' in outcome) {
      failure({ code: outcome.code, PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 });
    } else {
      success({ coords: { latitude: outcome.latitude, longitude: outcome.longitude } });
    }
  });
  Object.defineProperty(navigator, 'geolocation', { value: { getCurrentPosition: getCurrentPositionMock }, configurable: true });
  return getCurrentPositionMock;
}

async function failureOf(promise: Promise<unknown>): Promise<GeolocationFailure> {
  try {
    await promise;
  } catch (err) {
    expect(err).toBeInstanceOf(GeolocationError);
    return (err as GeolocationError).reason;
  }
  throw new Error('Expected the position request to fail');
}

beforeEach(() => {
  telegram.tg = {};
});

afterEach(() => {
  Reflect.deleteProperty(navigator, 'geolocation');
  vi.useRealTimers();
});

describe('inside Telegram (Bot API 8.0+)', () => {
  it("uses Telegram's own location access and never touches the browser API", async () => {
    const manager = locationManager({ latitude: 41.3275, longitude: 69.2818 });
    useTelegram('8.0', manager);
    const browser = browserLocation({ latitude: 1, longitude: 1 });

    await expect(getCurrentPosition()).resolves.toEqual({ lat: 41.3275, lng: 69.2818 });

    expect(manager.init).toHaveBeenCalledTimes(1);
    expect(manager.getLocation).toHaveBeenCalledTimes(1);
    expect(browser).not.toHaveBeenCalled();
  });

  it('reports "denied" when Telegram answers without a location (the customer said no)', async () => {
    useTelegram('8.0', locationManager(null));
    const browser = browserLocation({ latitude: 1, longitude: 1 });

    expect(await failureOf(getCurrentPosition())).toBe('denied');
    expect(browser).not.toHaveBeenCalled();
  });

  it('falls back to the browser when the device has no location services for Telegram', async () => {
    useTelegram('8.0', locationManager({ latitude: 5, longitude: 5 }, false));
    browserLocation({ latitude: 41.2, longitude: 69.1 });

    await expect(getCurrentPosition()).resolves.toEqual({ lat: 41.2, lng: 69.1 });
  });

  it('falls back to the browser when Telegram never answers', async () => {
    vi.useFakeTimers();
    const silent: LocationManagerMock = { isInited: false, isLocationAvailable: true, init: vi.fn(), getLocation: vi.fn() };
    useTelegram('8.0', silent);
    browserLocation({ latitude: 41.2, longitude: 69.1 });

    const position = getCurrentPosition();
    await vi.advanceTimersByTimeAsync(15000);

    await expect(position).resolves.toEqual({ lat: 41.2, lng: 69.1 });
  });

  it('answers every locate after the first one at once: an initialised manager never calls init() back', async () => {
    vi.useFakeTimers();
    const manager = locationManager({ latitude: 41.3275, longitude: 69.2818 });
    useTelegram('8.0', manager);
    const browser = browserLocation({ latitude: 1, longitude: 1 });

    await expect(getCurrentPosition()).resolves.toEqual({ lat: 41.3275, lng: 69.2818 });

    // The second tap on "locate" must not wait for the 15 s timeout of an init callback that never comes
    for (let tap = 2; tap <= 3; tap++) {
      const position = getCurrentPosition();
      let answered = false;
      void position.then(() => { answered = true; }, () => { answered = true; });
      await vi.advanceTimersByTimeAsync(0); // no virtual time passes: only what is already settled

      expect(answered, `tap ${tap} was answered without waiting`).toBe(true);
      await expect(position).resolves.toEqual({ lat: 41.3275, lng: 69.2818 });
    }

    expect(manager.init).toHaveBeenCalledTimes(1);
    expect(manager.getLocation).toHaveBeenCalledTimes(3);
    expect(browser).not.toHaveBeenCalled();
  });

  it('reports "denied" on a later locate too, from the already initialised manager', async () => {
    vi.useFakeTimers();
    useTelegram('8.0', locationManager(null));
    const browser = browserLocation({ latitude: 1, longitude: 1 });

    expect(await failureOf(getCurrentPosition())).toBe('denied');
    expect(await failureOf(getCurrentPosition())).toBe('denied');
    expect(browser).not.toHaveBeenCalled();
  });

  it('does not ask Telegram for a location once the browser has taken over', async () => {
    vi.useFakeTimers();
    let finishInit: (() => void) | undefined;
    const slow: LocationManagerMock = {
      isInited: false,
      isLocationAvailable: true,
      init: vi.fn((callback?: () => void) => { finishInit = callback; }),
      getLocation: vi.fn(),
    };
    useTelegram('8.0', slow);
    browserLocation({ latitude: 41.2, longitude: 69.1 });

    const position = getCurrentPosition();
    await vi.advanceTimersByTimeAsync(15000);
    await expect(position).resolves.toEqual({ lat: 41.2, lng: 69.1 });

    finishInit?.(); // Telegram finally answers, too late to matter

    expect(slow.getLocation).not.toHaveBeenCalled();
  });
});

describe('without Telegram location access', () => {
  it('uses the browser on a Telegram client older than 8.0', async () => {
    const manager = locationManager({ latitude: 5, longitude: 5 });
    useTelegram('7.10', manager);
    browserLocation({ latitude: 41.2, longitude: 69.1 });

    await expect(getCurrentPosition()).resolves.toEqual({ lat: 41.2, lng: 69.1 });
    expect(manager.init).not.toHaveBeenCalled();
  });

  it('uses the browser when there is no LocationManager at all (plain browser)', async () => {
    useTelegram('6.0');
    browserLocation({ latitude: 41.2, longitude: 69.1 });

    await expect(getCurrentPosition()).resolves.toEqual({ lat: 41.2, lng: 69.1 });
  });

  it.each([
    [1, 'denied'],
    [2, 'unavailable'],
    [3, 'timeout'],
  ] as const)('maps the browser error code %i to "%s"', async (code, reason) => {
    browserLocation({ code });

    expect(await failureOf(getCurrentPosition())).toBe(reason);
  });

  it('reports "unsupported" when the browser has no geolocation', async () => {
    expect(await failureOf(getCurrentPosition())).toBe('unsupported');
  });
});

describe('a location that cannot be right', () => {
  it('is "unavailable", not a pin at (0, 0)', async () => {
    useTelegram('8.0', locationManager({ latitude: 0, longitude: 0 }));

    expect(await failureOf(getCurrentPosition())).toBe('unavailable');
  });

  it('is "unavailable" when the numbers are out of range', async () => {
    browserLocation({ latitude: 123, longitude: 69 });

    expect(await failureOf(getCurrentPosition())).toBe('unavailable');
  });
});
