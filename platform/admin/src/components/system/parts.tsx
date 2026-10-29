'use client';

import { useState, type FormEvent, type ReactNode } from 'react';
import { ArrowLeft, type LucideIcon } from 'lucide-react';
import { Button, CardSkeletons, cx, Dialog, EmptyState, Field, fieldClass, FormError, InlineError } from '@rizqim/ui';
import { errorText } from '@/lib/errors';
import { useT } from '../I18nProvider';

// Small parts the Tizim and Integratsiyalar screens share (chips, label/value rows, a pager, list states and the action
// dialog that asks for a reason).

const CHIP = 'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-footnote font-bold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
const chipTone = (on: boolean) => (on ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-fg hover:bg-surface-2');

export function Chip({ on, onClick, children }: { on: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button type="button" aria-pressed={on} className={cx(CHIP, chipTone(on))} onClick={onClick}>
      {children}
    </button>
  );
}

export function ChipRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div role="group" aria-label={label} className="flex flex-wrap gap-2">
      {children}
    </div>
  );
}

export function Pager({ page, lastPage, onPage }: { page: number; lastPage: number; onPage: (page: number) => void }) {
  const t = useT();
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-between gap-2">
      <Button variant="secondary" disabled={page <= 1} onClick={() => onPage(page - 1)}>
        {t('system_prev')}
      </Button>
      <span className="text-footnote text-fg-muted">{t('system_pageOf', { page, pages: lastPage })}</span>
      <Button variant="secondary" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
        {t('system_next')}
      </Button>
    </div>
  );
}

export function BackButton({ onClick }: { onClick: () => void }) {
  const t = useT();
  return (
    <button type="button" onClick={onClick} className="mb-1 inline-flex min-h-11 items-center gap-1.5 self-start rounded-control px-2 text-footnote font-bold text-brand hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('system_back')}
    </button>
  );
}

// Label/value rows of a card
export function Facts({ rows, label }: { rows: ReadonlyArray<readonly [string, ReactNode]>; label?: string }) {
  return (
    <dl aria-label={label} className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
      {rows.map(([name, value]) => (
        <div key={name} className="flex min-w-0 flex-col">
          <dt className="text-caption font-bold text-fg-muted">{name}</dt>
          <dd className="break-words text-body text-fg">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

const reasonOk = (reason: string) => reason.trim().length >= 3;

// An action dialog: the form's own fields (children) and a reason of 3-500 characters that goes into the audit log. The step-up
// (a fresh 2FA code) is asked by the shell when the backend wants it (403 reauth_required).
export function ReasonDialog({
  title,
  text,
  submitLabel,
  tone = 'primary',
  busy,
  error,
  reasonError,
  canSubmit = true,
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
  canSubmit?: boolean;
  onSubmit: (reason: string) => void;
  onClose: () => void;
  children?: ReactNode;
}) {
  const t = useT();
  const [reason, setReason] = useState('');
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!busy && canSubmit && reasonOk(reason)) onSubmit(reason.trim());
  };
  return (
    <Dialog open title={title} description={text} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        {children}
        <Field label={t('system_reason')} hint={t('system_reasonHint')} error={reasonError}>
          {(control) => <textarea {...control} required minLength={3} maxLength={500} rows={2} className={fieldClass} value={reason} onChange={(event) => setReason(event.target.value)} />}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" variant={tone} loading={busy} disabled={!canSubmit || !reasonOk(reason)}>
            {submitLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

// What a list shows while loading, when it failed and when it is empty; `children` is the list itself
export function ListState({
  loading,
  error,
  fetching,
  onRetry,
  empty,
  emptyTitle,
  emptyText,
  icon,
  errorTitle,
  children,
}: {
  loading: boolean;
  error: unknown;
  fetching: boolean;
  onRetry: () => void;
  empty: boolean;
  emptyTitle: string;
  emptyText?: string;
  icon: LucideIcon;
  errorTitle: string;
  children: ReactNode;
}) {
  const t = useT();
  if (error) return <InlineError title={errorTitle} message={errorText(error, t)} retryLabel={t('retry')} retrying={fetching} onRetry={onRetry} />;
  if (loading) return <CardSkeletons count={3} lines={3} label={t('loading')} />;
  if (empty) return <EmptyState icon={icon} title={emptyTitle} text={emptyText} />;
  return <>{children}</>;
}
