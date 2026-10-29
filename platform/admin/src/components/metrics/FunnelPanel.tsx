'use client';

import Link from 'next/link';
import { ChevronRight, Filter } from 'lucide-react';
import { Card, CardSkeletons, EmptyState, formatNumber, InlineError } from '@rizqim/ui';
import { useFunnel } from '@/lib/api/metrics';
import { errorText } from '@/lib/errors';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { decimal } from './metricsModel';

// Arizadan faol ovqatxonagacha: the pipeline's stages as bars (how many applications of the period got there), the share
// of all, and how long applications stay in a stage (the median). The conversion and the time to "faol" on top.
export function FunnelPanel({ months, enabled }: { months: number; enabled: boolean }) {
  const t = useT();
  const { can } = useCan();
  const funnel = useFunnel(months, enabled);
  const data = funnel.data;

  return (
    <Card className="flex flex-col gap-3" aria-labelledby="metrics-funnel-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <h2 id="metrics-funnel-title" className="text-headline font-bold text-fg">
            {t('metrics_funnelTitle')}
          </h2>
          <p className="max-w-prose text-footnote text-fg-muted">{t('metrics_funnelText', { months })}</p>
        </div>
        {can('stores.view') && (
          <Link href="/stores/applications" className="inline-flex min-h-10 items-center gap-1 rounded-control px-2 text-footnote font-bold text-brand hover:underline focus-visible:outline-2 focus-visible:outline-brand">
            {t('metrics_openStores')}
            <ChevronRight aria-hidden="true" className="size-4" />
          </Link>
        )}
      </div>

      {funnel.isError && !data ? (
        <InlineError title={t('metrics_funnelLoadError')} message={errorText(funnel.error, t)} retryLabel={t('retry')} retrying={funnel.isFetching} onRetry={() => void funnel.refetch()} />
      ) : !data ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : data.applications === 0 ? (
        <EmptyState icon={Filter} title={t('metrics_funnelEmpty')} text={t('metrics_funnelEmptyText')} />
      ) : (
        <>
          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Figure label={t('metrics_funnelConversion')} value={data.conversion !== null ? `${decimal(data.conversion)}%` : '—'} note={t('metrics_funnelConversionText', { active: data.active, total: data.applications })} strong />
            <Figure label={t('metrics_funnelToActive')} value={data.median_days_to_active !== null ? t('metrics_funnelDays', { days: decimal(data.median_days_to_active) }) : '—'} />
            <Figure label={t('metrics_funnelOpen')} value={formatNumber(data.open, 'uz')} />
            <Figure label={t('metrics_funnelRejected')} value={formatNumber(data.rejected, 'uz')} />
          </dl>
          <ol aria-label={t('metrics_funnelList')} className="flex flex-col gap-2.5">
            {data.stages.map((stage) => {
              const share = Number(stage.share ?? 0);
              return (
                <li key={stage.stage} className="flex flex-col gap-1">
                  <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                    <span className="text-footnote font-bold text-fg">{stage.label}</span>
                    <span className="text-footnote text-fg-muted">
                      {t('metrics_funnelReached', { count: formatNumber(stage.reached, 'uz'), share: decimal(stage.share ?? '0') })}
                    </span>
                  </div>
                  <div aria-hidden="true" className="h-2.5 overflow-hidden rounded-full bg-surface-2">
                    <div className="h-full rounded-full bg-brand" style={{ width: `${Math.min(100, share)}%` }} />
                  </div>
                  {stage.stage !== 'active' && <p className="text-caption text-fg-muted">{stage.median_days !== null ? t('metrics_funnelMedian', { days: decimal(stage.median_days) }) : t('metrics_funnelNoMedian')}</p>}
                </li>
              );
            })}
          </ol>
        </>
      )}
    </Card>
  );
}

function Figure({ label, value, note, strong = false }: { label: string; value: string; note?: string; strong?: boolean }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-control bg-surface-2 p-3">
      <dt className="text-caption font-bold text-fg-muted">{label}</dt>
      <dd className={strong ? 'text-title font-extrabold text-brand' : 'text-headline font-bold text-fg'}>{value}</dd>
      {note && <dd className="text-caption text-fg-muted">{note}</dd>}
    </div>
  );
}
