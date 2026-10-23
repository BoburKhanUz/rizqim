'use client';

import type { ReactNode } from 'react';
import { Field, cx, fieldClass } from '@rizqim/ui';

// A date typed with the browser's date picker (YYYY-MM-DD)
export function DateField({ label, value, onChange, error, hint, disabled, min, max }: { label: string; value: string; onChange: (value: string) => void; error?: ReactNode; hint?: ReactNode; disabled?: boolean; min?: string; max?: string }) {
  return (
    <Field label={label} error={error} hint={hint}>
      {(props) => <input {...props} type="date" value={value} min={min} max={max} disabled={disabled} onChange={(event) => onChange(event.target.value)} className={cx(fieldClass, 'w-auto min-w-40')} />}
    </Field>
  );
}

// A time of day typed with the browser's time picker (HH:MM)
export function TimeField({ label, value, onChange, error, hint, disabled }: { label: string; value: string; onChange: (value: string) => void; error?: ReactNode; hint?: ReactNode; disabled?: boolean }) {
  return (
    <Field label={label} error={error} hint={hint}>
      {(props) => <input {...props} type="time" value={value} disabled={disabled} onChange={(event) => onChange(event.target.value)} className={cx(fieldClass, 'w-auto min-w-32')} />}
    </Field>
  );
}

export function TextField({
  label,
  value,
  onChange,
  error,
  hint,
  placeholder,
  maxLength,
  inputMode,
  disabled,
  autoFocus,
  className,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: ReactNode;
  hint?: ReactNode;
  placeholder?: string;
  maxLength?: number;
  inputMode?: 'text' | 'numeric' | 'decimal' | 'tel';
  disabled?: boolean;
  autoFocus?: boolean;
  className?: string;
}) {
  return (
    <Field label={label} error={error} hint={hint} className={className}>
      {(props) => (
        <input
          {...props}
          {...(autoFocus ? { 'data-autofocus': true } : {})}
          type="text"
          autoComplete="off"
          value={value}
          placeholder={placeholder}
          maxLength={maxLength}
          inputMode={inputMode}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
          className={fieldClass}
        />
      )}
    </Field>
  );
}

export function SelectField<T extends string>({
  label,
  value,
  onChange,
  options,
  error,
  hint,
  disabled,
  className,
}: {
  label: string;
  value: T;
  onChange: (value: T) => void;
  options: ReadonlyArray<{ value: T; label: string }>;
  error?: ReactNode;
  hint?: ReactNode;
  disabled?: boolean;
  className?: string;
}) {
  return (
    <Field label={label} error={error} hint={hint} className={className}>
      {(props) => (
        <select {...props} value={value} disabled={disabled} onChange={(event) => onChange(event.target.value as T)} className={fieldClass}>
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
