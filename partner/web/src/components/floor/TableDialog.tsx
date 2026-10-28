'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { MIN_SPEND_MODES, TABLE_KINDS, useCreateDiningTable, useUpdateDiningTable, type DiningTable, type Hall, type MinSpendMode, type TableKind } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal } from '../management/fields';
import { SelectField, TextField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';
import { spotForNewPlace, toLayoutInput } from './layoutModel';

const FIELDS = ['hall_id', 'name', 'kind', 'seats', 'sort_order', 'is_active', 'min_spend', 'min_spend_mode'];

// A place: a table, a room (kabinka) or a tapchan, in a hall, with its seats and the minimum spend the guest has to reach.
export function TableDialog({ halls, hall, table, onClose, onSaved }: { halls: Hall[]; hall: Hall; table: DiningTable | null; onClose: () => void; onSaved: (table: DiningTable) => void }) {
  const { t } = useI18n();
  const [hallId, setHallId] = useState(String(table?.hall_id ?? hall.id));
  const [name, setName] = useState(table?.name ?? '');
  const [kind, setKind] = useState<TableKind>((table?.kind as TableKind | undefined) ?? 'table');
  const [seats, setSeats] = useState(table?.seats ? String(table.seats) : '');
  const [minSpend, setMinSpend] = useState(table?.min_spend ? String(Number(table.min_spend)) : '');
  const [mode, setMode] = useState<MinSpendMode>((table?.min_spend_mode as MinSpendMode | undefined) ?? 'per_table');
  const [active, setActive] = useState(table?.is_active ?? true);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const create = useCreateDiningTable();
  const update = useUpdateDiningTable();
  const busy = create.isPending || update.isPending;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('floor_err_name');
    const seatCount = seats.trim() === '' ? null : Number(seats);
    if (seatCount !== null && (!Number.isInteger(seatCount) || seatCount < 1)) problems.seats = t('floor_err_seats');
    const spend = parseDecimal(minSpend);
    if (spend === undefined || (spend !== null && spend < 0)) problems.min_spend = t('mgmt_numberInvalid');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    const fields = {
      hall_id: Number(hallId),
      name: name.trim(),
      kind,
      seats: seatCount,
      min_spend: spend,
      min_spend_mode: mode,
      is_active: active,
    };
    const options = { onSuccess: onSaved, onError: (error: unknown) => setErrors(sortServerErrors(error, FIELDS, t)) };
    if (table) update.mutate({ id: table.id, patch: fields }, options);
    else {
      // A new place stands in the first free spot of its hall's map: the till draws the map only when every place has a layout
      const spot = spotForNewPlace(kind, halls.find((item) => item.id === fields.hall_id)?.tables ?? []);
      create.mutate({ ...fields, layout: toLayoutInput(spot) }, options);
    }
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={table ? t('floor_table_editTitle') : t('floor_table_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <TextField label={t('floor_table_name')} value={name} onChange={setName} maxLength={50} autoFocus error={error('name')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label={t('floor_table_kind')} value={kind} onChange={setKind} options={TABLE_KINDS.map((value) => ({ value, label: labelFor(t, 'floor_kind_', value) }))} error={error('kind')} />
          <SelectField label={t('floor_table_hall')} value={hallId} onChange={setHallId} options={halls.map((item) => ({ value: String(item.id), label: item.name }))} error={error('hall_id')} />
          <TextField label={t('floor_table_seats')} value={seats} inputMode="numeric" onChange={setSeats} error={error('seats')} />
          <NumberField label={t('floor_table_minSpendLabel')} hint={t('floor_table_minSpendHint')} value={minSpend} onChange={setMinSpend} suffix="so'm" error={error('min_spend')} />
        </div>
        <SelectField label={t('floor_table_minSpendMode')} value={mode} onChange={setMode} options={MIN_SPEND_MODES.map((value) => ({ value, label: labelFor(t, 'floor_mode_', value) }))} error={error('min_spend_mode')} />
        <Check checked={active} onChange={setActive} label={t('floor_table_activeLabel')} />
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
