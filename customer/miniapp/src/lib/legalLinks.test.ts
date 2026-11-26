import { describe, expect, it } from 'vitest';
import { legalLinks, siteBaseUrl } from './legalLinks';

// The public site's legal pages (infra/landing: /shartlar, /maxfiylik), from VITE_SITE_URL
describe('legal links', () => {
  it('default to rizqim.uz when VITE_SITE_URL is not set or empty', () => {
    expect(siteBaseUrl(undefined)).toBe('https://rizqim.uz');
    expect(siteBaseUrl('  ')).toBe('https://rizqim.uz');
    expect(legalLinks()).toEqual({ terms: 'https://rizqim.uz/shartlar', privacy: 'https://rizqim.uz/maxfiylik' });
  });

  it('follow VITE_SITE_URL, without a doubled slash', () => {
    expect(legalLinks(siteBaseUrl('https://stg.rizqim.uz/'))).toEqual({
      terms: 'https://stg.rizqim.uz/shartlar',
      privacy: 'https://stg.rizqim.uz/maxfiylik',
    });
  });
});
