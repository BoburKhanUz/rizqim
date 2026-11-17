import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { POLL_INTERVAL_MS, getPollInterval, pollingQueryOptions } from '../lib/polling';
import { openMenuChannel, resolveRealtimeConfig } from '../lib/realtime';
import { loadRealtimeStatus } from '../lib/realtimeStatus';
import type { ApiResponse, BranchAvailability, MenuAvailabilityData } from '../types';

export const menuAvailabilityKey = (storeId: number | string) => ['menu-availability', String(storeId)] as const;

export async function fetchMenuAvailability(storeId: number | string): Promise<BranchAvailability[]> {
  const { data } = await api.get<ApiResponse<MenuAvailabilityData>>('/catalog/availability', { params: { store_id: storeId } });
  return data.data?.branches ?? [];
}

// What a store's menu cannot offer right now (stop-list, sold-out batches), per branch, kept current:
//  - GET /catalog/availability for the first picture; `placeholder` (the `availability` that GET /catalog?store_id=
//    carries) fills in until it arrives;
//  - the public channel menu.{store_id} says when something changed (event `menu.availability_changed`): the
//    endpoint is asked again at once;
//  - real-time is optional (it is off without a Reverb key, the build's or the server's GET /realtime, and a socket can
//    drop): the query polls anyway, every 20 s (never faster than the server's 15 s availability cache), slowly (60 s)
//    only while the socket is up and the server says real-time is live, and refetches when the app is brought back or
//    the network returns.
// Without data (an older backend, a failed request) `branches` is undefined and nothing is flagged: checkout decides.
export function useMenuAvailability(storeId: number | string | undefined, placeholder?: BranchAvailability[]) {
  const queryClient = useQueryClient();
  const [socketUp, setSocketUp] = useState(false);
  const enabled = storeId !== undefined && storeId !== '';

  const query = useQuery({
    queryKey: menuAvailabilityKey(storeId ?? ''),
    queryFn: () => fetchMenuAvailability(storeId as number | string),
    enabled,
    placeholderData: placeholder,
    ...pollingQueryOptions(getPollInterval(POLL_INTERVAL_MS.menuAvailability, socketUp)),
  });

  useEffect(() => {
    if (!enabled) return;

    let disposed = false;
    let session: { close: () => void } | undefined;
    const refresh = () => void queryClient.invalidateQueries({ queryKey: menuAvailabilityKey(storeId) });

    const start = async () => {
      const status = await loadRealtimeStatus();
      const config = resolveRealtimeConfig(status);
      if (disposed || !config) return;
      const live = status?.live ?? true;
      const opened = await openMenuChannel(config, storeId, {
        onChange: refresh,
        onResync: refresh,
        onConnectionChange: (connected) => {
          if (!disposed) setSocketUp(connected && live);
        },
      });
      if (disposed) opened.close();
      else session = opened;
    };

    start()
      .catch((err: unknown) => {
        // Polling carries on at its normal pace
        if (!disposed) logApiError('Real-time menu updates unavailable:', err);
      });

    return () => {
      disposed = true;
      session?.close();
      setSocketUp(false);
    };
  }, [enabled, storeId, queryClient]);

  return { branches: query.data, refetch: query.refetch, isError: query.isError };
}
