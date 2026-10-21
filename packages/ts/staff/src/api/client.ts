import { createApiClient, type ApiClient } from '@rizqim/api-client';
import type { RequestFetch } from './auth';

export interface StaffApiOptions {
  // The API root the generated paths hang on (they start with /v1/...): "/api" or "https://api.rizqim.uz/api". An app
  // configured with the versioned URL ("/api/v1") passes it through toApiRoot().
  baseUrl: string;
  // Sends the requests and signs them (see createAuthFetch). Required: the package never reads a token itself.
  fetch: RequestFetch;
  // Extra headers on every request (the Mini App's tunnel header, for one)
  headers?: Record<string, string>;
}

// The typed client the API functions and hooks work with. `client` is the openapi-fetch client of
// @rizqim/api-client, so every path, parameter and body is checked against the spec.
export interface StaffApi {
  client: ApiClient;
}

// "/api/v1" and "https://x/api/v1/" -> "/api" and "https://x/api"
export function toApiRoot(url: string): string {
  return url.replace(/\/+$/, '').replace(/\/v1$/, '');
}

// openapi-fetch builds a `new Request(url)`, which needs an absolute URL outside a page (and in tests): a root-relative
// base is resolved against the page's origin. In a browser that is the very URL the relative one meant.
function absolute(baseUrl: string): string {
  if (!baseUrl.startsWith('/') || typeof location === 'undefined' || !location.origin || location.origin === 'null') return baseUrl;
  return `${location.origin}${baseUrl}`;
}

export function createStaffApi({ baseUrl, fetch, headers }: StaffApiOptions): StaffApi {
  return {
    client: createApiClient({
      baseUrl: absolute(baseUrl),
      fetch,
      // Laravel answers errors as JSON only when asked for JSON
      headers: { Accept: 'application/json', ...headers },
    }),
  };
}
