'use client';

import { useState, type FormEvent } from 'react';
import { Copy, ShieldCheck } from 'lucide-react';
import { Button, Field, fieldClass, Spinner } from '@rizqim/ui';
import type { TwoFactorSetup } from '@/lib/api/types';
import { copyText } from '@/lib/clipboard';
import { useT } from '../I18nProvider';

// A data URI of the server's SVG: inside an <img> an SVG cannot run scripts or reach the page
export function svgDataUri(svg: string): string | null {
  const text = svg.trim();
  return /^(<\?xml[^>]*\?>\s*)?<svg[\s>]/i.test(text) ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(text)}` : null;
}

// Step "2FA o'rnatish": the QR code, the key to type by hand, and the first code that proves the app works
export function SetupStep({
  setup,
  busy,
  error,
  onConfirm,
}: {
  // null while the server prepares the secret
  setup: TwoFactorSetup | null;
  busy: boolean;
  error: string | null;
  onConfirm: (code: string) => void;
}) {
  const t = useT();
  const [code, setCode] = useState('');
  const [copied, setCopied] = useState(false);
  const [localError, setLocalError] = useState<string | null>(null);

  function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    if (!/^\d{6}$/.test(code)) {
      setLocalError(t('auth_codeInvalid'));
      return;
    }
    setLocalError(null);
    onConfirm(code);
  }

  async function copySecret() {
    if (setup && (await copyText(setup.secret))) {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    }
  }

  if (!setup) {
    return (
      <div role="status" className="flex items-center gap-3 py-6 text-fg-muted">
        <Spinner />
        <span className="text-body">{t('auth_setupLoading')}</span>
      </div>
    );
  }

  const qr = svgDataUri(setup.qr_svg);
  return (
    <form onSubmit={submit} noValidate className="flex flex-col gap-5">
      <p className="text-footnote text-fg-muted">{t('auth_setupIntro')}</p>
      <p className="text-footnote font-bold text-fg">{t('auth_setupStep1')}</p>
      {qr && (
        // eslint-disable-next-line @next/next/no-img-element -- an inline SVG data URI, nothing to optimise; the white box is a QR code's quiet zone, it must stay white in dark mode
        <img src={qr} alt={t('auth_qrAlt')} width={192} height={192} className="mx-auto size-48 rounded-card border border-line bg-white p-2" />
      )}
      <Field label={t('auth_secretLabel')}>
        {(control) => (
          <div className="flex items-stretch gap-2">
            <input {...control} readOnly value={setup.secret} className={`${fieldClass} min-w-0 flex-1 font-mono tracking-wider`} onFocus={(event) => event.currentTarget.select()} />
            <Button variant="secondary" icon={Copy} aria-label={t('auth_secretCopy')} onClick={() => void copySecret()}>
              {copied ? t('copied') : t('copy')}
            </Button>
          </div>
        )}
      </Field>
      <p className="text-footnote font-bold text-fg">{t('auth_setupStep2')}</p>
      <Field label={t('auth_codeLabel')} error={localError ?? error}>
        {(control) => (
          <input
            {...control}
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
      <Button type="submit" size="lg" fullWidth icon={ShieldCheck} loading={busy}>
        {t('auth_setupConfirm')}
      </Button>
    </form>
  );
}
