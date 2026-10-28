'use client';

import { useState } from 'react';
import { Field, fieldClass, Switch } from '@rizqim/ui';
import {
  useAssignPlan,
  useCancelSubscription,
  useCatalog,
  useChangePeriod,
  useExtendTrial,
  useSetCommission,
  type BillingPeriod,
  type StoreBilling,
} from '@/lib/api/pos';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { CHANNEL_LABEL, percentText } from './posModel';
import { ReasonDialog } from './PosParts';

// The action dialogs of a store's subscription (P4-21's endpoints). Each asks for a reason (3-500 characters, it goes into the
// audit log); the step-up (a fresh 2FA code) is asked by the shell when the backend wants it.

export type BillingDialogKind = 'assign' | 'period' | 'trial' | 'cancel' | 'commission';

interface DialogProps {
  data: StoreBilling;
  onDone: (message: string) => void;
  onClose: () => void;
}

export function BillingDialog({ kind, ...props }: DialogProps & { kind: BillingDialogKind }) {
  switch (kind) {
    case 'assign':
      return <AssignDialog {...props} />;
    case 'period':
      return <PeriodDialog {...props} />;
    case 'trial':
      return <TrialDialog {...props} />;
    case 'cancel':
      return <CancelDialog {...props} />;
    case 'commission':
      return <CommissionDialog {...props} />;
  }
}

function AssignDialog({ data, onDone, onClose }: DialogProps) {
  const t = useT();
  const catalog = useCatalog();
  const action = useAssignPlan(data.store.id);
  const plans = (catalog.data?.plans ?? []).filter((plan) => plan.is_active);
  const current = data.subscription?.plan.code ?? '';
  const [plan, setPlan] = useState(current || plans[0]?.code || '');
  const [period, setPeriod] = useState<BillingPeriod>(data.subscription?.billing_period ?? 'monthly');
  const [immediately, setImmediately] = useState(false);
  const chosen = plans.find((candidate) => candidate.code === plan);
  const yearlyOk = chosen ? chosen.version >= 2 : false;
  return (
    <ReasonDialog
      title={t('pos_assign_title')}
      text={t(data.subscription ? 'pos_assign_text' : 'pos_assign_textNew')}
      submitLabel={t('pos_assign_confirm')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={plan !== '' && (!data.subscription || plan !== current || period !== data.subscription.billing_period)}
      onClose={onClose}
      onSubmit={(reason) =>
        action.mutate(
          { plan, period: yearlyOk ? period : 'monthly', immediately: data.subscription ? immediately : undefined, reason },
          { onSuccess: () => onDone(t('pos_assign_done')) },
        )
      }
    >
      <Field label={t('pos_plan')} error={fieldError(action.error, 'plan')}>
        {(control) => (
          <select {...control} data-autofocus className={fieldClass} value={plan} onChange={(event) => setPlan(event.target.value)}>
            {plans.map((candidate) => (
              <option key={candidate.code} value={candidate.code}>
                {candidate.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      <Field label={t('pos_period')} error={fieldError(action.error, 'period')}>
        {(control) => (
          <select {...control} className={fieldClass} value={yearlyOk ? period : 'monthly'} disabled={!yearlyOk} onChange={(event) => setPeriod(event.target.value as BillingPeriod)}>
            <option value="monthly">{t('pos_period_monthly')}</option>
            <option value="yearly">{t('pos_period_yearly')}</option>
          </select>
        )}
      </Field>
      {data.subscription && <Switch checked={immediately} onChange={setImmediately} label={t('pos_assign_immediately')} />}
    </ReasonDialog>
  );
}

function PeriodDialog({ data, onDone, onClose }: DialogProps) {
  const t = useT();
  const action = useChangePeriod(data.store.id);
  const current = data.subscription?.billing_period ?? 'monthly';
  const [period, setPeriod] = useState<BillingPeriod>(current === 'monthly' ? 'yearly' : 'monthly');
  return (
    <ReasonDialog
      title={t('pos_period_title')}
      text={t('pos_period_text')}
      submitLabel={t('save')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={period !== current}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ period, reason }, { onSuccess: () => onDone(t('pos_period_done')) })}
    >
      <Field label={t('pos_period')} error={fieldError(action.error, 'period')}>
        {(control) => (
          <select {...control} data-autofocus className={fieldClass} value={period} onChange={(event) => setPeriod(event.target.value as BillingPeriod)}>
            <option value="monthly">{t('pos_period_monthly')}</option>
            <option value="yearly">{t('pos_period_yearly')}</option>
          </select>
        )}
      </Field>
    </ReasonDialog>
  );
}

function TrialDialog({ data, onDone, onClose }: DialogProps) {
  const t = useT();
  const action = useExtendTrial(data.store.id);
  const [days, setDays] = useState('7');
  const valid = /^\d{1,2}$/.test(days) && Number(days) >= 1 && Number(days) <= 90;
  return (
    <ReasonDialog
      title={t('pos_trial_title')}
      text={t('pos_trial_text')}
      submitLabel={t('pos_trial_confirm')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={valid}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ days: Number(days), reason }, { onSuccess: () => onDone(t('pos_trial_done')) })}
    >
      <Field label={t('pos_trial_days')} error={(!valid && days !== '' ? t('pos_trial_invalid') : undefined) ?? fieldError(action.error, 'days')}>
        {(control) => <input {...control} data-autofocus type="text" inputMode="numeric" className={fieldClass} value={days} onChange={(event) => setDays(event.target.value)} />}
      </Field>
    </ReasonDialog>
  );
}

function CancelDialog({ data, onDone, onClose }: DialogProps) {
  const t = useT();
  const action = useCancelSubscription(data.store.id);
  const [immediately, setImmediately] = useState(false);
  return (
    <ReasonDialog
      title={t('pos_cancel_title')}
      text={t('pos_cancel_text')}
      submitLabel={t('pos_cancel_confirm')}
      tone="danger"
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ immediately, reason }, { onSuccess: () => onDone(t(immediately ? 'pos_cancel_doneNow' : 'pos_cancel_done')) })}
    >
      <Switch checked={immediately} onChange={setImmediately} label={t('pos_cancel_immediately')} />
    </ReasonDialog>
  );
}

const CHANNELS = ['rizqim_courier', 'own_delivery', 'pickup', 'pos', 'aggregator'] as const;
const RATE = /^\d{1,3}(\.\d{1,2})?$/;

function CommissionDialog({ data, onDone, onClose }: DialogProps) {
  const t = useT();
  const action = useSetCommission(data.store.id);
  const overrides = data.commission.overrides;
  const [values, setValues] = useState<Record<string, string>>(() => Object.fromEntries(CHANNELS.map((channel) => [channel, overrides[channel] !== undefined ? String(Number(overrides[channel])) : ''])));
  const [courier, setCourier] = useState<'own' | 'fleet'>(data.commission.delivery_courier === 'fleet' ? 'fleet' : 'own');

  const text = (channel: string) => values[channel]?.trim().replace(',', '.') ?? '';
  const invalid = (channel: string) => text(channel) !== '' && (!RATE.test(text(channel)) || Number(text(channel)) > 100);
  const changed = (channel: string) => (overrides[channel] === undefined ? text(channel) !== '' : text(channel) === '' || Number(text(channel)) !== Number(overrides[channel]));
  const anyInvalid = CHANNELS.some(invalid);
  const anyChange = CHANNELS.some(changed) || courier !== (data.commission.delivery_courier === 'fleet' ? 'fleet' : 'own');

  return (
    <ReasonDialog
      title={t('pos_commission_title')}
      text={t('pos_commission_text')}
      submitLabel={t('save')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={!anyInvalid && anyChange}
      onClose={onClose}
      onSubmit={(reason) => {
        const changes: Record<string, string | null> = {};
        for (const channel of CHANNELS) if (changed(channel)) changes[channel] = text(channel) === '' ? null : Number(text(channel)).toFixed(2);
        action.mutate(
          { ...(Object.keys(changes).length > 0 ? { overrides: changes } : {}), delivery_courier: courier, reason },
          { onSuccess: () => onDone(t('pos_commission_done')) },
        );
      }}
    >
      {CHANNELS.map((channel, index) => (
        <Field
          key={channel}
          label={t(CHANNEL_LABEL[channel] ?? 'pos_channel_pos')}
          hint={t('pos_commission_default', { rate: percentText(data.commission.rates[channel]?.default) })}
          error={invalid(channel) ? t('pos_commission_invalid') : fieldError(action.error, `overrides.${channel}`)}
        >
          {(control) => (
            <input
              {...control}
              data-autofocus={index === 0 ? true : undefined}
              type="text"
              inputMode="decimal"
              placeholder={percentText(data.commission.rates[channel]?.rate)}
              className={fieldClass}
              value={values[channel] ?? ''}
              onChange={(event) => setValues((previous) => ({ ...previous, [channel]: event.target.value }))}
            />
          )}
        </Field>
      ))}
      <Field label={t('pos_commission_courier')} hint={t('pos_commission_courierHint')}>
        {(control) => (
          <select {...control} className={fieldClass} value={courier} onChange={(event) => setCourier(event.target.value as 'own' | 'fleet')}>
            <option value="own">{t('pos_courier_own')}</option>
            <option value="fleet">{t('pos_courier_fleet')}</option>
          </select>
        )}
      </Field>
    </ReasonDialog>
  );
}
