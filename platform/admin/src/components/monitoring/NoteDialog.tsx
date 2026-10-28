'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import { useT } from '../I18nProvider';

// A question with one text box: a note for an alert (optional) or the reason for a change (required, 3-500 characters, what the
// backend's shared `reason` rule asks). The server's refusal is shown inside, so the dialog stays open where the click was.
export function NoteDialog({
  title,
  text,
  label,
  placeholder,
  confirmLabel,
  required,
  minLength,
  tone = 'primary',
  busy,
  error,
  onSubmit,
  onClose,
}: {
  title: string;
  text: string;
  label: string;
  placeholder: string;
  confirmLabel: string;
  required: boolean;
  minLength: number;
  tone?: 'primary' | 'danger';
  busy: boolean;
  error: string | null;
  onSubmit: (value: string) => void;
  onClose: () => void;
}) {
  const t = useT();
  const [value, setValue] = useState('');
  const [problem, setProblem] = useState<string | null>(null);

  function submit(event: FormEvent) {
    event.preventDefault();
    const trimmed = value.trim();
    // A note may stay empty; once typed it must be long enough
    if ((required || trimmed !== '') && trimmed.length < minLength) {
      setProblem(required ? t('monitoring_reason_short') : t('monitoring_alert_noteShort'));
      return;
    }
    setProblem(null);
    onSubmit(trimmed);
  }

  return (
    <Dialog
      open
      variant="center"
      title={title}
      description={text}
      busy={busy}
      closeLabel={t('close')}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('monitoring_reason_cancel')}
          </Button>
          <Button type="submit" form="monitoring-note-form" variant={tone} loading={busy}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <form id="monitoring-note-form" onSubmit={submit} className="flex flex-col gap-3">
        <Field label={label} error={problem ?? undefined}>
          {(control) => (
            <textarea
              {...control}
              data-autofocus
              rows={3}
              maxLength={500}
              value={value}
              placeholder={placeholder}
              onChange={(event) => setValue(event.target.value)}
              className={fieldClass}
            />
          )}
        </Field>
        <FormError message={error} />
      </form>
    </Dialog>
  );
}
