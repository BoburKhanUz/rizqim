'use client';

import { useState, type FormEvent } from 'react';
import { Save } from 'lucide-react';
import { Button, Card, FormError, Notice, Pill } from '@rizqim/ui';
import { QR_PROVIDERS, useQrPaymentSettings, useSaveQrPaymentSettings, type QrPaymentSettingsPatch, type QrPaymentSettingsView, type QrProviderKey } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { Check, NumberField } from '../management/fields';
import { Gate } from '../pos/Gate';
import { SelectField } from '../pos/fields';
import { changedKeys, useServerDraft } from '../pos/serverDraft';
import { NO_ERRORS, sortServerErrors, type FormErrors } from '../pos/serverErrors';

// The QR payments of a branch at the till (P6-41/P6-42; docs/API.md "QR to'lov kassada"): the providers the cashier may offer,
// the one offered first, how long a QR stays valid, and whether the store's own Click/Payme merchant account (or the UzQR
// gateway) is connected. The keys themselves live in the store's payment settings and are never shown here. The form sends
// only what changed (the server keeps the rest).

interface Draft {
  providers: QrProviderKey[];
  defaultProvider: QrProviderKey | '';
  expiry: string;
}

const order = (providers: readonly QrProviderKey[]) => QR_PROVIDERS.filter((key) => providers.includes(key));

function draftOf(view: QrPaymentSettingsView): Draft {
  return {
    providers: order(view.settings.providers),
    defaultProvider: view.settings.default_provider ?? '',
    expiry: String(view.settings.expiry_minutes),
  };
}

const SERVER_FIELDS = ['providers', 'default_provider', 'expiry_minutes'];

export function QrPaymentsPanel({ branchId, enabled }: { branchId: number; enabled: boolean }) {
  const { t } = useI18n();
  const settings = useQrPaymentSettings(enabled ? branchId : null);
  if (!enabled) return <p role="note" className="rounded-control bg-info-soft px-3 py-2 text-footnote font-bold text-info-soft-fg">{t('qrp_moduleOff')}</p>;
  return <Gate query={settings} count={2}>{(data) => <QrForm key={branchId} branchId={branchId} data={data} />}</Gate>;
}

function QrForm({ branchId, data }: { branchId: number; data: QrPaymentSettingsView }) {
  const { t } = useI18n();
  const save = useSaveQrPaymentSettings();
  const { draft, setDraft, base, dirty } = useServerDraft(data, draftOf);
  const [errors, setErrors] = useState<FormErrors>(NO_ERRORS);
  const [local, setLocal] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState<string | null>(null);
  const merchants = new Map(data.merchants.map((row) => [row.provider, row]));
  const labelOf = (key: QrProviderKey) => data.options.providers.find((option) => option.key === key)?.label ?? key;

  const patch = (changes: Partial<Draft>) => {
    setDraft((current) => {
      const next = { ...current, ...changes };
      // The default must be one of the offered providers
      if (next.defaultProvider !== '' && !next.providers.includes(next.defaultProvider)) next.defaultProvider = next.providers[0] ?? '';
      return next;
    });
    setSaved(null);
  };
  const toggle = (key: QrProviderKey, on: boolean) => patch({ providers: order(on ? [...draft.providers, key] : draft.providers.filter((item) => item !== key)) });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    const problems: Record<string, string> = {};
    const expiry = Number(draft.expiry.trim());
    if (!/^\d+$/.test(draft.expiry.trim()) || expiry < data.options.expiry_min || expiry > data.options.expiry_max) problems.expiry = t('qrp_expiryInvalid');
    setLocal(problems);
    setErrors(NO_ERRORS);
    if (Object.keys(problems).length > 0) return;

    const changed = new Set(changedKeys(draft, base));
    const body: QrPaymentSettingsPatch = {};
    if (changed.has('providers')) body.providers = draft.providers;
    if (changed.has('providers') || changed.has('defaultProvider')) body.default_provider = draft.defaultProvider === '' ? null : draft.defaultProvider;
    if (changed.has('expiry')) body.expiry_minutes = expiry;

    save.mutate(
      { branchId, patch: body },
      {
        onSuccess: (answer) => {
          setDraft(draftOf(answer));
          setSaved(t('qrp_saved'));
        },
        onError: (error) => setErrors(sortServerErrors(error, SERVER_FIELDS, t)),
      },
    );
  };

  const serverError = (field: string) => errors.fields[field];
  const hint = (key: QrProviderKey) => t(key === 'click' ? 'qrp_clickHint' : key === 'payme' ? 'qrp_paymeHint' : 'qrp_uzqrHint');

  return (
    <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('qrp_intro')}</p>
      <Notice message={saved} />

      <Card className="flex flex-col gap-4">
        <div>
          <h2 className="text-headline font-bold text-fg">{t('qrp_providersTitle')}</h2>
          <p className="text-footnote text-fg-muted">{t('qrp_providersHint')}</p>
        </div>
        <ul className="flex flex-col gap-1">
          {QR_PROVIDERS.map((key) => {
            const merchant = merchants.get(key);
            return (
              <li key={key} className="flex flex-wrap items-start justify-between gap-2">
                <Check checked={draft.providers.includes(key)} onChange={(on) => toggle(key, on)} label={labelOf(key)} hint={hint(key)} />
                <Pill tone={merchant?.configured ? 'success' : 'neutral'}>{merchant?.configured ? t('qrp_configured') : t('qrp_notConfigured')}</Pill>
              </li>
            );
          })}
        </ul>
        {serverError('providers') && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {serverError('providers')}
          </p>
        )}
        <SelectField
          label={t('qrp_default')}
          value={draft.defaultProvider}
          onChange={(defaultProvider) => patch({ defaultProvider: defaultProvider as QrProviderKey | '' })}
          options={[{ value: '', label: t('qrp_noneDefault') }, ...draft.providers.map((key) => ({ value: key, label: labelOf(key) }))]}
          error={serverError('default_provider')}
          className="sm:max-w-sm"
        />
      </Card>

      <Card className="flex flex-col gap-4">
        <div className="sm:max-w-xs">
          <NumberField label={t('qrp_expiry')} value={draft.expiry} onChange={(expiry) => patch({ expiry })} inputMode="numeric" suffix={t('qrp_minutes')} hint={t('qrp_expiryHint')} error={local.expiry ?? serverError('expiry_minutes')} />
        </div>
      </Card>

      <FormError message={errors.form} />
      <div>
        <Button type="submit" size="lg" icon={Save} loading={save.isPending} disabled={!dirty}>
          {t('qrp_save')}
        </Button>
      </div>
    </form>
  );
}
