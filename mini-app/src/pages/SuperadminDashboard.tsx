import { useState, useEffect, type ReactNode } from 'react';
import { Activity, Landmark, Calendar, RefreshCw, Store, Users } from 'lucide-react';
import { hapticFeedback } from '../lib/telegram';
import api from '../services/api';
import { logApiError } from '../lib/apiError';
import { Card, IconButton, InlineError, PageHeader, Section, cx } from '../components/ui';
import { Skeleton } from '../components/staff/Skeleton';
import { formatMoney, formatNumber } from '../lib/format';
import type { Amount, ApiResponse, DashboardStats } from '../types';

// A card of rows: a label on the left, its figure on the right (long money values never overflow a narrow column)
function StatList({ rows, className }: { rows: Array<{ label: string; value: ReactNode; tone?: string }>; className?: string }) {
  return (
    <Card padded={false} className={cx('overflow-hidden', className)}>
      <dl className="divide-y divide-line">
        {rows.map((row) => (
          <div key={row.label} className="flex items-baseline justify-between gap-3 px-4 py-3">
            <dt className="text-footnote text-fg-muted">{row.label}</dt>
            <dd className={cx('text-body font-bold', row.tone ?? 'text-fg')}>{row.value}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}

// "This month" / "last 30 days": a title with a caption, then turnover and the platform's profit
function PeriodCard({ title, caption, gmv, commission }: { title: string; caption: string; gmv: string; commission: string }) {
  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-baseline justify-between gap-3 border-b border-line pb-2">
        <h3 className="text-body font-bold text-fg">{title}</h3>
        <span className="text-footnote text-fg-muted">{caption}</span>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <div className="min-w-0">
          <span className="text-footnote text-fg-muted">Aylanma (GMV)</span>
          <p className="mt-0.5 break-words text-body font-bold text-fg">{gmv}</p>
        </div>
        <div className="min-w-0">
          <span className="text-footnote text-fg-muted">Platforma foydasi</span>
          <p className="mt-0.5 break-words text-body font-bold text-success-soft-fg">{commission}</p>
        </div>
      </div>
    </Card>
  );
}

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

  const header = (
    <PageHeader
      title="Boshqaruv paneli"
      subtitle="Tizimning umumiy holati"
      action={
        <IconButton
          icon={RefreshCw}
          variant="surface"
          label="Yangilash"
          disabled={loading}
          onClick={() => {
            hapticFeedback('light');
            refresh();
          }}
          className={loading ? '[&>svg]:animate-spin' : undefined}
        />
      }
    />
  );

  if (loading && !stats) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2" role="status" aria-label="Statistikalar yuklanmoqda">
        {header}
        <Skeleton className="h-24 rounded-card" />
        <Skeleton className="h-24 rounded-card" />
        <Skeleton className="h-28 rounded-card" />
        <Skeleton className="h-28 rounded-card" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="flex flex-col gap-4 px-4 pt-2">
        {header}
        <InlineError onRetry={refresh} retrying={loading} />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-5 px-4 pt-2 animate-fade-in">
      {header}

      {/* Platform totals: all time, this month, the last 30 days */}
      <Section
        title={
          <span className="inline-flex items-center gap-1.5">
            <Landmark aria-hidden="true" className="size-4 text-brand" />
            Aylanma va komissiyalar
          </span>
        }
        label="Aylanma va komissiyalar"
      >
        <div className="grid grid-cols-1 gap-3">
          <div className="flex flex-col gap-1 rounded-card bg-brand p-4 text-on-brand">
            <p className="text-footnote font-medium">Umumiy aylanma (GMV)</p>
            <p className="break-words text-title font-bold">{formatMoney(stats?.total_gmv || 0)}</p>
          </div>
          <div className="flex flex-col gap-1 rounded-card bg-success p-4 text-on-success">
            <p className="text-footnote font-medium">Umumiy komissiya</p>
            <p className="break-words text-title font-bold">{formatMoney(stats?.total_commission || 0)}</p>
          </div>
        </div>

        <PeriodCard
          title="Joriy oy"
          caption="1-sanadan bugungacha"
          gmv={formatMoney(stats?.monthly_gmv || 0)}
          commission={formatMoney(stats?.monthly_commission || 0)}
        />
        <PeriodCard
          title="Oxirgi 30 kun"
          caption="Bugungacha"
          gmv={formatMoney(stats?.last_30_days_gmv || 0)}
          commission={formatMoney(stats?.last_30_days_commission || 0)}
        />
      </Section>

      {/* Today */}
      <Section
        title={
          <span className="inline-flex items-center gap-1.5">
            <Calendar aria-hidden="true" className="size-4 text-brand" />
            Bugungi ko'rsatkichlar
          </span>
        }
        label="Bugungi ko'rsatkichlar"
      >
        <StatList
          rows={[
            { label: 'Aylanma (GMV)', value: formatMoney(stats?.today_gmv || 0) },
            { label: 'Platforma foydasi', value: formatMoney(stats?.today_commission || 0), tone: 'text-success-soft-fg' },
            { label: 'Buyurtmalar', value: `${stats?.today_orders || 0} ta`, tone: 'text-brand' },
          ]}
        />
      </Section>

      {/* Money flow */}
      <Section
        title={
          <span className="inline-flex items-center gap-1.5">
            <Activity aria-hidden="true" className="size-4 text-brand" />
            Tizimdagi pul oqimi
          </span>
        }
        label="Tizimdagi pul oqimi"
      >
        <StatList
          rows={[
            { label: 'Restoranlar qarzi', value: formatMoney(stats?.total_store_debt || 0), tone: 'text-danger-soft-fg' },
            { label: 'Jami keshbek', value: formatPoints(stats?.total_cashback_balance || 0), tone: 'text-warning-soft-fg' },
            {
              label: 'Kutilayotgan yechishlar',
              value: `${stats?.pending_payouts || 0} ta`,
              tone: stats?.pending_payouts && stats.pending_payouts > 0 ? 'text-info-soft-fg' : undefined,
            },
          ]}
        />
      </Section>

      {/* Store activity */}
      <Section
        title={
          <span className="inline-flex items-center gap-1.5">
            <Store aria-hidden="true" className="size-4 text-brand" />
            Restoranlar faolligi
          </span>
        }
        label="Restoranlar faolligi"
      >
        <StatList
          rows={[
            { label: 'Jami', value: `${stats?.total_stores || 0} ta` },
            { label: 'Faol', value: `${stats?.active_stores || 0} ta`, tone: 'text-success-soft-fg' },
            { label: "To'lov kutayotgan (bloklangan)", value: `${stats?.payment_required_stores || 0} ta`, tone: 'text-danger-soft-fg' },
          ]}
        />
      </Section>

      {/* Customers */}
      <Card className="flex items-center gap-3">
        <span className="flex size-11 shrink-0 items-center justify-center rounded-control bg-info-soft text-info-soft-fg">
          <Users aria-hidden="true" className="size-5" />
        </span>
        <div>
          <p className="text-footnote text-fg-muted">Umumiy mijozlar</p>
          <p className="text-headline font-bold text-fg">{stats?.total_users || 0} ta</p>
        </div>
      </Card>
    </div>
  );
}
