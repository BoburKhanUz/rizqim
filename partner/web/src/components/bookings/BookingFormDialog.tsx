'use client';

import { useState, type FormEvent } from 'react';
import { Button, Dialog, FormError } from '@rizqim/ui';
import { BOOKING_EVENT_TYPES, BOOKING_SOURCES, isStaffApiError, useCreateBooking, useUpdateBooking, type Booking, type BookingCalendar, type BookingPatch } from '@rizqim/staff';
import { addDays, inZone, localDateTime } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal, PhoneField, phoneToE164, phoneToInput } from '../management/fields';
import { DateField, SelectField, TextField, TimeField } from '../pos/fields';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';

export type BookingFormTarget = { kind: 'create'; day: string; roomId: number | null } | { kind: 'edit'; booking: Booking };

const FIELDS = ['dining_table_id', 'extra_table_ids', 'starts_at', 'ends_at', 'guests_planned', 'event_type', 'contact_name', 'contact_phone', 'source', 'min_spend', 'note', 'branch_id'];
const FIELD_ALIASES: Record<string, string> = { dining_table_id: 'room', starts_at: 'start', ends_at: 'end', guests_planned: 'guests', contact_name: 'name', contact_phone: 'phone', event_type: 'event', min_spend: 'minSpend' };

// The conflicts a 409 `booking_conflict` names: the bookings that hold the room at that time
function conflictsOf(error: unknown): Array<{ uuid: string; status: string; starts_at: string; ends_at: string }> {
  if (!isStaffApiError(error) || error.reason !== 'booking_conflict') return [];
  const data = (error.body as { data?: { conflicts?: unknown } } | null)?.data;
  return Array.isArray(data?.conflicts) ? (data.conflicts as Array<{ uuid: string; status: string; starts_at: string; ends_at: string }>) : [];
}

// Make or change a booking. The room and the time are checked by the server (409 `booking_conflict` names the bookings in the
// way); a time that ends before it starts runs over midnight into the next day.
export function BookingFormDialog({
  target,
  branchId,
  rooms,
  timeZone,
  onClose,
  onSaved,
}: {
  target: BookingFormTarget;
  branchId: number;
  rooms: BookingCalendar['rooms'];
  timeZone: string;
  onClose: () => void;
  onSaved: (message: string) => void;
}) {
  const { t } = useI18n();
  const editing = target.kind === 'edit' ? target.booking : null;
  const starts = editing ? inZone(editing.starts_at, timeZone) : null;
  const ends = editing ? inZone(editing.ends_at, timeZone) : null;

  const [roomId, setRoomId] = useState(editing?.dining_table_id ? String(editing.dining_table_id) : target.kind === 'create' && target.roomId ? String(target.roomId) : '');
  const [extra, setExtra] = useState<number[]>(editing ? editing.table_ids.filter((id) => id !== editing.dining_table_id) : []);
  const [day, setDay] = useState(starts?.day ?? (target.kind === 'create' ? target.day : ''));
  const [start, setStart] = useState(starts?.time ?? '18:00');
  const [end, setEnd] = useState(ends?.time ?? '');
  const [guests, setGuests] = useState(editing ? String(editing.guests_planned) : '');
  const [event, setEvent] = useState<string>(editing?.event_type ?? 'other');
  const [name, setName] = useState(editing?.contact_name ?? '');
  const [phone, setPhone] = useState(phoneToInput(editing?.contact_phone));
  const [source, setSource] = useState<string>(editing?.source ?? 'phone');
  const [minSpend, setMinSpend] = useState(editing?.min_spend ? String(Number(editing.min_spend)) : '');
  const [note, setNote] = useState(editing?.note ?? '');
  const [confirm, setConfirm] = useState(false);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [conflicts, setConflicts] = useState<ReturnType<typeof conflictsOf>>([]);
  const create = useCreateBooking();
  const update = useUpdateBooking();
  const busy = create.isPending || update.isPending;

  const overnight = end !== '' && end <= start;
  const submit = (event_: FormEvent) => {
    event_.preventDefault();
    const problems: Record<string, string> = {};
    if (name.trim() === '') problems.name = t('book_err_name');
    if (day === '') problems.start = t('book_err_date');
    if (!/^\d{2}:\d{2}$/.test(start)) problems.start = t('book_err_start');
    if (end !== '' && end === start) problems.end = t('book_err_endSame');
    const guestCount = guests.trim() === '' ? null : Number(guests);
    if (guestCount !== null && (!Number.isInteger(guestCount) || guestCount < 1)) problems.guests = t('book_err_guests');
    const e164 = phoneToE164(phone);
    if (e164 === undefined) problems.phone = t('mgmt_phoneInvalid');
    const spend = parseDecimal(minSpend);
    if (spend === undefined || (spend !== null && spend < 0)) problems.minSpend = t('mgmt_numberInvalid');
    setLocal(problems);
    setErrors(NO_ERRORS);
    setConflicts([]);
    if (Object.keys(problems).length > 0) return;

    const startsAt = localDateTime(day, start);
    const endsAt = end === '' ? null : localDateTime(overnight ? addDays(day, 1) : day, end);
    const fields = {
      dining_table_id: roomId === '' ? null : Number(roomId),
      extra_table_ids: extra,
      starts_at: startsAt,
      ends_at: endsAt,
      guests_planned: guestCount,
      event_type: event as (typeof BOOKING_EVENT_TYPES)[number],
      contact_name: name.trim(),
      contact_phone: e164 ?? null,
      source: source as (typeof BOOKING_SOURCES)[number],
      min_spend: spend,
      note: note.trim() === '' ? null : note.trim(),
    };
    const options = {
      onError: (error: unknown) => {
        setConflicts(conflictsOf(error));
        setErrors(sortServerErrors(error, FIELDS, t, FIELD_ALIASES));
      },
    };
    if (editing) {
      const patch: BookingPatch = { ...fields, version: editing.version };
      update.mutate({ uuid: editing.uuid, patch }, { ...options, onSuccess: () => onSaved(t('book_saved', { name: name.trim() })) });
    } else {
      create.mutate({ branch_id: branchId, ...fields, confirm }, { ...options, onSuccess: () => onSaved(t('book_created', { name: name.trim() })) });
    }
  };

  const error = (field: string) => local[field] ?? errors.fields[field];
  const others = rooms.filter((room) => String(room.id) !== roomId);

  return (
    <Dialog open title={editing ? t('book_editTitle') : t('book_newTitle')} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <SelectField
          label={t('book_room')}
          hint={t('book_roomHint')}
          value={roomId}
          onChange={setRoomId}
          options={[{ value: '', label: t('book_noRoom') }, ...rooms.map((room) => ({ value: String(room.id), label: `${room.name}${room.hall_name ? ` (${room.hall_name})` : ''}` }))]}
          error={error('room')}
        />
        <div className="grid gap-4 sm:grid-cols-3">
          <DateField label={t('book_date')} value={day} onChange={setDay} error={error('start')} />
          <TimeField label={t('book_start')} value={start} onChange={setStart} />
          <TimeField label={t('book_end')} value={end} onChange={setEnd} error={error('end')} hint={overnight ? t('book_endNextDay') : t('book_endHint')} />
        </div>
        {roomId !== '' && others.length > 0 && (
          <fieldset className="flex flex-col">
            <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('book_extraRooms')}</legend>
            <p className="mb-1 text-footnote text-fg-muted">{t('book_extraRoomsHint')}</p>
            <div className="flex flex-wrap gap-x-4">
              {others.map((room) => (
                <Check key={room.id} checked={extra.includes(room.id)} onChange={(on) => setExtra(on ? [...extra, room.id] : extra.filter((id) => id !== room.id))} label={room.name} />
              ))}
            </div>
            {errors.fields.extra_table_ids && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{errors.fields.extra_table_ids}</p>}
          </fieldset>
        )}
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('book_name')} value={name} onChange={setName} maxLength={100} autoFocus error={error('name')} />
          <PhoneField label={t('book_phone')} value={phone} onChange={setPhone} error={error('phone')} />
          <TextField label={t('book_guests')} value={guests} inputMode="numeric" onChange={setGuests} error={error('guests')} />
          <NumberField label={t('book_minSpend')} hint={t('book_minSpendHint')} value={minSpend} onChange={setMinSpend} suffix="so'm" error={error('minSpend')} />
          <SelectField label={t('book_event')} value={event} onChange={setEvent} options={BOOKING_EVENT_TYPES.map((value) => ({ value, label: labelFor(t, 'book_ev_', value) }))} error={error('event')} />
          <SelectField label={t('book_source')} value={source} onChange={setSource} options={BOOKING_SOURCES.map((value) => ({ value, label: labelFor(t, 'book_src_', value) }))} error={error('source')} />
        </div>
        <TextField label={t('book_note')} value={note} onChange={setNote} maxLength={500} error={error('note')} />
        {!editing && <Check checked={confirm} onChange={setConfirm} label={t('book_confirmNow')} hint={t('book_confirmNowHint')} />}

        {conflicts.length > 0 && (
          <div role="alert" className="rounded-card bg-danger-soft px-4 py-3 text-footnote text-danger-soft-fg">
            <p className="font-bold">{t('book_conflicts')}</p>
            <ul className="mt-1 list-disc pl-5">
              {conflicts.map((conflict) => (
                <li key={conflict.uuid}>
                  {inZone(conflict.starts_at, timeZone)?.time ?? '—'}–{inZone(conflict.ends_at, timeZone)?.time ?? '—'} ({labelFor(t, 'book_st_', conflict.status)})
                </li>
              ))}
            </ul>
          </div>
        )}
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
