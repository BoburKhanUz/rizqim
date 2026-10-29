'use client';

import { useMemo, useState } from 'react';
import { Wallet } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, Field, fieldClass, InlineError, Pill } from '@rizqim/ui';
import { TOPUP_STATUSES, useTopups, type TopupFilters } from '@/lib/api/finance';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { ExportButton } from '../ExportButton';
import { useT } from '../I18nProvider';
import { TOPUP_STATUS_LABEL, uzs } from './financeModel';
import { CHIP, chipTone } from './PayoutsPanel';

// To'ldirishlar: owners' Click and Payme top-ups of their debt. A stuck one (pending for more than 30 minutes) is said in
// words: the owner left the payment page, or the provider's callback never came.
export function TopupsPanel({ enabled, highlight }: { enabled: boolean; highlight: string }) {
  const t = useT();
  const [filters, setFilters] = useState<TopupFilters>({ status: 'all', provider: '', stuck: false });
  const topups = useTopups(filters, enabled);
  const rows = useMemo(() => topups.data?.pages.flatMap((page) => page.data) ?? [], [topups.data]);
  const counts = topups.data?.pages[0]?.counts;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <div role="group" aria-label={t('finance_topupStatus')} className="flex flex-wrap gap-2">
        {TOPUP_STATUSES.map((status) => (
          <button key={status} type="button" aria-pressed={filters.status === status} className={cx(CHIP, chipTone(filters.status === status))} onClick={() => setFilters((previous) => ({ ...previous, status }))}>
            {status === 'pending' && counts ? t('finance_tabCount', { label: t(TOPUP_STATUS_LABEL[status]), count: counts.pending }) : t(TOPUP_STATUS_LABEL[status])}
          </button>
        ))}
        <button type="button" aria-pressed={filters.stuck} className={cx(CHIP, chipTone(filters.stuck))} onClick={() => setFilters((previous) => ({ ...previous, stuck: !previous.stuck }))}>
          {counts ? t('finance_tabCount', { label: t('finance_topupStuckOnly'), count: counts.stuck }) : t('finance_topupStuckOnly')}
        </button>
      </div>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Field label={t('finance_topupProvider')}>
          {(control) => (
            <select {...control} className={fieldClass} value={filters.provider} onChange={(event) => setFilters((previous) => ({ ...previous, provider: event.target.value as TopupFilters['provider'] }))}>
              <option value="">{t('finance_topupAllProviders')}</option>
              <option value="click">Click</option>
              <option value="payme">Payme</option>
            </select>
          )}
        </Field>
        <div className="flex items-end">
          <ExportButton list="topups" query={{ status: filters.status, provider: filters.provider, stuck: filters.stuck }} />
        </div>
      </div>
      {topups.isError ? (
        <InlineError title={t('finance_loadError')} message={errorText(topups.error, t)} retryLabel={t('retry')} retrying={topups.isFetching} onRetry={() => void topups.refetch()} />
      ) : !topups.isSuccess ? (
        <CardSkeletons count={3} lines={2} label={t('loading')} />
      ) : rows.length === 0 ? (
        <EmptyState icon={Wallet} title={t('finance_topupsEmpty')} />
      ) : (
        <>
          <ul aria-label={t('finance_topupsLabel')} className="flex flex-col gap-2">
            {rows.map((row) => (
              <li key={row.id}>
                <Card className={cx('flex flex-col gap-1', highlight === row.uuid && 'border-brand')} aria-current={highlight === row.uuid ? 'true' : undefined}>
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="text-body font-bold text-fg">{uzs(row.amount)}</span>
                    <Pill tone={row.status === 'paid' ? 'success' : row.status === 'pending' ? 'warning' : 'neutral'}>{row.status_label}</Pill>
                    {row.stuck && <Pill tone="danger">{t('finance_topupStuck')}</Pill>}
                    <span className="text-footnote font-bold text-fg-muted">{row.provider === 'click' ? 'Click' : row.provider === 'payme' ? 'Payme' : row.provider}</span>
                  </span>
                  <span className="break-words text-footnote text-fg">{row.store?.name ?? '—'}</span>
                  <span className="flex flex-wrap gap-x-3 gap-y-1 text-footnote text-fg-muted">
                    <span>{when(row.created_at)}</span>
                    {row.paid_at && <span>{`${t('finance_topup_paid')}: ${when(row.paid_at)}`}</span>}
                    {row.transaction_id !== null && <span>{t('finance_topupLedger', { id: row.transaction_id })}</span>}
                    {row.provider_transaction_id && <span className="break-all">{t('finance_topupTransaction', { id: row.provider_transaction_id })}</span>}
                  </span>
                  {row.stuck && <span className="text-caption text-fg-muted">{t('finance_topupStuckHint')}</span>}
                </Card>
              </li>
            ))}
          </ul>
          {topups.hasNextPage && (
            <Button variant="secondary" loading={topups.isFetchingNextPage} onClick={() => void topups.fetchNextPage()}>
              {t('finance_loadMore')}
            </Button>
          )}
        </>
      )}
    </div>
  );
}
