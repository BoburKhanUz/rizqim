'use client';

import { useState, type FormEvent } from 'react';
import { Info, Save } from 'lucide-react';
import { Button, Card, Field, fieldClass, FormError, Notice } from '@rizqim/ui';
import {
  FISCAL_CHARGES,
  isMxik,
  isPackageCode,
  useFiscalSettings,
  useSaveFiscalSettings,
  type FiscalChargeKey,
  type FiscalSettingsInput,
  type FiscalSettingsView,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, TextFieldInline } from './formBits';
import { Gate } from '../pos/Gate';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';
import { labelFor } from '../reports/format';

// Charges the law gives no official code for: the owner looks it up (docs/API.md "Fiskal cheklar")
const NO_OFFICIAL_CODE: readonly FiscalChargeKey[] = ['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'delivery'];

type Tri = 'default' | 'yes' | 'no';
const toTri = (value: boolean | null | undefined): Tri => (value === null || value === undefined ? 'default' : value ? 'yes' : 'no');
const fromTri = (value: Tri): boolean | null => (value === 'default' ? null : value === 'yes');

interface ChargeDraft {
  name: string;
  mxik: string;
  packageCode: string;
  unit: string;
}

interface Draft {
  tin: string;
  regime: string;
  enabled: boolean;
  driver: string;
  charges: Record<FiscalChargeKey, ChargeDraft>;
  refund: Tri;
  retained: Tri;
}

function draftOf(view: FiscalSettingsView): Draft {
  const settings = view.fiscal_settings;
  const charges = Object.fromEntries(
    FISCAL_CHARGES.map((key) => {
      const charge = settings.charges[key];
      return [key, { name: charge?.name ?? '', mxik: charge?.mxik ?? '', packageCode: charge?.package_code ?? '', unit: charge?.unit ?? '' }];
    }),
  ) as Record<FiscalChargeKey, ChargeDraft>;
  return {
    tin: view.tin ?? '',
    regime: view.tax_regime ?? '',
    enabled: settings.enabled,
    driver: settings.driver || 'sandbox',
    charges,
    refund: toTri(settings.deposit_refund_receipt),
    retained: toTri(settings.retained_deposit_receipt),
  };
}

// The part of the form that changed, as PUT /owner/fiscal/settings takes it (what is left out stays). An emptied charge field
// goes as null, which brings the default back.
function changesOf(initial: Draft, draft: Draft): FiscalSettingsInput {
  const input: FiscalSettingsInput = {};
  if (draft.tin.trim() !== initial.tin.trim()) input.tin = draft.tin.trim() || null;
  if (draft.regime !== initial.regime) input.tax_regime = (draft.regime || null) as FiscalSettingsInput['tax_regime'];
  const settings: NonNullable<FiscalSettingsInput['fiscal_settings']> = { enabled: draft.enabled };
  let touched = draft.enabled !== initial.enabled;
  if (draft.driver !== initial.driver) {
    settings.driver = draft.driver;
    touched = true;
  }
  const charges: Record<string, { name?: string | null; mxik?: string | null; package_code?: string | null; unit?: string | null }> = {};
  for (const key of FISCAL_CHARGES) {
    const was = initial.charges[key];
    const now = draft.charges[key];
    const change: { name?: string | null; mxik?: string | null; package_code?: string | null; unit?: string | null } = {};
    if (now.name.trim() !== was.name.trim()) change.name = now.name.trim() || null;
    if (now.mxik.trim() !== was.mxik.trim()) change.mxik = now.mxik.trim() || null;
    if (now.packageCode.trim() !== was.packageCode.trim()) change.package_code = now.packageCode.trim() || null;
    if (now.unit.trim() !== was.unit.trim()) change.unit = now.unit.trim() || null;
    if (Object.keys(change).length > 0) charges[key] = change;
  }
  if (Object.keys(charges).length > 0) {
    settings.charges = charges;
    touched = true;
  }
  if (draft.refund !== initial.refund) {
    settings.deposit_refund_receipt = fromTri(draft.refund);
    touched = true;
  }
  if (draft.retained !== initial.retained) {
    settings.retained_deposit_receipt = fromTri(draft.retained);
    touched = true;
  }
  if (touched) input.fiscal_settings = settings;
  return input;
}

const SERVER_FIELDS = [
  'tin',
  'tax_regime',
  'fiscal_settings.enabled',
  'fiscal_settings.driver',
  'fiscal_settings.deposit_refund_receipt',
  'fiscal_settings.retained_deposit_receipt',
  ...FISCAL_CHARGES.flatMap((key) => [`fiscal_settings.charges.${key}.mxik`, `fiscal_settings.charges.${key}.package_code`, `fiscal_settings.charges.${key}.name`, `fiscal_settings.charges.${key}.unit`]),
] as const;

// The store's tax data and how receipts are sent (settings.manage). The driver is shown as what it is: a test or demo one,
// not the tax office. The deposit rules wait for the accountant and say so.
export function FiscalSettingsPanel() {
  const settings = useFiscalSettings();
  return <Gate query={settings} count={3}>{(view) => <SettingsForm view={view} />}</Gate>;
}

function SettingsForm({ view }: { view: FiscalSettingsView }) {
  const { t } = useI18n();
  // What the form started from (and, after a save, what the server answered): only the difference is sent
  const [initial, setInitial] = useState(() => draftOf(view));
  const [draft, setDraft] = useState<Draft>(initial);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<string | null>(null);
  const save = useSaveFiscalSettings();

  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => ({ ...current, ...changes }));
    setSaved(null);
  };
  const patchCharge = (key: FiscalChargeKey, changes: Partial<ChargeDraft>) => patch({ charges: { ...draft.charges, [key]: { ...draft.charges[key], ...changes } } });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const problems: Record<string, string> = {};
    const tin = draft.tin.trim();
    if (tin !== '' && !/^\d{9}$/.test(tin)) problems.tin = t('fiscal_err_tin');
    for (const key of FISCAL_CHARGES) {
      const charge = draft.charges[key];
      if (charge.mxik.trim() !== '' && !isMxik(charge.mxik.trim())) problems[`${key}.mxik`] = t('fiscal_err_mxik');
      if (charge.packageCode.trim() !== '' && !isPackageCode(charge.packageCode.trim())) problems[`${key}.package`] = t('fiscal_err_package');
    }
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;
    const input = changesOf(initial, draft);
    save.mutate(input, {
      onSuccess: (answer) => {
        const next = draftOf(answer);
        setInitial(next);
        setDraft(next);
        setSaved(t('fiscal_set_saved'));
      },
      onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
    });
  };

  const driverLabel = (driver: string) => labelFor(t, 'fiscal_driver_', driver);
  const serverError = (field: string) => errors.fields[field];
  const regimes = view.tax_regimes.map((regime) => regime.value);

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('fiscal_set_intro')}</p>
      <Notice message={saved} />

      <Card className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('fiscal_set_tin')} hint={t('fiscal_set_tinHint')} error={local.tin ?? serverError('tin')}>
            {(props) => <input {...props} inputMode="numeric" maxLength={9} value={draft.tin} onChange={(event) => patch({ tin: event.target.value })} className={fieldClass} />}
          </Field>
          <Field label={t('fiscal_set_regime')} error={serverError('tax_regime')}>
            {(props) => (
              <select {...props} value={draft.regime} onChange={(event) => patch({ regime: event.target.value })} className={fieldClass}>
                <option value="">{t('fiscal_set_regimeNone')}</option>
                {regimes.map((regime) => (
                  <option key={regime} value={regime}>
                    {labelFor(t, 'fiscal_regime_', regime)}
                  </option>
                ))}
              </select>
            )}
          </Field>
        </div>
        <div>
          <Check checked={draft.enabled} onChange={(enabled) => patch({ enabled })} label={t('fiscal_set_enabled')} hint={t('fiscal_set_enabledHint')} />
          {serverError('fiscal_settings.enabled') && (
            <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
              {serverError('fiscal_settings.enabled')}
            </p>
          )}
        </div>

        <Field label={t('fiscal_set_driver')} error={serverError('fiscal_settings.driver')}>
          {(props) => (
            <select {...props} value={draft.driver} onChange={(event) => patch({ driver: event.target.value })} className={`${fieldClass} sm:max-w-sm`}>
              {view.drivers.map((driver) => (
                <option key={driver} value={driver}>
                  {driverLabel(driver)}
                </option>
              ))}
            </select>
          )}
        </Field>
        <p role="note" className="flex items-start gap-2 rounded-card bg-info-soft px-4 py-3 text-footnote text-info-soft-fg">
          <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          <span>
            {t('fiscal_set_driverNote', { driver: driverLabel(view.fiscal_settings.active_driver) })}
            <span className="mt-1 block font-bold">{t('fiscal_set_activeDriver', { driver: driverLabel(view.fiscal_settings.active_driver) })}</span>
          </span>
        </p>
      </Card>

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('fiscal_set_charges')}</h2>
          <p className="text-footnote text-fg-muted">{t('fiscal_set_chargesHint')}</p>
        </div>
        <div className="flex flex-col gap-4">
          {FISCAL_CHARGES.map((key) => {
            const charge = draft.charges[key];
            const title = t(`fiscal_charge_${key}`);
            const field = (name: string) => serverError(`fiscal_settings.charges.${key}.${name}`);
            return (
              <fieldset key={key} className="flex flex-col gap-2 rounded-card border border-line p-3">
                <legend className="px-1 text-body font-bold text-fg">{title}</legend>
                {NO_OFFICIAL_CODE.includes(key) && <p className="text-footnote text-fg-muted">{t('fiscal_charge_noOfficial')}</p>}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  <TextFieldInline label={`${title}: ${t('fiscal_charge_mxik')}`} shownLabel={t('fiscal_charge_mxik')} value={charge.mxik} inputMode="numeric" maxLength={17} onChange={(mxik) => patchCharge(key, { mxik })} error={local[`${key}.mxik`] ?? field('mxik')} />
                  <TextFieldInline label={`${title}: ${t('fiscal_charge_package')}`} shownLabel={t('fiscal_charge_package')} value={charge.packageCode} inputMode="numeric" maxLength={20} onChange={(packageCode) => patchCharge(key, { packageCode })} error={local[`${key}.package`] ?? field('package_code')} />
                  <TextFieldInline label={`${title}: ${t('fiscal_charge_unit')}`} shownLabel={t('fiscal_charge_unit')} value={charge.unit} maxLength={64} onChange={(unit) => patchCharge(key, { unit })} error={field('unit')} />
                  <TextFieldInline label={`${title}: ${t('fiscal_charge_name')}`} shownLabel={t('fiscal_charge_name')} value={charge.name} maxLength={128} onChange={(name) => patchCharge(key, { name })} error={field('name')} />
                </div>
              </fieldset>
            );
          })}
        </div>
      </Card>

      <Card className="flex flex-col gap-4">
        <h2 className="text-headline font-bold text-fg">{t('fiscal_set_depositRules')}</h2>
        <p role="note" className="flex items-start gap-2 rounded-card bg-warning-soft px-4 py-3 text-footnote text-warning-soft-fg">
          <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
          {t('fiscal_set_accountantNote')}
        </p>
        <div className="grid gap-4 sm:grid-cols-2">
          <TriField label={t('fiscal_set_depositRefund')} value={draft.refund} onChange={(refund) => patch({ refund })} error={serverError('fiscal_settings.deposit_refund_receipt')} />
          <TriField label={t('fiscal_set_depositRetained')} value={draft.retained} onChange={(retained) => patch({ retained })} error={serverError('fiscal_settings.retained_deposit_receipt')} />
        </div>
      </Card>

      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending}>
          {t('fiscal_set_save')}
        </Button>
      </div>
    </form>
  );
}

function TriField({ label, value, onChange, error }: { label: string; value: Tri; onChange: (value: Tri) => void; error?: string }) {
  const { t } = useI18n();
  return (
    <Field label={label} error={error}>
      {(props) => (
        <select {...props} value={value} onChange={(event) => onChange(event.target.value as Tri)} className={fieldClass}>
          <option value="default">{t('fiscal_opt_default')}</option>
          <option value="yes">{t('fiscal_opt_yes')}</option>
          <option value="no">{t('fiscal_opt_no')}</option>
        </select>
      )}
    </Field>
  );
}
