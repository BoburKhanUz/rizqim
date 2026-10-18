import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';

export interface Variant {
  id: number;
  uuid: string;
  name: string;
  price: number;
  stock: number;
}

export interface Product {
  id: number;
  uuid: string;
  category_id: number;
  name: string;
  description: string;
  image_url: string | null;
  variants: Variant[];
}

export interface Category {
  id: number;
  uuid: string;
  name: string;
}

interface CatalogData {
  categories: Category[];
  products: Product[];
  stores?: any[];
  store?: any;
}

export const useCatalog = (storeId?: string) => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['catalog', storeId],
    queryFn: async (): Promise<CatalogData> => {
      const { data } = await api.get(storeId ? `/catalog?store_id=${storeId}` : '/catalog');
      return data.data; 
    },
    enabled: isAuthenticated && !!storeId,
    staleTime: 1000 * 60 * 5,
  });
};
