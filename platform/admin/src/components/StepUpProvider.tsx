'use client';

import { useCallback, useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { ShieldCheck } from 'lucide-react';
import { Button, Dialog, Field, fieldClass } from '@rizqim/ui';
import { setStepUpHandler } from '@/lib/api/client';
import { confirmStepUp } from '@/lib/api/endpoints';
import { rememberAcceptedCode, wasJustUsed } from '@/lib/api/usedCodes';
import { authErrorText } from '@/lib/errors';
import { useT } from './I18nProvider';

// Any call the backend answers with 403 reauth_required opens this dialog (lib/api/client.ts calls the handler registered
// here): the member types a fresh 6-digit code, POST /auth/confirm marks the session confirmed for 10 minutes, and the call
// that asked is repeated once. Closing the dialog leaves the call refused, as the server said.
export function StepUpProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  // Calls that need the confirmation while the dialog is already open wait for the same answer
  const pending = useRef<Promise<boolean> | null>(null);
  const settle = useRef<((confirmed: boolean) => void) | null>(null);

  const ask = useCallback((): Promise<boolean> => {
    if (!pending.current) {
      pending.current = new Promise<boolean>((resolve) => {
        settle.current = (confirmed) => {
          pending.current = null;
          settle.current = null;
          setOpen(false);
          resolve(confirmed);
        };
      });
      setOpen(true);
    }
    return pending.current;
  }, []);

  useEffect(() => {
    setStepUpHandler(ask);
    return () => setStepUpHandler(null);
  }, [ask]);

  return (
    <>
      {children}
      {open && <StepUpDialog onDone={(confirmed) => settle.current?.(confirmed)} />}
    </>
  );
}

function StepUpDialog({ onDone }: { onDone: (confirmed: boolean) => void }) {
  const t = useT();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    if (!/^\d{6}$/.test(code)) {
      setError(t('auth_codeInvalid'));
      return;
    }
    // The code that just signed the member in cannot be used again: the next one comes in at most 30 seconds
    if (wasJustUsed(code)) {
      setError(t('auth_codeUsed'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await confirmStepUp(code);
      rememberAcceptedCode(code);
      onDone(true);
    } catch (failure) {
      setError(authErrorText(failure, t, t('auth_codeWrong')));
      setBusy(false);
    }
  }

  return (
    <Dialog
      open
      variant="center"
      role="alertdialog"
      title={t('auth_stepUpTitle')}
      description={t('auth_stepUpText')}
      busy={busy}
      closeLabel={t('close')}
      onClose={() => onDone(false)}
    >
      <form onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
        <Field label={t('auth_codeLabel')} error={error}>
          {(control) => (
            <input
              {...control}
              data-autofocus
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
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={() => onDone(false)} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" icon={ShieldCheck} loading={busy}>
            {t('auth_stepUpConfirm')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
