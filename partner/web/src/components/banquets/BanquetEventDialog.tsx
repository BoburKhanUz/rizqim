'use client';

import { useMemo, useState, type FormEvent } from 'react';
import { Trash2 } from 'lucide-react';
import { Button, CardSkeletons, Dialog, FormError, IconButton, InlineError, formatMoney } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Check, NumberField, PhoneField, parseDecimal, phoneToE164, phoneToInput } from '../management/fields';
import { DateField, SelectField, TextField, TimeField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { EVENT_TYPES, SLOTS, newKey, type BanquetEvent, type EventInput, type EventType, type ExtraInput, type Slot } from './api';
import { useBanquetCatalog, useSaveEvent } from './hooks';

export type EventFormTarget = { kind: 'create'; day: string; hallId: number | null; slot: Slot } | { kind: 'edit'; event: BanquetEvent };

const FIELDS = [
  'customer_name',
  'customer_phone',
  'event_date',
  'slot',
  'start_time',
  'end_time',
  'hall_ids',
  'guests_guaranteed',
  'guests_minimum',
  'banquet_package_id',
  'quote_currency',
  'quote_rate',
  'note',
  'extras',
] as const;

interface FreeExtra {
  key: string;
  lineUuid?: string;
  name: string;
  price: string;
  perPerson: boolean;
  quantity: string;
}

// The event editor (P6-51/P6-52): who, what, when and where (one or several merged halls), the guests, the package and
// the extras. The server prices it (package × max(guaranteed, minimum) + hall rent + extras) and checks the halls are
// free (409 slot_taken); a frozen event keeps its prices and cannot change its package.
export function BanquetEventDialog({
  target,
  branchId,
  onClose,
  onSaved,
}: {
  target: EventFormTarget;
  branchId: number;
  onClose: () => void;
  onSaved: (message: string, event: BanquetEvent) => void;
}) {
  const { t, language } = useI18n();
  const catalog = useBanquetCatalog(branchId);
  const editing = target.kind === 'edit' ? target.event : null;

  const [name, setName] = useState(editing?.customer_name ?? '');
  const [phone, setPhone] = useState(phoneToInput(editing?.customer_phone));
  const [type, setType] = useState<EventType>(editing?.event_type ?? 'toy');
  const [title, setTitle] = useState(editing?.title ?? '');
  const [day, setDay] = useState(editing?.event_date ?? (target.kind === 'create' ? target.day : ''));
  const [slot, setSlot] = useState<Slot>(editing?.slot ?? (target.kind === 'create' ? target.slot : 'evening'));
  const [start, setStart] = useState(editing?.slot === 'custom' ? editing.start_time : '18:00');
  const [end, setEnd] = useState(editing?.slot === 'custom' ? editing.end_time : '23:00');
  const [halls, setHalls] = useState<number[]>(editing?.hall_ids ?? (target.kind === 'create' && target.hallId !== null ? [target.hallId] : []));
  const [guaranteed, setGuaranteed] = useState(editing ? String(editing.guests_guaranteed) : '');
  const [minimum, setMinimum] = useState(editing ? String(editing.guests_minimum) : '');
  const [packageId, setPackageId] = useState(editing?.package_id ? String(editing.package_id) : '');
  const [extras, setExtras] = useState<Record<number, string>>(() =>
    Object.fromEntries((editing?.lines ?? []).filter((line) => line.kind === 'extra' && line.extra_id !== null).map((line) => [line.extra_id as number, String(Number(line.quantity))])),
  );
  const [free, setFree] = useState<FreeExtra[]>(() =>
    (editing?.lines ?? [])
      .filter((line) => line.kind === 'extra' && line.extra_id === null)
      .map((line) => ({
        key: line.uuid,
        lineUuid: line.uuid,
        name: line.name,
        price: String(Number(line.unit_price)),
        perPerson: line.per_person,
        quantity: String(Number(line.quantity)),
      })),
  );
  const [currency, setCurrency] = useState(editing?.quote?.currency ?? '');
  const [rate, setRate] = useState(editing?.quote ? String(Number(editing.quote.rate)) : '');
  const [note, setNote] = useState(editing?.note ?? '');
  const [local, setLocal] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [key] = useState(newKey);
  const save = useSaveEvent();
  const busy = save.isPending;

  const packages = useMemo(() => catalog.data?.packages ?? [], [catalog.data]);
  const chosenPackage = useMemo(() => packages.find((p) => String(p.id) === packageId) ?? null, [packages, packageId]);
  const frozen = editing?.frozen ?? false;

  const submit = (event_: FormEvent) => {
    event_.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.customer_name = t('bq_err_name');
    if (day === '') problems.event_date = t('bq_err_date');
    if (halls.length === 0) problems.hall_ids = t('bq_err_halls');
    if (slot === 'custom' && (!/^\d{2}:\d{2}$/.test(start) || !/^\d{2}:\d{2}$/.test(end) || start === end)) problems.start_time = t('bq_err_times');
    const g = Number(guaranteed);
    if (guaranteed.trim() === '' || !Number.isInteger(g) || g < 0) problems.guests_guaranteed = t('bq_err_guests');
    const m = minimum.trim() === '' ? null : Number(minimum);
    if (m !== null && (!Number.isInteger(m) || m < 0)) problems.guests_minimum = t('bq_err_guests');
    const e164 = phoneToE164(phone);
    if (e164 === undefined) problems.customer_phone = t('mgmt_phoneInvalid');
    const quoteRate = parseDecimal(rate);
    if (currency.trim() !== '' && !/^[A-Za-z]{3}$/.test(currency.trim())) problems.quote_currency = t('bq_err_currency');
    if (currency.trim() !== '' && (quoteRate === undefined || quoteRate === null || quoteRate <= 0)) problems.quote_rate = t('bq_err_rate');
    const extraLines: ExtraInput[] = [];
    for (const [id, qty] of Object.entries(extras)) {
      const q = parseDecimal(qty);
      if (q === undefined || q === null || q <= 0) problems.extras = t('bq_err_quantity');
      else extraLines.push({ extra_id: Number(id), quantity: String(q) });
    }
    for (const line of free) {
      const price = parseDecimal(line.price);
      const q = parseDecimal(line.quantity);
      if (line.name.trim() === '' || price === undefined || price === null || price < 0 || q === undefined || q === null || q <= 0) problems.extras = t('bq_err_freeExtra');
      else
        extraLines.push({
          ...(line.lineUuid ? { line_uuid: line.lineUuid } : {}),
          name: line.name.trim(),
          unit_price: String(price),
          per_person: line.perPerson,
          quantity: String(q),
        });
    }
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const input: EventInput = {
      event_date: day,
      slot,
      start_time: slot === 'custom' ? start : null,
      end_time: slot === 'custom' ? end : null,
      hall_ids: halls,
      event_type: type,
      title: title.trim() === '' ? null : title.trim(),
      customer_name: name.trim(),
      customer_phone: e164 ?? null,
      guests_guaranteed: g,
      ...(m !== null ? { guests_minimum: m } : {}),
      ...(frozen ? {} : { banquet_package_id: packageId === '' ? null : Number(packageId) }),
      extras: extraLines,
      quote_currency: currency.trim() === '' ? null : currency.trim().toUpperCase(),
      quote_rate: currency.trim() === '' ? null : String(quoteRate),
      note: note.trim() === '' ? null : note.trim(),
    };
    save.mutate(
      {
        uuid: editing?.uuid ?? null,
        input: editing ? { ...input, version: editing.version } : { ...input, branch_id: branchId },
        key,
      },
      {
        onSuccess: (saved) => onSaved(editing ? t('bq_saved', { name: saved.customer_name }) : t('bq_held', { name: saved.customer_name }), saved),
        onError: (error) => setErrors(sortServerErrors(error, FIELDS, t)),
      },
    );
  };

  const error = (field: string) => local[field] ?? errors.fields[field];

  return (
    <Dialog open title={editing ? t('bq_editTitle') : t('bq_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      {catalog.isError ? (
        <InlineError title={t('loadErrorTitle')} message={errorText(catalog.error, t)} retryLabel={t('retry')} retrying={catalog.isFetching} onRetry={() => void catalog.refetch()} />
      ) : !catalog.data ? (
        <CardSkeletons count={1} lines={4} label={t('loading')} />
      ) : (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField label={t('bq_customer')} value={name} onChange={setName} maxLength={120} autoFocus error={error('customer_name')} />
            <PhoneField label={t('bq_phone')} value={phone} onChange={setPhone} error={error('customer_phone')} />
            <SelectField
              label={t('bq_type')}
              value={type}
              onChange={setType}
              options={EVENT_TYPES.map((value) => ({
                value,
                label: t(`bq_type_${value}`),
              }))}
            />
            <TextField label={t('bq_eventTitle')} value={title} onChange={setTitle} maxLength={160} />
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            <DateField label={t('bq_date')} value={day} onChange={setDay} error={error('event_date')} />
            <SelectField
              label={t('bq_slot')}
              value={slot}
              onChange={setSlot}
              options={SLOTS.map((value) => ({
                value,
                label: value === 'custom' ? t('bq_slot_custom') : `${t(`bq_slot_${value}`)} (${catalog.data.settings.slots[value].start}–${catalog.data.settings.slots[value].end})`,
              }))}
              error={error('slot')}
            />
          </div>
          {slot === 'custom' && (
            <div className="grid gap-4 sm:grid-cols-3">
              <TimeField label={t('bq_start')} value={start} onChange={setStart} error={error('start_time')} />
              <TimeField label={t('bq_end')} value={end} onChange={setEnd} error={error('end_time')} hint={end !== '' && end <= start ? t('bq_endNextDay') : undefined} />
            </div>
          )}
          <fieldset className="flex flex-col">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('bq_halls')}</legend>
            <p className="mb-1 text-footnote text-fg-muted">{t('bq_hallsHint')}</p>
            <div className="flex flex-wrap gap-x-4">
              {catalog.data.halls.map((hall) => (
                <Check
                  key={hall.hall_id}
                  checked={halls.includes(hall.hall_id)}
                  disabled={!hall.is_active && !halls.includes(hall.hall_id)}
                  onChange={(on) => setHalls(on ? [...halls, hall.hall_id] : halls.filter((id) => id !== hall.hall_id))}
                  label={hall.hall_name}
                  hint={slot !== 'custom' && hall.rates[slot] ? formatMoney(hall.rates[slot] as string, language) : undefined}
                />
              ))}
            </div>
            {error('hall_ids') && (
              <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                {error('hall_ids')}
              </p>
            )}
          </fieldset>
          <div className="grid gap-4 sm:grid-cols-3">
            <SelectField
              label={t('bq_package')}
              value={packageId}
              onChange={setPackageId}
              disabled={frozen}
              hint={
                frozen
                  ? t('bq_packageFrozen')
                  : chosenPackage
                    ? t('bq_packageHint', {
                        price: formatMoney(chosenPackage.per_person_price, language),
                        n: chosenPackage.minimum_guests,
                      })
                    : undefined
              }
              options={[
                { value: '', label: t('bq_noPackage') },
                ...packages.map((p) => ({
                  value: String(p.id),
                  label: p.name,
                })),
              ]}
              error={error('banquet_package_id')}
            />
            <TextField label={t('bq_guaranteed')} value={guaranteed} inputMode="numeric" onChange={setGuaranteed} error={error('guests_guaranteed')} />
            <TextField label={t('bq_minimum')} hint={t('bq_minimumHint')} value={minimum} inputMode="numeric" onChange={setMinimum} error={error('guests_minimum')} />
          </div>

          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('bq_extras')}</legend>
            {catalog.data.extras.map((extra) => {
              const on = extra.id in extras;
              return (
                <div key={extra.id} className="flex flex-wrap items-end gap-3">
                  <Check
                    checked={on}
                    onChange={(checked) =>
                      setExtras((current) => {
                        const next = { ...current };
                        if (checked) next[extra.id] = '1';
                        else delete next[extra.id];
                        return next;
                      })
                    }
                    label={extra.name}
                    hint={`${formatMoney(extra.price, language)} · ${extra.pricing === 'per_person' ? t('bq_perPerson') : t('bq_perEvent')}`}
                  />
                  {on && (
                    <NumberField
                      label={t('bq_quantityOf', { name: extra.name })}
                      value={extras[extra.id] ?? ''}
                      onChange={(value) =>
                        setExtras((current) => ({
                          ...current,
                          [extra.id]: value,
                        }))
                      }
                    />
                  )}
                </div>
              );
            })}
            {free.map((line, index) => (
              <div key={line.key} className="flex flex-wrap items-end gap-3 rounded-control border border-line p-2">
                <TextField label={t('bq_freeName')} value={line.name} onChange={(value) => setFree(free.map((row, i) => (i === index ? { ...row, name: value } : row)))} maxLength={255} />
                <NumberField label={t('bq_freePrice')} value={line.price} suffix="so'm" onChange={(value) => setFree(free.map((row, i) => (i === index ? { ...row, price: value } : row)))} />
                <NumberField label={t('bq_quantity')} value={line.quantity} onChange={(value) => setFree(free.map((row, i) => (i === index ? { ...row, quantity: value } : row)))} />
                <Check checked={line.perPerson} onChange={(value) => setFree(free.map((row, i) => (i === index ? { ...row, perPerson: value } : row)))} label={t('bq_perPerson')} />
                <IconButton icon={Trash2} label={t('bq_removeExtra', { name: line.name || '—' })} onClick={() => setFree(free.filter((_, i) => i !== index))} />
              </div>
            ))}
            <div>
              <Button
                variant="secondary"
                onClick={() =>
                  setFree([
                    ...free,
                    {
                      key: newKey(),
                      name: '',
                      price: '',
                      perPerson: false,
                      quantity: '1',
                    },
                  ])
                }
              >
                {t('bq_addFreeExtra')}
              </Button>
            </div>
            {error('extras') && (
              <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
                {error('extras')}
              </p>
            )}
          </fieldset>

          <div className="grid gap-4 sm:grid-cols-3">
            <TextField label={t('bq_quoteCurrency')} hint={t('bq_quoteHint')} value={currency} onChange={setCurrency} maxLength={3} error={error('quote_currency')} />
            <NumberField label={t('bq_quoteRate')} value={rate} onChange={setRate} suffix="so'm" error={error('quote_rate')} />
          </div>
          <TextField label={t('bq_note')} value={note} onChange={setNote} maxLength={2000} error={error('note')} />
          <FormError message={errors.form} />
          <div className="flex flex-wrap justify-end gap-3">
            <Button variant="secondary" onClick={onClose} disabled={busy}>
              {t('mgmt_cancel')}
            </Button>
            <Button type="submit" loading={busy}>
              {editing ? t('mgmt_save') : t('bq_hold')}
            </Button>
          </div>
        </form>
      )}
    </Dialog>
  );
}
