import { useQuery } from '@tanstack/react-query';
import { Bell, CheckCircle2, Clock, ShieldAlert, Sparkles, MessageSquare, Flame } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import api from '../services/api';

export default function NotificationCenter() {
  const { language } = useLanguageStore();

  const { data: notifications = [], isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await api.get('/notifications');
      return res.data?.data?.items || res.data?.data || [];
    },
    refetchInterval: 15000,
  });

  const getIcon = (type: string) => {
    switch (type) {
      case 'delivered':
      case 'accepted':
        return <CheckCircle2 className="w-4.5 h-4.5 text-emerald-500" />;
      case 'store_payment_required':
        return <ShieldAlert className="w-4.5 h-4.5 text-rose-500" />;
      case 'complaint_reply':
        return <MessageSquare className="w-4.5 h-4.5 text-blue-500" />;
      case 'new_order':
        return <Sparkles className="w-4.5 h-4.5 text-amber-500" />;
      case 'on_the_way':
        return <Flame className="w-4.5 h-4.5 text-orange-500" />;
      default:
        return <Clock className="w-4.5 h-4.5 text-slate-500" />;
    }
  };

  const getBgClass = (type: string) => {
    switch (type) {
      case 'delivered':
      case 'accepted':
        return 'bg-emerald-500/10 border border-emerald-500/20';
      case 'store_payment_required':
        return 'bg-rose-500/10 border border-rose-500/20 animate-pulse';
      case 'complaint_reply':
        return 'bg-blue-500/10 border border-blue-500/20';
      case 'new_order':
        return 'bg-amber-500/10 border border-amber-500/20';
      case 'on_the_way':
        return 'bg-orange-500/10 border border-orange-500/20';
      default:
        return 'bg-slate-100 border border-slate-200/50';
    }
  };

  return (
    <div className="bg-white p-5 rounded-3xl border border-slate-100 shadow-[0_8px_30px_rgb(0,0,0,0.02)] space-y-4">
      <div className="flex items-center gap-2 border-b border-slate-50 pb-2">
        <Bell className="w-5 h-5 text-amber-600" />
        <h4 className="text-xs font-black uppercase tracking-wider text-slate-800">
          {language === 'uz' ? 'Bildirishnomalar' : 'Уведомления'}
        </h4>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-4">
          <div className="w-5 h-5 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : notifications.length === 0 ? (
        <p className="text-[11px] text-slate-400 font-semibold italic text-center py-2">
          {language === 'uz' ? 'Hozircha xabarnomalar yo\'q.' : 'Уведомлений пока нет.'}
        </p>
      ) : (
        <div className="space-y-2.5 max-h-[250px] overflow-y-auto pr-1">
          {notifications.map((notif: any) => (
            <div
              key={notif.id}
              className={`p-3 rounded-2xl flex gap-3 items-start transition-all ${getBgClass(notif.type)}`}
            >
              <div className="mt-0.5 shrink-0">
                {getIcon(notif.type)}
              </div>
              <div className="text-left space-y-0.5">
                <span className="text-[11px] font-black text-slate-800 block leading-tight">
                  {notif.title}
                </span>
                <span className="text-[10px] text-slate-500 font-semibold block leading-relaxed">
                  {notif.message}
                </span>
                <span className="text-[8px] text-slate-400 font-bold block pt-0.5">
                  {new Date(notif.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
