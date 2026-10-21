'use client';

import type { ReactNode } from 'react';
import { Field, cx, fieldClass } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { getPeriodProblem, isDay } from './period';

export interface DateFilter {
  from: string;
  to: string;
}

// The days of a history filter that can be sent: a half-typed date is left out, and so is a reversed pair (the screen says so)
export function usableDates(filter: DateFilter): { from?: string; to?: string; reversed: boolean } {
  const from = isDay(filter.from) ? filter.from : undefined;
  const to = isDay(filter.to) ? filter.to : undefined;
  const reversed = from !== undefined && to !== undefined && getPeriodProblem({ from, to }) === 'reversed';
  return reversed ? { reversed } : { ...(from ? { from } : {}), ...(to ? { to } : {}), reversed };
}

// A row of filters above a history table: the screen's own selects and the two dates
export function HistoryFilters({ value, onChange, reversed, hideDates, children }: { value: DateFilter; onChange: (next: DateFilter) => void; reversed: boolean; hideDates?: boolean; children?: ReactNode }) {
  const { t } = useI18n();
  return (
    <div className="mb-4 flex flex-col gap-2">
      <div className="flex flex-wrap items-end gap-3">
        {children}
        {!hideDates && (
          <>
        <Field label={t('report_from')}>
          {(props) => <input {...props} type="date" className={cx(fieldClass, 'w-auto')} value={value.from} onChange={(event) => onChange({ ...value, from: event.target.value })} />}
        </Field>
        <Field label={t('report_to')}>
          {(props) => <input {...props} type="date" className={cx(fieldClass, 'w-auto')} value={value.to} onChange={(event) => onChange({ ...value, to: event.target.value })} />}
        </Field>
          </>
        )}
      </div>
      {reversed && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {t('report_problem_reversed')}
        </p>
      )}
    </div>
  );
}

export function SelectFilter({ label, value, onChange, options, allLabel }: { label: string; value: string; onChange: (value: string) => void; options: ReadonlyArray<{ value: string; label: string }>; allLabel: string }) {
  return (
    <Field label={label}>
      {(props) => (
        <select {...props} className={cx(fieldClass, 'w-auto')} value={value} onChange={(event) => onChange(event.target.value)}>
          <option value="">{allLabel}</option>
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      )}
    </Field>
  );
}
