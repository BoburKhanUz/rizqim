import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Bell } from 'lucide-react';
import { EmptyState, InlineError, PageHeader, cx } from '../components/ui';
import { useAuthStore } from '../store/authStore';
import { useNotifications } from '../hooks/useNotifications';
import { formatDateTime } from '../lib/format';
import { isCustomerRole } from '../lib/roles';
import { NOTIFICATION_TILE, getNotificationLook } from '../lib/notificationLook';
import { useCopy } from '../lib/customerCopy';
import api from '../services/api';

function NotificationsSkeleton() {
  return (
    <div className="flex flex-col gap-3 animate-pulse" aria-hidden="true">
      {[1, 2, 3].map((n) => (
        <div key={n} className="flex gap-3 rounded-card border border-line bg-surface p-4">
          <div className="size-10 shrink-0 rounded-control bg-surface-2" />
          <div className="flex flex-1 flex-col gap-2">
            <div className="h-4 w-1/2 rounded-control bg-surface-2" />
            <div className="h-3 w-full rounded-control bg-surface-2" />
          </div>
        </div>
      ))}
    </div>
  );
}

// The customer's and the staff's notification feed (GET /notifications): the same list, the theme does the rest.
export default function Notifications() {
  const c = useCopy();
  const queryClient = useQueryClient();
  const role = useAuthStore((state) => state.user?.role);
  const isStaff = !isCustomerRole(role);
  const { data: notifications = [], isLoading, isError, refetch, isRefetching } = useNotifications();

  // Looking at the feed reads it: the bell on the profile only shows what has not been seen
  const hasUnread = notifications.some((notification) => !notification.is_read);
  useEffect(() => {
    if (!hasUnread) return;
    api
      .post('/notifications/read')
      .then(() => queryClient.invalidateQueries({ queryKey: ['notifications'] }))
      .catch(() => undefined); // the feed itself is shown either way
  }, [hasUnread, queryClient]);

  return (
    <div className="flex flex-col gap-4 p-4 animate-fade-in">
      <PageHeader title={c('notificationsTitle')} subtitle={notifications.length > 0 ? c('notificationsCount', { n: notifications.length }) : undefined} back />

      {isLoading ? (
        <NotificationsSkeleton />
      ) : isError && notifications.length === 0 ? (
        <InlineError retrying={isRefetching} onRetry={() => void refetch()} />
      ) : notifications.length === 0 ? (
        <EmptyState
          icon={Bell}
          title={c('notificationsEmptyTitle')}
          text={isStaff ? c('notificationsEmptyStaff') : c('notificationsEmptyCustomer')}
        />
      ) : (
        <ul className="flex flex-col gap-3">
          {notifications.map((notification) => {
            const { icon: Icon, tone } = getNotificationLook(notification.type);
            return (
              <li key={notification.id} className="flex items-start gap-3 rounded-card border border-line bg-surface p-4">
                <span className={cx('flex size-10 shrink-0 items-center justify-center rounded-control', NOTIFICATION_TILE[tone])}>
                  <Icon aria-hidden="true" className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-2">
                    <span className="select-text break-words text-body font-bold text-fg">{notification.title}</span>
                    <time dateTime={notification.sent_at} className="shrink-0 whitespace-nowrap text-caption text-fg-muted">
                      {formatDateTime(notification.sent_at)}
                    </time>
                  </div>
                  <p className="mt-0.5 select-text break-words text-footnote text-fg-muted">{notification.message}</p>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
