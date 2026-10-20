import { useMemo, useRef, useState, useEffect } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { formatMoney } from '../lib/format';
import { InlineError } from '../components/ui/InlineError';
import { 
  Calendar, 
  FileSpreadsheet, 
  CheckCircle2, 
  XCircle,
  FileText,
  CalendarRange,
  Activity,
  Download
} from 'lucide-react';
import api from '../services/api';
import { getApiErrorMessage } from '../lib/apiError';
import { isInTimeframe, type Timeframe } from '../lib/analytics';
import { sumAmounts } from '../lib/money';
import { useOrdersForPeriod } from '../hooks/useOrdersForPeriod';
import { PartialPeriodNotice, TruncatedOrdersNotice } from '../components/OrderListParts';
import type { ApiResponse } from '../types';

const TIMEFRAMES: readonly Timeframe[] = ['today', 'weekly', 'monthly', 'yearly', 'custom'];

// POST /reports/export
interface ExportPayload {
  timeframe: Timeframe;
  start_date?: string;
  end_date?: string;
}

export default function AdminReports() {
  const { language } = useLanguageStore();
  const [timeframe, setTimeframe] = useState<Timeframe>('today');
  const [startDate, setStartDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [isExporting, setIsExporting] = useState(false);
  const [exportMessage, setExportMessage] = useState<string | null>(null);
  const clearMessageTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  // The message disappears after a few seconds; the timer must not outlive the screen
  useEffect(() => () => clearTimeout(clearMessageTimer.current), []);

  // The store's open orders (refreshed in the background while the screen is visible) and the finished ones of the timeframe
  const dateRange = useMemo(() => ({ start: startDate, end: endDate }), [startDate, endDate]);
  const { orders, isLoading, error, dataUpdatedAt, truncated, capped, refetch, isRefetching } = useOrdersForPeriod(timeframe, timeframe === 'custom' ? dateRange : undefined);

  const handleExportReport = async () => {
    setIsExporting(true);
    setExportMessage(null);
    try {
      const payload: ExportPayload = { timeframe };
      if (timeframe === 'custom') {
        payload.start_date = startDate;
        payload.end_date = endDate;
      }
      
      const response = await api.post<ApiResponse<null>>('/reports/export', payload);
      if (response.data?.success) {
        setExportMessage(response.data?.message || 'Hisobot muvaffaqiyatli yuborildi!');
      } else {
        setExportMessage('Xatolik yuz berdi.');
      }
    } catch (err) {
      setExportMessage(getApiErrorMessage(err) || 'Xatolik yuz berdi.');
    } finally {
      setIsExporting(false);
      clearTimeout(clearMessageTimer.current);
      clearMessageTimer.current = setTimeout(() => setExportMessage(null), 6000);
    }
  };

  // Orders of the timeframe, measured from the time of the last refresh so the periods move on with the data
  const { filteredOrders, deliveredOrders, canceledOrders, totalRevenue } = useMemo(() => {
    const filtered = orders.filter((o) => isInTimeframe(o.updated_at, timeframe, dataUpdatedAt, dateRange));
    const delivered = filtered.filter((o) => o.status === 'delivered');
    return {
      filteredOrders: filtered,
      deliveredOrders: delivered,
      canceledOrders: filtered.filter((o) => o.status === 'canceled'),
      totalRevenue: sumAmounts(delivered.map((o) => o.total_price)),
    };
  }, [orders, timeframe, dateRange, dataUpdatedAt]);

  // A failed background refresh keeps showing the last numbers; the error screen is only for "nothing to show"
  if (error && orders.length === 0) {
    return (
      <div className="p-4">
        <InlineError onRetry={refetch} retrying={isRefetching} />
      </div>
    );
  }

  const successRate = filteredOrders.length > 0 
    ? Math.round((deliveredOrders.length / filteredOrders.length) * 100) 
    : 100;

  // Helper to render premium inline skeleton loader
  const renderValue = (val: React.ReactNode, widthClass = 'w-20') => {
    if (isLoading && orders.length === 0) {
      return <span className={`inline-block ${widthClass} h-4.5 bg-hint/15 rounded animate-pulse`}></span>;
    }
    return val;
  };

  return (
    <div className="p-4 flex flex-col gap-5 min-h-screen pb-24">
      {/* Header with Premium Lucide Icon */}
      <header className="flex flex-col gap-1.5 bg-secondary/40 p-4 rounded-3xl border border-hint/5 backdrop-blur-md relative overflow-hidden">
        <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full bg-emerald-500/10 blur-[60px] pointer-events-none"></div>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 shadow-sm">
            <FileText className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-1">
              <span className="text-[9px] uppercase font-black text-emerald-500 tracking-wider">
                {language === 'uz' ? 'Moliyaviy Hisobot' : 'Финансовый Отчет'}
              </span>
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <h1 className="text-sm font-black text-fg">
              {language === 'uz' ? 'Excel Hisobotlar Markazi' : 'Центр Отчетов Excel'}
            </h1>
          </div>
        </div>
      </header>

      {/* Timeframe Switcher Tabs */}
      <div className="flex bg-secondary p-1 rounded-2xl border border-hint/5 overflow-x-auto gap-0.5 scrollbar-none">
        {TIMEFRAMES.map((tab) => (
          <button
            key={tab}
            onClick={() => setTimeframe(tab)}
            className={`flex-1 min-w-[64px] py-2 text-[9px] uppercase font-black rounded-xl transition-all ${
              timeframe === tab 
                ? 'bg-emerald-500 text-white shadow-sm' 
                : 'text-hint hover:text-fg'
            }`}
          >
            {tab === 'today' ? (language === 'uz' ? 'Bugun' : 'Сегодня') :
             tab === 'weekly' ? (language === 'uz' ? 'Hafta' : 'Неделя') :
             tab === 'monthly' ? (language === 'uz' ? 'Oy' : 'Месяц') :
             tab === 'yearly' ? (language === 'uz' ? 'Yil' : 'Год') :
             (language === 'uz' ? 'Davr' : 'Период')}
          </button>
        ))}
      </div>

      {/* Dynamic Date Inputs for Custom Timeframe */}
      {timeframe === 'custom' && (
        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-3 animate-fadeIn">
          <div className="flex items-center gap-2 mb-1">
            <CalendarRange className="w-4 h-4 text-emerald-500" />
            <span className="text-[10px] uppercase font-black text-fg">
              {language === 'uz' ? 'Davrni Tanlash' : 'Выбор периода'}
            </span>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="flex flex-col gap-1">
              <span className="text-[9px] text-hint uppercase font-black">
                {language === 'uz' ? 'Boshlanish sanasi' : 'Дата начала'}
              </span>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="bg-secondary text-text text-xs p-2.5 rounded-xl border border-hint/15 focus:outline-none focus:border-emerald-500 font-bold"
              />
            </div>
            <div className="flex flex-col gap-1">
              <span className="text-[9px] text-hint uppercase font-black">
                {language === 'uz' ? 'Tugash sanasi' : 'Дата окончания'}
              </span>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="bg-secondary text-text text-xs p-2.5 rounded-xl border border-hint/15 focus:outline-none focus:border-emerald-500 font-bold"
              />
            </div>
          </div>
        </div>
      )}

      {truncated && <TruncatedOrdersNotice />}
      {capped && <PartialPeriodNotice />}

      {/* Hero Revenue Stats */}
      <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-3xl text-white flex flex-col gap-1 shadow-lg shadow-emerald-500/15 relative overflow-hidden">
        <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <span className="text-[10px] uppercase font-black tracking-wider opacity-80 flex items-center gap-1">
          <Calendar className="w-3.5 h-3.5" />
          {timeframe === 'today' ? (language === 'uz' ? 'Bugungi tushum' : 'Выручка за сегодня') :
           timeframe === 'weekly' ? (language === 'uz' ? 'Haftalik tushum' : 'Выручка за неделю') :
           timeframe === 'monthly' ? (language === 'uz' ? 'Oylik tushum' : 'Выручка за месяц') :
           timeframe === 'yearly' ? (language === 'uz' ? 'Yillik tushum' : 'Выручка за год') :
           (language === 'uz' ? 'Tanlangan davr tushumi' : 'Выручка за выбранный период')}
        </span>
        <h2 className="text-2xl font-black mt-2 leading-none">
          {renderValue(formatMoney(totalRevenue, language), 'w-32')}
        </h2>
      </div>

      {/* Main Excel Exporter Block */}
      <div className="bg-secondary/40 p-5 rounded-3xl border border-hint/5 flex flex-col gap-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500">
            <FileSpreadsheet className="w-5 h-5" />
          </div>
          <div>
            <h3 className="text-xs font-black text-fg">
              {language === 'uz' ? 'Excel fayl yaratish' : 'Создать файл Excel'}
            </h3>
            <p className="text-[10px] text-hint">
              {language === 'uz' 
                ? 'Kategoriyalar va mahsulotlar kesimida to\'liq tahlil' 
                : 'Полный анализ в разрезе категорий и продуктов'}
            </p>
          </div>
        </div>

        <p className="text-[10px] text-hint leading-relaxed">
          {language === 'uz'
            ? 'Hisobot tugmasi bosilganda tanlangan davrdagi barcha buyurtmalar yuklanadi va dynamic toifali XLSX jadvali sizning Telegram profilingizga zudlik bilan bot orqali yuboriladi.'
            : 'При нажатии кнопки будут выгружены все заказы за выбранный период и динамическая таблица XLSX будет немедленно отправлена на ваш аккаунт Telegram.'}
        </p>

        <button
          onClick={handleExportReport}
          disabled={isExporting}
          className="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold text-xs py-4 rounded-2xl flex items-center justify-center gap-2 active:scale-95 transition-all shadow-md shadow-emerald-500/10 disabled:opacity-75"
        >
          {isExporting ? (
            <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          ) : (
            <Download className="w-4 h-4" />
          )}
          <span>
            {language === 'uz' 
              ? `Excel (XLSX) Hisobotini Telegramga yuborish` 
              : `Отправить отчет Excel (XLSX) в Telegram`}
          </span>
        </button>

        {exportMessage && (
          <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-extrabold text-[10px] py-2.5 px-4 rounded-xl text-center animate-pulse">
            {exportMessage}
          </div>
        )}
      </div>

      {/* Summary Metrics Cards */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-1">
          <span className="text-[9px] uppercase font-black text-hint flex items-center gap-1">
            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
            {language === 'uz' ? 'Muvaffaqiyatli' : 'Успешные'}
          </span>
          <span className="text-lg font-black text-fg">
            {renderValue(`${deliveredOrders.length} ta`, 'w-16')}
          </span>
        </div>

        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-1">
          <span className="text-[9px] uppercase font-black text-hint flex items-center gap-1">
            <XCircle className="w-3.5 h-3.5 text-red-500" />
            {language === 'uz' ? 'Bekor qilingan' : 'Отмененные'}
          </span>
          <span className="text-lg font-black text-fg">
            {renderValue(`${canceledOrders.length} ta`, 'w-16')}
          </span>
        </div>

        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 col-span-2">
          <span className="text-[9px] uppercase font-black text-hint flex items-center gap-1">
            <Activity className="w-3.5 h-3.5 text-primary" />
            {language === 'uz' ? 'Muvaffaqiyat ko\'rsatkichi' : 'Процент успеха'}
          </span>
          <div className="flex items-center justify-between mt-1">
            <span className="text-lg font-black text-fg">
              {renderValue(`${successRate}%`, 'w-16')}
            </span>
            <div className="w-24 h-1.5 bg-hint/10 rounded-full overflow-hidden">
              <div 
                className="h-full bg-emerald-500 rounded-full transition-all duration-500" 
                style={{ width: `${successRate}%` }}
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
