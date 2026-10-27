'use client';

import { useState, type FormEvent, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { Eye, EyeOff } from 'lucide-react';
import { Button, Field, fieldClass, FormError } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { acceptInvite, confirmTwoFactorSetup, login, startTwoFactorSetup, verifyTwoFactor } from '@/lib/api/endpoints';
import type { Challenge, TwoFactorSetup } from '@/lib/api/types';
import { authErrorText, errorText, fieldError } from '@/lib/errors';
import { useAuth } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { RecoveryCodesPanel } from './RecoveryCodesPanel';
import { SetupStep } from './SetupStep';

type Step =
  | { name: 'credentials' }
  // The invited member chooses a password (invite page)
  | { name: 'password' }
  | { name: 'code'; challenge: string; recovery: boolean }
  | { name: 'setup'; challenge: string; setup: TwoFactorSetup | null }
  | { name: 'codes'; codes: string[] };

const MIN_PASSWORD = 12;
const EXPIRED_CODES = ['challenge_expired', 'challenge_invalid', 'challenge_used'];

// Email and password → the 6-digit code from the phone app (or the 2FA setup the first time) → the control center. The same
// flow serves the invite page: a new member chooses a password first, then sets up 2FA. The session token never reaches this
// code: the proxy keeps it in an httpOnly cookie.
export function LoginFlow({ inviteToken }: { inviteToken?: string }) {
  const t = useT();
  const router = useRouter();
  const auth = useAuth();
  const [step, setStep] = useState<Step>(inviteToken ? { name: 'password' } : { name: 'credentials' });
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [repeat, setRepeat] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const restart = (message?: string) => {
    setStep(inviteToken ? { name: 'password' } : { name: 'credentials' });
    setPassword('');
    setRepeat('');
    setCode('');
    setRecoveryCode('');
    setError(null);
    setNotice(message ?? null);
  };

  // A challenge lives five minutes and works once: when the server says so, begin again
  const challengeGone = (failure: unknown) => failure instanceof ApiError && (EXPIRED_CODES.includes(failure.code) || failure.status === 410);

  async function enter(challenge: Challenge) {
    if (challenge.status === 'setup_required') {
      setStep({ name: 'setup', challenge: challenge.challenge_token, setup: null });
      try {
        const setup = await startTwoFactorSetup(challenge.challenge_token);
        setStep({ name: 'setup', challenge: challenge.challenge_token, setup });
      } catch (failure) {
        if (challengeGone(failure)) restart(t('auth_challengeExpired'));
        else {
          restart();
          setError(errorText(failure, t));
        }
      }
    } else {
      setStep({ name: 'code', challenge: challenge.challenge_token, recovery: false });
    }
  }

  async function run(action: () => Promise<void>) {
    if (busy) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await action();
    } finally {
      setBusy(false);
    }
  }

  const submitCredentials = (event: FormEvent) => {
    event.preventDefault();
    if (!email.trim() || !password) {
      setError(t('auth_invalidInput'));
      return;
    }
    void run(async () => {
      try {
        await enter(await login(email.trim(), password));
        setPassword('');
      } catch (failure) {
        setError(authErrorText(failure, t, t('auth_badCredentials')));
      }
    });
  };

  const submitPassword = (event: FormEvent) => {
    event.preventDefault();
    if (password.length < MIN_PASSWORD) {
      setError(t('auth_passwordShort'));
      return;
    }
    if (password !== repeat) {
      setError(t('auth_passwordMismatch'));
      return;
    }
    void run(async () => {
      try {
        await enter(await acceptInvite(inviteToken ?? '', password));
        setPassword('');
        setRepeat('');
      } catch (failure) {
        if (failure instanceof ApiError && !failure.unreachable && failure.status !== 429 && failure.status < 500 && !fieldError(failure, 'password')) setError(failure.message || t('auth_inviteExpired'));
        else setError(fieldError(failure, 'password') ?? errorText(failure, t));
      }
    });
  };

  const submitCode = (event: FormEvent) => {
    event.preventDefault();
    if (step.name !== 'code') return;
    const proof = step.recovery ? { recovery_code: recoveryCode.trim() } : { code };
    if (step.recovery ? proof.recovery_code === '' : !/^\d{6}$/.test(code)) {
      setError(step.recovery ? t('auth_recoveryInvalid') : t('auth_codeInvalid'));
      return;
    }
    void run(async () => {
      try {
        await verifyTwoFactor(step.challenge, proof);
        auth.start();
        router.replace('/');
      } catch (failure) {
        if (challengeGone(failure)) restart(t('auth_challengeExpired'));
        else setError(authErrorText(failure, t, step.recovery ? t('auth_recoveryWrong') : t('auth_codeWrong')));
      }
    });
  };

  const confirmSetup = (confirmCode: string) => {
    if (step.name !== 'setup') return;
    void run(async () => {
      try {
        const result = await confirmTwoFactorSetup(step.challenge, confirmCode);
        setStep({ name: 'codes', codes: result.recovery_codes });
      } catch (failure) {
        if (challengeGone(failure)) restart(t('auth_challengeExpired'));
        else setError(authErrorText(failure, t, t('auth_codeWrong')));
      }
    });
  };

  const finish = () => {
    auth.start();
    router.replace('/');
  };

  const passwordToggle = (
    <Button variant="ghost" aria-pressed={showPassword} aria-label={showPassword ? t('auth_hidePassword') : t('auth_showPassword')} icon={showPassword ? EyeOff : Eye} onClick={() => setShowPassword((shown) => !shown)} />
  );

  const banner = (
    <>
      {notice && (
        <p role="status" className="rounded-control bg-info-soft px-3 py-2.5 text-footnote font-bold text-info-soft-fg">
          {notice}
        </p>
      )}
    </>
  );

  const renderStep = (): ReactNode => {
    if (step.name === 'credentials') {
      return (
        <form onSubmit={submitCredentials} noValidate className="flex flex-col gap-5">
          {banner}
          <Field label={t('auth_email')}>
            {(control) => <input {...control} className={fieldClass} type="email" autoComplete="username" autoFocus inputMode="email" value={email} onChange={(event) => setEmail(event.target.value)} />}
          </Field>
          <Field label={t('auth_password')}>
            {(control) => (
              <div className="flex items-stretch gap-2">
                <input {...control} className={`${fieldClass} min-w-0 flex-1`} type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} />
                {passwordToggle}
              </div>
            )}
          </Field>
          <FormError message={error} />
          <Button type="submit" size="lg" fullWidth loading={busy}>
            {t('auth_login')}
          </Button>
        </form>
      );
    }

    if (step.name === 'password') {
      return (
        <form onSubmit={submitPassword} noValidate className="flex flex-col gap-5">
          <Field label={t('auth_newPassword')} hint={t('auth_passwordHint')}>
            {(control) => (
              <div className="flex items-stretch gap-2">
                <input {...control} className={`${fieldClass} min-w-0 flex-1`} type={showPassword ? 'text' : 'password'} autoComplete="new-password" autoFocus value={password} onChange={(event) => setPassword(event.target.value)} />
                {passwordToggle}
              </div>
            )}
          </Field>
          <Field label={t('auth_confirmPassword')}>
            {(control) => <input {...control} className={fieldClass} type={showPassword ? 'text' : 'password'} autoComplete="new-password" value={repeat} onChange={(event) => setRepeat(event.target.value)} />}
          </Field>
          <FormError message={error} />
          <Button type="submit" size="lg" fullWidth loading={busy}>
            {t('auth_inviteAccept')}
          </Button>
        </form>
      );
    }

    if (step.name === 'code') {
      return (
        <form onSubmit={submitCode} noValidate className="flex flex-col gap-5">
          <p className="text-footnote text-fg-muted">{step.recovery ? t('auth_recoveryHint') : t('auth_codeHint')}</p>
          {step.recovery ? (
            <Field label={t('auth_recoveryLabel')}>
              {(control) => <input {...control} className={`${fieldClass} font-mono`} type="text" autoComplete="off" autoCapitalize="none" spellCheck={false} autoFocus value={recoveryCode} onChange={(event) => setRecoveryCode(event.target.value)} />}
            </Field>
          ) : (
            <Field label={t('auth_codeLabel')}>
              {(control) => (
                <input
                  {...control}
                  className={`${fieldClass} tracking-[0.4em]`}
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={6}
                  placeholder="------"
                  autoFocus
                  value={code}
                  onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                />
              )}
            </Field>
          )}
          <FormError message={error} />
          <Button type="submit" size="lg" fullWidth loading={busy}>
            {t('auth_verify')}
          </Button>
          <div className="flex flex-col gap-1">
            <Button
              variant="ghost"
              disabled={busy}
              onClick={() => {
                setStep({ ...step, recovery: !step.recovery });
                setError(null);
              }}
            >
              {step.recovery ? t('auth_useApp') : t('auth_useRecovery')}
            </Button>
            <Button variant="ghost" disabled={busy} onClick={() => restart()}>
              {t('auth_startOver')}
            </Button>
          </div>
        </form>
      );
    }

    if (step.name === 'setup') {
      return <SetupStep setup={step.setup} busy={busy} error={error} onConfirm={confirmSetup} />;
    }

    return (
      <div className="flex flex-col gap-5">
        <p className="text-footnote text-fg-muted">{t('auth_recoveryIntro')}</p>
        <RecoveryCodesPanel codes={step.codes} />
        <label className="flex min-h-11 cursor-pointer items-start gap-3 rounded-control py-2">
          <input type="checkbox" checked={saved} onChange={(event) => setSaved(event.target.checked)} className="mt-0.5 size-5 shrink-0 accent-[var(--rz-brand)]" />
          <span className="text-body text-fg">{t('auth_recoverySaved')}</span>
        </label>
        <Button size="lg" fullWidth disabled={!saved} onClick={finish}>
          {t('auth_recoveryEnter')}
        </Button>
      </div>
    );
  };

  const subtitleKey = step.name === 'credentials' ? 'auth_loginSubtitle' : step.name === 'password' ? 'auth_inviteSubtitle' : step.name === 'code' ? null : null;

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="text-headline font-bold text-fg">{t(stepTitleKey(step.name, Boolean(inviteToken)))}</h1>
        {subtitleKey && <p className="mt-1 text-footnote text-fg-muted">{t(subtitleKey)}</p>}
      </div>
      {renderStep()}
    </div>
  );
}

// What the screen around the flow says above it, per step
export function stepTitleKey(step: Step['name'], invite: boolean): 'auth_loginTitle' | 'auth_inviteTitle' | 'auth_codeTitle' | 'auth_setupTitle' | 'auth_recoveryTitle' {
  if (step === 'code') return 'auth_codeTitle';
  if (step === 'setup') return 'auth_setupTitle';
  if (step === 'codes') return 'auth_recoveryTitle';
  return invite ? 'auth_inviteTitle' : 'auth_loginTitle';
}
