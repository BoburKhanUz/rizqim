'use client';

import { useState } from 'react';
import { Button, Card, CardSkeletons, Field, fieldClass, formatMoney, InlineError } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { ErrorBanner, SuccessNote } from '../menu/ErrorBanner';
import { describeError } from '../menu/serverError';
import { formatPercent, parseAmountInput } from '../staffpay/describe';
import type { ServiceChargePage, ServiceChargeRow, ServiceOrderType } from './api';
import { useSaveServiceChargeRules, useServiceChargeRules } from './hooks';

// "Xizmat haqi": the service charge variants of the branch (P6-22, C1) over its base percent (Sozlamalar → Xizmat
// haqi): a percent per order type (in the hall, carried to the table, takeaway), the large-group rule, the restaurant
// cover per guest and the notice printed on the bill (worked out by the server unless the owner writes their own).
// A stolovaya takes none (0% by law); changing them needs the `service_charge_rules` module.
export function ServiceChargePanel({ branchId }: { branchId: number }) {
  const { t } = useI18n();
  const page = useServiceChargeRules(branchId);
  const [hallId, setHallId] = useState<number | null>(null);

  if (page.isPending) return <CardSkeletons count={1} lines={4} label={t('loading')} />;
  if (page.isError) {
    return <InlineError title={t('loadErrorTitle')} message={describeError(page.error, t).message} retryLabel={t('retry')} retrying={page.isFetching} onRetry={() => void page.refetch()} />;
  }

  const halls = page.data.rows.filter((row) => row.hall_id !== null);
  const row = page.data.rows.find((item) => item.hall_id === hallId && (hallId !== null || item.branch_id === branchId)) ?? page.data.rows.find((item) => hallId === null && item.branch_id === null && item.hall_id === null) ?? null;

  return (
    <div className="flex max-w-2xl flex-col gap-4">
      {halls.length > 0 && (
        <Field label={t('tips_scRow')}>
          {(control) => (
            <select {...control} className={fieldClass} value={hallId ?? ''} onChange={(event) => setHallId(event.target.value === '' ? null : Number(event.target.value))}>
              <option value="">{t('tips_scBranchRow')}</option>
              {halls.map((hall) => (
                <option key={hall.hall_id} value={hall.hall_id ?? ''}>
                  {hall.hall_name ?? `#${hall.hall_id}`}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
      <RulesForm key={`${hallId ?? 'branch'}`} branchId={branchId} hallId={hallId} page={page.data} row={row} />
    </div>
  );
}

function RulesForm({ branchId, hallId, page, row }: { branchId: number; hallId: number | null; page: ServiceChargePage; row: ServiceChargeRow | null }) {
  const { t, language } = useI18n();
  const [saved, setSaved] = useState(false);
  const save = useSaveServiceChargeRules(branchId, { onSuccess: () => setSaved(true) });
  const rules = row?.rules;
  const trim = (value: string | null | undefined) => (value ?? '').replace(/\.00$/, '');
  const [byType, setByType] = useState<Partial<Record<ServiceOrderType, string>>>(() => Object.fromEntries(Object.entries(rules?.by_order_type ?? {}).map(([type, percent]) => [type, trim(percent)])));
  const [minGuests, setMinGuests] = useState(rules?.large_group_min_guests ? String(rules.large_group_min_guests) : '');
  const [groupPercent, setGroupPercent] = useState(trim(rules?.large_group_percent));
  const [cover, setCover] = useState(trim(rules?.cover_amount));
  const [notice, setNotice] = useState(row?.notice ?? '');
  const [problem, setProblem] = useState<Record<string, string>>({});
  const disabled = !page.enabled;
  const failure = save.error ? describeError(save.error, t) : null;
  const fieldError = (name: string) => problem[name] ?? failure?.fields[name];

  const submit = () => {
    setSaved(false);
    const found: Record<string, string> = {};
    const percents: Partial<Record<ServiceOrderType, string>> = {};
    for (const type of page.order_types) {
      const raw = (byType[type.key] ?? '').trim();
      if (raw === '') continue;
      const value = parseAmountInput(raw);
      if (value === null || Number(value) < 0 || Number(value) > 30) found[`by_order_type.${type.key}`] = t('tips_err_percent');
      else percents[type.key] = value;
    }
    const guests = minGuests.trim() === '' ? null : Number(minGuests);
    const group = groupPercent.trim() === '' ? null : parseAmountInput(groupPercent);
    if ((guests === null) !== (group === null)) found.large_group_percent = t('tips_err_group');
    if (guests !== null && (!Number.isInteger(guests) || guests < 2 || guests > 500)) found.large_group_min_guests = t('tips_err_guests');
    if (group !== null && (Number(group) < 0 || Number(group) > 30)) found.large_group_percent = t('tips_err_percent');
    const coverValue = cover.trim() === '' ? null : parseAmountInput(cover);
    if (cover.trim() !== '' && (coverValue === null || Number(coverValue) < 0)) found.cover_amount = t('tips_err_amount');
    setProblem(found);
    if (Object.keys(found).length > 0) return;
    save.mutate({ hallId, byOrderType: percents, largeGroupMinGuests: guests, largeGroupPercent: group, coverAmount: coverValue, notice: notice.trim() === '' ? null : notice.trim() });
  };

  return (
    <Card className="flex flex-col gap-4">
      {page.locked && <ErrorBanner message={t('tips_scLocked')} />}
      {disabled && <ErrorBanner message={t('tips_scModuleOff')} />}
      <p className="text-footnote text-fg-muted">
        {row ? t('tips_scBase', { percent: formatPercent(row.service_charge_percent, language), fee: formatMoney(row.place_fee_amount, language) }) : t('tips_scNoRow')}
      </p>
      <fieldset className="grid gap-3 sm:grid-cols-3">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('tips_scByType')}</legend>
        {page.order_types.map((type) => (
          <Field key={type.key} label={type.label} error={fieldError(`by_order_type.${type.key}`)} hint={t('tips_scTypeHint')}>
            {(control) => <input {...control} inputMode="decimal" className={fieldClass} disabled={disabled} value={byType[type.key] ?? ''} placeholder="%" onChange={(event) => setByType((current) => ({ ...current, [type.key]: event.target.value }))} />}
          </Field>
        ))}
      </fieldset>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label={t('tips_scGroupGuests')} error={fieldError('large_group_min_guests')}>
          {(control) => <input {...control} inputMode="numeric" className={fieldClass} disabled={disabled} value={minGuests} onChange={(event) => setMinGuests(event.target.value)} />}
        </Field>
        <Field label={t('tips_scGroupPercent')} error={fieldError('large_group_percent')}>
          {(control) => <input {...control} inputMode="decimal" className={fieldClass} disabled={disabled} value={groupPercent} placeholder="%" onChange={(event) => setGroupPercent(event.target.value)} />}
        </Field>
      </div>
      <Field label={t('tips_scCover')} error={fieldError('cover_amount')} hint={t('tips_scCoverHint')}>
        {(control) => <input {...control} inputMode="decimal" className={fieldClass} disabled={disabled} value={cover} onChange={(event) => setCover(event.target.value)} />}
      </Field>
      <Field label={t('tips_scNotice')} error={fieldError('notice')} hint={t('tips_scNoticeHint')}>
        {(control) => <textarea {...control} rows={2} maxLength={300} className={fieldClass} disabled={disabled} value={notice} onChange={(event) => setNotice(event.target.value)} />}
      </Field>
      {row?.notice_preview && (
        <p className="rounded-control bg-surface-2 px-3 py-2 text-footnote" aria-label={t('tips_scPreview')}>
          <span className="font-bold">{t('tips_scPreview')}: </span>
          {row.notice_preview}
        </p>
      )}
      {failure && <ErrorBanner message={failure.message} />}
      {saved && <SuccessNote>{t('tips_scSaved')}</SuccessNote>}
      <div>
        <Button loading={save.isPending} disabled={disabled} onClick={submit}>
          {t('tips_save')}
        </Button>
      </div>
    </Card>
  );
}
