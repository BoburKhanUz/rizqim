import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';

export interface Branch {
  id: number;
  store_id: number;
  name: string;
  latitude: number;
  longitude: number;
  radius_km: number;
}

export interface Store {
  id: number;
  uuid: string;
  name: string;
  address: string;
  phone: string;
  logo: string;
  banner: string;
  description: string;
  is_open: boolean;
  is_premium: boolean;
  review_count: number;
  branches: Branch[];
  min_distance?: number;
}

export const useStores = (lat?: number, lng?: number) => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['stores', lat, lng],
    queryFn: async (): Promise<Store[]> => {
      const params = new URLSearchParams();
      if (lat && lng) {
        params.append('lat', lat.toString());
        params.append('lng', lng.toString());
      }
      const { data } = await api.get(`/stores?${params.toString()}`);
      return data.data; 
    },
    enabled: isAuthenticated,
    staleTime: 1000 * 60 * 2,
  });
};
