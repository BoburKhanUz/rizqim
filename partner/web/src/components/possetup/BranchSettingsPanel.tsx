'use client';

import { useState, type FormEvent } from 'react';
import { Save, X } from 'lucide-react';
import { Button, Card, formatDate } from '@rizqim/ui';
import { ALERT_KEYS, POS_MODES, useBranchPosSettings, useSaveBranchPosSettings, useStoreDevices, type AlertKey, type BranchPosSettings, type BranchPosSettingsPatch, type PosMode } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField, parseDecimal } from '../management/fields';
import { FormError, Notice } from '../management/Notice';
import { Gate } from '../pos/Gate';
import { DateField, SelectField, TimeField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor, localDate } from '../reports/format';

// Each alert: whether it is on, its value and the limits the server keeps (docs/API.md "Filial POS sozlamalari")
const ALERTS: Record<AlertKey, { min: number; max: number; integer: boolean; fallback: string; suffix: string }> = {
  open_room_hours: { min: 1, max: 72, integer: true, fallback: '6', suffix: 'soat' },
  void_amount: { min: 0, max: 100_000_000, integer: false, fallback: '200000', suffix: "so'm" },
  discount_percent: { min: 0, max: 100, integer: false, fallback: '10', suffix: '%' },
  unaccepted_online_minutes: { min: 1, max: 120, integer: true, fallback: '3', suffix: 'daq' },
  cash_out_amount: { min: 0, max: 100_000_000, integer: false, fallback: '500000', suffix: "so'm" },
};

interface Draft {
  mode: PosMode;
  dayStart: string;
  printingDevice: string;
  lock: string;
  queue: boolean;
  // The biggest discount (%) a cashier gives without a manager; empty: always a manager's PIN
  cashierDiscount: string;
  alerts: Record<AlertKey, { on: boolean; value: string }>;
  slot: string;
  holidays: string[];
}

const textOf = (value: string | number | null | undefined, fallback: string) => (value === null || value === undefined ? fallback : String(Number(value)));

function draftOf(settings: BranchPosSettings): Draft {
  const pos = settings.pos_settings;
  const alerts = Object.fromEntries(ALERT_KEYS.map((key) => [key, { on: pos.alerts[key] !== null && pos.alerts[key] !== undefined, value: textOf(pos.alerts[key], ALERTS[key].fallback) }])) as Draft['alerts'];
  return {
    mode: (POS_MODES as readonly string[]).includes(pos.mode) ? (pos.mode as PosMode) : 'hybrid',
    dayStart: settings.business_day_starts_at,
    printingDevice: pos.printing_device_id ?? '',
    lock: String(pos.screen_lock_minutes),
    queue: pos.queue_numbers,
    cashierDiscount: textOf(pos.cashier_discount_max_percent, ''),
    alerts,
    slot: pos.booking_slot_minutes === null ? '' : String(pos.booking_slot_minutes),
    holidays: [...pos.holidays].sort(),
  };
}

const SERVER_FIELDS = [
  'business_day_starts_at',
  'pos_settings.mode',
  'pos_settings.printing_device_id',
  'pos_settings.screen_lock_minutes',
  'pos_settings.queue_numbers',
  'pos_settings.cashier_discount_max_percent',
  'pos_settings.booking_slot_minutes',
  'pos_settings.holidays',
  ...ALERT_KEYS.map((key) => `pos_settings.alerts.${key}`),
];

// The branch's POS settings: the till's mode, when the business day starts, the printing till, the screen lock, queue numbers,
// the alerts the owner is told about, the holidays and how long an unfinished booking holds a room. The form is shown from
// the cached settings while they are read again, and the holidays are also edited on the floor's calendar: newer settings move
// under the edits (useServerDraft) and only what was changed is sent (the server merges the settings key by key).
export function BranchSettingsPanel({ branchId }: { branchId: number }) {
  const settings = useBranchPosSettings(branchId);
  return <Gate query={settings} count={3}>{(data) => <SettingsForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

function SettingsForm({ branchId, data }: { branchId: number; data: BranchPosSettings }) {
  const { t, language } = useI18n();
  const devices = useStoreDevices();
  const save = useSaveBranchPosSettings();
  const { draft, setDraft, base, dirty } = useServerDraft(data, draftOf);
  const [adding, setAdding] = useState('');
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<string | null>(null);

  const terminals = (devices.data ?? []).filter((device) => device.type === 'pos' && (device.branch_id === null || device.branch_id === branchId));
  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const problems: Record<string, string> = {};
    if (!/^\d{2}:\d{2}$/.test(draft.dayStart)) problems.dayStart = t('pos_err_time');
    else if (Number(draft.dayStart.slice(0, 2)) > 11) problems.dayStart = t('pos_set_dayStartRange');
    const lock = Number(draft.lock);
    if (draft.lock.trim() === '' || !Number.isInteger(lock) || lock < 0 || lock > 240) problems.lock = t('pos_set_lockRange');
    const slot = draft.slot.trim() === '' ? null : Number(draft.slot);
    if (slot !== null && (!Number.isInteger(slot) || slot < 30 || slot > 1440)) problems.slot = t('pos_set_slotRange');
    const discount = parseDecimal(draft.cashierDiscount);
    // 0-100 with at most two decimals
    if (discount === undefined || (discount !== null && (discount < 0 || discount > 100 || Math.abs(discount * 100 - Math.round(discount * 100)) > 1e-6))) problems.cashierDiscount = t('pos_set_cashierDiscountRange');
    const alerts: Record<string, number | null> = {};
    for (const key of ALERT_KEYS) {
      const entry = draft.alerts[key];
      if (!entry.on) {
        alerts[key] = null;
        continue;
      }
      const rule = ALERTS[key];
      const value = parseDecimal(entry.value);
      if (value === undefined || value === null || value < rule.min || value > rule.max || (rule.integer && !Number.isInteger(value))) problems[`alert_${key}`] = t('pos_set_alertRange', { min: rule.min, max: rule.max });
      else alerts[key] = value;
    }
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    // Only what changed: a key left out keeps what the server has (another device may have changed it meanwhile)
    const changed = new Set(changedKeys(draft, base));
    const settings: NonNullable<BranchPosSettingsPatch['pos_settings']> = {};
    if (changed.has('mode')) settings.mode = draft.mode;
    if (changed.has('printingDevice')) settings.printing_device_id = draft.printingDevice === '' ? null : draft.printingDevice;
    if (changed.has('lock')) settings.screen_lock_minutes = lock;
    if (changed.has('queue')) settings.queue_numbers = draft.queue;
    if (changed.has('cashierDiscount')) settings.cashier_discount_max_percent = discount ?? null;
    if (changed.has('slot')) settings.booking_slot_minutes = slot;
    if (changed.has('holidays')) settings.holidays = draft.holidays;
    const alertChanges = Object.fromEntries(changedKeys(draft.alerts, base.alerts).map((key) => [key, alerts[key] ?? null]));
    if (Object.keys(alertChanges).length > 0) settings.alerts = alertChanges;
    const body: BranchPosSettingsPatch = {
      ...(changed.has('dayStart') ? { business_day_starts_at: draft.dayStart } : {}),
      ...(Object.keys(settings).length > 0 ? { pos_settings: settings } : {}),
    };
    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(draftOf(answer));
          setSaved(t('pos_set_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
      },
    );
  };

  const serverError = (field: string) => errors.fields[field];
  const addHoliday = () => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(adding) || draft.holidays.includes(adding)) return;
    patch({ holidays: [...draft.holidays, adding].sort() });
    setAdding('');
  };

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('pos_set_intro', { timezone: data.timezone })}</p>
      <Notice message={saved} />

      <Card className="flex flex-col gap-4">
        <h2 className="text-headline font-bold text-fg">{t('pos_set_till')}</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField label={t('pos_set_mode')} hint={t(`pos_set_mode_${draft.mode}`)} value={draft.mode} onChange={(mode) => patch({ mode })} options={POS_MODES.map((value) => ({ value, label: labelFor(t, 'pos_mode_', value) }))} error={serverError('pos_settings.mode')} />
          <TimeField label={t('pos_set_dayStart')} hint={t('pos_set_dayStartHint')} value={draft.dayStart} onChange={(dayStart) => patch({ dayStart })} error={local.dayStart ?? serverError('business_day_starts_at')} />
          <SelectField
            label={t('pos_set_printing')}
            hint={t('pos_set_printingHint')}
            value={draft.printingDevice}
            onChange={(printingDevice) => patch({ printingDevice })}
            options={[{ value: '', label: t('pos_set_noPrinting') }, ...terminals.map((device) => ({ value: device.uuid, label: device.name }))]}
            error={serverError('pos_settings.printing_device_id')}
          />
          <NumberField label={t('pos_set_lock')} hint={t('pos_set_lockHint')} value={draft.lock} onChange={(lock) => patch({ lock })} inputMode="numeric" suffix="daq" error={local.lock ?? serverError('pos_settings.screen_lock_minutes')} />
          <NumberField
            label={t('pos_set_cashierDiscount')}
            hint={t('pos_set_cashierDiscountHint')}
            value={draft.cashierDiscount}
            onChange={(cashierDiscount) => patch({ cashierDiscount })}
            suffix="%"
            error={local.cashierDiscount ?? serverError('pos_settings.cashier_discount_max_percent')}
          />
          <NumberField label={t('pos_set_slot')} hint={t('pos_set_slotHint')} value={draft.slot} onChange={(slot) => patch({ slot })} inputMode="numeric" suffix="daq" error={local.slot ?? serverError('pos_settings.booking_slot_minutes')} />
        </div>
        <Check checked={draft.queue} onChange={(queue) => patch({ queue })} label={t('pos_set_queue')} hint={t('pos_set_queueHint')} />
      </Card>

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('pos_set_alerts')}</h2>
          <p className="text-footnote text-fg-muted">{t('pos_set_alertsHint')}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          {ALERT_KEYS.map((key) => {
            const entry = draft.alerts[key];
            return (
              <div key={key} className="flex flex-col gap-1 rounded-card border border-line p-3">
                <Check checked={entry.on} onChange={(on) => patch({ alerts: { ...draft.alerts, [key]: { ...entry, on } } })} label={t(`pos_alert_${key}`)} />
                <NumberField
                  label={t('pos_set_alertValue', { name: t(`pos_alert_${key}`) })}
                  value={entry.value}
                  onChange={(value) => patch({ alerts: { ...draft.alerts, [key]: { ...entry, value } } })}
                  disabled={!entry.on}
                  suffix={ALERTS[key].suffix}
                  hint={t(`pos_alert_${key}_hint`)}
                  error={local[`alert_${key}`] ?? serverError(`pos_settings.alerts.${key}`)}
                />
              </div>
            );
          })}
        </div>
      </Card>

      <Card className="flex flex-col gap-3">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('pos_set_holidays')}</h2>
          <p className="text-footnote text-fg-muted">{t('pos_set_holidaysHint')}</p>
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <DateField label={t('pos_set_holidayDate')} value={adding} onChange={setAdding} />
          <Button variant="secondary" onClick={addHoliday} disabled={!/^\d{4}-\d{2}-\d{2}$/.test(adding) || draft.holidays.includes(adding)}>
            {t('pos_set_holidayAdd')}
          </Button>
        </div>
        {draft.holidays.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('pos_set_noHolidays')}</p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {draft.holidays.map((day) => (
              <li key={day} className="inline-flex items-center gap-1 rounded-full bg-surface-2 py-0.5 pl-3 pr-1 text-footnote text-fg">
                {formatDate(localDate(day), language)} · {day}
                <button
                  type="button"
                  aria-label={t('pos_set_holidayRemove', { date: day })}
                  onClick={() => patch({ holidays: draft.holidays.filter((item) => item !== day) })}
                  className="inline-flex size-8 items-center justify-center rounded-full text-fg-muted hover:bg-line focus-visible:outline-2 focus-visible:outline-brand"
                >
                  <X aria-hidden="true" className="size-4" />
                </button>
              </li>
            ))}
          </ul>
        )}
        {serverError('pos_settings.holidays') && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{serverError('pos_settings.holidays')}</p>}
      </Card>

      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
          {t('pos_set_save')}
        </Button>
      </div>
    </form>
  );
}
