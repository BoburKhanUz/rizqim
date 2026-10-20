import { useState, type ReactNode } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { formatMoney } from '../lib/format';
import { Card, InlineError, PageHeader, Section, Skeleton } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { Award, Bike, ChefHat, TrendingUp } from 'lucide-react';
import { useOwnerAnalytics } from '../hooks/useOwnerAnalytics';

type AnalyticsTimeframe = 'today' | 'weekly' | 'monthly' | 'yearly';

// "--" until there is data; seconds below a minute, whole minutes above
const formatPrepTime = (secs: number | null | undefined) => {
  if (secs === null || secs === undefined) return '--';
  return secs < 60 ? `${Math.round(secs)}s` : `${Math.round(secs / 60)}m`;
};

const formatDeliveryTime = (secs: number | null | undefined) => (secs === null || secs === undefined ? '--' : `${Math.round(secs / 60)}m`);

// How many names a top list shows (the server sends more)
const TOP_SHOWN = 3;

export default function AdminAnalytics() {
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const [timeframe, setTimeframe] = useState<AnalyticsTimeframe>('today');

  // The server's figures for the timeframe: one request, refreshed every minute while the screen is visible
  const { data, isLoading, error, refetch, isRefetching } = useOwnerAnalytics(timeframe);

  const title = uz ? 'Analitika' : 'Аналитика';
  const subtitle = uz ? "Savdo va buyurtmalar ko'rsatkichlari" : 'Показатели продаж и заказов';

  // A failed background refresh keeps showing the last numbers; the error screen is only for "nothing to show"
  if (error && !data) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={title} subtitle={subtitle} />
        <InlineError onRetry={refetch} retrying={isRefetching} />
      </div>
    );
  }

  const totalRevenue = data?.summary.revenue ?? 0;
  const deliveredCount = data?.summary.delivered_count ?? 0;
  const cashRevenue = data?.by_payment_method.find((row) => row.method === 'cash')?.revenue ?? 0;
  // Everything that is not cash (cards, wallets) is paid online
  const onlineRevenue = (data?.by_payment_method ?? []).filter((row) => row.method !== 'cash').reduce((sum, row) => sum + row.revenue, 0);
  const loading = isLoading && !data;

  // The value, or a grey block of the same size while the first load runs
  const renderValue = (value: ReactNode, widthClass = 'w-20') => (loading ? <Skeleton className={`inline-block h-5 ${widthClass}`} /> : value);

  const timeframes = [
    { value: 'today' as const, label: uz ? 'Bugun' : 'Сегодня' },
    { value: 'weekly' as const, label: uz ? 'Hafta' : 'Неделя' },
    { value: 'monthly' as const, label: uz ? 'Oy' : 'Месяц' },
    { value: 'yearly' as const, label: uz ? 'Yil' : 'Год' },
  ];
  const revenueLabel: Record<AnalyticsTimeframe, string> = {
    today: uz ? 'Bugungi umumiy savdo' : 'Продажи за сегодня',
    weekly: uz ? 'Haftalik umumiy savdo' : 'Продажи за неделю',
    monthly: uz ? 'Oylik umumiy savdo' : 'Продажи за месяц',
    yearly: uz ? 'Yillik umumiy savdo' : 'Продажи за год',
  };
  const share = (part: number) => (totalRevenue > 0 ? `${(part / totalRevenue) * 100}%` : '0%');

  return (
    <div className="flex flex-col gap-4 px-4 pt-2">
      <PageHeader title={title} subtitle={subtitle} />

      <SegmentedControl segments={timeframes} value={timeframe} onChange={setTimeframe} label={uz ? 'Davr' : 'Период'} />

      {/* The revenue of the period */}
      <section aria-label={revenueLabel[timeframe]} className="flex flex-col gap-1 rounded-sheet bg-brand p-5 text-on-brand">
        <p className="text-footnote font-medium">{revenueLabel[timeframe]}</p>
        <p className="text-display font-extrabold">{renderValue(formatMoney(totalRevenue, language), 'w-40')}</p>
        <p className="text-footnote">
          {renderValue(uz ? `${deliveredCount} ta muvaffaqiyatli buyurtmadan` : `Из ${deliveredCount} успешных заказов`, 'w-32')}
        </p>
      </section>

      <div className="grid grid-cols-2 gap-3">
        <Card className="flex flex-col gap-1.5">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <ChefHat aria-hidden="true" className="size-4 text-info" />
            {uz ? "Tayyorlash (o'rtacha)" : 'Кухня (в среднем)'}
          </span>
          <span className="text-title font-bold text-fg">{renderValue(formatPrepTime(data?.times.average_prep_seconds), 'w-12')}</span>
        </Card>
        <Card className="flex flex-col gap-1.5">
          <span className="flex items-center gap-1.5 text-footnote font-medium text-fg-muted">
            <Bike aria-hidden="true" className="size-4 text-brand" />
            {uz ? "Yetkazish (o'rtacha)" : 'Доставка (в среднем)'}
          </span>
          <span className="text-title font-bold text-fg">{renderValue(formatDeliveryTime(data?.times.average_delivery_seconds), 'w-12')}</span>
        </Card>
      </div>

      <Section title={uz ? "To'lov turlari bo'yicha" : 'По типам оплаты'}>
        <Card className="flex flex-col gap-4">
          {[
            { label: uz ? 'Karta orqali (onlayn)' : 'Картой (онлайн)', amount: onlineRevenue, bar: 'bg-brand' },
            { label: uz ? 'Naqd pul' : 'Наличными', amount: cashRevenue, bar: 'bg-success' },
          ].map((row) => (
            <div key={row.label} className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between gap-3 text-body">
                <span className="text-fg-muted">{row.label}</span>
                <span className="font-bold text-fg">{renderValue(formatMoney(row.amount, language), 'w-24')}</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-surface-2">
                <div className={`h-full rounded-full transition-[width] duration-500 ${row.bar}`} style={{ width: share(row.amount) }} />
              </div>
            </div>
          ))}
        </Card>
      </Section>

      <RankList
        icon={TrendingUp}
        title={uz ? "Eng ko'p sotilganlar" : 'Самые продаваемые'}
        empty={uz ? 'Sotuvlar mavjud emas' : 'Нет продаж'}
        loading={loading}
        rows={(data?.top_products ?? []).slice(0, TOP_SHOWN).map((p) => ({ name: p.name, value: `${p.quantity} ${uz ? 'ta' : 'шт'}` }))}
      />

      <RankList
        icon={Award}
        title={uz ? 'Kuryerlar reytingi' : 'Рейтинг курьеров'}
        empty={uz ? "Kuryerlar ro'yxati bo'sh" : 'Список курьеров пуст'}
        loading={loading}
        rows={(data?.couriers ?? []).slice(0, TOP_SHOWN).map((c) => ({ name: c.name, value: `${c.delivered_count} ${uz ? 'ta buyurtma' : 'заказов'}` }))}
      />
    </div>
  );
}

interface RankListProps {
  icon: typeof TrendingUp;
  title: string;
  empty: string;
  loading: boolean;
  rows: Array<{ name: string; value: string }>;
}

// A top list: rank, name, a figure
function RankList({ icon: Icon, title, empty, loading, rows }: RankListProps) {
  return (
    <Section
      title={
        <span className="inline-flex items-center gap-1.5">
          <Icon aria-hidden="true" className="size-4 text-brand" />
          {title}
        </span>
      }
      label={title}
    >
      <Card className="flex flex-col gap-1">
        {loading ? (
          [1, 2].map((n) => (
            <div key={n} className="flex items-center justify-between py-2">
              <Skeleton className="h-4 w-28" />
              <Skeleton className="h-4 w-12" />
            </div>
          ))
        ) : rows.length === 0 ? (
          <p className="py-2 text-center text-footnote text-fg-muted">{empty}</p>
        ) : (
          <ol className="flex flex-col">
            {rows.map((row, index) => (
              <li key={row.name} className="flex items-center justify-between gap-3 border-b border-line py-2.5 last:border-0">
                <span className="flex min-w-0 items-center gap-2 text-body font-medium text-fg">
                  <span className="w-6 shrink-0 text-footnote font-bold text-fg-muted">{index + 1}</span>
                  <span className="truncate">{row.name}</span>
                </span>
                <span className="shrink-0 rounded-control bg-surface-2 px-2 py-1 text-caption font-bold text-fg-muted">{row.value}</span>
              </li>
            ))}
          </ol>
        )}
      </Card>
    </Section>
  );
}
