'use client';

import { useId, useState, type FormEvent } from 'react';
import { CheckCircle2, Send } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import type { MessageKey } from '@/i18n';
import { ApiError } from '@/lib/api/client';
import { sendTestSms, type SettingItem } from '@/lib/api/system';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';

// "Sinov SMS yuborish" on the SMS card: one fixed text (the backend's own, SmsTestSender::TEXT) to a phone the admin types,
// through what is SAVED. Only the phone is sent from here. Which provider the backend uses mirrors SmsTestSender::provider():
// the saved real method with its credentials, or with SMS off (log) the saved Onlinegroup key (checked before switching).

const filled = (value: string | null | undefined) => (value ?? '').trim() !== '';

// The saved SMS method as the backend reads it (SmsDriver::current()): an empty value is Log
export function savedSmsMethod(items: SettingItem[]): string {
  const driver = items.find((item) => item.key === 'sms_driver');
  return (driver?.value ?? '').trim() || 'log';
}

// An Uzbek mobile number as the gateway takes it (OnlinegroupSmsService::normalizePhone): +998 and nine digits; written with
// "+", only +998; else 9 digits, 998 + 9 or 8998 + 9, any spacing. Null for anything else.
export function normalizeUzPhone(input: string): string | null {
  let digits = input.replace(/\D+/g, '');
  if (input.trimStart().startsWith('+')) return /^998\d{9}$/.test(digits) ? `+${digits}` : null;
  if (digits.length === 9) digits = `998${digits}`;
  else if (digits.length === 13 && digits.startsWith('8998')) digits = digits.slice(1);
  return /^998\d{9}$/.test(digits) ? `+${digits}` : null;
}

// What the backend can test with the saved settings: 'method' (the saved method), 'key' (SMS off, the Onlinegroup key) or null
export function smsTestTarget(items: SettingItem[]): 'method' | 'key' | null {
  const byKey = new Map(items.map((item) => [item.key, item]));
  const method = savedSmsMethod(items);
  const key = Boolean(byKey.get('onlinegroup_api_key')?.configured);
  if (method === 'onlinegroup') return key ? 'method' : null;
  if (method === 'eskiz') return filled(byKey.get('eskiz_email')?.value) && byKey.get('eskiz_password')?.configured ? 'method' : null;
  if (method === 'log') return key ? 'key' : null;
  return null;
}

// The backend's codes (AdminSystemSettingController::testSms) in the app's own words
const CODE_TEXT: Record<string, MessageKey> = {
  sms_not_configured: 'system_smsTest_err_notConfigured',
  sms_key_refused: 'system_smsTest_err_keyRefused',
  sms_provider_rate_limited: 'system_smsTest_err_providerLimit',
  sms_provider_refused: 'system_smsTest_err_refused',
  sms_provider_unavailable: 'system_smsTest_err_unavailable',
  sms_send_failed: 'system_smsTest_err_failed',
  sms_test_rate_limited: 'system_smsTest_err_rateLimited',
};

function testErrorText(error: unknown, t: ReturnType<typeof useT>): string | null {
  if (!error || fieldError(error, 'phone')) return null;
  const key = error instanceof ApiError ? CODE_TEXT[error.code] : undefined;
  if (key) return t(key, { minutes: Math.max(1, Math.ceil(((error as ApiError).retryAfter ?? 3600) / 60)) });
  return errorText(error, t);
}

// What the result of a test was a test of: a new key, method or credentials makes an old "sent" note stale
const fingerprint = (items: SettingItem[]) => JSON.stringify(items.map((item) => [item.key, item.value, item.configured, item.last4, item.updated_at]));

export function SmsTestRow({ items, unsaved }: { items: SettingItem[]; unsaved: boolean }) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [sent, setSent] = useState<{ phone: string; of: string } | null>(null);
  const hintId = useId();
  const target = smsTestTarget(items);
  const hint = target === null ? t('system_smsTest_unavailable') : target === 'key' ? t('system_smsTest_viaKey') : t('system_smsTest_hint');
  const current = fingerprint(items);
  const sentTo = sent && sent.of === current ? sent.phone : null;

  return (
    <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <span id={hintId} className="min-w-0 flex-1 text-footnote text-fg-muted">
          {hint}
        </span>
        <Button
          variant="secondary"
          disabled={target === null}
          aria-describedby={hintId}
          onClick={() => {
            setSent(null);
            setOpen(true);
          }}
        >
          <Send aria-hidden="true" className="size-4" />
          {t('system_smsTest_button')}
        </Button>
      </div>
      {sentTo && (
        <p role="status" className="flex items-start gap-2 text-footnote font-bold text-success-soft-fg">
          <CheckCircle2 aria-hidden="true" className="mt-px size-4 shrink-0" />
          {t('system_smsTest_sent', { phone: sentTo })}
        </p>
      )}
      {open && (
        <SmsTestDialog
          unsaved={unsaved}
          onClose={() => setOpen(false)}
          onSent={(phone) => {
            setOpen(false);
            setSent({ phone, of: current });
          }}
        />
      )}
    </div>
  );
}

function SmsTestDialog({ unsaved, onClose, onSent }: { unsaved: boolean; onClose: () => void; onSent: (phone: string) => void }) {
  const t = useT();
  const [phone, setPhone] = useState('');
  // The phone as typed was checked here and is not an Uzbek mobile number (nothing was sent)
  const [badPhone, setBadPhone] = useState(false);
  const send = useMutation({ mutationFn: sendTestSms });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (send.isPending || phone.trim() === '') return;
    if (normalizeUzPhone(phone) === null) {
      send.reset();
      setBadPhone(true);
      return;
    }
    send.mutate(phone.trim(), { onSuccess: (result) => onSent(result.phone || phone.trim()) });
  };

  return (
    <Dialog open title={t('system_smsTest_title')} description={t('system_smsTest_text')} busy={send.isPending} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        {unsaved && <p className="text-footnote text-fg-muted">{t('system_smsTest_unsaved')}</p>}
        <Field label={t('system_smsTest_phone')} hint={t('system_smsTest_phoneHint')} error={badPhone ? t('system_smsTest_err_phone') : fieldError(send.error, 'phone')}>
          {(control) => (
            <input
              {...control}
              data-autofocus
              type="tel"
              inputMode="tel"
              autoComplete="tel"
              maxLength={32}
              className={fieldClass}
              value={phone}
              onChange={(event) => {
                setPhone(event.target.value);
                setBadPhone(false);
              }}
            />
          )}
        </Field>
        <FormError message={testErrorText(send.error, t)} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={send.isPending}>
            {t('cancel')}
          </Button>
          <Button type="submit" loading={send.isPending} disabled={phone.trim() === ''}>
            {t('system_smsTest_send')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
