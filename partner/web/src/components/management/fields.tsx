'use client';

import type { ReactNode } from 'react';
import { Field, cx, fieldClass } from '@rizqim/ui';
import { formatNationalInput, nationalDigits, PHONE_PREFIX } from '@/lib/phone';

// A checkbox with a label and an optional line under it. The whole row is the target (44px high).
export function Check({
  checked,
  onChange,
  label,
  hint,
  disabled,
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label: ReactNode;
  hint?: ReactNode;
  disabled?: boolean;
}) {
  return (
    <label className={cx('flex min-h-11 items-start gap-3 rounded-control py-2', disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer')}>
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(event) => onChange(event.target.checked)} className="mt-0.5 size-5 shrink-0 accent-[var(--rz-brand)]" />
      <span className="flex min-w-0 flex-col">
        <span className="text-body text-fg">{label}</span>
        {hint && <span className="text-footnote text-fg-muted">{hint}</span>}
      </span>
    </label>
  );
}

// A number typed as text (so "12 000" or an empty box are possible while typing); the screen parses it on submit.
export function NumberField({
  label,
  value,
  onChange,
  hint,
  error,
  suffix,
  inputMode = 'decimal',
  placeholder,
  disabled,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  hint?: ReactNode;
  error?: ReactNode;
  suffix?: string;
  inputMode?: 'decimal' | 'numeric';
  placeholder?: string;
  disabled?: boolean;
}) {
  return (
    <Field label={label} hint={hint} error={error}>
      {(props) => (
        <div className="relative">
          <input {...props} type="text" inputMode={inputMode} value={value} placeholder={placeholder} disabled={disabled} onChange={(event) => onChange(event.target.value)} className={cx(fieldClass, suffix && 'pr-14')} />
          {suffix && <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-footnote text-fg-muted">{suffix}</span>}
        </div>
      )}
    </Field>
  );
}

// "+998" and the 9 digits of a number, typed with spaces. `value` is what is typed (national digits with spaces).
export function PhoneField({
  label,
  value,
  onChange,
  hint,
  error,
  disabled,
  readOnly,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  hint?: ReactNode;
  error?: ReactNode;
  disabled?: boolean;
  readOnly?: boolean;
}) {
  return (
    <Field label={label} hint={hint} error={error}>
      {(props) => (
        <div className="flex items-stretch gap-2">
          <span aria-hidden="true" className="flex min-h-11 items-center rounded-control border border-line bg-surface-2 px-3 text-headline text-fg-muted">
            {PHONE_PREFIX}
          </span>
          <input
            {...props}
            type="tel"
            inputMode="tel"
            autoComplete="off"
            placeholder="90 123 45 67"
            value={value}
            disabled={disabled}
            readOnly={readOnly}
            onChange={(event) => onChange(formatNationalInput(event.target.value))}
            className={cx(fieldClass, readOnly && 'cursor-not-allowed opacity-70')}
          />
        </div>
      )}
    </Field>
  );
}

// The typed digits as the +998 number the API takes; null when empty; undefined when it is not a whole number.
export function phoneToE164(value: string): string | null | undefined {
  if (value.trim() === '') return null;
  const nine = nationalDigits(value);
  return nine ? `${PHONE_PREFIX}${nine}` : undefined;
}

// A stored "+998901234567" as typed digits ("90 123 45 67")
export const phoneToInput = (phone: string | null | undefined): string => formatNationalInput(phone ?? '');

// A decimal typed as text ("12 000", "12,5"): the number, null when empty, undefined when it is not one.
export function parseDecimal(value: string): number | null | undefined {
  const cleaned = value.replace(/[\s ]/g, '').replace(',', '.');
  if (cleaned === '') return null;
  const parsed = Number(cleaned);
  return Number.isFinite(parsed) ? parsed : undefined;
}
