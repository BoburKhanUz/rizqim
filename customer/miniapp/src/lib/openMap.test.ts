import { describe, expect, it } from 'vitest';
import { cleanAddress, linkInAddress, mapRouteUrl } from './openMap';

describe('mapRouteUrl', () => {
  it('builds a Yandex route to the point', () => {
    expect(mapRouteUrl(41.2856, 69.2037)).toBe('https://yandex.com/maps/?rtext=~41.2856,69.2037&rtt=auto');
  });

  it('is null when a coordinate is missing or out of range', () => {
    expect(mapRouteUrl(null, 69.2)).toBeNull();
    expect(mapRouteUrl(41.2, undefined)).toBeNull();
    expect(mapRouteUrl(Number.NaN, 69.2)).toBeNull();
    expect(mapRouteUrl(141.2, 69.2)).toBeNull();
  });
});

describe('address text with a location link', () => {
  const text = '📍 Lokatsiya: https://maps.example/?ll=1,2';

  it('finds the link and strips it from the text', () => {
    expect(linkInAddress(text)).toBe('https://maps.example/?ll=1,2');
    expect(cleanAddress(text, 'GPS lokatsiya')).toBe('GPS lokatsiya');
    expect(cleanAddress(`Chilonzor 9 ${text}`, 'GPS lokatsiya')).toBe('Chilonzor 9');
  });

  it('leaves a plain address alone', () => {
    expect(linkInAddress('Chilonzor 9')).toBeNull();
    expect(cleanAddress('Chilonzor 9', 'GPS lokatsiya')).toBe('Chilonzor 9');
  });
});
