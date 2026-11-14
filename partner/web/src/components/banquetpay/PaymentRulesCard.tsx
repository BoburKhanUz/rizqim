'use client';

import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button, Card, InlineError, Notice, Skeleton } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { SelectField, TextField } from '../pos/fields';
import { PAYMENT_KINDS, type CancellationRule, type PaymentKind, type PaymentRules, type TemplateRow } from './api';
import { usePaymentRules, useSavePaymentRules } from './hooks';

// The owner's banquet payment rules (P6-53): the schedule template (percent of the total and days before the event;
// the final row takes the rest), the cancellation rule table (days before → retained % of what was paid) and the
// reminder days. The server validates across the rows and audits every change.
export function PaymentRulesCard() {
  const { t } = useI18n();
  const rules = usePaymentRules();
  const [notice, setNotice] = useState<string | null>(null);

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h2 className="text-headline font-extrabold text-fg">{t('bqp_rulesTitle')}</h2>
        <p className="text-footnote text-fg-muted">{t('bqp_rulesText')}</p>
      </div>
      {rules.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(rules.error, t)} retryLabel={t('retry')} retrying={rules.isFetching} onRetry={() => void rules.refetch()} />
      ) : !rules.data ? (
        <Skeleton className="h-40 w-full" />
      ) : (
        <>
          <Notice message={notice} />
          <RulesForm key={rules.data.version} initial={rules.data} onSaved={() => setNotice(t('bqp_rulesSaved'))} />
        </>
      )}
    </Card>
  );
}

function RulesForm({ initial, onSaved }: { initial: PaymentRules; onSaved: () => void }) {
  const { t } = useI18n();
  const [template, setTemplate] = useState<TemplateRow[]>(initial.schedule_template);
  const [cancellation, setCancellation] = useState<CancellationRule[]>(initial.cancellation_rules);
  const [reminders, setReminders] = useState(initial.reminder_days.join(', '));
  const [overdue, setOverdue] = useState(initial.overdue_reminders);
  const save = useSavePaymentRules({ onSuccess: onSaved });
  const kinds = PAYMENT_KINDS.map((kind) => ({ value: kind, label: t(`bqp_kind_${kind}`) }));
  const setRow = (index: number, patch: Partial<TemplateRow>) => setTemplate((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  const setRule = (index: number, patch: Partial<CancellationRule>) => setCancellation((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

  return (
    <div className="flex flex-col gap-4">
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-body font-bold">{t('bqp_template')}</legend>
        {template.map((row, index) => (
          <div key={index} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <SelectField<PaymentKind> label={t('bqp_colKind')} value={row.kind} options={kinds} onChange={(kind) => setRow(index, { kind, ...(kind === 'final' ? { percent: null } : {}) })} />
            <TextField
              label={t('bqp_percent')}
              value={row.percent ?? ''}
              placeholder={row.kind === 'final' ? t('bqp_percentRest') : undefined}
              disabled={row.kind === 'final'}
              inputMode="decimal"
              maxLength={6}
              onChange={(percent) => setRow(index, { percent: percent.trim() === '' ? null : percent })}
            />
            <TextField
              label={t('bqp_daysBeforeField')}
              value={row.days_before === null ? '' : String(row.days_before)}
              placeholder={row.kind === 'deposit' ? t('bqp_atBooking') : undefined}
              inputMode="numeric"
              maxLength={3}
              onChange={(days) => setRow(index, { days_before: days.trim() === '' ? null : Number(days) })}
            />
            <Button variant="secondary" icon={Trash2} aria-label={t('bqp_removeRow')} disabled={template.length <= 1} onClick={() => setTemplate((rows) => rows.filter((_, i) => i !== index))} />
          </div>
        ))}
        <div>
          <Button variant="secondary" icon={Plus} onClick={() => setTemplate((rows) => [...rows.slice(0, -1), { kind: 'interim', percent: '10', days_before: 7 }, ...rows.slice(-1)])}>
            {t('bqp_addRow')}
          </Button>
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-body font-bold">{t('bqp_cancelRules')}</legend>
        {cancellation.map((rule, index) => (
          <div key={index} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]">
            <TextField label={t('bqp_minDays')} value={String(rule.min_days)} inputMode="numeric" maxLength={3} onChange={(days) => setRule(index, { min_days: Number(days) || 0 })} />
            <TextField label={t('bqp_retainedPercent')} value={rule.retained_percent} inputMode="decimal" maxLength={6} onChange={(retained_percent) => setRule(index, { retained_percent })} />
            <Button variant="secondary" icon={Trash2} aria-label={t('bqp_removeRow')} disabled={cancellation.length <= 1} onClick={() => setCancellation((rows) => rows.filter((_, i) => i !== index))} />
          </div>
        ))}
        <div>
          <Button variant="secondary" icon={Plus} onClick={() => setCancellation((rows) => [...rows, { min_days: 7, retained_percent: '50' }])}>
            {t('bqp_addRow')}
          </Button>
        </div>
      </fieldset>

      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label={t('bqp_reminders')} value={reminders} maxLength={40} onChange={setReminders} />
        <label className="flex min-h-11 items-center gap-2 self-end text-body">
          <input type="checkbox" checked={overdue} onChange={(event) => setOverdue(event.target.checked)} className="size-5" />
          {t('bqp_overdueReminders')}
        </label>
      </div>

      {save.isError && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {errorText(save.error, t)}
        </p>
      )}
      <div>
        <Button
          loading={save.isPending}
          onClick={() =>
            save.mutate({
              schedule_template: template,
              cancellation_rules: cancellation,
              reminder_days: reminders
                .split(/[,\s]+/)
                .filter(Boolean)
                .map(Number)
                .filter((n) => Number.isInteger(n) && n >= 0),
              overdue_reminders: overdue,
              version: initial.version,
            })
          }
        >
          {t('bqp_save')}
        </Button>
      </div>
    </div>
  );
}
