'use client';

import { createContext, useContext } from 'react';
import { getApi } from '@/lib/session/client';
import type { ApiClient } from '@rizqim/api-client';
import { DEFAULT_MAP_CONFIG, type MapConfig } from './map';
import type { RealtimeConfig } from './realtime';

export const ApiConfigContext = createContext<{ apiBaseUrl: string } | null>(null);

// The Reverb settings of the running server (null: real-time is off, the board polls)
export const RealtimeConfigContext = createContext<RealtimeConfig | null>(null);

export const useRealtimeConfig = (): RealtimeConfig | null => useContext(RealtimeConfigContext);

// Map tiles of the running server (lib/map.ts)
export const MapConfigContext = createContext<MapConfig>(DEFAULT_MAP_CONFIG);

export const useMapConfig = (): MapConfig => useContext(MapConfigContext);

// The typed API client of the page (signed with the session, renews it on a 401)
export function useApi(): ApiClient {
  const config = useContext(ApiConfigContext);
  if (!config) throw new Error('useApi must be used inside <Providers>');
  return getApi(config.apiBaseUrl);
}
