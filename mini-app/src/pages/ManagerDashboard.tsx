import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { InlineError } from '../components/ui/InlineError';
import { 
  Bike, 
  CheckCircle, 
  XCircle, 
  MapPin,
  Activity
} from 'lucide-react';
import api from '../services/api';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatMoney } from '../lib/format';
import { getPaymentMethodLabel } from '../lib/paymentMethod';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, ORDERS_KEY, useOrderHistory, useStaffOrders } from '../hooks/useOrders';
import { StaffCancelDialog } from '../components/CancelDialogs';
import { LoadMoreOrders, TruncatedOrdersNotice } from '../components/OrderListParts';
import { getStaffCancelReasonLabel } from '../lib/cancelReasons';
import type { ApiResponse, AssignableStaff, CancelReason, Order, OrderStatus } from '../types';

const ACTIVE_STATUSES: readonly OrderStatus[] = ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'];
const WITH_COURIER_STATUSES: readonly OrderStatus[] = ['courier_assigned', 'on_the_way', 'delivery'];
// Statuses a manager can still cancel from the kitchen (a new order is rejected instead)
const CANCELABLE_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready'];

interface StatusChange {
  orderUuid: string;
  status: OrderStatus;
  courierId?: number;
  reason?: CancelReason; // required by the API for canceled and rejected
  note?: string;
}

interface CancelRequest {
  order: Order;
  mode: 'cancel' | 'reject';
}

const isCancellation = (status: OrderStatus) => status === 'canceled' || status === 'rejected';

export default function ManagerDashboard() {
  const { user } = useAuthStore();
  const { language, t } = useLanguageStore();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');
  const [selectedCourierId, setSelectedCourierId] = useState<Record<string, number>>({});
  const [cancelRequest, setCancelRequest] = useState<CancelRequest | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  // 1. Fetch the open store orders: polled while the screen is visible, pushed over the socket when it is up
  const { data: activeData, isLoading, error, refetch: refetchActive, isRefetching: isRefetchingActive } = useStaffOrders('manager', POLL_INTERVAL_MS.managerOrders);
  const orders = activeData?.orders ?? NO_ORDERS;

  // The finished orders (the archive tab) come a page at a time, once the tab is opened
  const history = useOrderHistory('manager', { enabled: activeTab === 'completed' });
  const completedOrders = history.orders;

  // 2. Fetch active couriers
  const { data: couriers = [] } = useQuery({
    queryKey: ['storeCouriers'],
    queryFn: async () => {
      const response = await api.get<ApiResponse<AssignableStaff[]>>('/couriers');
      return response.data?.data || [];
    }
  });

  // 3. Update order status mutation
  const updateStatusMutation = useMutation({
    mutationFn: async ({ orderUuid, status, courierId, reason, note }: StatusChange) => {
      hapticFeedback('medium');
      if (status === 'courier_assigned') {
        const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/assign-courier`, {
          courier_id: courierId
        });
        return response.data;
      } else {
        const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/status`, {
          status,
          ...(isCancellation(status) ? { reason, ...(note ? { note } : {}) } : {})
        });
        return response.data;
      }
    },
    onSuccess: (_data, variables) => {
      // The order moves between the open list and the archive
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
      hapticFeedback('medium');
      if (isCancellation(variables.status)) {
        setCancelRequest(null);
        setCancelError(null);
      }
    },
    onError: (err, variables) => {
      logApiError('Failed to update status:', err);
      const message = getApiFieldError(err, 'reason') ?? getApiErrorMessage(err);
      if (isCancellation(variables.status)) {
        // Shown in the reason picker, which stays open
        setCancelError(message ?? (language === 'uz' ? 'Bekor qilishda xatolik yuz berdi!' : 'Ошибка при отмене заказа!'));
        return;
      }
      alert(message || (language === 'uz' ? 'Holatni yangilashda xatolik yuz berdi!' : 'Ошибка при обновлении статуса!'));
    }
  });

  const openCancelDialog = (order: Order, mode: CancelRequest['mode']) => {
    hapticFeedback('light');
    updateStatusMutation.reset();
    setCancelError(null);
    setCancelRequest({ order, mode });
  };

  const closeCancelDialog = () => {
    if (updateStatusMutation.isPending) return;
    setCancelRequest(null);
    setCancelError(null);
  };

  // A failed background refresh keeps showing the last orders; the error screen is only for "nothing to show"
  if (error && orders.length === 0) {
    return (
      <div className="p-4">
        <InlineError onRetry={() => void refetchActive()} retrying={isRefetchingActive} />
      </div>
    );
  }

  const activeOrders = orders.filter((order) => ACTIVE_STATUSES.includes(order.status));

  const formatPrice = (price: number) => formatMoney(price, language);

  const getCleanAddress = (addressText: string | null) => {
    if (!addressText) return '';
    const urlMatches = addressText.match(/(https?:\/\/[^\s]+)/);
    if (urlMatches) {
      const clean = addressText.replace(/📍 Lokatsiya:|Lokatsiya:/g, '').replace(urlMatches[0], '').trim();
      return clean || (language === 'uz' ? 'GPS lokatsiya' : 'GPS локация');
    }
    return addressText;
  };


  // --- Individual Order Stage Time Trackers ---
  const getStageDurations = (order: Order) => {
    const histories = order.status_histories;
    if (!histories || histories.length === 0) return null;
    
    const times: Partial<Record<OrderStatus, Date>> = {};
    histories.forEach((h) => {
      times[h.status] = new Date(h.created_at);
    });
    
    const formatDiff = (start?: Date, end?: Date) => {
      if (!start || !end) return null;
      const diffMs = end.getTime() - start.getTime();
      const diffMins = Math.floor(diffMs / 60000);
      const diffSecs = Math.floor((diffMs % 60000) / 1000);
      
      if (diffMins > 0) {
        return `${diffMins}m ${diffSecs}s`;
      }
      return `${diffSecs}s`;
    };
    
    return {
      acceptTime: formatDiff(times['new'], times['accepted']),
      prepTime: formatDiff(times['accepted'], times['preparing']),
      readyTime: formatDiff(times['preparing'], times['ready']),
      transitTime: formatDiff(times['ready'] || times['preparing'], times['on_the_way'] || times['delivery']),
      deliveryTime: formatDiff((times['on_the_way'] || times['delivery']) || times['ready'], times['delivered']),
    };
  };

  return (
    <div className="p-4 pb-24 min-h-screen flex flex-col gap-5">
      {/* Premium Header */}
      <header className="flex flex-col gap-1.5 bg-secondary/40 p-4 rounded-3xl border border-hint/5 backdrop-blur-md relative overflow-hidden">
        <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full bg-primary/10 blur-[60px] pointer-events-none"></div>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary text-xl font-bold shadow-sm">
            🛡️
          </div>
          <div>
            <div className="flex items-center gap-1">
              <span className="text-[9px] uppercase font-black text-primary tracking-wider">
                {language === 'uz' ? 'Menejer Boshqaruvi' : 'Панель Менеджера'}
              </span>
              <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
            </div>
            <h1 className="text-sm font-black text-fg">
              {user?.name}
            </h1>
          </div>
        </div>
      </header>

      {/* Quick Operational Status Counters */}
      <div className="grid grid-cols-4 gap-2">
        <div className="bg-secondary p-2 rounded-2xl border border-hint/5 flex flex-col gap-0.5 items-center justify-center text-center">
          <span className="text-[8px] font-black uppercase text-hint tracking-wider">
            {language === 'uz' ? 'Yangi' : 'Новые'}
          </span>
          <span className="text-sm font-black text-amber-500 mt-1">
            {orders.filter((o) => o.status === 'new').length}
          </span>
        </div>
        <div className="bg-secondary p-2 rounded-2xl border border-hint/5 flex flex-col gap-0.5 items-center justify-center text-center">
          <span className="text-[8px] font-black uppercase text-hint tracking-wider">
            {language === 'uz' ? 'Tayyorlash' : 'Готовка'}
          </span>
          <span className="text-sm font-black text-purple-500 mt-1">
            {orders.filter((o) => o.status === 'preparing').length}
          </span>
        </div>
        <div className="bg-secondary p-2 rounded-2xl border border-hint/5 flex flex-col gap-0.5 items-center justify-center text-center">
          <span className="text-[8px] font-black uppercase text-hint tracking-wider">
            {language === 'uz' ? 'Tayyor' : 'Готовы'}
          </span>
          <span className="text-sm font-black text-emerald-500 mt-1">
            {orders.filter((o) => o.status === 'ready').length}
          </span>
        </div>
        <div className="bg-secondary p-2 rounded-2xl border border-hint/5 flex flex-col gap-0.5 items-center justify-center text-center">
          <span className="text-[8px] font-black uppercase text-hint tracking-wider">
            {language === 'uz' ? 'Kuryer' : 'Курьер'}
          </span>
          <span className="text-sm font-black text-orange-500 mt-1">
            {orders.filter((o) => WITH_COURIER_STATUSES.includes(o.status)).length}
          </span>
        </div>
      </div>

      {activeData?.truncated && <TruncatedOrdersNotice />}

      {/* Tabs */}
      <div className="flex bg-secondary p-1 rounded-2xl border border-hint/5">
        <button
          onClick={() => { hapticFeedback('light'); setActiveTab('active'); }}
          className={`flex-1 py-3 text-xs font-black rounded-xl transition-all ${
            activeTab === 'active' 
              ? 'bg-primary text-on-brand shadow-sm' 
              : 'text-hint'
          }`}
        >
          {language === 'uz' ? `Faol (${activeOrders.length})` : `Активные (${activeOrders.length})`}
        </button>
        <button
          onClick={() => { hapticFeedback('light'); setActiveTab('completed'); }}
          className={`flex-1 py-3 text-xs font-black rounded-xl transition-all ${
            activeTab === 'completed' 
              ? 'bg-primary text-on-brand shadow-sm' 
              : 'text-hint'
          }`}
        >
          {language === 'uz' ? 'Yakunlanganlar' : 'Завершенные'}{completedOrders.length > 0 ? ` (${completedOrders.length}${history.hasNextPage ? '+' : ''})` : ''}
        </button>
      </div>

      {/* Orders List */}
      <div className="flex flex-col gap-4">
        {(activeTab === 'active' ? isLoading && orders.length === 0 : history.isLoading) ? (
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
              <span className="text-3xl">📋</span>
              <p className="text-xs text-hint font-medium">
                {language === 'uz' ? 'Hozircha faol buyurtmalar yo\'q.' : 'Активных заказов пока нет.'}
              </p>
            </div>
          ) : (
            activeOrders.map((order) => {
              const timings = getStageDurations(order);
              return (
                <div 
                  key={order.id}
                  className="bg-secondary p-5 rounded-3xl border border-hint/5 shadow-sm flex flex-col gap-4 relative overflow-hidden"
                >
                  {/* Header Info */}
                  <div className="flex justify-between items-center pb-3 border-b border-hint/5">
                    <div className="flex flex-col">
                      <span className="text-[10px] font-black uppercase text-hint tracking-wider">
                        #{order.short_code}
                      </span>
                      <span className="text-xs font-black text-fg">
                        {order.user?.name}
                      </span>
                    </div>
                    <span className={`text-[9px] font-black px-2.5 py-1 rounded-full border uppercase ${
                      order.status === 'new' ? 'bg-amber-500/10 text-amber-500 border-amber-500/20' :
                      order.status === 'accepted' ? 'bg-blue-500/10 text-blue-500 border-blue-500/20' :
                      order.status === 'preparing' ? 'bg-purple-500/10 text-purple-500 border-purple-500/20 animate-pulse' :
                      order.status === 'ready' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' :
                      order.status === 'courier_assigned' ? 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20 animate-pulse' :
                      'bg-orange-500/10 text-orange-500 border-orange-500/20'
                    }`}>
                      {order.status === 'new' ? (language === 'uz' ? 'Yangi' : 'Новый') :
                       order.status === 'accepted' ? (language === 'uz' ? 'Qabul qilindi' : 'Принят') :
                       order.status === 'preparing' ? (language === 'uz' ? 'Tayyorlanmoqda' : 'Готовится') :
                       order.status === 'ready' ? (language === 'uz' ? 'Tayyor' : 'Готов') :
                       order.status === 'courier_assigned' ? (language === 'uz' ? 'Kuryer kutilmoqda' : 'Ожидание курьера') :
                       (language === 'uz' ? 'Yo\'lda' : 'В пути')}
                    </span>
                  </div>

                  {/* Items & Pricing */}
                  <div className="bg-bg/40 p-3.5 rounded-2xl border border-hint/5 flex flex-col gap-2">
                    <div className="text-[9px] font-black uppercase text-hint tracking-wider mb-1">
                      {language === 'uz' ? 'Tarkibi' : 'Детали заказа'}
                    </div>
                    {order.items?.map((item, idx) => (
                      <div key={idx} className="flex justify-between items-center text-[11px] font-bold text-text">
                        <span>{item.product_name} x{item.quantity}</span>
                      </div>
                    ))}
                    <div className="flex justify-between items-center text-[11px] font-black text-fg border-t border-hint/5 pt-2 mt-1">
                      <span>{language === 'uz' ? 'Jami summa' : 'Сумма'}</span>
                      <span className="text-primary">{formatPrice(toAmount(order.total_price))}</span>
                    </div>
                    <div className="flex justify-between items-center text-[10px] text-hint font-medium pt-1">
                      <span>{language === 'uz' ? 'To\'lov turi' : 'Оплата'}</span>
                      <span>{getPaymentMethodLabel(order.payment_method, t)}</span>
                    </div>
                  </div>

                  {/* CUSTOM TIMING TRACKER SYSTEM (LIFECYCLE TIMELINES) */}
                  {timings && (
                    <div className="bg-bg/60 p-3.5 rounded-2xl border border-hint/5 flex flex-col gap-2.5">
                      <span className="text-[9px] font-black uppercase text-hint tracking-wider flex items-center gap-1">
                        <Activity className="w-3 h-3 text-primary" />
                        {language === 'uz' ? 'Operatsion vaqtlar hisobi' : 'Хронология заказа'}
                      </span>
                      
                      <div className="flex flex-col gap-2 text-[10px] font-bold text-fg">
                        {timings.acceptTime && (
                          <div className="flex justify-between items-center bg-secondary/50 px-3 py-1.5 rounded-xl">
                            <span className="text-hint">🆕 ➔ ✅ {language === 'uz' ? 'Qabul qilish' : 'Принятие'}:</span>
                            <span className="text-amber-500 font-extrabold">{timings.acceptTime}</span>
                          </div>
                        )}
                        {timings.prepTime && (
                          <div className="flex justify-between items-center bg-secondary/50 px-3 py-1.5 rounded-xl">
                            <span className="text-hint">🍳 {language === 'uz' ? 'Tayyorlash' : 'Готовка'}:</span>
                            <span className="text-purple-500 font-extrabold">{timings.prepTime}</span>
                          </div>
                        )}
                        {timings.readyTime && (
                          <div className="flex justify-between items-center bg-secondary/50 px-3 py-1.5 rounded-xl">
                            <span className="text-hint">🔔 {language === 'uz' ? 'Tayyor bo\'lishi' : 'Готовность'}:</span>
                            <span className="text-emerald-500 font-extrabold">{timings.readyTime}</span>
                          </div>
                        )}
                        {timings.transitTime && (
                          <div className="flex justify-between items-center bg-secondary/50 px-3 py-1.5 rounded-xl">
                            <span className="text-hint">🚚 {language === 'uz' ? 'Kuryerga topshirish' : 'Передача курьеру'}:</span>
                            <span className="text-orange-500 font-extrabold">{timings.transitTime}</span>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Customer Location */}
                  {order.address && (
                    <div className="bg-bg/60 p-3.5 rounded-2xl border border-hint/5 flex flex-col gap-1">
                      <span className="text-[9px] font-black uppercase text-hint tracking-wider">
                        {language === 'uz' ? 'Yetkazish manzili' : 'Адрес доставки'}
                      </span>
                      <div className="flex gap-2 mt-1 items-start">
                        <MapPin className="w-3.5 h-3.5 text-primary shrink-0 mt-0.5" />
                        <span className="text-[11px] text-fg font-bold leading-normal">
                          {getCleanAddress(order.address)}
                        </span>
                      </div>
                    </div>
                  )}

                  {/* Operational Action Buttons */}
                  <div className="flex flex-col gap-2 pt-2 border-t border-hint/5">
                    {order.status === 'new' && (
                      <div className="flex gap-2">
                        <button
                          onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'accepted' })}
                          className="flex-1 bg-primary text-on-brand font-extrabold text-[11px] py-3 rounded-xl active:scale-95 transition-all shadow-md shadow-primary/10"
                        >
                          {language === 'uz' ? 'Qabul qilish ✅' : 'Принять ✅'}
                        </button>
                        <button
                          onClick={() => openCancelDialog(order, 'reject')}
                          className="bg-red-500/10 border border-red-500/20 text-red-500 font-extrabold text-[11px] px-4 py-3 rounded-xl active:scale-95 transition-all"
                        >
                          {language === 'uz' ? 'Rad etish' : 'Отклонить'}
                        </button>
                      </div>
                    )}

                    {order.status === 'accepted' && (
                      <button
                        onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'preparing' })}
                        className="w-full bg-purple-500 text-white font-extrabold text-[11px] py-3.5 rounded-xl active:scale-95 transition-all shadow-md shadow-purple-500/15"
                      >
                        {language === 'uz' ? 'Tayyorlashni boshlash 🍳' : 'Начать готовку 🍳'}
                      </button>
                    )}

                    {order.status === 'preparing' && (
                      <button
                        onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'ready' })}
                        className="w-full bg-emerald-500 text-white font-extrabold text-[11px] py-3.5 rounded-xl active:scale-95 transition-all shadow-md shadow-emerald-500/15"
                      >
                        {language === 'uz' ? 'Tayyor bo\'ldi 🔔' : 'Готово 🔔'}
                      </button>
                    )}

                    {order.status === 'ready' && (
                      <div className="flex flex-col gap-2.5 bg-bg/50 p-3 rounded-2xl border border-hint/5">
                        {order.order_type === 'delivery' ? (
                          <>
                            <div className="flex flex-col gap-1">
                              <label className="text-[9px] uppercase font-black text-hint tracking-wider flex items-center gap-1">
                                <Bike className="w-3.5 h-3.5 text-primary" />
                                {language === 'uz' ? 'Kuryerni biriktirish' : 'Назначить курьера'}
                              </label>
                              <select
                                value={selectedCourierId[order.uuid] || ''}
                                onChange={(e) => {
                                  hapticFeedback('light');
                                  setSelectedCourierId(prev => ({
                                    ...prev,
                                    [order.uuid]: parseInt(e.target.value)
                                  }));
                                }}
                                className="w-full px-3 py-2 rounded-xl bg-bg border border-hint/15 font-bold text-xs text-fg outline-none"
                              >
                                <option value="">-- {language === 'uz' ? 'Kuryerni tanlang' : 'Выбрать курьера'} --</option>
                                {couriers.map((courier) => (
                                  <option key={courier.id} value={courier.id}>
                                    {courier.name} {courier.telegram_username ? `(@${courier.telegram_username})` : ''}
                                  </option>
                                ))}
                              </select>
                            </div>
                            <button
                              disabled={!selectedCourierId[order.uuid]}
                              onClick={() => updateStatusMutation.mutate({ 
                                orderUuid: order.uuid, 
                                status: 'courier_assigned',
                                courierId: selectedCourierId[order.uuid]
                              })}
                              className="w-full bg-orange-500 text-white font-extrabold text-[11px] py-3 rounded-xl active:scale-95 transition-all shadow-md shadow-orange-500/15 disabled:opacity-50 disabled:pointer-events-none"
                            >
                              {language === 'uz' ? 'Kuryerni tayinlash 🛵' : 'Назначить курьера 🛵'}
                            </button>
                          </>
                        ) : (
                          <button
                            onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'delivered' })}
                            className="w-full bg-emerald-500 text-white font-extrabold text-[11px] py-3.5 rounded-xl active:scale-95 transition-all shadow-md shadow-emerald-500/15"
                          >
                            {language === 'uz' ? 'Mijozga topshirish (Yakunlash) ✅' : 'Выдать заказ (Завершить) ✅'}
                          </button>
                        )}
                      </div>
                    )}

                    {order.status === 'courier_assigned' && (
                      <div className="flex flex-col gap-1.5 p-2 bg-bg/40 rounded-2xl text-[10px] text-hint font-bold">
                        <div className="flex justify-between items-center">
                          <span>{language === 'uz' ? 'Biriktirilgan kuryer' : 'Назначенный курьер'}</span>
                          <span className="text-fg">{order.courier?.name || 'Kuryer'}</span>
                        </div>
                        <p className="text-[9px] text-amber-500 mt-1 italic">
                          {language === 'uz' ? 'Kuryer buyurtmani qabul qilib yo\'lga chiqishini kutmoqda...' : 'Ожидание принятия курьером...'}
                        </p>
                        <div className="flex gap-2 mt-2">
                          <button
                            onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'on_the_way' })}
                            className="flex-1 bg-primary text-on-brand font-extrabold text-[11px] py-2.5 rounded-xl active:scale-95 transition-all"
                          >
                            {language === 'uz' ? 'Yo\'lga chiqdi 🚚' : 'В пути 🚚'}
                          </button>
                        </div>
                      </div>
                    )}

                    {(order.status === 'on_the_way' || order.status === 'delivery') && (
                      <div className="flex flex-col gap-1.5 p-2 bg-bg/40 rounded-2xl text-[10px] text-hint font-bold">
                        <div className="flex justify-between items-center">
                          <span>{language === 'uz' ? 'Mas\'ul kuryer' : 'Курьер'}</span>
                          <span className="text-fg">{order.courier?.name || 'Kuryer'}</span>
                        </div>
                        <button
                          onClick={() => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'delivered' })}
                          className="w-full bg-emerald-500 text-white font-extrabold text-[11px] py-3 mt-1.5 rounded-xl active:scale-95 transition-all shadow-md shadow-emerald-500/15"
                        >
                          {language === 'uz' ? 'Yetkazilganini tasdiqlash ✅' : 'Подтвердить доставку ✅'}
                        </button>
                      </div>
                    )}

                    {CANCELABLE_STATUSES.includes(order.status) && (
                      <button
                        onClick={() => openCancelDialog(order, 'cancel')}
                        className="w-full bg-red-500/10 border border-red-500/20 text-red-500 font-extrabold text-[11px] py-2.5 rounded-xl active:scale-95 transition-all"
                      >
                        {language === 'uz' ? 'Bekor qilish' : 'Отменить заказ'}
                      </button>
                    )}
                  </div>
                </div>
              );
            })
          )
        ) : (
          completedOrders.length === 0 ? (
            <div className="bg-secondary/40 p-8 rounded-3xl text-center border border-hint/5 flex flex-col items-center gap-2.5">
              <span className="text-3xl">🏁</span>
              <p className="text-xs text-hint font-medium">
                {language === 'uz' ? 'Hozircha yakunlangan buyurtmalar yo\'q.' : 'Завершенных заказов пока нет.'}
              </p>
            </div>
          ) : (
            completedOrders.map((order) => {
              const timings = getStageDurations(order);
              return (
                <div 
                  key={order.id}
                  className="bg-secondary p-4 rounded-3xl border border-hint/5 shadow-sm flex flex-col gap-3 opacity-80"
                >
                  <div className="flex justify-between items-center pb-2 border-b border-hint/5">
                    <span className="text-xs font-black text-fg">#{order.short_code}</span>
                    <span className={`text-[9px] font-black px-2 py-0.5 rounded-md border uppercase flex items-center gap-1 ${
                      order.status === 'delivered' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/10' :
                      order.status === 'refunded' ? 'bg-sky-500/10 text-sky-500 border-sky-500/10' :
                      'bg-red-500/10 text-red-500 border-red-500/10'
                    }`}>
                      {order.status === 'delivered' ? (
                        <>
                          <CheckCircle className="w-3.5 h-3.5" />
                          {language === 'uz' ? 'Yetkazildi' : 'Доставлен'}
                        </>
                      ) : order.status === 'refunded' ? (
                        <>
                          <XCircle className="w-3.5 h-3.5" />
                          {language === 'uz' ? 'Qaytarildi' : 'Возвращен'}
                        </>
                      ) : (
                        <>
                          <XCircle className="w-3.5 h-3.5" />
                          {order.status === 'rejected'
                            ? (language === 'uz' ? 'Rad etildi' : 'Отклонен')
                            : (language === 'uz' ? 'Bekor qilindi' : 'Отменен')}
                        </>
                      )}
                    </span>
                  </div>
                  <div className="flex justify-between items-center text-xs font-bold text-hint">
                    <span>{order.user?.name}</span>
                    <span className="text-fg">{formatPrice(toAmount(order.total_price))}</span>
                  </div>
                  {(order.status === 'canceled' || order.status === 'rejected') && (order.cancel_reason || order.cancel_note) && (
                    <div className="text-[10px] font-bold text-red-500 bg-red-500/5 border border-red-500/10 rounded-xl px-3 py-2 flex flex-col gap-0.5">
                      {order.cancel_reason && <span>{getStaffCancelReasonLabel(order.cancel_reason, language)}</span>}
                      {order.cancel_note && <span className="font-medium italic text-hint">"{order.cancel_note}"</span>}
                    </div>
                  )}

                  {/* Historic Timings */}
                  {timings && (
                    <div className="flex flex-wrap gap-1.5 pt-2 border-t border-hint/5 text-[8.5px] font-extrabold text-hint">
                      {timings.acceptTime && (
                        <span className="bg-bg/50 px-2 py-1 rounded-md border border-hint/5">
                          🆕➔✅: <span className="text-amber-500">{timings.acceptTime}</span>
                        </span>
                      )}
                      {timings.prepTime && (
                        <span className="bg-bg/50 px-2 py-1 rounded-md border border-hint/5">
                          🍳: <span className="text-purple-500">{timings.prepTime}</span>
                        </span>
                      )}
                      {timings.readyTime && (
                        <span className="bg-bg/50 px-2 py-1 rounded-md border border-hint/5">
                          🔔: <span className="text-emerald-500">{timings.readyTime}</span>
                        </span>
                      )}
                      {timings.transitTime && (
                        <span className="bg-bg/50 px-2 py-1 rounded-md border border-hint/5">
                          🚚: <span className="text-orange-500">{timings.transitTime}</span>
                        </span>
                      )}
                      {timings.deliveryTime && (
                        <span className="bg-bg/50 px-2 py-1 rounded-md border border-hint/5">
                          🏁: <span className="text-emerald-500">{timings.deliveryTime}</span>
                        </span>
                      )}
                    </div>
                  )}
                </div>
              );
            })
          )
        )}
        {activeTab === 'completed' && (
          <LoadMoreOrders
            hasNextPage={history.hasNextPage}
            isFetchingNextPage={history.isFetchingNextPage}
            isFetching={history.isFetching}
            isError={history.isFetchNextPageError}
            loadedCount={completedOrders.length}
            onLoadMore={() => void history.fetchNextPage()}
          />
        )}
      </div>

      {cancelRequest && (
        <StaffCancelDialog
          order={cancelRequest.order}
          mode={cancelRequest.mode}
          isPending={updateStatusMutation.isPending}
          error={cancelError}
          onClose={closeCancelDialog}
          onConfirm={({ reason, note }) =>
            updateStatusMutation.mutate({
              orderUuid: cancelRequest.order.uuid,
              status: cancelRequest.mode === 'reject' ? 'rejected' : 'canceled',
              reason,
              note,
            })
          }
        />
      )}
    </div>
  );
}
