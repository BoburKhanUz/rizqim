import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLanguageStore } from '../store/languageStore';
import { ErrorFallback } from '../App';
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

type Timeframe = 'today' | 'weekly' | 'monthly' | 'yearly' | 'custom';

export default function AdminReports() {
  const { language } = useLanguageStore();
  const [timeframe, setTimeframe] = useState<Timeframe>('today');
  const [startDate, setStartDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [isExporting, setIsExporting] = useState(false);
  const [exportMessage, setExportMessage] = useState<string | null>(null);

  // Fetch all orders of the store with shared caching and non-blocking background refetching
  const { data: orders = [], isLoading, error } = useQuery({
    queryKey: ['adminOrders'],
    queryFn: async () => {
      const response = await api.get('/orders');
      return response.data?.data || [];
    },
    refetchInterval: 15000,
    staleTime: 1000 * 60 * 5, // 5 minutes stale time for instant tab switches
  });

  const handleExportReport = async () => {
    setIsExporting(true);
    setExportMessage(null);
    try {
      const payload: any = { timeframe };
      if (timeframe === 'custom') {
        payload.start_date = startDate;
        payload.end_date = endDate;
      }
      
      const response = await api.post('/reports/export', payload);
      if (response.data?.success) {
        setExportMessage(response.data?.message || 'Hisobot muvaffaqiyatli yuborildi!');
      } else {
        setExportMessage('Xatolik yuz berdi.');
      }
    } catch (err: any) {
      setExportMessage(err.response?.data?.message || 'Xatolik yuz berdi.');
    } finally {
      setIsExporting(false);
      setTimeout(() => setExportMessage(null), 6000);
    }
  };

  if (error) return <ErrorFallback error="Failed to fetch reports data." />;

  // Filter orders based on timeframe
  const filteredOrders = orders.filter((o: any) => {
    const updatedAt = new Date(o.updated_at).getTime();
    const now = Date.now();
    
    if (timeframe === 'today') {
      const todayDate = new Date().toDateString();
      return new Date(o.updated_at).toDateString() === todayDate;
    }
    if (timeframe === 'weekly') {
      return now - updatedAt <= 7 * 24 * 60 * 60 * 1000;
    }
    if (timeframe === 'monthly') {
      return now - updatedAt <= 30 * 24 * 60 * 60 * 1000;
    }
    if (timeframe === 'yearly') {
      return now - updatedAt <= 365 * 24 * 60 * 60 * 1000;
    }
    if (timeframe === 'custom') {
      const start = new Date(startDate).setHours(0, 0, 0, 0);
      const end = new Date(endDate).setHours(23, 59, 59, 999);
      return updatedAt >= start && updatedAt <= end;
    }
    return true;
  });

  const deliveredOrders = filteredOrders.filter((o: any) => o.status === 'delivered');
  const canceledOrders = filteredOrders.filter((o: any) => o.status === 'canceled');

  const totalRevenue = deliveredOrders.reduce((sum: number, o: any) => sum + Number(o.total_price), 0);
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
            <h1 className="text-sm font-black text-text-h">
              {language === 'uz' ? 'Excel Hisobotlar Markazi' : 'Центр Отчетов Excel'}
            </h1>
          </div>
        </div>
      </header>

      {/* Timeframe Switcher Tabs */}
      <div className="flex bg-secondary p-1 rounded-2xl border border-hint/5 overflow-x-auto gap-0.5 scrollbar-none">
        {(['today', 'weekly', 'monthly', 'yearly', 'custom'] as Timeframe[]).map((tab) => (
          <button
            key={tab}
            onClick={() => setTimeframe(tab)}
            className={`flex-1 min-w-[64px] py-2 text-[9px] uppercase font-black rounded-xl transition-all ${
              timeframe === tab 
                ? 'bg-emerald-500 text-white shadow-sm' 
                : 'text-hint hover:text-text-h'
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
            <span className="text-[10px] uppercase font-black text-text-h">
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
          {renderValue(`${totalRevenue.toLocaleString()} UZS`, 'w-32')}
        </h2>
      </div>

      {/* Main Excel Exporter Block */}
      <div className="bg-secondary/40 p-5 rounded-3xl border border-hint/5 flex flex-col gap-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500">
            <FileSpreadsheet className="w-5 h-5" />
          </div>
          <div>
            <h3 className="text-xs font-black text-text-h">
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
          <span className="text-lg font-black text-text-h">
            {renderValue(`${deliveredOrders.length} ta`, 'w-16')}
          </span>
        </div>

        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-1">
          <span className="text-[9px] uppercase font-black text-hint flex items-center gap-1">
            <XCircle className="w-3.5 h-3.5 text-red-500" />
            {language === 'uz' ? 'Bekor qilingan' : 'Отмененные'}
          </span>
          <span className="text-lg font-black text-text-h">
            {renderValue(`${canceledOrders.length} ta`, 'w-16')}
          </span>
        </div>

        <div className="bg-secondary/40 p-4 rounded-3xl border border-hint/5 flex flex-col gap-1 col-span-2">
          <span className="text-[9px] uppercase font-black text-hint flex items-center gap-1">
            <Activity className="w-3.5 h-3.5 text-primary" />
            {language === 'uz' ? 'Muvaffaqiyat ko\'rsatkichi' : 'Процент успеха'}
          </span>
          <div className="flex items-center justify-between mt-1">
            <span className="text-lg font-black text-text-h">
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
