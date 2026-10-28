'use client';

import { useState, type FormEvent } from 'react';
import { Button, Card, CardSkeletons, Field, InlineError, Section, fieldClass, FormError, Notice } from '@rizqim/ui';
import { getApiFieldError, useFeature, useMenuBranches, useSaveServiceFee, useServiceFees, type PlaceFeeMode, type ServiceFeeRow } from '@rizqim/staff';
import { isMessageKey } from '@/i18n';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { Check, NumberField, parseDecimal } from './fields';

// A channel with no setting means the hall only (`["pos_hall"]`, docs: Telegram orders never get either fee)
const DEFAULT_CHANNELS = ['pos_hall'];
const EXCLUDED_CHANNELS = ['telegram'];
const MODES: readonly PlaceFeeMode[] = ['per_person', 'per_table'];

// GET/PUT /owner/service-fees: the service charge (percent of the bill) and the place fee (per guest or per table), for the whole
// store or one branch's own values. The server limits the values (a percent over 30 is a 422 with its message). A branch with
// no row of its own uses the store's: the form starts from those and says so.
export function ServiceFeesForm() {
  const { t } = useI18n();
  const fees = useServiceFees();
  const branches = useMenuBranches();
  const [scope, setScope] = useState('');

  if (fees.isPending) return <CardSkeletons count={2} lines={3} label={t('loading')} />;
  if (fees.isError) return <InlineError title={t('loadErrorTitle')} message={errorText(fees.error, t)} retryLabel={t('retry')} retrying={fees.isFetching} onRetry={() => void fees.refetch()} />;

  const branchId = scope === '' ? null : Number(scope);
  const own = fees.data.settings.find((row) => row.branch_id === branchId && !row.hall_id) ?? null;
  const base = own ?? fees.data.settings.find((row) => row.branch_id === null && !row.hall_id) ?? null;
  const channels = fees.data.channels.filter((channel) => !EXCLUDED_CHANNELS.includes(channel));

  return (
    <div className="flex flex-col gap-4">
      <p className="text-footnote text-fg-muted">{t('settings_feesIntro')}</p>
      <Field label={t('settings_feesScope')}>
        {(props) => (
          <select {...props} value={scope} onChange={(event) => setScope(event.target.value)} className={`${fieldClass} sm:max-w-sm`}>
            <option value="">{t('settings_feesWholeStore')}</option>
            {(branches.data ?? []).map((branch) => (
              <option key={branch.id} value={branch.id}>
                {branch.name}
              </option>
            ))}
          </select>
        )}
      </Field>
      {branchId !== null && !own && <p className="text-footnote text-fg-muted">{t('settings_feesInherited')}</p>}
      <FeeForm key={`${scope}:${JSON.stringify(base)}`} branchId={branchId} base={base} channels={channels} />
    </div>
  );
}

function FeeForm({ branchId, base, channels }: { branchId: number | null; base: ServiceFeeRow | null; channels: string[] }) {
  const { t } = useI18n();
  const [percent, setPercent] = useState(base ? String(Number(base.service_charge_percent)) : '0');
  const [serviceChannels, setServiceChannels] = useState<string[]>(base?.service_charge_channels ?? DEFAULT_CHANNELS);
  const [amount, setAmount] = useState(base ? String(Number(base.place_fee_amount)) : '0');
  const [mode, setMode] = useState<PlaceFeeMode>(base?.place_fee_mode ?? 'per_person');
  const [placeChannels, setPlaceChannels] = useState<string[]>(base?.place_fee_channels ?? DEFAULT_CHANNELS);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState<string | null>(null);
  const save = useSaveServiceFee();
  // P4-21: a service charge above 0 is the plan's `service_charge`; the place fee below is not
  const serviceCharge = useFeature('service_charge');

  const toggle = (list: string[], channel: string, on: boolean) => (on ? [...list, channel] : list.filter((item) => item !== channel));

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    setFormError(null);
    const percentValue = parseDecimal(percent);
    const amountValue = parseDecimal(amount);
    const problems: Record<string, string> = {};
    if (percentValue === undefined || percentValue === null) problems.service_charge_percent = t('mgmt_numberInvalid');
    if (amountValue === undefined || amountValue === null) problems.place_fee_amount = t('mgmt_numberInvalid');
    setErrors(problems);
    if (Object.keys(problems).length > 0) return;
    save.mutate(
      {
        branch_id: branchId,
        service_charge_percent: percentValue as number,
        service_charge_channels: serviceChannels,
        place_fee_amount: amountValue as number,
        place_fee_mode: mode,
        place_fee_channels: placeChannels,
      },
      {
        onSuccess: () => setSaved(t('settings_feesSaved')),
        onError: (err) => {
          const next: Record<string, string> = {};
          for (const field of ['service_charge_percent', 'service_charge_channels', 'place_fee_amount', 'place_fee_mode', 'place_fee_channels', 'branch_id']) {
            const message = getApiFieldError(err, field);
            if (message) next[field] = message;
          }
          setErrors(next);
          setFormError(Object.keys(next).length === 0 ? errorText(err, t) : null);
        },
      },
    );
  };

  const channelLabel = (channel: string) => (isMessageKey(`settings_channel_${channel}`) ? t(`settings_channel_${channel}` as 'settings_channel_pos_hall') : channel);

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-6">
      <Notice message={saved} />
      <Section title={t('settings_serviceCharge')}>
        <Card className="flex flex-col gap-3">
          <NumberField
            label={t('settings_serviceChargePercent')}
            value={percent}
            onChange={setPercent}
            error={errors.service_charge_percent}
            suffix="%"
            disabled={!serviceCharge.allowed}
            hint={serviceCharge.allowed ? t('settings_serviceChargeHint') : t(serviceCharge.planName ? 'billing_serviceChargeLocked' : 'billing_serviceChargeLockedNoPlan', { plan: serviceCharge.planName ?? '' })}
          />
          <fieldset className="flex flex-col">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('settings_appliesTo')}</legend>
            {channels.map((channel) => (
              <Check key={channel} checked={serviceChannels.includes(channel)} onChange={(on) => setServiceChannels((list) => toggle(list, channel, on))} label={channelLabel(channel)} />
            ))}
            {errors.service_charge_channels && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{errors.service_charge_channels}</p>}
          </fieldset>
        </Card>
      </Section>
      <Section title={t('settings_placeFee')}>
        <Card className="flex flex-col gap-3">
          <NumberField label={t('settings_placeFeeAmount')} value={amount} onChange={setAmount} error={errors.place_fee_amount} suffix="so'm" />
          <Field label={t('settings_placeFeeMode')} error={errors.place_fee_mode}>
            {(props) => (
              <select {...props} value={mode} onChange={(event) => setMode(event.target.value as PlaceFeeMode)} className={`${fieldClass} sm:max-w-sm`}>
                {MODES.map((value) => (
                  <option key={value} value={value}>
                    {t(`settings_mode_${value}`)}
                  </option>
                ))}
              </select>
            )}
          </Field>
          <fieldset className="flex flex-col">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('settings_appliesTo')}</legend>
            {channels.map((channel) => (
              <Check key={channel} checked={placeChannels.includes(channel)} onChange={(on) => setPlaceChannels((list) => toggle(list, channel, on))} label={channelLabel(channel)} />
            ))}
            {errors.place_fee_channels && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{errors.place_fee_channels}</p>}
          </fieldset>
          <p className="text-footnote text-fg-muted">{t('settings_telegramNoFee')}</p>
        </Card>
      </Section>
      <FormError message={formError ?? errors.branch_id} />
      <div>
        <Button type="submit" loading={save.isPending}>
          {t('mgmt_save')}
        </Button>
      </div>
    </form>
  );
}
