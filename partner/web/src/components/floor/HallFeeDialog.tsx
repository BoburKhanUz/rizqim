'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { useDeleteHallServiceFee, useFeature, useSaveServiceFee, type Hall, type PlaceFeeMode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { NumberField, parseDecimal } from '../management/fields';
import { SelectField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// A hall's own service charge and place fee (VIP 15%, a self-service zone 0%). Without its own, the branch's apply.
export function HallFeeDialog({ hall, onClose, onSaved }: { hall: Hall; onClose: () => void; onSaved: () => void }) {
  const { t } = useI18n();
  const own = hall.service_fee;
  const [percent, setPercent] = useState(own ? String(Number(own.service_charge_percent)) : '');
  const [amount, setAmount] = useState(own ? String(Number(own.place_fee_amount)) : '0');
  const [mode, setMode] = useState<PlaceFeeMode>(own?.place_fee_mode === 'per_table' ? 'per_table' : 'per_person');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const save = useSaveServiceFee();
  const remove = useDeleteHallServiceFee();
  const busy = save.isPending || remove.isPending;
  // P4-21: a service charge above 0 is the plan's `service_charge`; without it only the place fee is set
  const serviceCharge = useFeature('service_charge');

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const parsedPercent = parseDecimal(percent);
    const percentValue = !serviceCharge.allowed && parsedPercent === null ? 0 : parsedPercent;
    const amountValue = parseDecimal(amount);
    const problems: Record<string, string> = {};
    if (percentValue === undefined || percentValue === null) problems.service_charge_percent = t('mgmt_numberInvalid');
    if (amountValue === undefined || amountValue === null) problems.place_fee_amount = t('mgmt_numberInvalid');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    save.mutate(
      { hall_id: hall.id, branch_id: hall.branch_id, service_charge_percent: percentValue as number, place_fee_amount: amountValue as number, place_fee_mode: mode },
      { onSuccess: onSaved, onError: (error) => setErrors(sortServerErrors(error, ['service_charge_percent', 'place_fee_amount', 'place_fee_mode', 'hall_id'], t)) },
    );
  };

  return (
    <Dialog open title={t('floor_fee_title', { name: hall.name })} description={t('floor_fee_intro')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <NumberField
          label={t('floor_fee_percent')}
          hint={serviceCharge.allowed ? t('floor_fee_percentHint') : t(serviceCharge.planName ? 'billing_serviceChargeLocked' : 'billing_serviceChargeLockedNoPlan', { plan: serviceCharge.planName ?? '' })}
          value={percent}
          onChange={setPercent}
          suffix="%"
          disabled={!serviceCharge.allowed}
          error={local.service_charge_percent ?? errors.fields.service_charge_percent}
        />
        <NumberField label={t('floor_fee_place')} value={amount} onChange={setAmount} suffix="so'm" error={local.place_fee_amount ?? errors.fields.place_fee_amount} />
        <SelectField
          label={t('floor_fee_mode')}
          value={mode}
          onChange={setMode}
          options={[
            { value: 'per_person', label: t('floor_fee_perPerson') },
            { value: 'per_table', label: t('floor_fee_perTable') },
          ]}
          error={errors.fields.place_fee_mode}
        />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-between gap-3">
          {own ? (
            <Button
              variant="ghost"
              onClick={() => remove.mutate(hall.id, { onSuccess: onSaved, onError: (error) => setErrors({ fields: {}, form: errorText(error, t) }) })}
              disabled={busy}
            >
              {t('floor_fee_useBranch')}
            </Button>
          ) : (
            <span />
          )}
          <div className="flex gap-3">
            <Button variant="secondary" onClick={onClose} disabled={busy}>
              {t('mgmt_cancel')}
            </Button>
            <Button type="submit" loading={save.isPending} disabled={remove.isPending}>
              {t('mgmt_save')}
            </Button>
          </div>
        </div>
      </form>
    </Dialog>
  );
}
