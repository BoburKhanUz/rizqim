'use client';

import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { ImageIcon, X } from 'lucide-react';
import { Button, CardSkeletons, cx, Dialog, EmptyState, Field, fieldClass, FormError, InlineError } from '@rizqim/ui';
import type { LucideIcon } from 'lucide-react';
import { imageSrc, useLookup, type Named } from '@/lib/api/marketing';
import { errorText } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { imageProblem } from './marketingModel';

// Small parts the tabs of Marketing share.

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
        {t('marketing_prev')}
      </Button>
      <span className="text-footnote text-fg-muted">{t('marketing_pageOf', { page, pages: lastPage })}</span>
      <Button variant="secondary" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
        {t('marketing_next')}
      </Button>
    </div>
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
        <Field label={t('marketing_reason')} hint={t('marketing_reasonHint')} error={reasonError}>
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
  if (loading) return <CardSkeletons count={4} lines={3} label={t('loading')} />;
  if (empty) return <EmptyState icon={icon} title={emptyTitle} text={emptyText} />;
  return <>{children}</>;
}

// A picture the backend serves through the admin API (same session), or a calm square when there is none
export function Thumb({ path, label, className }: { path: string | null; label: string; className?: string }) {
  const [broken, setBroken] = useState(false);
  const src = imageSrc(path);
  if (!src || broken) {
    return (
      <span role="img" aria-label={label} className={cx('flex shrink-0 items-center justify-center rounded-control border border-line bg-surface-2 text-fg-muted', className)}>
        <ImageIcon aria-hidden="true" className="size-5" />
      </span>
    );
  }
  // eslint-disable-next-line @next/next/no-img-element -- the picture comes through the session proxy, not a static asset
  return <img src={src} alt={label} onError={() => setBroken(true)} className={cx('shrink-0 rounded-control border border-line bg-surface-2 object-cover', className)} />;
}

// A file field for a picture: checks the type and the 4 MB cap before anything is sent, and shows what was picked
export function ImageField({
  label,
  hint,
  current,
  currentLabel,
  file,
  onFile,
  error,
  required = false,
}: {
  label: string;
  hint: string;
  // The stored picture's admin path (when editing)
  current?: string | null;
  currentLabel?: string;
  file: File | null;
  onFile: (file: File | null) => void;
  error?: string;
  required?: boolean;
}) {
  const t = useT();
  const [problem, setProblem] = useState<'type' | 'size' | null>(null);
  // A local address for the picked file's preview, given back when the file changes or the field goes
  const url = useMemo(() => (file && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : null), [file]);
  useEffect(() => {
    if (!url) return;
    return () => URL.revokeObjectURL(url);
  }, [url]);

  const message = problem === 'type' ? t('marketing_imageType') : problem === 'size' ? t('marketing_imageSize') : error;
  return (
    <Field label={label} hint={hint} error={message}>
      {(control) => (
        <div className="flex items-center gap-3">
          {url ? (
            // eslint-disable-next-line @next/next/no-img-element -- a local preview of the picked file
            <img src={url} alt={t('marketing_imagePicked')} className="h-16 w-24 shrink-0 rounded-control border border-line object-cover" />
          ) : (
            <Thumb path={current ?? null} label={currentLabel ?? t('marketing_noImage')} className="h-16 w-24" />
          )}
          <input
            {...control}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            aria-required={required && !current ? true : undefined}
            className="min-w-0 flex-1 text-footnote text-fg file:mr-3 file:min-h-10 file:rounded-control file:border file:border-line file:bg-surface-2 file:px-3 file:font-bold"
            onChange={(event) => {
              const picked = event.target.files?.[0] ?? null;
              const bad = picked ? imageProblem(picked) : null;
              setProblem(bad);
              onFile(bad ? null : picked);
              if (bad) event.target.value = '';
            }}
          />
        </div>
      )}
    </Field>
  );
}

// Picks a store (or a product of a store) by searching its name; the chosen one is shown with a way to change it
export function LookupPicker({
  label,
  value,
  onChange,
  kind,
  storeId,
  disabled = false,
  hint,
}: {
  label: string;
  value: Named | null;
  onChange: (value: Named | null) => void;
  kind: 'store' | 'product';
  storeId?: number;
  disabled?: boolean;
  hint?: string;
}) {
  const t = useT();
  const [q, setQ] = useState('');
  const [typed, setTyped] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setTyped(q.trim()), 250);
    return () => clearTimeout(timer);
  }, [q]);
  const enabled = value === null && !disabled && (kind === 'store' || storeId !== undefined);
  const found = useLookup({ q: typed, store_id: kind === 'product' ? storeId : undefined }, enabled);
  const options = kind === 'store' ? (found.data?.stores ?? []) : (found.data?.products ?? []);

  if (value) {
    return (
      <Field label={label} hint={hint}>
        {(control) => (
          <div className="flex items-center justify-between gap-2 rounded-control border border-line bg-surface-2 px-3 py-1.5">
            <span {...control} className="min-w-0 break-words text-body font-bold text-fg">
              {value.name}
            </span>
            {!disabled && (
              <Button variant="ghost" onClick={() => onChange(null)} aria-label={t('marketing_pickClear', { label, name: value.name })}>
                <X aria-hidden="true" className="size-4" />
                {t('marketing_pickClearShort')}
              </Button>
            )}
          </div>
        )}
      </Field>
    );
  }

  return (
    <Field label={label} hint={disabled ? hint : kind === 'product' && storeId === undefined ? t('marketing_pickStoreFirst') : hint}>
      {(control) => (
        <div className="flex flex-col gap-2">
          <input
            {...control}
            type="search"
            className={fieldClass}
            value={q}
            disabled={disabled || (kind === 'product' && storeId === undefined)}
            placeholder={t('marketing_pickSearch')}
            onChange={(event) => setQ(event.target.value)}
          />
          {enabled && (
            <ul aria-label={t('marketing_pickResults', { label })} className="flex max-h-44 flex-col overflow-y-auto rounded-control border border-line">
              {options.length === 0 && <li className="px-3 py-2 text-footnote text-fg-muted">{found.isPending ? t('loading') : t('marketing_pickNone')}</li>}
              {options.map((option) => (
                <li key={option.id}>
                  <button type="button" className="min-h-11 w-full px-3 text-left text-body hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-brand" onClick={() => onChange({ id: option.id, name: option.name })}>
                    {option.name}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </Field>
  );
}
