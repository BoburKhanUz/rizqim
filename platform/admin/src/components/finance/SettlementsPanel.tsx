'use client';

import Link from 'next/link';
import { useState } from 'react';
import { CheckCircle2, RotateCw } from 'lucide-react';
import { Button, Card, CardSkeletons, EmptyState, InlineError, Pill } from '@rizqim/ui';
import { idempotencyKey } from '@/lib/api/orders';
import { useRetrySettlement, useSettlementFailures, type SettlementFailure } from '@/lib/api/finance';
import { errorText, fieldError } from '@/lib/errors';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';
import { uzs } from './financeModel';
import { ReasonDialog } from './ReasonDialog';

// Hisob-kitob xatolari: delivered orders whose ledger booking failed. The automatic retry (every 10 minutes) keeps going until
// it gives up; a member with finance.manage can run the same retry now.
export function SettlementsPanel({ enabled, canRetry, onDone }: { enabled: boolean; canRetry: boolean; onDone: (message: string) => void }) {
  const t = useT();
  const failures = useSettlementFailures(enabled);
  const [retrying, setRetrying] = useState<SettlementFailure | null>(null);

  if (failures.isError) return <InlineError title={t('finance_loadError')} message={errorText(failures.error, t)} retryLabel={t('retry')} retrying={failures.isFetching} onRetry={() => void failures.refetch()} />;
  if (!failures.isSuccess) return <CardSkeletons count={2} lines={2} label={t('loading')} />;
  if (failures.data.data.length === 0) return <EmptyState icon={CheckCircle2} title={t('finance_failuresEmpty')} text={t('finance_failuresEmptyText')} />;

  return (
    <div className="flex max-w-5xl flex-col gap-3">
      <p className="text-footnote text-fg-muted">{t('finance_failuresHint', { max: failures.data.max_attempts })}</p>
      <ul aria-label={t('finance_failuresLabel')} className="flex flex-col gap-2">
        {failures.data.data.map((row) => (
          <li key={row.uuid}>
            <Card className="flex flex-col gap-2">
              <span className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-body font-bold text-fg">#{row.short_code ?? row.uuid.slice(0, 8)}</span>
                <Pill tone={row.gave_up ? 'danger' : 'warning'}>{row.gave_up ? t('finance_failureGaveUp') : t('finance_failureAttempts', { count: row.attempts, max: row.max_attempts })}</Pill>
              </span>
              <span className="break-words text-footnote text-fg">{[row.store?.name, uzs(row.total), row.payment_method].filter(Boolean).join(' · ')}</span>
              <span className="text-footnote text-fg-muted">{when(row.failed_at)}</span>
              {row.error && <span className="break-words font-mono text-caption text-danger-soft-fg">{row.error}</span>}
              <span className="flex flex-wrap gap-2">
                <Link href={`/orders/${row.uuid}`} className="inline-flex min-h-11 items-center rounded-control px-3 text-footnote font-bold text-brand hover:bg-surface-2">
                  {t('finance_failureOrder')}
                </Link>
                {canRetry && (
                  <Button variant="secondary" icon={RotateCw} onClick={() => setRetrying(row)}>
                    {t('finance_failureRetry')}
                  </Button>
                )}
              </span>
            </Card>
          </li>
        ))}
      </ul>
      {retrying && (
        <RetryDialog
          failure={retrying}
          onClose={() => setRetrying(null)}
          onDone={(message) => {
            setRetrying(null);
            onDone(message);
          }}
        />
      )}
    </div>
  );
}

function RetryDialog({ failure, onClose, onDone }: { failure: SettlementFailure; onClose: () => void; onDone: (message: string) => void }) {
  const t = useT();
  const retry = useRetrySettlement();
  const [key] = useState(idempotencyKey);
  return (
    <ReasonDialog
      title={t('finance_failureRetryTitle')}
      text={t('finance_failureRetryText', { code: failure.short_code ?? failure.uuid.slice(0, 8) })}
      submitLabel={t('finance_failureRetry')}
      busy={retry.isPending}
      error={retry.error ? errorText(retry.error, t) : null}
      reasonError={fieldError(retry.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) =>
        retry.mutate(
          { uuid: failure.uuid, reason, key },
          { onSuccess: (result) => onDone(result.booked ? t('finance_failureBooked') : result.order ? t('finance_failureStill', { error: result.order.error ?? '—' }) : t('finance_failureCleared')) },
        )
      }
    />
  );
}
