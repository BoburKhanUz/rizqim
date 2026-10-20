import { useState, useEffect } from 'react';
import { 
  Store, Users, DollarSign, RefreshCw, 
  Calendar, Activity, Landmark 
} from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { FullScreenLoader } from '../components/Feedback';
import { InlineError } from '../components/ui/InlineError';
import { formatMoney, formatNumber } from '../lib/format';
import type { Amount, ApiResponse, DashboardStats } from '../types';

export default function SuperadminDashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  // Bumped by the refresh button
  const [reloadCount, setReloadCount] = useState(0);

  useEffect(() => {
    let cancelled = false;
    const fetchStats = async () => {
      try {
        const res = await api.get<ApiResponse<DashboardStats>>('/superadmin/dashboard');
        if (cancelled) return;
        setStats(res.data.data);
        setError(null);
      } catch (err) {
        if (cancelled) return;
        logApiError('Failed to load dashboard:', err);
        setError('Statistikalarni yuklashda xatolik yuz berdi');
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchStats();
    return () => {
      cancelled = true;
    };
  }, [reloadCount]);

  const refresh = () => {
    setLoading(true);
    setReloadCount((count) => count + 1);
  };

  const formatPoints = (amount: Amount) => `${formatNumber(amount)} ball`;

  if (loading && !stats) return <FullScreenLoader message="Statistikalar yuklanmoqda..." />;
  if (error) {
    return (
      <div className="p-4">
        <InlineError onRetry={refresh} retrying={loading} />
      </div>
    );
  }

  return (
    <div className="p-4 pb-28 animate-fade-in space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-black text-fg">Boshqaruv Paneli</h1>
          <p className="text-xs text-hint font-medium">Tizimning umumiy holati</p>
        </div>
        <button 
          onClick={() => {
            hapticFeedback('light');
            refresh();
          }}
          className="p-2.5 bg-secondary rounded-2xl active:scale-95 transition-transform shadow-sm"
        >
          <RefreshCw className={`w-5 h-5 text-primary ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Section 1: Platform Totals (All-time vs Month vs 30 Days) */}
      <div className="space-y-4">
        <div className="flex items-center gap-1.5 px-1">
          <Landmark className="w-4 h-4 text-primary" />
          <h3 className="text-xs font-bold text-hint uppercase tracking-wider">Aylanma va Komissiyalar</h3>
        </div>

        {/* Total All-Time */}
        <div className="grid grid-cols-2 gap-3">
          <div className="bg-gradient-to-br from-primary to-blue-500 rounded-[24px] p-5 text-white shadow-lg shadow-primary/10 relative overflow-hidden">
            <div className="absolute top-0 right-0 p-3 opacity-20">
              <DollarSign className="w-12 h-12" />
            </div>
            <p className="text-[9px] font-bold uppercase tracking-wider opacity-85 mb-1">Umumiy GMV (Aylanma)</p>
            <h2 className="text-base font-black">{formatMoney(stats?.total_gmv || 0)}</h2>
          </div>

          <div className="bg-gradient-to-br from-[#10b981] to-[#059669] rounded-[24px] p-5 text-white shadow-lg shadow-green-500/10 relative overflow-hidden">
            <div className="absolute top-0 right-0 p-3 opacity-20">
              <Activity className="w-12 h-12" />
            </div>
            <p className="text-[9px] font-bold uppercase tracking-wider opacity-85 mb-1">Umumiy Komissiya</p>
            <h2 className="text-base font-black">{formatMoney(stats?.total_commission || 0)}</h2>
          </div>
        </div>

        {/* Current Month (Calendar Month) */}
        <div className="bg-secondary rounded-[24px] p-4 border border-hint/10 space-y-3">
          <div className="flex justify-between items-center border-b border-hint/10 pb-2">
            <span className="text-xs font-bold text-fg">Joriy oy (Taqvim bo'yicha)</span>
            <span className="text-[10px] font-medium text-hint">1-sanadan bugungacha</span>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <span className="text-[9px] font-bold text-hint uppercase">GMV Aylanma</span>
              <p className="text-sm font-black text-fg mt-0.5">{formatMoney(stats?.monthly_gmv || 0)}</p>
            </div>
            <div>
              <span className="text-[9px] font-bold text-hint uppercase">Platforma foydasi</span>
              <p className="text-sm font-black text-green-500 mt-0.5">{formatMoney(stats?.monthly_commission || 0)}</p>
            </div>
          </div>
        </div>

        {/* Last 30 Days (Rolling) */}
        <div className="bg-secondary rounded-[24px] p-4 border border-hint/10 space-y-3">
          <div className="flex justify-between items-center border-b border-hint/10 pb-2">
            <span className="text-xs font-bold text-fg">Oxirgi 30 kunlik</span>
            <span className="text-[10px] font-medium text-hint">Rolling 30 kun</span>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <span className="text-[9px] font-bold text-hint uppercase">GMV Aylanma</span>
              <p className="text-sm font-black text-fg mt-0.5">{formatMoney(stats?.last_30_days_gmv || 0)}</p>
            </div>
            <div>
              <span className="text-[9px] font-bold text-hint uppercase">Platforma foydasi</span>
              <p className="text-sm font-black text-green-500 mt-0.5">{formatMoney(stats?.last_30_days_commission || 0)}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Section 2: Today's Indicators */}
      <div className="space-y-3">
        <div className="flex items-center gap-1.5 px-1">
          <Calendar className="w-4 h-4 text-primary" />
          <h3 className="text-xs font-bold text-hint uppercase tracking-wider">Bugungi ko'rsatkichlar</h3>
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Bugun GMV</span>
            <span className="text-xs font-black text-fg mt-1">{formatMoney(stats?.today_gmv || 0)}</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Bugun foyda</span>
            <span className="text-xs font-black text-green-500 mt-1">{formatMoney(stats?.today_commission || 0)}</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Buyurtmalar</span>
            <span className="text-xs font-black text-primary mt-1">{stats?.today_orders || 0} ta</span>
          </div>
        </div>
      </div>

      {/* Section 3: Financial State */}
      <div className="space-y-3">
        <div className="flex items-center gap-1.5 px-1">
          <Activity className="w-4 h-4 text-green-500" />
          <h3 className="text-xs font-bold text-hint uppercase tracking-wider">Tizim Pul Oqimi</h3>
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between relative overflow-hidden">
            <span className="text-[9px] font-bold text-hint uppercase">Restoranlar Qarzi</span>
            <span className="text-xs font-black text-red-500 mt-1">{formatMoney(stats?.total_store_debt || 0)}</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between relative overflow-hidden">
            <span className="text-[9px] font-bold text-hint uppercase">Jami Keshbek</span>
            <span className="text-xs font-black text-yellow-500 mt-1">{formatPoints(stats?.total_cashback_balance || 0)}</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between relative overflow-hidden">
            <span className="text-[9px] font-bold text-hint uppercase">Kutilayotgan Payout</span>
            <span className={`text-xs font-black mt-1 ${stats?.pending_payouts && stats.pending_payouts > 0 ? 'text-blue-500 animate-pulse' : 'text-fg'}`}>
              {stats?.pending_payouts || 0} ta
            </span>
          </div>
        </div>
      </div>

      {/* Section 4: Store Activity */}
      <div className="space-y-3">
        <div className="flex items-center gap-1.5 px-1">
          <Store className="w-4 h-4 text-orange-500" />
          <h3 className="text-xs font-bold text-hint uppercase tracking-wider">Restoranlar Faolligi</h3>
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Jami</span>
            <span className="text-xs font-black text-fg mt-1">{stats?.total_stores || 0} ta</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Faol</span>
            <span className="text-xs font-black text-green-500 mt-1">{stats?.active_stores || 0} ta</span>
          </div>

          <div className="bg-secondary rounded-[20px] p-3 border border-hint/10 flex flex-col justify-between">
            <span className="text-[9px] font-bold text-hint uppercase">Bloklangan</span>
            <span className="text-xs font-black text-red-500 mt-1">{stats?.payment_required_stores || 0} ta</span>
          </div>
        </div>
      </div>

      {/* Section 5: General Directory Counts */}
      <div className="space-y-3">
        <div className="bg-secondary rounded-[24px] p-4 border border-hint/10 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
              <Users className="w-5 h-5 text-purple-500" />
            </div>
            <div>
              <p className="text-[10px] font-bold text-hint uppercase tracking-wider">Umumiy Mijozlar</p>
              <h3 className="text-sm font-black text-fg">{stats?.total_users || 0} ta</h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
