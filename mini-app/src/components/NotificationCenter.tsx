import { Bell } from 'lucide-react';
import { Card, cx } from './ui';
import { Spinner } from './ui/Spinner';
import { formatDateTime } from '../lib/format';
import { useCopy } from '../lib/customerCopy';
import { useNotifications } from '../hooks/useNotifications';
import { NOTIFICATION_TILE, getNotificationLook } from '../lib/notificationLook';

// The latest notifications as a card to drop into a screen (the full feed is pages/Notifications.tsx).
export default function NotificationCenter() {
  const c = useCopy();
  const { data: notifications = [], isLoading } = useNotifications();

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-center gap-2 border-b border-line pb-3">
        <Bell aria-hidden="true" className="size-5 text-brand" />
        <h2 className="text-headline font-bold text-fg">{c('notificationsTitle')}</h2>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-4 text-brand" role="status" aria-label={c('notificationsTitle')}>
          <Spinner />
        </div>
      ) : notifications.length === 0 ? (
        <p className="py-2 text-center text-footnote text-fg-muted">{c('notificationsEmptyTitle')}</p>
      ) : (
        <ul className="flex max-h-72 flex-col gap-2.5 overflow-y-auto">
          {notifications.map((notification) => {
            const { icon: Icon, tone } = getNotificationLook(notification.type);
            return (
              <li key={notification.id} className="flex items-start gap-3 rounded-control bg-surface-2 p-3">
                <span className={cx('flex size-8 shrink-0 items-center justify-center rounded-control', NOTIFICATION_TILE[tone])}>
                  <Icon aria-hidden="true" className="size-4" />
                </span>
                <div className="min-w-0">
                  <span className="block select-text break-words text-footnote font-bold text-fg">{notification.title}</span>
                  <span className="block select-text break-words text-footnote text-fg-muted">{notification.message}</span>
                  <span className="block pt-0.5 text-caption text-fg-muted">{formatDateTime(notification.sent_at)}</span>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );
}
