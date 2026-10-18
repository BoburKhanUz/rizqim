import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import type { ApiResponse, StoreCatalog } from '../types';

export type { Category, Product, ProductVariant, StoreCatalog } from '../types';

export const useCatalog = (storeId?: string) => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['catalog', storeId],
    queryFn: async (): Promise<StoreCatalog> => {
      const { data } = await api.get<ApiResponse<StoreCatalog>>(storeId ? `/catalog?store_id=${storeId}` : '/catalog');
      return data.data;
    },
    enabled: isAuthenticated && !!storeId,
    staleTime: 1000 * 60 * 5,
  });
};
