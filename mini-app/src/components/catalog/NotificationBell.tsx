import { Bell } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useNotifications } from '../../hooks/useNotifications';
import { hapticFeedback } from '../../lib/telegram';
import { useLanguageStore } from '../../store/languageStore';

// The bell of the home screen. The red badge is there only while there are unread notifications (and says how many).
export function NotificationBell() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const { data } = useNotifications();
  const unread = data?.filter((item) => !item.is_read).length ?? 0;
  const base = language === 'uz' ? 'Bildirishnomalar' : 'Уведомления';
  const label = unread > 0 ? `${base}: ${unread}` : base;

  return (
    <button
      type="button"
      aria-label={label}
      title={base}
      onClick={() => {
        hapticFeedback('light');
        navigate('/notifications');
      }}
      className="relative flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-line bg-surface text-fg transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand motion-safe:active:scale-95"
    >
      <Bell aria-hidden="true" className="size-5" />
      {unread > 0 && (
        <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-caption font-bold text-on-danger">
          {unread > 9 ? '9+' : unread}
        </span>
      )}
    </button>
  );
}
