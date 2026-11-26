// The public site (infra/landing) and its legal pages: the Terms of Use (/shartlar) and the Privacy Policy (/maxfiylik).
// The site comes from VITE_SITE_URL (build time); unset or empty = https://rizqim.uz. The backend's bot links the same
// pages from SITE_URL (config services.site.url).
export const DEFAULT_SITE_URL = 'https://rizqim.uz';

export function siteBaseUrl(raw: string | undefined = import.meta.env.VITE_SITE_URL): string {
  const value = raw?.trim();
  return (value || DEFAULT_SITE_URL).replace(/\/+$/, '');
}

export interface LegalLinks {
  terms: string;
  privacy: string;
}

export function legalLinks(base: string = siteBaseUrl()): LegalLinks {
  return { terms: `${base}/shartlar`, privacy: `${base}/maxfiylik` };
}
