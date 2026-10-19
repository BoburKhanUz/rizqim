import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { ErrorFallback } from '../components/Feedback';
import { Phone, MapPin, CheckCircle, Navigation, Clock, ShieldCheck, DollarSign } from 'lucide-react';
import api from '../services/api';
import { getApiErrorMessage, logApiError } from '../lib/apiError';
import { sumAmounts, toAmount } from '../lib/money';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, ORDERS_KEY, useOrderHistory, useStaffOrders } from '../hooks/useOrders';
import { TruncatedOrdersNotice } from '../components/OrderListParts';
import type { ApiResponse, Order, OrderStatus } from '../types';

const ACTIVE_STATUSES: readonly OrderStatus[] = ['courier_assigned', 'on_the_way', 'delivery'];

export default function CourierDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');

  // Courier assigned orders: polled while the screen is visible (active deliveries change quickly) and pushed over the socket
  const { data: activeData, isLoading, error, dataUpdatedAt } = useStaffOrders('courier', POLL_INTERVAL_MS.courierOrders);
  const allOrders = activeData?.orders ?? NO_ORDERS;

  // What was delivered today: the finished orders, loaded back to the start of the day. "Today" is the day of the last
  // refresh, so a screen left open over midnight starts the new day with the next poll
  const [openedAt] = useState(() => Date.now());
  const startOfToday = new Date(Math.max(dataUpdatedAt, openedAt)).setHours(0, 0, 0, 0);
  const history = useOrderHistory('courier', { since: startOfToday, pageSize: 50 });

  // Complete order mutation
  const completeMutation = useMutation({
    mutationFn: async (orderUuid: string) => {
      hapticFeedback('medium');
      const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/complete`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
      hapticFeedback('medium');
    },
    onError: (err) => {
      logApiError('Failed to complete order:', err);
      alert(language === 'uz' ? 'Buyurtmani topshirishda xatolik yuz berdi!' : 'Ошибка при завершении заказа!');
    }
  });

  // Update status mutation (e.g. for marking as on_the_way)
  const updateStatusMutation = useMutation({
    mutationFn: async ({ orderUuid, status }: { orderUuid: string; status: OrderStatus }) => {
      hapticFeedback('medium');
      const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/status`, {
        status
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
      hapticFeedback('medium');
    },
    onError: (err) => {
      logApiError('Failed to update status:', err);
      alert(getApiErrorMessage(err) || (language === 'uz' ? 'Holatni yangilashda xatolik yuz berdi!' : 'Ошибка!'));
    }
  });

  // A failed background refresh keeps showing the last deliveries; the error screen is only for "nothing to show"
  if (error && allOrders.length === 0) return <ErrorFallback error="Failed to fetch courier deliveries." />;

  // Filter active and completed orders
  const activeOrders = allOrders.filter((order) => ACTIVE_STATUSES.includes(order.status));
  
  const completedOrders = history.orders.filter(
    (order) => order.status === 'delivered' && new Date(order.updated_at).getTime() >= startOfToday,
  );

  // Compute statistics
  const todayTotalEarnings = sumAmounts(completedOrders.map((order) => order.delivery_price));

  const formatPrice = (price: number) => {
    return price.toLocaleString() + ' UZS';
  };

  const handleOpenMap = (addressText: string | null) => {
    hapticFeedback('light');
    if (addressText && pregMatchUrl(addressText)) {
      const url = getUrl(addressText);
      window.open(url, '_blank');
    }
  };

  const pregMatchUrl = (str: string) => {
    return /(https?:\/\/[^\s]+)/.test(str);
  };

  const getUrl = (str: string) => {
    const matches = str.match(/(https?:\/\/[^\s]+)/);
    return matches ? matches[0] : '';
  };

  const getCleanAddress = (addressText: string) => {
    if (pregMatchUrl(addressText)) {
      const url = getUrl(addressText);
      const clean = addressText.replace(/📍 Lokatsiya:|Lokatsiya:/g, '').replace(url, '').trim();
      return clean || (language === 'uz' ? 'GPS lokatsiya' : 'GPS локация');
    }
    return addressText;
  };

  return (
    <div className="p-4 flex flex-col gap-5 min-h-screen pb-24">
      {/* Top Header Card */}
      <header className="flex flex-col gap-1.5 bg-secondary/40 p-4 rounded-3xl border border-hint/5 backdrop-blur-md relative overflow-hidden">
        <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full bg-purple-500/10 blur-[60px] pointer-events-none"></div>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-500 text-xl font-bold shadow-sm">
            🚴
          </div>
          <div>
            <div className="flex items-center gap-1">
              <span className="text-[9px] uppercase font-black text-purple-400 tracking-wider">
                {language === 'uz' ? 'Kuryer Kabineti' : 'Кабинет курьера'}
              </span>
              <span className="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span>
            </div>
            <h1 className="text-sm font-black text-text-h">
              {user?.name}
            </h1>
          </div>
        </div>
      </header>

      {/* Analytics Stats Widgets */}
      <div className="grid grid-cols-2 gap-3.5">
        <div className="bg-secondary p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 shadow-sm">
          <div className="flex items-center gap-1.5 text-purple-500">
            <DollarSign className="w-4 h-4" />
            <span className="text-[9px] font-black uppercase tracking-wider text-hint">
              {language === 'uz' ? 'Bugungi daromad' : 'Сегодня заработано'}
            </span>
          </div>
          <span className="text-sm font-black text-text-h mt-1">
            {formatPrice(todayTotalEarnings)}
          </span>
        </div>
        <div className="bg-secondary p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 shadow-sm">
          <div className="flex items-center gap-1.5 text-emerald-500">
            <ShieldCheck className="w-4 h-4" />
            <span className="text-[9px] font-black uppercase tracking-wider text-hint">
              {language === 'uz' ? 'Yetkazilganlar' : 'Доставлено'}
            </span>
          </div>
          <span className="text-sm font-black text-text-h mt-1">
            {completedOrders.length} {language === 'uz' ? 'ta' : 'шт'}
          </span>
        </div>
      </div>

      {activeData?.truncated && <TruncatedOrdersNotice />}

      {/* Tabs Switcher */}
      <div className="flex bg-secondary p-1 rounded-2xl border border-hint/5">
        <button
          onClick={() => { hapticFeedback('light'); setActiveTab('active'); }}
          className={`flex-1 py-3 text-xs font-black rounded-xl transition-all ${
            activeTab === 'active' 
              ? 'bg-primary text-white shadow-sm' 
              : 'text-hint'
          }`}
        >
          {language === 'uz' ? `Faol (${activeOrders.length})` : `Активные (${activeOrders.length})`}
        </button>
        <button
          onClick={() => { hapticFeedback('light'); setActiveTab('completed'); }}
          className={`flex-1 py-3 text-xs font-black rounded-xl transition-all ${
            activeTab === 'completed' 
              ? 'bg-primary text-white shadow-sm' 
              : 'text-hint'
          }`}
        >
          {language === 'uz' ? `Topshirilganlar (${completedOrders.length})` : `Завершенные (${completedOrders.length})`}
        </button>
      </div>

      {/* Orders List Container */}
      <div className="flex flex-col gap-4">
        {(activeTab === 'active' ? isLoading && allOrders.length === 0 : history.isLoading) ? (
          <div className="flex flex-col gap-4 animate-pulse">
            {[1, 2].map((n) => (
              <div key={n} className="bg-secondary p-5 rounded-3xl border border-hint/5 flex flex-col gap-3">
                <div className="flex justify-between items-center">
                  <div className="h-4 bg-hint/15 rounded w-24"></div>
                  <div className="h-6 bg-hint/15 rounded-full w-20"></div>
                </div>
                <div className="h-3.5 bg-hint/10 rounded w-48 mt-1"></div>
                <div className="h-3.5 bg-hint/10 rounded w-32"></div>
              </div>
            ))}
          </div>
        ) : activeTab === 'active' ? (
          activeOrders.length === 0 ? (
            <div className="bg-secondary/40 p-8 rounded-3xl text-center border border-hint/5 flex flex-col items-center gap-2.5">
              <span className="text-3xl">🛵</span>
              <p className="text-xs text-hint font-medium">
                {language === 'uz' ? 'Sizga hali buyurtma biriktirilmagan.' : 'У вас пока нет активных заказов.'}
              </p>
            </div>
          ) : (
            activeOrders.map((order) => (
              <div 
                key={order.id}
                className="bg-secondary p-5 rounded-3xl border border-hint/5 shadow-sm flex flex-col gap-4 relative overflow-hidden"
              >
                {/* Order ID & Status Badge */}
                <div className="flex justify-between items-center pb-3 border-b border-hint/5">
                  <div className="flex flex-col">
                    <span className="text-[10px] font-black uppercase text-hint tracking-wider">
                      {language === 'uz' ? 'Buyurtma' : 'Заказ'}
                    </span>
                    <span className="text-xs font-black text-text-h">
                      #{order.short_code}
                    </span>
                  </div>
                  <span className="bg-amber-500/10 text-amber-500 text-[9px] font-black px-2.5 py-1 rounded-full border border-amber-500/20 uppercase">
                    {order.status === 'courier_assigned' ? (language === 'uz' ? 'Kuryer kutilmoqda' : 'Ожидание курьера') :
                     order.status === 'on_the_way' || order.status === 'delivery' ? (language === 'uz' ? 'Yo\'lda' : 'В пути') : 
                     (language === 'uz' ? 'Tayyorlanmoqda' : 'Готовится')}
                  </span>
                </div>

                {/* Customer Info */}
                <div className="flex flex-col gap-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <h4 className="text-xs font-black text-text-h">{order.user?.name}</h4>
                      <p className="text-[10px] text-hint mt-0.5">{order.user?.phone || (language === 'uz' ? 'Telefon kiritilmagan' : 'Телефон не указан')}</p>
                    </div>
                    {order.user?.phone && (
                      <a
                        href={`tel:${order.user.phone}`}
                        onClick={() => hapticFeedback('light')}
                        className="w-9 h-9 bg-primary/10 border border-primary/20 text-primary rounded-full flex items-center justify-center shadow-sm"
                      >
                        <Phone className="w-4 h-4" />
                      </a>
                    )}
                  </div>

                  {/* Delivery Location / Address */}
                  {order.address && (
                    <div className="bg-bg/60 p-3.5 rounded-2xl border border-hint/5 flex flex-col gap-3">
                      <div className="flex gap-2">
                        <MapPin className="w-4 h-4 text-purple-500 shrink-0" />
                        <span className="text-[11px] text-text font-bold leading-normal">
                          {getCleanAddress(order.address)}
                        </span>
                      </div>

                      {pregMatchUrl(order.address) && (
                        <button
                          onClick={() => handleOpenMap(order.address)}
                          className="w-full bg-purple-500/10 border border-purple-500/20 text-purple-500 font-extrabold text-[11px] py-2.5 rounded-xl flex items-center justify-center gap-1.5 transition-all active:scale-95"
                        >
                          <Navigation className="w-3.5 h-3.5 fill-current" />
                          <span>{language === 'uz' ? 'Navigatorda ochish' : 'Открыть в Навигаторе'}</span>
                        </button>
                      )}
                    </div>
                  )}
                </div>

                {/* Order Items Summary */}
                <div className="bg-bg/40 p-3 rounded-2xl border border-hint/5 flex flex-col gap-1.5">
                  <div className="text-[9px] font-black uppercase text-hint tracking-wider mb-1">
                    {language === 'uz' ? 'Mahsulotlar' : 'Содержимое'}
                  </div>
                  {order.items?.map((item, idx) => (
                    <div key={idx} className="flex justify-between items-center text-[11px] font-bold text-text">
                      <span>{item.product_name} x{item.quantity}</span>
                    </div>
                  ))}
                  <div className="flex justify-between items-center text-[11px] font-black text-text-h border-t border-hint/5 pt-2 mt-1">
                    <span>{language === 'uz' ? 'Jami summa' : 'Итоговая сумма'}</span>
                    <span className="text-primary">{formatPrice(toAmount(order.total_price))}</span>
                  </div>
                </div>

                {/* Status-specific Action Button */}
                {order.status === 'courier_assigned' && (
                  <button
                    onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'on_the_way' })}
                    disabled={updateStatusMutation.isPending}
                    className="w-full bg-primary text-white font-extrabold text-xs py-4 rounded-2xl active:scale-95 transition-all shadow-md shadow-primary/20 flex items-center justify-center gap-2"
                  >
                    {updateStatusMutation.isPending ? (
                      <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    ) : (
                      <>
                        <Navigation className="w-4 h-4" />
                        <span>{language === 'uz' ? 'Olib ketdim (Yo\'lga chiqdim) 🚚' : 'Забрал (В пути) 🚚'}</span>
                      </>
                    )}
                  </button>
                )}

                {(order.status === 'on_the_way' || order.status === 'delivery') && (
                  <button
                    onClick={() => completeMutation.mutate(order.uuid)}
                    disabled={completeMutation.isPending}
                    className="w-full bg-emerald-500 text-white font-extrabold text-xs py-4 rounded-2xl active:scale-95 transition-all shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
                  >
                    {completeMutation.isPending ? (
                      <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    ) : (
                      <>
                        <CheckCircle className="w-4 h-4" />
                        <span>{language === 'uz' ? 'Yetkazib berdim (Yakunlash) ✅' : 'Доставил (Завершить) ✅'}</span>
                      </>
                    )}
                  </button>
                )}
              </div>
            ))
          )
        ) : (
          completedOrders.length === 0 ? (
            <div className="bg-secondary/40 p-8 rounded-3xl text-center border border-hint/5 flex flex-col items-center gap-2.5">
              <span className="text-3xl">🏁</span>
              <p className="text-xs text-hint font-medium">
                {language === 'uz' ? 'Bugun hali yetkazib berilgan buyurtmalar yo\'q.' : 'Сегодня еще нет доставленных заказов.'}
              </p>
            </div>
          ) : (
            completedOrders.map((order) => (
              <div 
                key={order.id}
                className="bg-secondary p-4 rounded-3xl border border-hint/5 shadow-sm flex flex-col gap-3 opacity-80"
              >
                <div className="flex justify-between items-center pb-2 border-b border-hint/5">
                  <span className="text-xs font-black text-text-h">#{order.short_code}</span>
                  <span className="bg-emerald-500/10 text-emerald-500 text-[9px] font-black px-2 py-0.5 rounded-md border border-emerald-500/10 uppercase flex items-center gap-1">
                    <CheckCircle className="w-3.5 h-3.5" />
                    {language === 'uz' ? 'Yetkazildi' : 'Доставлен'}
                  </span>
                </div>
                <div className="flex justify-between items-center text-xs font-bold text-hint">
                  <span>{order.user?.name}</span>
                  <span className="text-text-h">{formatPrice(toAmount(order.total_price))}</span>
                </div>
                <div className="text-[10px] text-hint flex items-center gap-1.5">
                  <Clock className="w-3.5 h-3.5" />
                  {new Date(order.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                </div>
              </div>
            ))
          )
        )}
      </div>
    </div>
  );
}
