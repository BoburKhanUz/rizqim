'use client';

import { useEffect, useState, type FormEvent } from 'react';
import { Check, Copy, RefreshCw } from 'lucide-react';
import { Button, Dialog, Field, fieldClass, formatTime, FormError } from '@rizqim/ui';
import { getApiFieldError, useCreatePairingCode, useEntitlements, useFeature, useMenuBranches, useStoreDevices, type PairingCode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';

// P6-14: `display` is the free customer display / ready-numbers board (it never counts toward the plan's devices)
type TerminalType = 'pos' | 'kds' | 'display';
const TYPES: readonly TerminalType[] = ['pos', 'kds', 'display'];

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
  const [tillUuid, setTillUuid] = useState('');
  const [code, setCode] = useState<PairingCode | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);
  const create = useCreatePairingCode();
  // P6-14: a customer display may follow one till of its branch (otherwise it shows the branch's latest cart)
  const devices = useStoreDevices(false);
  const branchTills = type === 'display' && branchId ? (devices.data ?? []).filter((item) => item.type === 'pos' && !item.revoked_at && (item.branch_id === null || item.branch_id === Number(branchId))) : [];
  // P4-21: a KDS is the plan's `kds`; while an invoice is overdue for a week only the plan's own terminals can be paired
  const kds = useFeature('kds');
  const entitlements = useEntitlements();
  const kdsOff = type === 'kds' && !kds.allowed;
  const device = type === 'display' ? undefined : entitlements.data?.devices[type];
  const limitHit = !kdsOff && device?.can_add === false;
  const secondsLeft = useSecondsLeft(code?.expires_at ?? null);
  const expired = code !== null && secondsLeft === 0;

  const submit = (event?: FormEvent) => {
    event?.preventDefault();
    setErrors({});
    setFormError(null);
    setCopied(false);
    // A customer display shows one branch's numbers: the branch is required (the server says 422 `branch_required` too)
    if (type === 'display' && !branchId) {
      setErrors({ branch_id: t('devices_branchRequired') });
      return;
    }
    create.mutate(
      { type, ...(branchId ? { branch_id: Number(branchId) } : {}), ...(name.trim() ? { name: name.trim() } : {}), ...(type === 'display' && tillUuid && branchTills.some((till) => till.uuid === tillUuid) ? { till_uuid: tillUuid } : {}) },
      {
        onSuccess: setCode,
        onError: (err) => {
          const next: Record<string, string> = {};
          for (const field of ['type', 'branch_id', 'name', 'till_uuid']) {
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
        description={t('devices_codeText', { type: t(`devices_type_${code.type === 'kds' || code.type === 'display' ? code.type : 'pos'}`) })}
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
        {branchTills.length > 0 && (
          <Field label={t('devices_till')} hint={t('devices_tillHint')} error={errors.till_uuid}>
            {(props) => (
              <select {...props} value={tillUuid} onChange={(event) => setTillUuid(event.target.value)} className={fieldClass}>
                <option value="">{t('devices_tillAny')}</option>
                {branchTills.map((till) => (
                  <option key={till.uuid} value={till.uuid}>
                    {till.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
        )}
        <Field label={t('devices_name')} hint={t('devices_nameHint')} error={errors.name}>
          {(props) => <input {...props} type="text" maxLength={100} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} className={fieldClass} />}
        </Field>
        {(kdsOff || limitHit) && (
          <p role="status" className="rounded-control bg-info-soft px-3 py-2.5 text-footnote font-bold text-info-soft-fg">
            {kdsOff ? t(kds.planName ? 'billing_kdsLocked' : 'billing_kdsLockedNoPlan', { plan: kds.planName ?? '' }) : t('billing_err_device')}
          </p>
        )}
        <FormError message={formError} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={create.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={create.isPending} disabled={kdsOff || limitHit}>
            {t('devices_createCode')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
