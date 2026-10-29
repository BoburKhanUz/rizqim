'use client';

import { useState } from 'react';
import { HandCoins } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, Field, fieldClass, formatDateTime, formatMoney, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { DateField } from '../pos/fields';
import { formatDay } from '../reports/format';
import { DataTable, Pill, Td, Th } from '../reports/ui';
import { parseAmountInput, todayIso } from '../staffpay/describe';
import type { Tip, TipMode } from './api';
import { useCorrectTip, useTips, useTipsReport } from './hooks';

// "Hisobot": the branch's tips for a range of business days — the totals (by method, personal, pool, not given to
// anyone yet), each person's personal tips and pool shares, each day, and the tips themselves with their corrections.
// A manager (tips.manage) corrects a tip with a reason: a new amount, another person, or into the pool.
export function ReportPanel({ branchId, canManage }: { branchId: number; canManage: boolean }) {
  const { t, language } = useI18n();
  const [from, setFrom] = useState(() => todayIso(-6));
  const [to, setTo] = useState(() => todayIso());
  const range = from !== '' && to !== '' ? { branchId, from, to } : null;
  const report = useTipsReport(range);
  const tips = useTips(range, null);
  const [correcting, setCorrecting] = useState<Tip | null>(null);
  const money = (value: string) => formatMoney(value, language);
  const people = report.data?.by_staff ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-4">
        <DateField label={t('dm_from')} value={from} max={to || undefined} onChange={setFrom} />
        <DateField label={t('dm_to')} value={to} min={from || undefined} onChange={setTo} />
      </div>
      <p className="text-footnote text-fg-muted">{t('tips_reportHint')}</p>
      {report.isPending ? (
        <CardSkeletons count={2} lines={3} label={t('loading')} />
      ) : report.isError ? (
        <InlineError title={t('loadErrorTitle')} message={describeError(report.error, t).message} retryLabel={t('retry')} retrying={report.isFetching} onRetry={() => void report.refetch()} />
      ) : report.data.totals.count === 0 && report.data.by_day.length === 0 ? (
        <EmptyState icon={HandCoins} title={t('tips_emptyTitle')} text={t('tips_emptyText')} />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label={t('tips_totals')}>
            <Figure label={t('tips_total')} value={money(report.data.totals.amount)} hint={t('tips_countN', { n: report.data.totals.count })} />
            <Figure label={t('tips_personal')} value={money(report.data.totals.personal)} />
            <Figure label={t('tips_pool')} value={money(report.data.totals.pool)} hint={t('tips_undistributedN', { amount: money(report.data.undistributed) })} />
            <Figure label={t('tips_unassigned')} value={money(report.data.totals.unassigned)} />
            {report.data.totals.by_method.map((row) => (
              <Figure key={row.method} label={t(`tips_method_${row.method}`)} value={money(row.amount)} hint={t('tips_countN', { n: row.count })} />
            ))}
          </div>

          {people.length > 0 && (
            <Card>
              <DataTable caption={t('tips_byStaff')}>
                <thead>
                  <tr>
                    <Th>{t('tips_colPerson')}</Th>
                    <Th numeric>{t('tips_personal')}</Th>
                    <Th numeric>{t('tips_colPoolShare')}</Th>
                    <Th numeric>{t('tips_total')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {people.map((row) => (
                    <tr key={row.staff_id}>
                      <Td className="font-bold">{row.name ?? `#${row.staff_id}`}</Td>
                      <Td numeric>{money(row.personal)}</Td>
                      <Td numeric>
                        {money(row.pool_share)}
                        {row.pool_share !== row.pool_share_closed && <span className="block text-caption text-fg-muted">{t('tips_closedShare', { amount: money(row.pool_share_closed) })}</span>}
                      </Td>
                      <Td numeric className="font-bold">
                        {money(row.total)}
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            </Card>
          )}

          <Card>
            <DataTable caption={t('tips_byDay')}>
              <thead>
                <tr>
                  <Th>{t('tips_colDay')}</Th>
                  <Th numeric>{t('tips_total')}</Th>
                  <Th numeric>{t('tips_personal')}</Th>
                  <Th numeric>{t('tips_pool')}</Th>
                  <Th>{t('tips_colSplit')}</Th>
                </tr>
              </thead>
              <tbody>
                {report.data.by_day.map((day) => (
                  <tr key={day.business_date}>
                    <Td className="whitespace-nowrap">{formatDay(day.business_date, language)}</Td>
                    <Td numeric className="font-bold">
                      {money(day.amount)}
                    </Td>
                    <Td numeric>{money(day.personal)}</Td>
                    <Td numeric>{money(day.pool)}</Td>
                    <Td>
                      {day.distributions.length === 0 ? (
                        day.pool !== '0.00' ? <Pill tone="warning">{t('tips_notSplit')}</Pill> : '—'
                      ) : (
                        day.distributions.map((split) => (
                          <Pill key={split.uuid} tone={split.status === 'closed' ? 'success' : 'info'}>
                            {t(split.status === 'closed' ? 'tips_splitClosed' : 'tips_splitOpen')}
                          </Pill>
                        ))
                      )}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </DataTable>
          </Card>
        </>
      )}

      {tips.data && tips.data.tips.length > 0 && (
        <Card>
          <DataTable caption={t('tips_entries')}>
            <thead>
              <tr>
                <Th>{t('tips_colWhen')}</Th>
                <Th>{t('tips_colMethod')}</Th>
                <Th>{t('tips_colTo')}</Th>
                <Th numeric>{t('tips_colAmount')}</Th>
                {canManage && <Th />}
              </tr>
            </thead>
            <tbody>
              {tips.data.tips.map((tip) => (
                <tr key={tip.uuid}>
                  <Td muted className="whitespace-nowrap">
                    {tip.created_at ? formatDateTime(tip.created_at, language) : formatDay(tip.business_date, language)}
                  </Td>
                  <Td>
                    {t(`tips_method_${tip.method}`)}
                    {tip.source === 'change' && <span className="block text-caption text-fg-muted">{t('tips_fromChange')}</span>}
                  </Td>
                  <Td>
                    {tip.holders.length === 0
                      ? '—'
                      : tip.holders.map((holder, index) => (
                          <span key={index} className="block">
                            {holder.mode === 'pool' ? t('tips_toPool') : (holder.staff?.name ?? t('tips_toNobody'))}
                            {tip.holders.length > 1 && ` · ${money(holder.amount)}`}
                          </span>
                        ))}
                    {tip.corrections.length > 0 && <span className="block text-caption text-fg-muted">{t('tips_correctedN', { n: tip.corrections.length, reason: tip.corrections[tip.corrections.length - 1]?.reason ?? '' })}</span>}
                  </Td>
                  <Td numeric className="font-bold">
                    {money(tip.amount)}
                    {tip.amount !== tip.original_amount && <span className="block text-caption font-normal text-fg-muted">{t('tips_wasAmount', { amount: money(tip.original_amount) })}</span>}
                  </Td>
                  {canManage && (
                    <Td className="whitespace-nowrap text-right">
                      {tip.closed ? (
                        <Pill tone="neutral">{t('tips_locked')}</Pill>
                      ) : (
                        <Button variant="secondary" onClick={() => setCorrecting(tip)}>
                          {t('tips_correct')}
                        </Button>
                      )}
                    </Td>
                  )}
                </tr>
              ))}
            </tbody>
          </DataTable>
          {tips.data.truncated && <p className="mt-2 text-caption text-fg-muted">{t('tips_truncated')}</p>}
        </Card>
      )}
      {correcting !== null && <CorrectionDialog tip={correcting} people={report.data?.staff ?? []} onClose={() => setCorrecting(null)} />}
    </div>
  );
}

function Figure({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <Card>
      <p className="text-caption text-fg-muted">{label}</p>
      <p className="text-title font-bold">{value}</p>
      {hint && <p className="text-caption text-fg-muted">{hint}</p>}
    </Card>
  );
}

function CorrectionDialog({ tip, people, onClose }: { tip: Tip; people: Array<{ id: number; name: string }>; onClose: () => void }) {
  const { t, language } = useI18n();
  const correct = useCorrectTip({ onSuccess: onClose });
  const known = tip.staff !== null && !people.some((person) => person.id === tip.staff?.id) ? [...people, { id: tip.staff.id, name: tip.staff.name ?? `#${tip.staff.id}` }] : people;
  const [mode, setMode] = useState<TipMode>(tip.mode);
  const [staffId, setStaffId] = useState<number | null>(tip.staff?.id ?? null);
  const [amount, setAmount] = useState(tip.amount.replace(/\.00$/, ''));
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState<Record<string, string>>({});
  const failure = correct.error ? describeError(correct.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const submit = () => {
    const found: Record<string, string> = {};
    const parsed = parseAmountInput(amount);
    if (parsed === null || parsed.startsWith('-')) found.amount = t('tips_err_amount');
    if (reason.trim() === '') found.reason = t('menu_err_required');
    setProblem(found);
    if (Object.keys(found).length > 0 || parsed === null) return;
    correct.mutate({ uuid: tip.uuid, amount: parsed, mode, staffId: mode === 'personal' ? staffId : null, reason: reason.trim() });
  };

  return (
    <Dialog
      open
      onClose={onClose}
      title={t('tips_correctTitle', { amount: formatMoney(tip.amount, language) })}
      description={t('tips_correctHint')}
      busy={correct.isPending}
      closeLabel={t('menu_close')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={correct.isPending}>
            {t('menu_cancel')}
          </Button>
          <Button loading={correct.isPending} onClick={submit}>
            {t('tips_save')}
          </Button>
        </>
      }
    >
      <Field label={t('tips_colAmount')} error={fieldError('amount')}>
        {(control) => <input {...control} inputMode="decimal" className={fieldClass} value={amount} onChange={(event) => setAmount(event.target.value)} />}
      </Field>
      <Field label={t('tips_colTo')} error={fieldError('mode')}>
        {(control) => (
          <select
            {...control}
            className={fieldClass}
            value={mode === 'pool' ? 'pool' : String(staffId ?? '')}
            onChange={(event) => {
              const value = event.target.value;
              if (value === 'pool') {
                setMode('pool');
                setStaffId(null);
              } else {
                setMode('personal');
                setStaffId(value === '' ? null : Number(value));
              }
            }}
          >
            <option value="pool">{t('tips_toPool')}</option>
            <option value="">{t('tips_toNobody')}</option>
            {known.map((person) => (
              <option key={person.id} value={person.id}>
                {person.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      {fieldError('staff_id') && <ErrorBanner message={fieldError('staff_id') ?? ''} />}
      <Field label={t('tips_reason')} error={fieldError('reason')}>
        {(control) => <input {...control} data-autofocus className={fieldClass} maxLength={300} value={reason} onChange={(event) => setReason(event.target.value)} />}
      </Field>
      {failure && <ErrorBanner message={failure.message} />}
    </Dialog>
  );
}
