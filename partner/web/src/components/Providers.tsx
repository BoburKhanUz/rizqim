'use client';

import { useState, type ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StaffApiProvider } from '@rizqim/staff';
import type { Language } from '@/i18n';
import { AuthProvider } from './AuthProvider';
import { I18nProvider } from './I18nProvider';
import { ApiConfigContext, MapConfigContext, RealtimeConfigContext } from '@/lib/apiConfig';
import { DEFAULT_MAP_CONFIG, type MapConfig } from '@/lib/map';
import type { RealtimeConfig } from '@/lib/realtime';
import { getStaffApi } from '@/lib/session/client';

// Everything the pages share: language, the API address (read at runtime on the server), the session and the query cache.
export function Providers({
  language,
  apiBaseUrl,
  realtime = null,
  map = DEFAULT_MAP_CONFIG,
  children,
}: {
  language: Language;
  apiBaseUrl: string;
  realtime?: RealtimeConfig | null;
  map?: MapConfig;
  children: ReactNode;
}) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          // A failed request is shown with a "Try again" button rather than retried behind the person's back
          queries: { retry: false, refetchOnWindowFocus: true, staleTime: 15_000 },
        },
      }),
  );
  return (
    <I18nProvider language={language}>
      <ApiConfigContext.Provider value={{ apiBaseUrl }}>
        <RealtimeConfigContext.Provider value={realtime}>
          <MapConfigContext.Provider value={map}>
            <StaffApiProvider api={getStaffApi(apiBaseUrl)}>
              <QueryClientProvider client={queryClient}>
                <AuthProvider apiBaseUrl={apiBaseUrl}>{children}</AuthProvider>
              </QueryClientProvider>
            </StaffApiProvider>
          </MapConfigContext.Provider>
        </RealtimeConfigContext.Provider>
      </ApiConfigContext.Provider>
    </I18nProvider>
  );
}
