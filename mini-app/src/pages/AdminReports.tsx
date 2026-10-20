import { useMemo, useRef, useState, useEffect, type ReactNode } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { formatMoney } from '../lib/format';
import { Button, Card, Field, InlineError, PageHeader, Skeleton, fieldClass } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { Activity, CheckCircle2, Download, FileSpreadsheet, XCircle } from 'lucide-react';
import api from '../services/api';
import { getApiErrorMessage } from '../lib/apiError';
import { notificationOccurred } from '../lib/twa';
import { isInTimeframe, type Timeframe } from '../lib/analytics';
import { sumAmounts } from '../lib/money';
import { useOrdersForPeriod } from '../hooks/useOrdersForPeriod';
import { PartialPeriodNotice, TruncatedOrdersNotice } from '../components/OrderListParts';
import type { ApiResponse } from '../types';

// POST /reports/export
interface ExportPayload {
  timeframe: Timeframe;
  start_date?: string;
  end_date?: string;
}

export default function AdminReports() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const [timeframe, setTimeframe] = useState<Timeframe>('today');
  const [startDate, setStartDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [isExporting, setIsExporting] = useState(false);
  const [exportMessage, setExportMessage] = useState<{ text: string; ok: boolean } | null>(null);
  const clearMessageTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  // The message disappears after a few seconds; the timer must not outlive the screen
  useEffect(() => () => clearTimeout(clearMessageTimer.current), []);

  // The store's open orders (refreshed in the background while the screen is visible) and the finished ones of the timeframe
  const dateRange = useMemo(() => ({ start: startDate, end: endDate }), [startDate, endDate]);
  const { orders, isLoading, error, dataUpdatedAt, truncated, capped, refetch, isRefetching } = useOrdersForPeriod(timeframe, timeframe === 'custom' ? dateRange : undefined);

  const handleExportReport = async () => {
    setIsExporting(true);
    setExportMessage(null);
    let result: { text: string; ok: boolean };
    try {
      const payload: ExportPayload = { timeframe };
      if (timeframe === 'custom') {
        payload.start_date = startDate;
        payload.end_date = endDate;
      }

      const response = await api.post<ApiResponse<null>>('/reports/export', payload);
      result = response.data?.success
        ? { text: response.data?.message || (uz ? 'Hisobot muvaffaqiyatli yuborildi!' : 'Отчёт успешно отправлен!'), ok: true }
        : { text: uz ? 'Xatolik yuz berdi.' : 'Произошла ошибка.', ok: false };
    } catch (err) {
      result = { text: getApiErrorMessage(err) || (uz ? 'Xatolik yuz berdi.' : 'Произошла ошибка.'), ok: false };
    } finally {
      setIsExporting(false);
    }
    notificationOccurred(result.ok ? 'success' : 'error');
    setExportMessage(result);
    clearTimeout(clearMessageTimer.current);
    clearMessageTimer.current = setTimeout(() => setExportMessage(null), 6000);
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

  const title = uz ? 'Hisobotlar' : 'Отчёты';
  const subtitle = uz ? 'Tushum va Excel hisobot' : 'Выручка и отчёт Excel';

  // A failed background refresh keeps showing the last numbers; the error screen is only for "nothing to show"
  if (error && orders.length === 0) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={title} subtitle={subtitle} />
        <InlineError onRetry={refetch} retrying={isRefetching} />
      </div>
    );
  }

  const successRate = filteredOrders.length > 0
    ? Math.round((deliveredOrders.length / filteredOrders.length) * 100)
    : 100;

  const loading = isLoading && orders.length === 0;
  // The value, or a grey block of the same size while the first load runs
  const renderValue = (value: ReactNode, widthClass = 'w-20') => (loading ? <Skeleton className={`inline-block h-5 ${widthClass}`} /> : value);

  const timeframes = [
    { value: 'today' as const, label: uz ? 'Bugun' : 'Сегодня' },
    { value: 'weekly' as const, label: uz ? 'Hafta' : 'Неделя' },
    { value: 'monthly' as const, label: uz ? 'Oy' : 'Месяц' },
    { value: 'yearly' as const, label: uz ? 'Yil' : 'Год' },
    { value: 'custom' as const, label: uz ? 'Davr' : 'Период' },
  ];
  const revenueLabel: Record<Timeframe, string> = {
    today: uz ? 'Bugungi tushum' : 'Выручка за сегодня',
    weekly: uz ? 'Haftalik tushum' : 'Выручка за неделю',
    monthly: uz ? 'Oylik tushum' : 'Выручка за месяц',
    yearly: uz ? 'Yillik tushum' : 'Выручка за год',
    custom: uz ? 'Tanlangan davr tushumi' : 'Выручка за выбранный период',
  };

  return (
    <div className="flex flex-col gap-4 px-4 pt-2">
      <PageHeader title={title} subtitle={subtitle} />

      <SegmentedControl segments={timeframes} value={timeframe} onChange={setTimeframe} label={uz ? 'Davr' : 'Период'} />

      {/* Dates of a custom period */}
      {timeframe === 'custom' && (
        <Card className="grid grid-cols-2 gap-3 animate-fade-in">
          <Field label={uz ? 'Boshlanish sanasi' : 'Дата начала'}>
            {(props) => <input {...props} type="date" value={startDate} max={endDate || undefined} onChange={(e) => setStartDate(e.target.value)} className={fieldClass} />}
          </Field>
          <Field label={uz ? 'Tugash sanasi' : 'Дата окончания'}>
            {(props) => <input {...props} type="date" value={endDate} min={startDate || undefined} onChange={(e) => setEndDate(e.target.value)} className={fieldClass} />}
          </Field>
        </Card>
      )}

      {truncated && <TruncatedOrdersNotice />}
      {capped && <PartialPeriodNotice />}

      {/* The revenue of the period */}
      <section aria-label={revenueLabel[timeframe]} className="flex flex-col gap-1 rounded-sheet bg-brand p-5 text-on-brand">
        <p className="text-footnote font-medium">{revenueLabel[timeframe]}</p>
        <p className="text-display font-extrabold">{renderValue(formatMoney(totalRevenue, language), 'w-40')}</p>
      </section>

      {/* Excel export */}
      <Card className="flex flex-col gap-3">
        <div className="flex items-center gap-3">
          <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-success-soft text-success-soft-fg">
            <FileSpreadsheet aria-hidden="true" className="size-5" />
          </span>
          <div className="min-w-0">
            <h2 className="text-headline font-bold text-fg">{uz ? 'Excel fayl yaratish' : 'Создать файл Excel'}</h2>
            <p className="text-footnote text-fg-muted">
              {uz ? "Kategoriyalar va mahsulotlar kesimida to'liq tahlil" : 'Полный анализ в разрезе категорий и продуктов'}
            </p>
          </div>
        </div>

        <p className="text-footnote text-fg-muted">
          {uz
            ? 'Tugma bosilganda tanlangan davrdagi barcha buyurtmalar yuklanadi va XLSX jadvali Telegramdagi profilingizga bot orqali yuboriladi.'
            : 'При нажатии кнопки будут выгружены все заказы за выбранный период, и таблица XLSX будет отправлена на ваш аккаунт Telegram.'}
        </p>

        <Button size="lg" icon={Download} loading={isExporting} onClick={handleExportReport} fullWidth>
          {uz ? 'Excelni Telegramga yuborish' : 'Отправить Excel в Telegram'}
        </Button>

        {exportMessage && (
          <p
            role={exportMessage.ok ? 'status' : 'alert'}
            className={`rounded-control px-4 py-2.5 text-center text-footnote font-bold ${exportMessage.ok ? 'bg-success-soft text-success-soft-fg' : 'bg-danger-soft text-danger-soft-fg'}`}
          >
            {exportMessage.text}
          </p>
        )}
      </Card>

      {/* Summary */}
      <div className="grid grid-cols-2 gap-3">
        <Card className="flex flex-col gap-1">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <CheckCircle2 aria-hidden="true" className="size-4 text-success" />
            {uz ? 'Muvaffaqiyatli' : 'Успешные'}
          </span>
          <span className="text-title font-bold text-fg">{renderValue(`${deliveredOrders.length} ${uz ? 'ta' : 'шт'}`, 'w-16')}</span>
        </Card>

        <Card className="flex flex-col gap-1">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <XCircle aria-hidden="true" className="size-4 text-danger" />
            {uz ? 'Bekor qilingan' : 'Отмененные'}
          </span>
          <span className="text-title font-bold text-fg">{renderValue(`${canceledOrders.length} ${uz ? 'ta' : 'шт'}`, 'w-16')}</span>
        </Card>

        <Card className="col-span-2 flex flex-col gap-2">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <Activity aria-hidden="true" className="size-4 text-brand" />
            {uz ? "Muvaffaqiyat ko'rsatkichi" : 'Процент успеха'}
          </span>
          <div className="flex items-center justify-between gap-4">
            <span className="text-title font-bold text-fg">{renderValue(`${successRate}%`, 'w-16')}</span>
            <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-2" role="presentation">
              <div className="h-full rounded-full bg-success transition-[width] duration-500" style={{ width: `${successRate}%` }} />
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
