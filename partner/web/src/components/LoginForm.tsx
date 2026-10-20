'use client';

import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { Button, Field, fieldClass } from '@rizqim/ui';
import { login, sendCode, SessionError } from '@/lib/session/client';
import { formatNationalInput, nationalDigits, PHONE_PREFIX, toE164 } from '@/lib/phone';
import type { Translate } from '@/i18n';
import { useAuth } from './AuthProvider';
import { useT } from './I18nProvider';

// The backend sends one code a minute per number
const RESEND_SECONDS = 60;

function messageFor(error: unknown, t: Translate, fallback: string): string {
  if (!(error instanceof SessionError)) return t('unknownError');
  if (error.status === 0 || error.reason === 'unreachable') return t('networkError');
  if (error.status === 429) return t('rateLimited');
  // The backend words its refusals ("Kod noto'g'ri", "Bugun urinishlar soni tugadi") in Uzbek already
  return error.message || fallback;
}

// Phone number → SMS code → signed in. The refresh token never reaches this code: the server sets it as an httpOnly
// cookie (app/api/session/login).
export function LoginForm() {
  const t = useT();
  const router = useRouter();
  const auth = useAuth();
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [phoneInput, setPhoneInput] = useState('');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [cooldown, setCooldown] = useState(0);
  const codeRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (cooldown <= 0) return;
    const timer = window.setTimeout(() => setCooldown((seconds) => seconds - 1), 1000);
    return () => window.clearTimeout(timer);
  }, [cooldown]);

  useEffect(() => {
    if (step === 'code') codeRef.current?.focus();
  }, [step]);

  async function requestCode() {
    const national = nationalDigits(phoneInput);
    if (!national) {
      setError(t('invalidPhone'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await sendCode(toE164(national));
      setPhone(toE164(national));
      setCode('');
      setStep('code');
      setCooldown(RESEND_SECONDS);
    } catch (failure) {
      setError(messageFor(failure, t, t('unknownError')));
    } finally {
      setBusy(false);
    }
  }

  async function verify() {
    if (!/^\d{6}$/.test(code)) {
      setError(t('invalidCode'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await login(phone, code);
      auth.start();
      router.replace('/');
    } catch (failure) {
      setError(messageFor(failure, t, t('invalidCode')));
      setBusy(false);
    }
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    void (step === 'phone' ? requestCode() : verify());
  }

  return (
    <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5">
      {step === 'phone' ? (
        <Field label={t('phoneLabel')} hint={t('phoneHint')} error={error}>
          {(control) => (
            <div className="flex items-stretch gap-2">
              <span className="flex min-h-11 items-center rounded-control border border-line bg-surface-2 px-3 text-headline text-fg-muted">{PHONE_PREFIX}</span>
              <input
                {...control}
                className={fieldClass}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                autoFocus
                placeholder="90 123 45 67"
                value={phoneInput}
                onChange={(event) => setPhoneInput(formatNationalInput(event.target.value))}
              />
            </div>
          )}
        </Field>
      ) : (
        <>
          <p className="text-footnote text-fg-muted">{t('codeSent', { phone })}</p>
          <Field label={t('codeLabel')} error={error}>
            {(control) => (
              <input
                {...control}
                ref={codeRef}
                className={`${fieldClass} tracking-[0.4em]`}
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                placeholder="------"
                value={code}
                onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
              />
            )}
          </Field>
        </>
      )}

      <Button type="submit" size="lg" fullWidth loading={busy}>
        {step === 'phone' ? t('sendCode') : t('verify')}
      </Button>

      {step === 'code' && (
        <div className="flex flex-col gap-1">
          <Button variant="ghost" disabled={busy || cooldown > 0} onClick={() => void requestCode()}>
            {cooldown > 0 ? t('resendIn', { seconds: cooldown }) : t('resend')}
          </Button>
          <Button
            variant="ghost"
            disabled={busy}
            onClick={() => {
              setStep('phone');
              setError(null);
            }}
          >
            {t('changePhone')}
          </Button>
        </div>
      )}
    </form>
  );
}
