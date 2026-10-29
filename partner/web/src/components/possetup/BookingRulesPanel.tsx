'use client';

import { useState, type FormEvent } from 'react';
import { Save } from 'lucide-react';
import { Card, FormError, Notice, Button } from '@rizqim/ui';
import { BOOKING_DEPOSIT_MODES, useBookingRules, useSaveBookingRules, type BookingDepositMode, type BookingRules, type BookingRulesPatch } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal } from '../management/fields';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// The table booking rules of a branch (P6-26; docs/API.md "Stol broni sozlamalari"): how long a booking holds a table, a room or a
// tapchan, how far ahead it can be made and an optional deposit for tables. The limits are the server's; the form checks the same
// ones so a typo does not need a round trip, and sends only what changed (the server merges key by key).

interface Draft {
  tableSlot: string;
  roomSlot: string;
  tapchanSlot: string;
  tableAdvance: string;
  roomAdvance: string;
  tapchanAdvance: string;
  maxDays: string;
  depositMode: BookingDepositMode;
  depositAmount: string;
  depositFromGuests: string;
  depositRequired: boolean;
}

const text = (value: number | null) => (value === null ? '' : String(value));

function draftOf(rules: BookingRules): Draft {
  return {
    tableSlot: String(rules.table_slot_minutes),
    roomSlot: text(rules.room_slot_minutes),
    tapchanSlot: text(rules.tapchan_slot_minutes),
    tableAdvance: String(rules.table_min_advance_minutes),
    roomAdvance: String(rules.room_min_advance_minutes),
    tapchanAdvance: String(rules.tapchan_min_advance_minutes),
    maxDays: text(rules.max_advance_days),
    depositMode: (BOOKING_DEPOSIT_MODES as readonly string[]).includes(rules.deposit_mode) ? rules.deposit_mode : 'off',
    depositAmount: String(Number(rules.deposit_amount)),
    depositFromGuests: String(rules.deposit_from_guests),
    depositRequired: rules.deposit_required_to_confirm,
  };
}

const SERVER_FIELDS = [
  'table_slot_minutes',
  'room_slot_minutes',
  'tapchan_slot_minutes',
  'table_min_advance_minutes',
  'room_min_advance_minutes',
  'tapchan_min_advance_minutes',
  'max_advance_days',
  'deposit_mode',
  'deposit_amount',
  'deposit_from_guests',
  'deposit_required_to_confirm',
];

export function BookingRulesPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const rules = useBookingRules(enabled ? branchId : null);
  if (!enabled) return <p role="note" className="rounded-control bg-info-soft px-3 py-2 text-footnote font-bold text-info-soft-fg">{t('hst_moduleOff')}</p>;
  return <Gate query={rules} count={3}>{(data) => <RulesForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

// A whole number in [min, max] typed as text; undefined when it is not one. An empty box is `null`.
function whole(value: string, min: number, max: number): number | null | undefined {
  const parsed = parseDecimal(value);
  if (parsed === null) return null;
  return parsed !== undefined && Number.isInteger(parsed) && parsed >= min && parsed <= max ? parsed : undefined;
}

function RulesForm({ branchId, data }: { branchId: number; data: BookingRules }) {
  const { t } = useI18n();
  const save = useSaveBookingRules();
  const { draft, setDraft, base, dirty } = useServerDraft(data, draftOf);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<string | null>(null);

  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const problems: Record<string, string> = {};
    const slotError = (min: number, max: number) => t('hst_rules_err_slot', { min, max });

    const tableSlot = whole(draft.tableSlot, 30, 480);
    if (tableSlot === undefined || tableSlot === null) problems.tableSlot = slotError(30, 480);
    const roomSlot = whole(draft.roomSlot, 30, 1440);
    if (roomSlot === undefined) problems.roomSlot = slotError(30, 1440);
    const tapchanSlot = whole(draft.tapchanSlot, 30, 1440);
    if (tapchanSlot === undefined) problems.tapchanSlot = slotError(30, 1440);
    const advance = {
      table: whole(draft.tableAdvance, 0, 1440),
      room: whole(draft.roomAdvance, 0, 1440),
      tapchan: whole(draft.tapchanAdvance, 0, 1440),
    };
    for (const [kind, value] of Object.entries(advance)) if (value === undefined || value === null) problems[`${kind}Advance`] = t('hst_rules_err_advance');
    const maxDays = whole(draft.maxDays, 1, 365);
    if (maxDays === undefined) problems.maxDays = t('hst_rules_err_days');
    const amount = parseDecimal(draft.depositAmount);
    // 0 - 99 999 999 with at most two decimals
    if (amount === undefined || amount === null || amount < 0 || amount > 99_999_999 || Math.abs(amount * 100 - Math.round(amount * 100)) > 1e-6) problems.depositAmount = t('hst_rules_err_amount');
    const fromGuests = whole(draft.depositFromGuests, 1, 1000);
    if (fromGuests === undefined || fromGuests === null) problems.depositFromGuests = t('hst_rules_err_guests');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    // Only what changed: a key left out keeps what the server has
    const changed = new Set(changedKeys(draft, base));
    const body: BookingRulesPatch = {};
    if (changed.has('tableSlot')) body.table_slot_minutes = tableSlot as number;
    if (changed.has('roomSlot')) body.room_slot_minutes = roomSlot ?? null;
    if (changed.has('tapchanSlot')) body.tapchan_slot_minutes = tapchanSlot ?? null;
    if (changed.has('tableAdvance')) body.table_min_advance_minutes = advance.table as number;
    if (changed.has('roomAdvance')) body.room_min_advance_minutes = advance.room as number;
    if (changed.has('tapchanAdvance')) body.tapchan_min_advance_minutes = advance.tapchan as number;
    if (changed.has('maxDays')) body.max_advance_days = maxDays ?? null;
    if (changed.has('depositMode')) body.deposit_mode = draft.depositMode;
    if (changed.has('depositAmount')) body.deposit_amount = (amount as number).toFixed(2);
    if (changed.has('depositFromGuests')) body.deposit_from_guests = fromGuests as number;
    if (changed.has('depositRequired')) body.deposit_required_to_confirm = draft.depositRequired;

    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(draftOf(answer));
          setSaved(t('hst_rules_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
      },
    );
  };

  const serverError = (field: string) => errors.fields[field];
  const depositOff = draft.depositMode === 'off';

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('hst_rules_intro')}</p>
      <Notice message={saved} />

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('hst_rules_slotTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('hst_rules_slotHint')}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <NumberField label={t('hst_rules_tableSlot')} hint={t('hst_rules_tableSlotHint')} value={draft.tableSlot} onChange={(tableSlot) => patch({ tableSlot })} inputMode="numeric" suffix="daq" error={local.tableSlot ?? serverError('table_slot_minutes')} />
          <NumberField label={t('hst_rules_roomSlot')} hint={t('hst_rules_slotEmptyHint')} value={draft.roomSlot} onChange={(roomSlot) => patch({ roomSlot })} inputMode="numeric" suffix="daq" error={local.roomSlot ?? serverError('room_slot_minutes')} />
          <NumberField label={t('hst_rules_tapchanSlot')} hint={t('hst_rules_slotEmptyHint')} value={draft.tapchanSlot} onChange={(tapchanSlot) => patch({ tapchanSlot })} inputMode="numeric" suffix="daq" error={local.tapchanSlot ?? serverError('tapchan_slot_minutes')} />
        </div>
      </Card>

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('hst_rules_advanceTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('hst_rules_advanceHint')}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <NumberField label={t('hst_rules_tableAdvance')} value={draft.tableAdvance} onChange={(tableAdvance) => patch({ tableAdvance })} inputMode="numeric" suffix="daq" error={local.tableAdvance ?? serverError('table_min_advance_minutes')} />
          <NumberField label={t('hst_rules_roomAdvance')} value={draft.roomAdvance} onChange={(roomAdvance) => patch({ roomAdvance })} inputMode="numeric" suffix="daq" error={local.roomAdvance ?? serverError('room_min_advance_minutes')} />
          <NumberField label={t('hst_rules_tapchanAdvance')} value={draft.tapchanAdvance} onChange={(tapchanAdvance) => patch({ tapchanAdvance })} inputMode="numeric" suffix="daq" error={local.tapchanAdvance ?? serverError('tapchan_min_advance_minutes')} />
          <NumberField label={t('hst_rules_maxDays')} hint={t('hst_rules_maxDaysHint')} value={draft.maxDays} onChange={(maxDays) => patch({ maxDays })} inputMode="numeric" suffix="kun" error={local.maxDays ?? serverError('max_advance_days')} />
        </div>
      </Card>

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('hst_rules_depositTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('hst_rules_depositHint')}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <SelectField
            label={t('hst_rules_depositMode')}
            value={draft.depositMode}
            onChange={(depositMode) => patch({ depositMode })}
            options={BOOKING_DEPOSIT_MODES.map((value) => ({ value, label: t(`hst_rules_mode_${value}`) }))}
            error={serverError('deposit_mode')}
          />
          <NumberField label={t('hst_rules_depositAmount')} value={draft.depositAmount} onChange={(depositAmount) => patch({ depositAmount })} suffix="so'm" disabled={depositOff} error={local.depositAmount ?? serverError('deposit_amount')} />
          <NumberField
            label={t('hst_rules_depositFromGuests')}
            hint={t('hst_rules_depositFromGuestsHint')}
            value={draft.depositFromGuests}
            onChange={(depositFromGuests) => patch({ depositFromGuests })}
            inputMode="numeric"
            suffix="kishi"
            disabled={depositOff}
            error={local.depositFromGuests ?? serverError('deposit_from_guests')}
          />
        </div>
        <Check checked={draft.depositRequired} onChange={(depositRequired) => patch({ depositRequired })} label={t('hst_rules_depositRequired')} hint={serverError('deposit_required_to_confirm') ?? t('hst_rules_depositRequiredHint')} disabled={depositOff} />
      </Card>

      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
          {t('hst_rules_save')}
        </Button>
      </div>
    </form>
  );
}
