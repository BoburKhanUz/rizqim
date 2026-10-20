import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Bell, CheckCircle2, Clock, ShieldAlert, Sparkles, MessageSquare, Flame } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { formatTime } from '../lib/format';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { useNotifications } from '../hooks/useNotifications';

export default function Notifications() {
  const navigate = useNavigate();
  const { language } = useLanguageStore();
  const { user } = useAuthStore();
  const isBusiness = user?.role === 'owner' || user?.role === 'manager' || user?.role === 'courier' || user?.role === 'super_admin';

  const { data: notifications = [], isLoading } = useNotifications();

  const getIcon = (type: string) => {
    switch (type) {
      case 'delivered':
      case 'accepted':
        return <CheckCircle2 className="w-5 h-5 text-emerald-500" />;
      case 'store_payment_required':
        return <ShieldAlert className="w-5 h-5 text-rose-500 animate-bounce" />;
      case 'complaint_reply':
        return <MessageSquare className="w-5 h-5 text-blue-500" />;
      case 'new_order':
        return <Sparkles className="w-5 h-5 text-amber-500" />;
      case 'on_the_way':
        return <Flame className="w-5 h-5 text-orange-500" />;
      default:
        return <Clock className="w-5 h-5 text-slate-500" />;
    }
  };

  const getBgClass = (type: string) => {
    if (isBusiness) {
      switch (type) {
        case 'delivered':
        case 'accepted':
          return 'bg-emerald-500/10 border border-emerald-500/20';
        case 'store_payment_required':
          return 'bg-rose-500/10 border border-rose-500/20';
        case 'complaint_reply':
          return 'bg-blue-500/10 border border-blue-500/20';
        case 'new_order':
          return 'bg-amber-500/10 border border-amber-500/20';
        case 'on_the_way':
          return 'bg-orange-500/10 border border-orange-500/20';
        default:
          return 'bg-secondary border border-white/5';
      }
    } else {
      switch (type) {
        case 'delivered':
        case 'accepted':
          return 'bg-emerald-500/5 border border-emerald-500/10';
        case 'store_payment_required':
          return 'bg-rose-500/5 border border-rose-500/10';
        case 'complaint_reply':
          return 'bg-blue-500/5 border border-blue-500/10';
        case 'new_order':
          return 'bg-amber-500/5 border border-amber-500/10';
        case 'on_the_way':
          return 'bg-orange-500/5 border border-orange-500/10';
        default:
          return 'bg-slate-50 border border-slate-100';
      }
    }
  };

  return (
    <div className={`min-h-screen pb-28 animate-fade-in flex flex-col ${isBusiness ? 'bg-bg text-fg' : 'bg-slate-50 text-slate-800'}`}>
      {/* Header */}
      <div className={`sticky top-0 px-4 py-4 flex items-center justify-between z-10 shadow-sm ${
        isBusiness 
          ? 'bg-secondary border-b border-white/5 shadow-black/10' 
          : 'bg-white border-b border-slate-100 shadow-slate-100/10'
      }`}>
        <div className="flex items-center gap-3">
          <button 
            onClick={() => { hapticFeedback('light'); navigate(-1); }} 
            className={`w-10 h-10 rounded-2xl flex items-center justify-center active:scale-95 transition ${
              isBusiness 
                ? 'bg-bg border border-white/5 text-fg' 
                : 'bg-slate-50 border border-slate-100 text-slate-800'
            }`}
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <h1 className={`text-sm font-black leading-none ${isBusiness ? 'text-fg' : 'text-slate-800'}`}>
              {language === 'uz' ? 'Bildirishnomalar' : 'Уведомления'}
            </h1>
            <p className={`text-[10px] font-bold mt-1 uppercase tracking-wider ${isBusiness ? 'text-hint' : 'text-slate-400'}`}>
              {notifications.length} {language === 'uz' ? 'xabarlar' : 'уведомлений'}
            </p>
          </div>
        </div>
        
        <div className={`w-10 h-10 rounded-2xl flex items-center justify-center ${
          isBusiness ? 'bg-primary/20 text-primary' : 'bg-amber-500/10 text-amber-500'
        }`}>
          <Bell className="w-5 h-5" />
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 p-4">
        {isLoading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3">
            <div className={`w-8 h-8 border-4 border-t-transparent rounded-full animate-spin ${
              isBusiness ? 'border-primary' : 'border-amber-500'
            }`}></div>
            <p className={`text-[11px] font-bold uppercase tracking-wider ${isBusiness ? 'text-hint' : 'text-slate-400'}`}>
              Yuklanmoqda...
            </p>
          </div>
        ) : notifications.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-24 px-6 text-center gap-4">
            <div className={`w-16 h-16 rounded-full flex items-center justify-center ${
              isBusiness ? 'bg-secondary border border-white/5 text-hint' : 'bg-slate-100 border border-slate-200/50 text-slate-400'
            }`}>
              <Bell className="w-8 h-8 opacity-60" />
            </div>
            <div>
              <h3 className={`text-sm font-black ${isBusiness ? 'text-fg' : 'text-slate-800'}`}>
                {language === 'uz' ? 'Hozircha xabarlar yo\'q' : 'Уведомлений пока нет'}
              </h3>
              <p className={`text-[11px] font-medium mt-1.5 leading-relaxed max-w-[220px] mx-auto ${
                isBusiness ? 'text-hint' : 'text-slate-400'
              }`}>
                {language === 'uz' 
                  ? 'Buyurtmalaringiz holati haqidagi ma\'lumotlar shu yerda ko\'rsatiladi' 
                  : 'Здесь будут отображаться статусы ваших заказов'
                }
              </p>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {notifications.map((notif) => (
              <div
                key={notif.id}
                className={`p-4 rounded-3xl flex gap-3.5 items-start shadow-[0_4px_15px_rgba(0,0,0,0.01)] transition-all ${getBgClass(notif.type)}`}
              >
                <div className={`mt-1 p-2 rounded-2xl shrink-0 shadow-sm ${
                  isBusiness ? 'bg-bg border border-white/5' : 'bg-white border border-slate-100'
                }`}>
                  {getIcon(notif.type)}
                </div>
                <div className="text-left flex-1 min-w-0 space-y-1">
                  <div className="flex items-center justify-between gap-2">
                    <span className={`text-xs font-black truncate leading-none ${
                      isBusiness ? 'text-fg' : 'text-slate-800'
                    }`}>
                      {notif.title}
                    </span>
                    <span className={`text-[9px] font-bold whitespace-nowrap ${
                      isBusiness ? 'text-hint/80' : 'text-slate-400'
                    }`}>
                      {formatTime(notif.sent_at)}
                    </span>
                  </div>
                  <span className={`text-[11px] font-medium block leading-relaxed ${
                    isBusiness ? 'text-hint' : 'text-slate-500'
                  }`}>
                    {notif.message}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
