'use client';

import { useState } from 'react';
import { Field, fieldClass } from '@rizqim/ui';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { ReasonDialog } from './parts';

// How a secret shows (never in full: "kiritilgan, oxiri ••••9876") and the dialog that replaces one. The new value is sent once
// and is not kept anywhere in the page after the dialog closes.

export function SecretText({ item }: { item: { configured: boolean; last4: string | null } }) {
  const t = useT();
  if (!item.configured) return <span className="text-fg-muted">{t('system_secret_unset')}</span>;
  return <span className="font-mono">{item.last4 ? t('system_secret_last4', { last4: item.last4 }) : t('system_secret_short')}</span>;
}

export function SourceText({ source }: { source: string | null }) {
  const t = useT();
  if (source === 'settings') return <>{t('system_source_settings')}</>;
  if (source === 'env') return <>{t('system_source_env')}</>;
  if (source === 'default') return <>{t('system_source_default')}</>;
  return <>{t('system_source_none')}</>;
}

export function SecretDialog({
  name,
  busy,
  error,
  onSubmit,
  onClose,
}: {
  name: string;
  busy: boolean;
  error: unknown;
  onSubmit: (value: string, reason: string) => void;
  onClose: () => void;
}) {
  const t = useT();
  const [value, setValue] = useState('');
  return (
    <ReasonDialog
      title={t('system_secret_replaceTitle', { name })}
      text={t('system_secret_replaceText')}
      submitLabel={t('system_secret_replace')}
      busy={busy}
      canSubmit={value.trim() !== ''}
      error={error && !fieldError(error, 'value') ? errorText(error, t) : null}
      reasonError={fieldError(error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => onSubmit(value.trim(), reason)}
    >
      <Field label={t('system_secret_newValue')} error={fieldError(error, 'value')}>
        {(control) => <input {...control} data-autofocus type="password" autoComplete="new-password" spellCheck={false} maxLength={500} className={fieldClass} value={value} onChange={(event) => setValue(event.target.value)} />}
      </Field>
    </ReasonDialog>
  );
}
