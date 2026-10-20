import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import type { ApiResponse, NotificationItem, NotificationsPage } from '../types';

// GET /notifications answers a page object; an older backend answered the bare list.
export async function fetchNotifications(): Promise<NotificationItem[]> {
  const { data } = await api.get<ApiResponse<NotificationsPage | NotificationItem[]>>('/notifications');
  const payload = data.data;
  if (Array.isArray(payload)) return payload;
  return payload?.items ?? [];
}

export const useNotifications = () =>
  useQuery({
    queryKey: ['notifications'],
    queryFn: fetchNotifications,
    ...pollingQueryOptions(POLL_INTERVAL_MS.notifications),
  });
