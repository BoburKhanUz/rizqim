'use client';

import type { ReactNode } from 'react';
import { Field, fieldClass } from '@rizqim/ui';

export { Check } from '../management/fields';

// A small text input whose accessible name is longer than what is shown above it (several rows repeat "MXIK": the name says
// which row). Used where one screen holds many inputs with the same caption.
export function TextFieldInline({
  label,
  shownLabel,
  value,
  onChange,
  error,
  hint,
  inputMode,
  maxLength,
}: {
  label: string;
  shownLabel: string;
  value: string;
  onChange: (value: string) => void;
  error?: ReactNode;
  hint?: ReactNode;
  inputMode?: 'text' | 'numeric' | 'decimal';
  maxLength?: number;
}) {
  return (
    <Field label={<span aria-hidden="true">{shownLabel}</span>} error={error} hint={hint}>
      {(props) => <input {...props} aria-label={label} type="text" autoComplete="off" inputMode={inputMode} maxLength={maxLength} value={value} onChange={(event) => onChange(event.target.value)} className={fieldClass} />}
    </Field>
  );
}
