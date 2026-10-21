'use client';

import { Field, fieldClass } from '@rizqim/ui';
import type { ReportGroupBy } from '@rizqim/staff';
import { cx } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { PERIOD_PRESETS, type DayRange, type PeriodPreset, type PeriodProblem } from './period';

export interface PeriodState {
  preset: PeriodPreset;
  custom: Partial<DayRange>;
  groupBy: ReportGroupBy;
}

const GROUPS: readonly ReportGroupBy[] = ['day', 'week', 'month'];

// The filters of the reports screen: a period (presets and a custom range), the grouping of the series and a branch
export function PeriodPicker({
  state,
  onChange,
  problem,
}: {
  state: PeriodState;
  onChange: (next: Partial<PeriodState>) => void;
  problem: PeriodProblem | null;
}) {
  const { t } = useI18n();
  return (
    <div className="flex flex-col gap-4 rounded-card border border-line bg-surface p-4">
      <div role="group" aria-label={t('report_periodLabel')} className="flex flex-wrap gap-2">
        {PERIOD_PRESETS.map((preset) => {
          const active = state.preset === preset;
          return (
            <button
              key={preset}
              type="button"
              aria-pressed={active}
              onClick={() => onChange({ preset })}
              className={cx(
                'min-h-11 cursor-pointer rounded-control border px-4 text-body font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                active ? 'border-brand bg-brand-soft text-brand-soft-fg' : 'border-line bg-surface-2 text-fg hover:border-brand',
              )}
            >
              {t(`report_p_${preset}` as const)}
            </button>
          );
        })}
      </div>

      <div className="flex flex-wrap items-end gap-3">
        {state.preset === 'custom' && (
          <>
            <Field label={t('report_from')}>
              {(props) => <input {...props} type="date" className={cx(fieldClass, 'w-auto')} value={state.custom.from ?? ''} onChange={(event) => onChange({ custom: { ...state.custom, from: event.target.value } })} />}
            </Field>
            <Field label={t('report_to')}>
              {(props) => <input {...props} type="date" className={cx(fieldClass, 'w-auto')} value={state.custom.to ?? ''} onChange={(event) => onChange({ custom: { ...state.custom, to: event.target.value } })} />}
            </Field>
          </>
        )}
        <Field label={t('report_groupBy')}>
          {(props) => (
            <select {...props} className={cx(fieldClass, 'w-auto')} value={state.groupBy} onChange={(event) => onChange({ groupBy: event.target.value as ReportGroupBy })}>
              {GROUPS.map((group) => (
                <option key={group} value={group}>
                  {t(`report_g_${group}` as const)}
                </option>
              ))}
            </select>
          )}
        </Field>
      </div>

      {problem && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {t(`report_problem_${problem}` as const, { days: 366 })}
        </p>
      )}
    </div>
  );
}
