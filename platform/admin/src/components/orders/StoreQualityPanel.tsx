'use client';

import { useState } from 'react';
import { BarChart3 } from 'lucide-react';
import { Button, Card, InlineError, Section, Skeleton } from '@rizqim/ui';
import { useStoreQuality, type StoreQualityRow } from '@/lib/api/orders';
import { errorText } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { percent } from './ordersModel';

// Ovqatxona sifati (last 30 days, GET /admin/orders/store-quality): the weakest stores first. Opened on demand (one
// aggregate query, cached 5 minutes on the server).
export function StoreQualityPanel({ enabled, storeId = null }: { enabled: boolean; storeId?: number | null }) {
  const t = useT();
  const [open, setOpen] = useState(storeId !== null);
  const quality = useStoreQuality(storeId, enabled && open);

  return (
    <Section title={t('orders_quality')} className="mt-6 max-w-5xl">
      {!open ? (
        <div>
          <Button variant="secondary" icon={BarChart3} onClick={() => setOpen(true)}>
            {t('orders_quality')}
          </Button>
        </div>
      ) : quality.isError ? (
        <InlineError title={t('orders_qualityLoadError')} message={errorText(quality.error, t)} retryLabel={t('retry')} retrying={quality.isFetching} onRetry={() => void quality.refetch()} />
      ) : !quality.isSuccess ? (
        <Skeleton className="h-24" />
      ) : quality.data.stores.length === 0 ? (
        <p className="text-footnote text-fg-muted">{t('orders_qualityEmpty')}</p>
      ) : (
        <ul aria-label={t('orders_quality')} className="grid grid-cols-1 gap-2 md:grid-cols-2">
          {quality.data.stores.map((row) => (
            <QualityCard key={row.store.id} row={row} />
          ))}
        </ul>
      )}
    </Section>
  );
}

function QualityCard({ row }: { row: StoreQualityRow }) {
  const t = useT();
  const pct = (value: number | null) => (value === null ? '—' : t('orders_qualityPercent', { value: percent(value) ?? '0' }));
  const minutes = (value: number | null) => (value === null ? '—' : t('orders_qualityMinutes', { value }));
  const items: [string, string][] = [
    [t('orders_qualityOrders'), String(row.orders)],
    [t('orders_qualityAccept'), pct(row.acceptance_rate)],
    [t('orders_qualityAcceptTime'), minutes(row.avg_accept_minutes)],
    [t('orders_qualityPrep'), minutes(row.avg_prep_minutes)],
    [t('orders_qualityCancel'), pct(row.cancel_rate)],
    [t('orders_qualityRating'), t('orders_qualityRatingValue', { rating: row.rating ?? '—', count: row.review_count })],
  ];
  return (
    <li className="list-none">
      <Card className="flex flex-col gap-2">
        <h3 className="break-words text-body font-bold text-fg">{row.store.name}</h3>
        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-footnote">
          {items.map(([label, value]) => (
            <div key={label}>
              <dt className="text-fg-muted">{label}</dt>
              <dd className="text-fg">{value}</dd>
            </div>
          ))}
        </dl>
      </Card>
    </li>
  );
}
