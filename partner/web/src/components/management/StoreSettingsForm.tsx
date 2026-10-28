'use client';

import { useState, type FormEvent } from 'react';
import { Button, Card, Field, Section, fieldClass, FormError, Notice, Pill } from '@rizqim/ui';
import { getApiFieldError, useSaveStoreSettings, type StoreSettings } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { Check, NumberField, PhoneField, parseDecimal, phoneToInput } from './fields';
import { draftFromSettings, inputFromDraft, MERCHANT_FIELDS, MERCHANT_PROVIDERS, providerAvailable, PROVIDERS, unavailableReason, type MerchantProvider, type Provider, type SettingsDraft } from './paymentSettings';
import { TelegramGroupCard } from './TelegramGroupCard';

const COMMON_TIMEZONES = ['Asia/Tashkent', 'Asia/Samarkand'];

function timezoneOptions(current: string): string[] {
  let all: string[] = [];
  try {
    all = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
  } catch {
    all = [];
  }
  return Array.from(new Set([current, ...COMMON_TIMEZONES, ...all])).filter(Boolean);
}

// POST /owner/settings: the store's name and timezone, how it takes orders (open, delivery, pickup, the minimum, the working
// hours) and which payment methods it offers. Logo and banner stay as stored (the Mini App uploads them). The server
// judges every value: a refusal is shown under its field (the timezone, a payment method that cannot be enabled yet).
export function StoreSettingsForm({ settings }: { settings: StoreSettings }) {
  const { t } = useI18n();
  const [draft, setDraft] = useState<SettingsDraft>(() => draftFromSettings(settings));
  const [phone, setPhone] = useState(() => phoneToInput(settings.phone));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState<string | null>(null);
  const save = useSaveStoreSettings();
  const patch = <K extends keyof SettingsDraft>(key: K, value: SettingsDraft[K]) => setDraft((previous) => ({ ...previous, [key]: value }));
  const allowed = (provider: Provider) => settings.allowed_methods.includes(provider);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setSaved(null);
    setFormError(null);
    const problems: Record<string, string> = {};
    const minOrder = parseDecimal(draft.minOrder);
    const deliveryFee = parseDecimal(draft.deliveryFee);
    const digits = phone.replace(/\D/g, '');
    if (draft.name.trim() === '') problems.name = t('settings_nameRequired');
    if (draft.timezone.trim() === '') problems.timezone = t('settings_timezoneRequired');
    if (minOrder === undefined) problems.min_order_amount = t('mgmt_numberInvalid');
    if (deliveryFee === undefined) problems.delivery_fee = t('mgmt_numberInvalid');
    if (digits !== '' && digits.length !== 9) problems.phone = t('mgmt_phoneInvalid');
    setErrors(problems);
    if (Object.keys(problems).length > 0) return;

    const input = inputFromDraft(settings, { ...draft, phone: digits ? `+998${digits}` : '' }, { minOrder: minOrder ?? 0, deliveryFee: deliveryFee ?? 0 });
    save.mutate(input, {
      onSuccess: () => setSaved(t('settings_saved')),
      onError: (err) => {
        const next: Record<string, string> = {};
        for (const field of ['name', 'timezone', 'phone', 'min_order_amount', 'payment_settings', 'description', 'address', 'location_url']) {
          const message = getApiFieldError(err, field);
          if (message) next[field] = message;
        }
        const feeMessage = getApiFieldError(err, 'payment_settings.delivery_fee');
        if (feeMessage) next.delivery_fee = feeMessage;
        setErrors(next);
        setFormError(Object.keys(next).length === 0 ? errorText(err, t) : null);
      },
    });
  };

  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-6">
      <Notice message={saved} />

      <Section title={t('settings_storeSection')}>
        <Card className="flex flex-col gap-4">
          <Field label={t('settings_name')} error={errors.name}>
            {(props) => <input {...props} type="text" maxLength={255} autoComplete="off" value={draft.name} onChange={(event) => patch('name', event.target.value)} className={fieldClass} />}
          </Field>
          <Field label={t('settings_timezone')} hint={t('settings_timezoneHint')} error={errors.timezone}>
            {(props) => (
              <>
                <input {...props} type="text" list="settings-timezones" autoComplete="off" value={draft.timezone} onChange={(event) => patch('timezone', event.target.value)} className={fieldClass} />
                <datalist id="settings-timezones">
                  {timezoneOptions(settings.timezone).map((zone) => (
                    <option key={zone} value={zone} />
                  ))}
                </datalist>
              </>
            )}
          </Field>
          <PhoneField label={t('settings_phone')} value={phone} onChange={setPhone} error={errors.phone} />
          <Field label={t('settings_description')} error={errors.description}>
            {(props) => <textarea {...props} rows={3} maxLength={1000} value={draft.description} onChange={(event) => patch('description', event.target.value)} className={`${fieldClass} resize-none`} />}
          </Field>
          <Field label={t('settings_address')} hint={t('settings_addressHint')} error={errors.address}>
            {(props) => <input {...props} type="text" maxLength={500} value={draft.address} onChange={(event) => patch('address', event.target.value)} className={fieldClass} />}
          </Field>
          <Field label={t('settings_locationUrl')} hint={t('settings_locationUrlHint')} error={errors.location_url}>
            {(props) => <input {...props} type="text" inputMode="url" maxLength={1000} value={draft.locationUrl} onChange={(event) => patch('locationUrl', event.target.value)} className={fieldClass} />}
          </Field>
        </Card>
      </Section>

      <Section title={t('settings_ordersSection')}>
        <Card className="flex flex-col gap-3">
          <Check checked={draft.isOpen} onChange={(value) => patch('isOpen', value)} label={t('settings_isOpen')} hint={t('settings_isOpenHint')} />
          <Check checked={draft.deliveryEnabled} onChange={(value) => patch('deliveryEnabled', value)} label={t('settings_delivery')} hint={t('settings_deliveryHint')} />
          <Check checked={draft.pickupEnabled} onChange={(value) => patch('pickupEnabled', value)} label={t('settings_pickup')} hint={t('settings_pickupHint')} />
          <div className="grid gap-4 sm:grid-cols-2">
            <NumberField label={t('settings_deliveryFee')} value={draft.deliveryFee} onChange={(value) => patch('deliveryFee', value)} error={errors.delivery_fee} suffix="so'm" hint={t('settings_deliveryFeeHint')} />
            <NumberField label={t('settings_minOrder')} value={draft.minOrder} onChange={(value) => patch('minOrder', value)} error={errors.min_order_amount} suffix="so'm" hint={t('settings_minOrderHint')} />
            <Field label={t('settings_opens')}>
              {(props) => <input {...props} type="time" value={draft.openTime} onChange={(event) => patch('openTime', event.target.value)} className={fieldClass} />}
            </Field>
            <Field label={t('settings_closes')}>
              {(props) => <input {...props} type="time" value={draft.closeTime} onChange={(event) => patch('closeTime', event.target.value)} className={fieldClass} />}
            </Field>
          </div>
        </Card>
      </Section>

      <Section title={t('settings_paymentsSection')}>
        <Card className="flex flex-col gap-1">
          <p className="mb-1 text-footnote text-fg-muted">
            {settings.payment_account_type === 'own_account' ? t('settings_ownAccount') : t('settings_platformAccount')}
          </p>
          {PROVIDERS.map((provider) => {
            const available = providerAvailable(settings.payment_settings?.[provider]);
            const permitted = allowed(provider);
            return (
              <div key={provider} className="flex flex-wrap items-center justify-between gap-2">
                <Check
                  checked={draft.providers[provider] && available}
                  onChange={(value) => setDraft((previous) => ({ ...previous, providers: { ...previous.providers, [provider]: value } }))}
                  disabled={!available || (!permitted && !draft.providers[provider])}
                  label={t(`settings_provider_${provider}`)}
                />
                {!available && <Pill tone="warning">{unavailableReason(settings.payment_settings?.[provider]) === 'switched_off' ? t('settings_switchedOff') : t('settings_comingSoon')}</Pill>}
                {available && !permitted && <Pill tone="neutral">{t('settings_notAllowed')}</Pill>}
              </div>
            );
          })}
          {errors.payment_settings && <p role="alert" className="text-footnote font-bold text-danger-soft-fg">{errors.payment_settings}</p>}
        </Card>
        {settings.payment_account_type === 'own_account' &&
          MERCHANT_PROVIDERS.filter((provider) => providerAvailable(settings.payment_settings?.[provider])).map((provider) => (
            <MerchantFields key={provider} provider={provider} draft={draft} onChange={(field, value) => setDraft((previous) => ({ ...previous, merchant: { ...previous.merchant, [provider]: { ...previous.merchant[provider], [field]: value } } }))} />
          ))}
      </Section>

      <TelegramGroupCard settings={settings} />

      <FormError message={formError} />
      <div>
        <Button type="submit" loading={save.isPending}>
          {t('mgmt_save')}
        </Button>
      </div>
    </form>
  );
}

// P4-32: the store's own merchant keys of one provider (own account only). The secret is never shown: an empty field keeps the
// stored one, a typed one replaces it.
function MerchantFields({ provider, draft, onChange }: { provider: MerchantProvider; draft: SettingsDraft; onChange: (field: 'merchant_id' | 'service_id' | 'secret_key', value: string) => void }) {
  const { t } = useI18n();
  const keys = draft.merchant[provider];
  const name = t(`settings_provider_${provider}`);
  return (
    <Card className="mt-3 flex flex-col gap-3">
      <p className="text-body font-bold text-fg">{t('settings_merchantTitle', { name })}</p>
      <p className="text-footnote text-fg-muted">{t('settings_merchantHint')}</p>
      <div className="grid gap-4 sm:grid-cols-2">
        {MERCHANT_FIELDS[provider].map((field) => (
          <Field key={field} label={t(field === 'service_id' ? 'settings_serviceId' : 'settings_merchantId', { name })}>
            {(props) => <input {...props} type="text" autoComplete="off" spellCheck={false} maxLength={255} value={keys[field]} onChange={(event) => onChange(field, event.target.value)} className={fieldClass} />}
          </Field>
        ))}
        <Field label={t('settings_secretKey', { name })} hint={keys.hasSecret ? t('settings_secretKeyStored') : t('settings_secretKeyMissing')}>
          {(props) => <input {...props} type="password" autoComplete="new-password" spellCheck={false} maxLength={255} value={keys.secret_key} onChange={(event) => onChange('secret_key', event.target.value)} className={fieldClass} />}
        </Field>
      </div>
    </Card>
  );
}
