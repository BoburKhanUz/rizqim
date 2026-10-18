import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';

export interface OrderItem {
  id: number;
  order_id: number;
  product_variant_id: number;
  quantity: number;
  unit_price: number | string;
  total_price: number | string;
  product_name: string;
  variant_name: string | null;
  variant?: {
    id: number;
    name: string;
    product?: {
      id: number;
      name: string;
    };
  };
}

// Mirrors the backend PaymentStatus enum
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'cancelled' | 'expired' | 'refunded';

export interface Order {
  id: number;
  uuid: string;
  user_id: number;
  store_id: number;
  total_price: number | string;
  status: 'new' | 'accepted' | 'preparing' | 'delivery' | 'delivered' | 'canceled';
  type: 'delivery' | 'pickup' | 'dine_in';
  payment_method: 'cash' | 'click' | 'payme';
  payment_status: PaymentStatus;
  address: string | null;
  notes: string | null;
  created_at: string;
  items: OrderItem[];
}

export const useOrders = () => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['orders'],
    queryFn: async (): Promise<Order[]> => {
      const { data } = await api.get('/orders');
      return data.data; // Assuming ApiResponse structure
    },
    enabled: isAuthenticated,
    refetchInterval: 10000, // Poll every 10 seconds for real-time status updates!
    staleTime: 1000 * 60 * 5, // 5 minutes stale time for instant transitions
  });
};
