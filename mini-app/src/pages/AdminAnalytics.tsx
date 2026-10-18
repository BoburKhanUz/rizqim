import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLanguageStore } from '../store/languageStore';
import { ErrorFallback } from '../App';
import { 
  Clock, 
  Bike,
  Calendar,
  Wallet,
  TrendingUp,
  Award,
  BarChart3
} from 'lucide-react';
import api from '../services/api';

type Timeframe = 'today' | 'weekly' | 'monthly' | 'yearly';

export default function AdminAnalytics() {
  const { language } = useLanguageStore();
  const [timeframe, setTimeframe] = useState<Timeframe>('today');

  // Fetch all orders of the store with shared caching and non-blocking background refetching
  const { data: orders = [], isLoading, error } = useQuery({
    queryKey: ['adminOrders'],
    queryFn: async () => {
      const response = await api.get('/orders');
      return response.data?.data || [];
    },
    refetchInterval: 15000, // Refresh analytics every 15s in background
    staleTime: 1000 * 60 * 5, // 5 minutes stale time for instant transitions
  });

  if (error) return <ErrorFallback error="Failed to fetch analytics data." />;

  const completedOrders = orders.filter((order: any) => 
    ['delivered', 'canceled'].includes(order.status)
  );

  // --- Dynamic Filtering Logic based on timeframe ---
  const getFilteredOrders = () => {
    const now = Date.now();
    const todayStr = new Date().toDateString();

    return completedOrders.filter((o: any) => {
      const orderTime = new Date(o.updated_at).getTime();
      const orderDateStr = new Date(o.updated_at).toDateString();

      switch (timeframe) {
        case 'today':
          return orderDateStr === todayStr;
        case 'weekly':
          // Past 7 days
          return orderTime >= now - 7 * 24 * 60 * 60 * 1000;
        case 'monthly':
          // Past 30 days
          return orderTime >= now - 30 * 24 * 60 * 60 * 1000;
        case 'yearly':
          // Past 365 days
          return orderTime >= now - 365 * 24 * 60 * 60 * 1000;
        default:
          return orderDateStr === todayStr;
      }
    });
  };

  const periodOrders = getFilteredOrders();
  const deliveredOrders = periodOrders.filter((o: any) => o.status === 'delivered');

  // Calculations for active timeframe
  const totalRevenue = deliveredOrders.reduce((sum: number, o: any) => sum + parseFloat(o.total_price), 0);

  // Split payment methods revenue
  const cashRevenue = deliveredOrders
    .filter((o: any) => o.payment_method === 'cash')
    .reduce((sum: number, o: any) => sum + parseFloat(o.total_price), 0);

  const onlineRevenue = totalRevenue - cashRevenue;

  // --- Individual Order Stage Time Trackers ---
  const getAveragePrepTime = () => {
    let count = 0;
    let totalMs = 0;
    
    periodOrders.forEach((o: any) => {
      const histories = o.status_histories || o.statusHistories;
      if (!histories || histories.length === 0) return;
      
      const times: Record<string, number> = {};
      histories.forEach((h: any) => {
        times[h.status] = new Date(h.created_at).getTime();
      });
      
      const start = times['accepted'];
      if (start && times['preparing']) {
        totalMs += (times['preparing'] - start);
        count++;
      }
    });
    
    if (count === 0) return '--';
    const avgSecs = totalMs / count / 1000;
    if (avgSecs < 60) return `${Math.round(avgSecs)}s`;
    return `${Math.round(avgSecs / 60)}m`;
  };

  const getAverageDeliveryTime = () => {
    let count = 0;
    let totalMs = 0;
    
    periodOrders.forEach((o: any) => {
      const histories = o.status_histories || o.statusHistories;
      if (!histories || histories.length === 0) return;
      
      const times: Record<string, number> = {};
      histories.forEach((h: any) => {
        times[h.status] = new Date(h.created_at).getTime();
      });
      
      const start = times['delivery'] || times['preparing'];
      if (start && times['delivered']) {
        totalMs += (times['delivered'] - start);
        count++;
      }
    });
    
    if (count === 0) return '--';
    const avgMins = Math.round((totalMs / count) / 60000);
    return `${avgMins}m`;
  };

  // --- Advanced Trend Analytics: Popular Products ---
  const getTopProducts = () => {
    const counts: Record<string, { count: number; name: string }> = {};
    deliveredOrders.forEach((o: any) => {
      o.items?.forEach((item: any) => {
        const name = item.product_name || 'Burger';
        if (!counts[name]) {
          counts[name] = { count: 0, name };
        }
        counts[name].count += item.quantity;
      });
    });
    return Object.values(counts)
      .sort((a, b) => b.count - a.count)
      .slice(0, 3);
  };

  // --- Advanced Trend Analytics: Courier Standings ---
  const getTopCouriers = () => {
    const counts: Record<string, { count: number; name: string }> = {};
    deliveredOrders.forEach((o: any) => {
      if (!o.courier) return;
      const name = o.courier.name;
      if (!counts[name]) {
        counts[name] = { count: 0, name };
      }
      counts[name].count += 1;
    });
    return Object.values(counts)
      .sort((a, b) => b.count - a.count)
      .slice(0, 3);
  };

  const topProducts = getTopProducts();
  const topCouriers = getTopCouriers();

  // Helper to render premium inline skeleton loader
  const renderValue = (val: React.ReactNode, widthClass = 'w-20') => {
    if (isLoading && orders.length === 0) {
      return <span className={`inline-block ${widthClass} h-4.5 bg-hint/15 rounded animate-pulse`}></span>;
    }
    return val;
  };

  return (
    <div className="p-4 flex flex-col gap-5 min-h-screen pb-24">
      {/* Header */}
      <header className="flex flex-col gap-1.5 bg-secondary/40 p-4 rounded-3xl border border-hint/5 backdrop-blur-md relative overflow-hidden">
        <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full bg-primary/10 blur-[60px] pointer-events-none"></div>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shadow-sm">
            <BarChart3 className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-1">
              <span className="text-[9px] uppercase font-black text-primary tracking-wider">
                {language === 'uz' ? 'Kengaytirilgan Analitika' : 'Расширенная Аналитика'}
              </span>
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <h1 className="text-sm font-black text-text-h">
              {language === 'uz' ? 'Tizim Trendlari va Hisoboti' : 'Отчеты и Тренды Системы'}
            </h1>
          </div>
        </div>
      </header>

      {/* Timeframe Switcher Tabs */}
      <div className="flex bg-secondary p-1 rounded-2xl border border-hint/5">
        {(['today', 'weekly', 'monthly', 'yearly'] as Timeframe[]).map((tab) => (
          <button
            key={tab}
            onClick={() => setTimeframe(tab)}
            className={`flex-1 py-2 text-[10px] uppercase font-black rounded-xl transition-all ${
              timeframe === tab 
                ? 'bg-primary text-white shadow-sm' 
                : 'text-hint hover:text-text-h'
            }`}
          >
            {tab === 'today' ? (language === 'uz' ? 'Bugun' : 'Сегодня') :
             tab === 'weekly' ? (language === 'uz' ? 'Hafta' : 'Неделя') :
             tab === 'monthly' ? (language === 'uz' ? 'Oy' : 'Месяц') :
             (language === 'uz' ? 'Yil' : 'Год')}
          </button>
        ))}
      </div>

      {/* Hero Revenue Stats */}
      <div className="bg-gradient-to-br from-primary to-primary-hover p-6 rounded-3xl text-white flex flex-col gap-1 shadow-lg shadow-primary/15 relative overflow-hidden">
        <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <span className="text-[10px] uppercase font-black tracking-wider opacity-80 flex items-center gap-1">
          <Calendar className="w-3.5 h-3.5" />
          {timeframe === 'today' ? (language === 'uz' ? 'Bugungi umumiy savdo' : 'Продажи за сегодня') :
           timeframe === 'weekly' ? (language === 'uz' ? 'Haftalik umumiy savdo' : 'Продажи за неделю') :
           timeframe === 'monthly' ? (language === 'uz' ? 'Oylik umumiy savdo' : 'Продажи за месяц') :
           (language === 'uz' ? 'Yillik umumiy savdo' : 'Продажи за год')}
        </span>
        <h2 className="text-2xl font-black mt-2 leading-none">
          {renderValue(`${totalRevenue.toLocaleString()} UZS`, 'w-32')}
        </h2>
        <p className="text-[10px] opacity-75 mt-1">
          {renderValue(
            language === 'uz' 
              ? `${deliveredOrders.length} ta muvaffaqiyatli buyurtmadan` 
              : `Из ${deliveredOrders.length} успешных заказов`,
            'w-24'
          )}
        </p>
      </div>

      {/* Grid operational timing analysis */}
      <div className="grid grid-cols-2 gap-3.5">
        <div className="bg-secondary p-4 rounded-2xl border border-hint/5 flex flex-col gap-0.5 shadow-sm">
          <span className="text-[9px] font-bold text-hint uppercase tracking-wider flex items-center gap-1">
            <Clock className="w-3.5 h-3.5 text-purple-500" />
            {language === 'uz' ? 'Tayyorlash (Avg)' : 'Кухня (Среднее)'}
          </span>
          <span className="text-base font-black text-purple-500 mt-2">
            {renderValue(getAveragePrepTime(), 'w-12')}
          </span>
        </div>

        <div className="bg-secondary p-4 rounded-2xl border border-hint/5 flex flex-col gap-0.5 shadow-sm">
          <span className="text-[9px] font-bold text-hint uppercase tracking-wider flex items-center gap-1">
            <Bike className="w-3.5 h-3.5 text-orange-500" />
            {language === 'uz' ? 'Yetkazish (Avg)' : 'Доставка (Среднее)'}
          </span>
          <span className="text-base font-black text-orange-500 mt-2">
            {renderValue(getAverageDeliveryTime(), 'w-12')}
          </span>
        </div>
      </div>

      {/* Payment methods splits */}
      <div className="bg-secondary p-5 rounded-3xl border border-hint/5 flex flex-col gap-4 shadow-sm">
        <h3 className="text-xs font-black text-text-h flex items-center gap-1.5">
          <Wallet className="w-4 h-4 text-primary" />
          {language === 'uz' ? "To'lov turlari bo'yicha" : "По типам оплаты"}
        </h3>

        <div className="flex flex-col gap-3">
          {/* Card / Online */}
          <div className="flex flex-col gap-1.5">
            <div className="flex justify-between items-center text-[11px] font-bold">
              <span className="text-hint">{language === 'uz' ? 'Karta orqali (Online):' : 'Картой (Онлайн):'}</span>
              <span className="text-text-h">{renderValue(`${onlineRevenue.toLocaleString()} UZS`, 'w-24')}</span>
            </div>
            <div className="w-full h-2 rounded-full bg-bg overflow-hidden">
              <div 
                className="h-full bg-primary rounded-full transition-all duration-500"
                style={{ width: totalRevenue > 0 ? `${(onlineRevenue / totalRevenue) * 100}%` : '0%' }}
              ></div>
            </div>
          </div>

          {/* Cash */}
          <div className="flex flex-col gap-1.5">
            <div className="flex justify-between items-center text-[11px] font-bold">
              <span className="text-hint">{language === 'uz' ? 'Naqd pul (Cash):' : 'Наличными (Cash):'}</span>
              <span className="text-text-h">{renderValue(`${cashRevenue.toLocaleString()} UZS`, 'w-24')}</span>
            </div>
            <div className="w-full h-2 rounded-full bg-bg overflow-hidden">
              <div 
                className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                style={{ width: totalRevenue > 0 ? `${(cashRevenue / totalRevenue) * 100}%` : '0%' }}
              ></div>
            </div>
          </div>
        </div>
      </div>

      {/* ADVANCED BUSINESS TRENDS: Top Products sold */}
      <div className="bg-secondary p-5 rounded-3xl border border-hint/5 flex flex-col gap-4 shadow-sm">
        <h3 className="text-xs font-black text-text-h flex items-center gap-1.5">
          <TrendingUp className="w-4 h-4 text-primary" />
          {language === 'uz' ? 'Eng ko\'p sotilgan burgerlar' : 'Популярные бургеры'}
        </h3>

        {isLoading && orders.length === 0 ? (
          <div className="flex flex-col gap-2.5">
            {[1, 2].map((n) => (
              <div key={n} className="flex justify-between items-center py-1">
                <span className="w-24 h-4 bg-hint/15 rounded animate-pulse"></span>
                <span className="w-10 h-4 bg-hint/15 rounded animate-pulse"></span>
              </div>
            ))}
          </div>
        ) : topProducts.length === 0 ? (
          <div className="text-[10px] text-hint text-center py-2">
            {language === 'uz' ? 'Sotuvlar mavjud emas' : 'Нет продаж'}
          </div>
        ) : (
          <div className="flex flex-col gap-2">
            {topProducts.map((p, index) => (
              <div key={p.name} className="flex justify-between items-center border-b border-hint/5 pb-2 last:border-0 last:pb-0">
                <span className="text-[11px] font-bold text-text-h flex items-center gap-1.5">
                  <span className="text-[10px] text-hint">#{index + 1}</span>
                  {p.name}
                </span>
                <span className="text-[10px] text-hint bg-bg px-2 py-0.5 rounded-md font-black">
                  {p.count} ta
                </span>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* ADVANCED BUSINESS TRENDS: Top Couriers standings */}
      <div className="bg-secondary p-5 rounded-3xl border border-hint/5 flex flex-col gap-4 shadow-sm">
        <h3 className="text-xs font-black text-text-h flex items-center gap-1.5">
          <Award className="w-4 h-4 text-primary" />
          {language === 'uz' ? 'Kuryerlar reytingi (Top)' : 'Рейтинг курьеров (Топ)'}
        </h3>

        {isLoading && orders.length === 0 ? (
          <div className="flex flex-col gap-2.5">
            {[1, 2].map((n) => (
              <div key={n} className="flex justify-between items-center py-1">
                <span className="w-24 h-4 bg-hint/15 rounded animate-pulse"></span>
                <span className="w-10 h-4 bg-hint/15 rounded animate-pulse"></span>
              </div>
            ))}
          </div>
        ) : topCouriers.length === 0 ? (
          <div className="text-[10px] text-hint text-center py-2">
            {language === 'uz' ? 'Kuryerlar ro\'yxati bo\'sh' : 'Список курьеров пуст'}
          </div>
        ) : (
          <div className="flex flex-col gap-2">
            {topCouriers.map((c, index) => (
              <div key={c.name} className="flex justify-between items-center border-b border-hint/5 pb-2 last:border-0 last:pb-0">
                <span className="text-[11px] font-bold text-text-h flex items-center gap-1.5">
                  <span className="text-[10px] text-hint">#{index + 1}</span>
                  {c.name}
                </span>
                <span className="text-[10px] text-hint bg-bg px-2 py-0.5 rounded-md font-black">
                  {c.count} ta buyurtma
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
