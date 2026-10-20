import { isValidPoint } from '../../components/map/geo';
import { tg } from '../../lib/telegram';
import type { LatLng } from './types';

export type GeolocationFailure = 'unsupported' | 'denied' | 'unavailable' | 'timeout';

export class GeolocationError extends Error {
  reason: GeolocationFailure;

  constructor(reason: GeolocationFailure) {
    super(`Geolocation failed: ${reason}`);
    this.name = 'GeolocationError';
    this.reason = reason;
  }
}

// Telegram's LocationManager (Bot API 8.0+), as far as it is used here
interface TelegramLocationManager {
  // Once true, init() returns at once and never calls its callback again
  isInited: boolean;
  isLocationAvailable: boolean;
  init(callback?: (isInitialized: boolean) => unknown): unknown;
  getLocation(callback: (data: { latitude: number; longitude: number } | null) => unknown): unknown;
}

interface TelegramLocationHost {
  isVersionAtLeast?: (version: string) => boolean;
  LocationManager?: TelegramLocationManager;
}

// If Telegram never answers (a prompt left open, an old client) the browser API gets its turn
const TELEGRAM_TIMEOUT_MS = 15000;
const BROWSER_TIMEOUT_MS = 10000;

/**
 * The device location through Telegram's own location access, which is what works reliably inside the Mini App.
 * Resolves null when Telegram can't provide it here (old client, desktop, plain browser), so the caller can fall back.
 */
function fromTelegram(): Promise<LatLng | null> {
  const host = tg as unknown as TelegramLocationHost;
  const manager = host.LocationManager;
  if (!manager || typeof host.isVersionAtLeast !== 'function' || !host.isVersionAtLeast('8.0')) {
    return Promise.resolve(null);
  }

  return new Promise((resolve, reject) => {
    // Once settled (answered, or given up on after the timeout) a late answer of Telegram must not start a new request
    let settled = false;
    const timer = window.setTimeout(() => {
      settled = true;
      resolve(null);
    }, TELEGRAM_TIMEOUT_MS);
    const settle = (action: () => void) => {
      settled = true;
      window.clearTimeout(timer);
      action();
    };

    const locate = () => {
      if (settled) return;
      if (!manager.isLocationAvailable) {
        settle(() => resolve(null));
        return;
      }
      manager.getLocation((data) => {
        settle(() => (data ? resolve({ lat: data.latitude, lng: data.longitude }) : reject(new GeolocationError('denied'))));
      });
    };

    // init() only calls back the first time. Once the manager is initialised it returns without calling back at all,
    // so waiting for it would hang every later locate until the timeout.
    if (manager.isInited) locate();
    else manager.init(locate);
  });
}

function fromBrowser(): Promise<LatLng> {
  return new Promise((resolve, reject) => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      reject(new GeolocationError('unsupported'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ lat: position.coords.latitude, lng: position.coords.longitude }),
      (error) => {
        if (error.code === error.PERMISSION_DENIED) reject(new GeolocationError('denied'));
        else if (error.code === error.TIMEOUT) reject(new GeolocationError('timeout'));
        else reject(new GeolocationError('unavailable'));
      },
      { enableHighAccuracy: true, timeout: BROWSER_TIMEOUT_MS, maximumAge: 30000 },
    );
  });
}

/**
 * Where the device is right now. Rejects with a GeolocationError saying why not.
 */
export async function getCurrentPosition(): Promise<LatLng> {
  const point = (await fromTelegram()) ?? (await fromBrowser());
  if (!isValidPoint(point.lat, point.lng)) throw new GeolocationError('unavailable');
  return point;
}
