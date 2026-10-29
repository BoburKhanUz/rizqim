'use client';

import { useState } from 'react';
import { Scale } from 'lucide-react';
import { Button, Card, CardSkeletons, ConfirmDialog, EmptyState, formatDateTime, formatMoney, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import { formatMinutes, todayIso } from '../staffpay/describe';
import type { Distribution } from './api';
import { useCloseDistribution, useComputeDistribution, useDistributions } from './hooks';

// "Qozonni bo'lish": the split of the branch's tip pool per business day (by the branch's rule: equal, by hours, by
// sales or by role weights), computed by the server in whole so'm (the so'm left over to the largest remainders) and
// recomputed until a manager closes it; a closed split freezes the day's tips.
export function PoolPanel({ branchId, canManage }: { branchId: number; canManage: boolean }) {
  const { t, language } = useI18n();
  const [from, setFrom] = useState(() => todayIso(-6));
  const [to, setTo] = useState(() => todayIso());
  const [day, setDay] = useState(() => todayIso());
  const [closing, setClosing] = useState<Distribution | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const distributions = useDistributions(from !== '' && to !== '' ? { branchId, from, to } : null);
  const compute = useComputeDistribution({ onSuccess: () => setDone(t('tips_computed')) });
  const close = useCloseDistribution({
    onSuccess: () => {
      setClosing(null);
      setDone(t('tips_closedDone'));
    },
  });
  const failure = compute.error ? describeError(compute.error, t) : null;

  return (
    <div className="flex flex-col gap-4">
      {canManage && (
        <Card className="flex flex-wrap items-end gap-4">
          <DateField label={t('tips_splitDay')} value={day} onChange={setDay} />
          <Button loading={compute.isPending} disabled={day === ''} onClick={() => compute.mutate({ branchId, businessDate: day })}>
            {t('tips_compute')}
          </Button>
          <p className="basis-full text-footnote text-fg-muted">{t('tips_computeHint')}</p>
        </Card>
      )}
      {done && <SuccessNote>{done}</SuccessNote>}
      {failure && <ErrorBanner message={failure.message} />}
      <div className="flex flex-wrap items-end gap-4">
        <DateField label={t('dm_from')} value={from} max={to || undefined} onChange={setFrom} />
        <DateField label={t('dm_to')} value={to} min={from || undefined} onChange={setTo} />
      </div>
      {distributions.isPending ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : distributions.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(distributions.error, t).message} retryLabel={t('retry')} retrying={distributions.isFetching} onRetry={() => void distributions.refetch()} />
      ) : distributions.data.distributions.length === 0 ? (
        <EmptyState icon={Scale} title={t('tips_splitsEmptyTitle')} text={t('tips_splitsEmptyText')} />
      ) : (
        distributions.data.distributions.map((split) => (
          <Card key={split.uuid}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 className="text-headline font-bold">
                  {formatDay(split.business_date, language)}
                  {split.shift_uuid ? ` · ${t('tips_shift')}` : ''}
                </h3>
                <p className="text-footnote text-fg-muted">
                  {t('tips_splitLine', { pool: formatMoney(split.pool_amount, language), rule: t(`tips_rule_${split.rule}`), n: split.tips_count })}
                  {split.computed_at ? ` · ${t('tips_computedAt', { time: formatDateTime(split.computed_at, language) })}` : ''}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Pill tone={split.status === 'closed' ? 'success' : 'info'}>{t(split.status === 'closed' ? 'tips_splitClosed' : 'tips_splitOpen')}</Pill>
                {canManage && split.status === 'open' && (
                  <>
                    <Button variant="secondary" loading={compute.isPending} onClick={() => compute.mutate({ branchId, businessDate: split.business_date, shiftUuid: split.shift_uuid })}>
                      {t('tips_recompute')}
                    </Button>
                    <Button onClick={() => setClosing(split)}>{t('tips_close')}</Button>
                  </>
                )}
              </div>
            </div>
            {split.lines.length === 0 ? (
              <p className="text-footnote text-fg-muted">{t('tips_noPeople')}</p>
            ) : (
              <DataTable caption={t('tips_splitCaption', { day: formatDay(split.business_date, language) })}>
                <thead>
                  <tr>
                    <Th>{t('tips_colPerson')}</Th>
                    <Th>{t('tips_colBasis')}</Th>
                    <Th numeric>{t('tips_colAmount')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {split.lines.map((line) => (
                    <tr key={line.staff_id ?? line.name}>
                      <Td className="font-bold">{line.name ?? `#${line.staff_id}`}</Td>
                      <Td muted>{describeBasis(split, line.basis, t, language)}</Td>
                      <Td numeric className="font-bold">
                        {formatMoney(line.amount, language)}
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}
          </Card>
        ))
      )}
      {closing !== null && (
        <ConfirmDialog
          title={t('tips_closeTitle', { day: formatDay(closing.business_date, language) })}
          text={t('tips_closeText')}
          confirmLabel={t('tips_close')}
          cancelLabel={t('menu_cancel')}
          closeLabel={t('menu_close')}
          tone="primary"
          busy={close.isPending}
          error={close.error ? describeError(close.error, t).message : null}
          onConfirm={() => close.mutate(closing.uuid)}
          onClose={() => setClosing(null)}
        />
      )}
    </div>
  );
}

function describeBasis(split: Distribution, basis: string, t: ReturnType<typeof useI18n>['t'], language: ReturnType<typeof useI18n>['language']): string {
  switch (split.rule) {
    case 'hours':
      return formatMinutes(Math.round(Number(basis)), t);
    case 'sales':
      return formatMoney(basis, language);
    case 'role_weights':
      return t('tips_weight', { w: language === 'en' ? basis.replace(/\.?0+$/, '') : basis.replace(/\.?0+$/, '').replace('.', ',') });
    default:
      return t('tips_equalShare');
  }
}
