// Map tiles of the running server (MAP_TILE_URL, MAP_ATTRIBUTION; read at runtime in lib/config.ts). A plain module:
// lib/config.ts runs on the server and must not import from a 'use client' file.
export interface MapConfig {
  tileUrl: string;
  attribution: string;
}

export const DEFAULT_MAP_CONFIG: MapConfig = {
  tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  attribution:
    '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
};
