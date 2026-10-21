import { DEFAULT_MAP_CONFIG, type MapConfig } from './map';
import { realtimeConfigFromEnv, type RealtimeConfig } from './realtime';

// Runtime configuration (server side only). Read when a request arrives, never at build time, so one image serves
// every environment. See .env.example.

// Where the Next server calls the backend (route handlers under app/api/session). Ends with "/api", no trailing slash.
export function apiBaseUrl(): string {
  return (process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/+$/, '');
}

// Where the browser calls the backend directly (with the access token). Falls back to API_BASE_URL.
export function publicApiBaseUrl(): string {
  return (process.env.PUBLIC_API_BASE_URL || process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/+$/, '');
}

// Real-time (Laravel Reverb) for the browser: null without REVERB_APP_KEY (the board then only polls). See .env.example.
export function realtimeConfig(): RealtimeConfig | null {
  return realtimeConfigFromEnv(process.env, publicApiBaseUrl());
}

// Map tiles for the zone editor. The public OSM tile server suits development and light traffic only (its usage policy):
// production points MAP_TILE_URL (and MAP_ATTRIBUTION) at a tile provider the business has an account with.
export function mapConfig(): MapConfig {
  return {
    tileUrl: process.env.MAP_TILE_URL?.trim() || DEFAULT_MAP_CONFIG.tileUrl,
    attribution: process.env.MAP_ATTRIBUTION?.trim() || DEFAULT_MAP_CONFIG.attribution,
  };
}

// `Secure` on the session cookies: on in production unless SESSION_COOKIE_SECURE=false (plain-HTTP trials only).
export function secureCookies(): boolean {
  const flag = process.env.SESSION_COOKIE_SECURE?.trim().toLowerCase();
  if (flag === 'false' || flag === '0') return false;
  if (flag === 'true' || flag === '1') return true;
  return process.env.NODE_ENV === 'production';
}
