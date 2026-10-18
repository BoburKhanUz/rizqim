import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import type { ApiResponse, StoreListItem } from '../types';

export type { Branch, BranchSummary, StoreListItem } from '../types';

export const useStores = (lat?: number, lng?: number) => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['stores', lat, lng],
    queryFn: async (): Promise<StoreListItem[]> => {
      const params = new URLSearchParams();
      if (lat && lng) {
        params.append('lat', lat.toString());
        params.append('lng', lng.toString());
      }
      const { data } = await api.get<ApiResponse<StoreListItem[]>>(`/stores?${params.toString()}`);
      return data.data;
    },
    enabled: isAuthenticated,
    staleTime: 1000 * 60 * 2,
  });
};
