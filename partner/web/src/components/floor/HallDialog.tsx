'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog } from '@rizqim/ui';
import { useCreateHall, useUpdateHall, type Hall } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check } from '../management/fields';
import { FormError } from '../management/Notice';
import { TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// A hall: its name, its place in the list, and whether it is open (a seasonal zone is switched off out of season).
export function HallDialog({ branchId, hall, onClose, onSaved }: { branchId: number; hall: Hall | null; onClose: () => void; onSaved: (hall: Hall) => void }) {
  const { t } = useI18n();
  const [name, setName] = useState(hall?.name ?? '');
  const [sortOrder, setSortOrder] = useState(hall ? String(hall.sort_order) : '');
  const [active, setActive] = useState(hall?.is_active ?? true);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateHall();
  const update = useUpdateHall();
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('floor_err_name');
    const order = sortOrder.trim() === '' ? null : Number(sortOrder);
    if (order !== null && (!Number.isInteger(order) || order < 0)) problems.sort_order = t('mgmt_integerInvalid');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, ['name', 'sort_order', 'is_active', 'branch_id'], t)) };
    if (hall) update.mutate({ id: hall.id, patch: { name: name.trim(), ...(order !== null ? { sort_order: order } : {}), is_active: active } }, options);
    else create.mutate({ branch_id: branchId, name: name.trim(), ...(order !== null ? { sort_order: order } : {}), is_active: active }, options);
  };

  return (
    <Dialog open title={hall ? t('floor_hall_editTitle') : t('floor_hall_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('floor_hall_name')} value={name} onChange={setName} maxLength={100} autoFocus error={local.name ?? errors.fields.name} />
        <TextField label={t('floor_hall_order')} hint={t('floor_hall_orderHint')} value={sortOrder} inputMode="numeric" onChange={setSortOrder} error={local.sort_order ?? errors.fields.sort_order} />
        <Check checked={active} onChange={setActive} label={t('floor_hall_activeLabel')} hint={t('floor_hall_activeHint')} />
        <FormError message={errors.form} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('mgmt_save')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
