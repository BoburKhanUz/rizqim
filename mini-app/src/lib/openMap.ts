import { tg } from './telegram';

// Opens a link outside the Mini App: through Telegram (openLink) when it is there, else a new tab.
export function openExternalLink(url: string): void {
  if (typeof tg.openLink === 'function') {
    tg.openLink(url);
    return;
  }
  window.open(url, '_blank', 'noopener');
}

const isCoordinate = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);

// A route to a point in Yandex Maps (the map most used in Uzbekistan): from where the person is now to lat/lng.
// null when the point is not known.
export function mapRouteUrl(lat: number | null | undefined, lng: number | null | undefined): string | null {
  if (!isCoordinate(lat) || !isCoordinate(lng)) return null;
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
  return `https://yandex.com/maps/?rtext=~${lat},${lng}&rtt=auto`;
}

// The first http(s) link inside an address text (an older order carries "Lokatsiya: <link>" in its address)
export function linkInAddress(address: string | null | undefined): string | null {
  return address?.match(/https?:\/\/[^\s]+/)?.[0] ?? null;
}

// The address without the link and its "Lokatsiya:" prefix; `fallback` when nothing is left.
export function cleanAddress(address: string | null | undefined, fallback: string): string {
  if (!address) return '';
  const link = linkInAddress(address);
  if (!link) return address;
  return address.replace(/📍 Lokatsiya:|Lokatsiya:/g, '').replace(link, '').trim() || fallback;
}
