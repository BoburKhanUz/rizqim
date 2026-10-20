'use client';

import { createContext, useContext } from 'react';
import { getApi } from '@/lib/session/client';
import type { ApiClient } from '@rizqim/api-client';

export const ApiConfigContext = createContext<{ apiBaseUrl: string } | null>(null);

// The typed API client of the page (signed with the session, renews it on a 401)
export function useApi(): ApiClient {
  const config = useContext(ApiConfigContext);
  if (!config) throw new Error('useApi must be used inside <Providers>');
  return getApi(config.apiBaseUrl);
}
