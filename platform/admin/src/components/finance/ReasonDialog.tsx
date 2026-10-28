'use client';

import { useState, type FormEvent, type ReactNode } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { useT } from '../I18nProvider';

// The reason every Moliya action asks for (3–500 characters, it goes into the audit log). The step-up (a fresh 2FA code)
// is asked by the shell when the backend wants it.
export const reasonOk = (reason: string) => reason.trim().length >= 3;

export function ReasonFields({ reason, onChange, error }: { reason: string; onChange: (value: string) => void; error?: string }) {
  const t = useT();
  return (
    <Field label={t('finance_reason')} hint={t('finance_reasonHint')} error={error}>
      {(control) => <textarea {...control} required minLength={3} maxLength={500} rows={2} className={fieldClass} value={reason} onChange={(event) => onChange(event.target.value)} />}
    </Field>
  );
}

export function ReasonDialog({
  title,
  text,
  submitLabel,
  tone = 'primary',
  busy,
  error,
  reasonError,
  onSubmit,
  onClose,
  children,
}: {
  title: string;
  text?: ReactNode;
  submitLabel: string;
  tone?: 'primary' | 'danger';
  busy: boolean;
  error: string | null;
  reasonError?: string;
  onSubmit: (reason: string) => void;
  onClose: () => void;
  children?: ReactNode;
}) {
  const t = useT();
  const [reason, setReason] = useState('');
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!busy && reasonOk(reason)) onSubmit(reason.trim());
  };
  return (
    <Dialog open title={title} description={text} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        {children}
        <ReasonFields reason={reason} onChange={setReason} error={reasonError} />
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" variant={tone} loading={busy} disabled={!reasonOk(reason)}>
            {submitLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

// A label and a value in a card's definition list
export function Row({ label, value, strong = false }: { label: ReactNode; value: ReactNode; strong?: boolean }) {
  return (
    <div className="flex items-baseline justify-between gap-3 py-1.5">
      <dt className="text-footnote text-fg-muted">{label}</dt>
      <dd className={strong ? 'text-right text-body font-bold text-fg' : 'text-right text-footnote text-fg'}>{value}</dd>
    </div>
  );
}
