'use client';

import { useEffect, useState, type FormEvent } from 'react';
import { Check, Copy, RefreshCw } from 'lucide-react';
import { Button, Dialog, Field, fieldClass, formatTime } from '@rizqim/ui';
import { getApiFieldError, useCreatePairingCode, useMenuBranches, type PairingCode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { FormError } from './Notice';

type TerminalType = 'pos' | 'kds';
const TYPES: readonly TerminalType[] = ['pos', 'kds'];

// Seconds left until `expiresAt`, counting down once a second
function useSecondsLeft(expiresAt: string | null): number | null {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (!expiresAt) return;
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, [expiresAt]);
  if (!expiresAt) return null;
  const end = Date.parse(expiresAt);
  return Number.isNaN(end) ? null : Math.max(0, Math.ceil((end - now) / 1000));
}

const clockText = (seconds: number) => `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

// POST /owner/devices/pairing-codes: a one-time code (ABCD-EFGH) a POS or kitchen display types in to join the store. It is
// shown big, with the time it stops working; once it runs out (or is used) a new one is a click away.
export function PairingCodeDialog({ onClose }: { onClose: () => void }) {
  const { t } = useI18n();
  const branches = useMenuBranches();
  const [type, setType] = useState<TerminalType>('pos');
  const [branchId, setBranchId] = useState('');
  const [name, setName] = useState('');
  const [code, setCode] = useState<PairingCode | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const create = useCreatePairingCode();
  const secondsLeft = useSecondsLeft(code?.expires_at ?? null);
  const expired = code !== null && secondsLeft === 0;

  const submit = (event?: FormEvent) => {
    event?.preventDefault();
    setErrors({});
    setFormError(null);
    setCopied(false);
    create.mutate(
      { type, ...(branchId ? { branch_id: Number(branchId) } : {}), ...(name.trim() ? { name: name.trim() } : {}) },
      {
        onSuccess: setCode,
        onError: (err) => {
          const next: Record<string, string> = {};
          for (const field of ['type', 'branch_id', 'name']) {
            const message = getApiFieldError(err, field);
            if (message) next[field] = message;
          }
          setErrors(next);
          setFormError(Object.keys(next).length === 0 ? errorText(err, t) : null);
        },
      },
    );
  };

  const copy = async () => {
    if (!code) return;
    try {
      await navigator.clipboard.writeText(code.code);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  };

  if (code) {
    return (
      <Dialog
        open
        title={t('devices_codeTitle')}
        description={t('devices_codeText', { type: t(`devices_type_${code.type === 'kds' ? 'kds' : 'pos'}`) })}
        closeLabel={t('mgmt_close')}
        onClose={onClose}
        footer={
          <>
            <Button variant="secondary" icon={expired ? RefreshCw : copied ? Check : Copy} onClick={expired ? () => setCode(null) : () => void copy()}>
              {expired ? t('devices_newCode') : copied ? t('mgmt_copied') : t('mgmt_copy')}
            </Button>
            <Button onClick={onClose} data-autofocus>
              {t('mgmt_done')}
            </Button>
          </>
        }
      >
        <div className="flex flex-col items-center gap-2 rounded-card bg-surface-2 px-4 py-6 text-center">
          <p aria-label={t('devices_codeAria')} className={`select-all font-mono text-5xl font-bold tracking-[0.15em] ${expired ? 'text-fg-subtle line-through' : 'text-fg'}`} data-testid="pairing-code">
            {code.code}
          </p>
          {expired ? (
            <p role="status" className="text-footnote font-bold text-danger-soft-fg">
              {t('devices_codeExpired')}
            </p>
          ) : (
            <p role="timer" className="text-footnote text-fg-muted">
              {t('devices_codeExpires', { time: formatTime(code.expires_at), left: secondsLeft === null ? '' : clockText(secondsLeft) })}
            </p>
          )}
        </div>
        <p className="text-footnote text-fg-muted">{t('devices_codeOnce')}</p>
      </Dialog>
    );
  }

  return (
    <Dialog open title={t('devices_pairTitle')} description={t('devices_pairText')} busy={create.isPending} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <Field label={t('devices_type')} error={errors.type}>
          {(props) => (
            <select {...props} data-autofocus value={type} onChange={(event) => setType(event.target.value as TerminalType)} className={fieldClass}>
              {TYPES.map((value) => (
                <option key={value} value={value}>
                  {t(`devices_type_${value}`)}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('devices_branch')} hint={t('devices_branchHint')} error={errors.branch_id}>
          {(props) => (
            <select {...props} value={branchId} onChange={(event) => setBranchId(event.target.value)} className={fieldClass}>
              <option value="">{t('devices_noBranch')}</option>
              {(branches.data ?? []).map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </select>
          )}
        </Field>
        <Field label={t('devices_name')} hint={t('devices_nameHint')} error={errors.name}>
          {(props) => <input {...props} type="text" maxLength={100} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
        </Field>
        <FormError message={formError} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={create.isPending}>
            {t('devices_createCode')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
