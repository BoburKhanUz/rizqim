import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { useAuthStore } from '../store/authStore';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import type { ApiResponse, Order } from '../types';

export type { Order, OrderItem, PaymentStatus } from '../types';

export async function fetchOrders(): Promise<Order[]> {
  const { data } = await api.get<ApiResponse<Order[]>>('/orders');
  return data.data ?? [];
}

// The signed-in customer's orders, polled so status changes show up without a reload.
export const useOrders = () => {
  const { isAuthenticated } = useAuthStore();
  return useQuery({
    queryKey: ['orders'],
    queryFn: fetchOrders,
    enabled: isAuthenticated,
    ...pollingQueryOptions(POLL_INTERVAL_MS.customerOrders),
  });
};

// The store's orders for a staff dashboard (what GET /orders returns depends on the role). Each dashboard
// has its own query key so its mutations can invalidate just that cache entry.
export const useStaffOrders = (queryKey: 'managerOrders' | 'courierOrders' | 'adminOrders', intervalMs: number) =>
  useQuery({
    queryKey: [queryKey],
    queryFn: fetchOrders,
    ...pollingQueryOptions(intervalMs),
  });
