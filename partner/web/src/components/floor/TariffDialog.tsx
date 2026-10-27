'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { DAY_TYPES, TABLE_KINDS, useCreateRoomTariff, useUpdateRoomTariff, type DayType, type RoomTariff, type TableKind } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { NumberField, parseDecimal } from '../management/fields';
import { DateField, SelectField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';

type Scope = 'room' | 'kind';
const FIELDS = ['branch_id', 'dining_table_id', 'hall_id', 'table_kind', 'day_type', 'price', 'valid_from', 'valid_to'];

// A room tariff: for one room or for a kind of place (in one hall or all), on a day type, for a range of dates.
export function TariffDialog({
  branchId,
  tariff,
  places,
  halls,
  onClose,
  onSaved,
}: {
  branchId: number;
  tariff: RoomTariff | null;
  places: Array<{ id: number; name: string; kind: string; hallName: string }>;
  halls: Array<{ id: number; name: string }>;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { t } = useI18n();
  const [scope, setScope] = useState<Scope>(tariff?.dining_table_id != null ? 'room' : 'kind');
  const [tableId, setTableId] = useState(tariff?.dining_table_id != null ? String(tariff.dining_table_id) : '');
  const [kind, setKind] = useState<TableKind>((tariff?.table_kind as TableKind | null) ?? 'room');
  const [hallId, setHallId] = useState(tariff?.hall_id != null ? String(tariff.hall_id) : '');
  const [dayType, setDayType] = useState<DayType>((tariff?.day_type as DayType | undefined) ?? 'weekday');
  const [price, setPrice] = useState(tariff ? String(Number(tariff.price)) : '');
  const [from, setFrom] = useState(tariff?.valid_from ?? '');
  const [to, setTo] = useState(tariff?.valid_to ?? '');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateRoomTariff();
  const update = useUpdateRoomTariff();
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    const amount = parseDecimal(price);
    if (amount === undefined || amount === null || amount < 0) problems.price = price.trim() === '' ? t('floor_err_price') : t('mgmt_numberInvalid');
    if (scope === 'room' && tableId === '') problems.dining_table_id = t('floor_err_room');
    if (from !== '' && to !== '' && from > to) problems.valid_to = t('floor_err_dates');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    // A tariff is for ONE room or for a kind of place: the other half is sent as null so an edit can switch between them
    const where = scope === 'room' ? { dining_table_id: Number(tableId), table_kind: null, hall_id: null } : { dining_table_id: null, table_kind: kind, hall_id: hallId === '' ? null : Number(hallId) };
    const fields = { ...where, day_type: dayType, price: amount as number, valid_from: from || null, valid_to: to || null };
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t)) };
    if (tariff) update.mutate({ id: tariff.id, patch: fields }, options);
    else create.mutate({ branch_id: branchId, ...fields }, options);
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={tariff ? t('floor_tariff_editTitle') : t('floor_tariff_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <SelectField
          label={t('floor_tariff_for')}
          value={scope}
          onChange={setScope}
          options={[
            { value: 'kind', label: t('floor_tariff_forKind') },
            { value: 'room', label: t('floor_tariff_forRoom') },
          ]}
        />
        {scope === 'room' ? (
          <SelectField
            label={t('floor_tariff_room')}
            value={tableId}
            onChange={setTableId}
            options={[{ value: '', label: t('floor_tariff_chooseRoom') }, ...places.map((place) => ({ value: String(place.id), label: `${place.name} (${place.hallName})` }))]}
            error={error('dining_table_id')}
          />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField label={t('floor_table_kind')} value={kind} onChange={setKind} options={TABLE_KINDS.map((value) => ({ value, label: labelFor(t, 'floor_kind_', value) }))} error={error('table_kind')} />
            <SelectField label={t('floor_table_hall')} value={hallId} onChange={setHallId} options={[{ value: '', label: t('floor_tariff_allHalls') }, ...halls.map((hall) => ({ value: String(hall.id), label: hall.name }))]} error={error('hall_id')} />
          </div>
        )}
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label={t('floor_tariff_day')} value={dayType} onChange={setDayType} options={DAY_TYPES.map((value) => ({ value, label: labelFor(t, 'floor_day_', value) }))} error={error('day_type')} />
          <NumberField label={t('floor_tariff_price')} hint={t('floor_tariff_priceHint')} value={price} onChange={setPrice} suffix="so'm" error={error('price')} />
          <DateField label={t('floor_tariff_from')} value={from} onChange={setFrom} error={error('valid_from')} />
          <DateField label={t('floor_tariff_to')} value={to} onChange={setTo} error={error('valid_to')} hint={t('floor_tariff_dateHint')} />
        </div>
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
