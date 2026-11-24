'use client';

import { useState, type FormEvent } from 'react';
import { Button, Card, Field, Section, fieldClass, FormError, Notice, Pill } from '@rizqim/ui';
import { getApiFieldError, useSaveStoreSettings, type StoreSettings } from '@rizqim/staff';
import { useI18n } from '../I18nProvider';
import { errorText } from './errors';
import { Check, NumberField, PhoneField, parseDecimal, phoneToInput } from './fields';
import {
  draftFromSettings,
  inputFromDraft,
  MERCHANT_FIELDS,
  MERCHANT_PROVIDERS,
  PAYLOV_SECRETS,
  PAYLOV_WEBHOOK_URL,
  paylovCallbackPasswordTooShort,
  platformCredentialFields,
  providerAvailable,
  PROVIDERS,
  unavailableReason,
  type MerchantProvider,
  type PaylovSecret,
  type Provider,
  type SettingsDraft,
} from './paymentSettings';
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
  const ownAccount = settings.payment_account_type === 'own_account';
  const paylovKeys = ownAccount && providerAvailable(settings.payment_settings?.paylov);

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
    if (paylovKeys && paylovCallbackPasswordTooShort(draft.paylov)) problems.paylov_callback_password = t('settings_paylovCallbackPasswordShort');
    setErrors(problems);
    if (Object.keys(problems).length > 0) return;

    const input = inputFromDraft(settings, { ...draft, phone: digits ? `+998${digits}` : '' }, { minOrder: minOrder ?? 0, deliveryFee: deliveryFee ?? 0 });
    save.mutate(input, {
      onSuccess: () => setSaved(t('settings_saved')),
      onError: (err) => {
        const next: Record<string, string> = {};
        for (const field of ['name', 'timezone', 'phone', 'min_order_amount', 'payment_settings', 'description', 'address', 'location_url', 'legal_address', 'complaint_contact']) {
          const message = getApiFieldError(err, field);
          if (message) next[field] = message;
        }
        const feeMessage = getApiFieldError(err, 'payment_settings.delivery_fee');
        if (feeMessage) next.delivery_fee = feeMessage;
        for (const key of ['merchant_id', ...PAYLOV_SECRETS]) {
          const message = getApiFieldError(err, `payment_settings.paylov.${key}`);
          if (message) next[`paylov_${key}`] = message;
        }
        for (const provider of MERCHANT_PROVIDERS) {
          for (const key of ['merchant_id', 'service_id', 'secret_key']) {
            const message = getApiFieldError(err, `payment_settings.${provider}.${key}`);
            if (message) next[`${provider}_${key}`] = message;
          }
        }
        // Owner decision 1: RIZQIM's own credential, said in the panel's language under each field it names (one this form
        // does not show is said above the button)
        let hidden: string | null = null;
        for (const [provider, key] of platformCredentialFields(err)) {
          const text = t('settings_platformCredential', { name: t(`settings_provider_${provider as Provider}`) });
          const shown = (provider === 'paylov' && paylovKeys) || (ownAccount && (MERCHANT_PROVIDERS as readonly string[]).includes(provider) && providerAvailable(settings.payment_settings?.[provider as MerchantProvider]));
          if (shown && (provider !== 'paylov' || key === 'merchant_id' || (PAYLOV_SECRETS as readonly string[]).includes(key))) next[`${provider}_${key}`] = text;
          else {
            delete next[`${provider}_${key}`];
            hidden ??= text;
          }
        }
        setErrors(next);
        setFormError(hidden ?? (Object.keys(next).length === 0 ? errorText(err, t) : null));
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

      {(draft.legalAddress !== null || draft.complaintContact !== null) && (
        <Section title={t('settings_sellerSection')}>
          <Card className="flex flex-col gap-4">
            <p className="text-footnote text-fg-muted">{t('settings_sellerHint')}</p>
            <dl className="grid gap-3 sm:grid-cols-2">
              <div className="flex flex-col gap-0.5">
                <dt className="text-footnote text-fg-muted">{t('settings_legalName')}</dt>
                <dd className="text-body font-bold text-fg">{settings.legal_name || t('settings_notSet')}</dd>
              </div>
              <div className="flex flex-col gap-0.5">
                <dt className="text-footnote text-fg-muted">{t('settings_tin')}</dt>
                <dd className="text-body font-bold text-fg">{settings.tin || t('settings_notSet')}</dd>
              </div>
            </dl>
            <p className="text-footnote text-fg-muted">{t('settings_legalNameHint')}</p>
            {draft.legalAddress !== null && (
              <Field label={t('settings_legalAddress')} hint={t('settings_legalAddressHint')} error={errors.legal_address}>
                {(props) => <textarea {...props} rows={2} maxLength={500} value={draft.legalAddress ?? ''} onChange={(event) => patch('legalAddress', event.target.value)} className={`${fieldClass} resize-none`} />}
              </Field>
            )}
            {draft.complaintContact !== null && (
              <Field label={t('settings_complaintContact')} hint={t('settings_complaintContactHint')} error={errors.complaint_contact}>
                {(props) => <input {...props} type="text" maxLength={255} autoComplete="off" value={draft.complaintContact ?? ''} onChange={(event) => patch('complaintContact', event.target.value)} className={fieldClass} />}
              </Field>
            )}
          </Card>
        </Section>
      )}

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
            {ownAccount ? t('settings_ownAccount') : t('settings_platformAccount')}
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
        {ownAccount &&
          MERCHANT_PROVIDERS.filter((provider) => providerAvailable(settings.payment_settings?.[provider])).map((provider) => (
            <MerchantFields key={provider} provider={provider} draft={draft} errors={errors} onChange={(field, value) => setDraft((previous) => ({ ...previous, merchant: { ...previous.merchant, [provider]: { ...previous.merchant[provider], [field]: value } } }))} />
          ))}
        {paylovKeys && (
          <PaylovFields
            draft={draft}
            errors={errors}
            onMerchantId={(value) => setDraft((previous) => ({ ...previous, paylov: { ...previous.paylov, merchant_id: value } }))}
            onSecret={(key, value) => setDraft((previous) => ({ ...previous, paylov: { ...previous.paylov, secrets: { ...previous.paylov.secrets, [key]: value } } }))}
          />
        )}
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
function MerchantFields({
  provider,
  draft,
  errors,
  onChange,
}: {
  provider: MerchantProvider;
  draft: SettingsDraft;
  errors: Record<string, string>;
  onChange: (field: 'merchant_id' | 'service_id' | 'secret_key', value: string) => void;
}) {
  const { t } = useI18n();
  const keys = draft.merchant[provider];
  const name = t(`settings_provider_${provider}`);
  return (
    <Card className="mt-3 flex flex-col gap-3">
      <p className="text-body font-bold text-fg">{t('settings_merchantTitle', { name })}</p>
      <p className="text-footnote text-fg-muted">{t('settings_merchantHint')}</p>
      <div className="grid gap-4 sm:grid-cols-2">
        {MERCHANT_FIELDS[provider].map((field) => (
          <Field key={field} label={t(field === 'service_id' ? 'settings_serviceId' : 'settings_merchantId', { name })} error={errors[`${provider}_${field}`]}>
            {(props) => <input {...props} type="text" autoComplete="off" spellCheck={false} maxLength={255} value={keys[field]} onChange={(event) => onChange(field, event.target.value)} className={fieldClass} />}
          </Field>
        ))}
        <Field label={t('settings_secretKey', { name })} hint={keys.hasSecret ? t('settings_secretKeyStored') : t('settings_secretKeyMissing')} error={errors[`${provider}_secret_key`]}>
          {(props) => <input {...props} type="password" autoComplete="new-password" spellCheck={false} maxLength={255} value={keys.secret_key} onChange={(event) => onChange('secret_key', event.target.value)} className={fieldClass} />}
        </Field>
      </div>
    </Card>
  );
}

const PAYLOV_LABEL = {
  consumer_key: 'settings_paylovConsumerKey',
  consumer_secret: 'settings_paylovConsumerSecret',
  username: 'settings_paylovUsername',
  password: 'settings_paylovPassword',
  callback_login: 'settings_paylovCallbackLogin',
  callback_password: 'settings_paylovCallbackPassword',
} as const satisfies Record<PaylovSecret, string>;

// Wave P: the store's own Paylov account (own account only, while RIZQIM offers Paylov). The six secrets are password
// fields that never show a stored value ("saqlangan" from has_*), an empty one keeps it; the webhook address to give Paylov.
function PaylovFields({
  draft,
  errors,
  onMerchantId,
  onSecret,
}: {
  draft: SettingsDraft;
  errors: Record<string, string>;
  onMerchantId: (value: string) => void;
  onSecret: (key: PaylovSecret, value: string) => void;
}) {
  const { t } = useI18n();
  const keys = draft.paylov;
  return (
    <Card className="mt-3 flex flex-col gap-3">
      <h3 className="text-body font-bold text-fg">{t('settings_paylovTitle')}</h3>
      <p className="text-footnote text-fg-muted">{t('settings_paylovHint')}</p>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('settings_paylovMerchantId')} hint={t('settings_paylovMerchantIdHint')} error={errors.paylov_merchant_id}>
          {(props) => <input {...props} type="text" autoComplete="off" spellCheck={false} maxLength={255} value={keys.merchant_id} onChange={(event) => onMerchantId(event.target.value)} className={fieldClass} />}
        </Field>
        {PAYLOV_SECRETS.map((key) => (
          <Field key={key} label={t(PAYLOV_LABEL[key])} hint={keys.stored[key] ? t('settings_secretKeyStored') : t('settings_secretKeyMissing')} error={errors[`paylov_${key}`]}>
            {(props) => (
              <input
                {...props}
                type="password"
                autoComplete="new-password"
                spellCheck={false}
                maxLength={255}
                value={keys.secrets[key]}
                onChange={(event) => onSecret(key, event.target.value)}
                className={fieldClass}
              />
            )}
          </Field>
        ))}
      </div>
      <div className="flex flex-col gap-1 rounded-control border border-line p-3">
        <p className="text-footnote font-bold text-fg">{t('settings_paylovWebhookTitle')}</p>
        <code className="break-all font-mono text-footnote text-fg">{PAYLOV_WEBHOOK_URL}</code>
        <p className="text-footnote text-fg-muted">{t('settings_paylovWebhookText')}</p>
      </div>
    </Card>
  );
}
