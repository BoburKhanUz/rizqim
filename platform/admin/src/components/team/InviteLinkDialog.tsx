'use client';

import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { Button, Dialog, Field, fieldClass } from '@rizqim/ui';
import type { InviteLink } from '@/lib/api/types';
import { copyText } from '@/lib/clipboard';
import { when } from '@/lib/format';
import { useT } from '../I18nProvider';

// The one-time invite link: shown here once, with a copy button. Closing the dialog forgets it (the server cannot show it again;
// "Taklifni qayta yuborish" makes a new one).
export function InviteLinkDialog({ name, link, onClose }: { name: string; link: InviteLink; onClose: () => void }) {
  const t = useT();
  const [copied, setCopied] = useState(false);

  async function copy() {
    if (await copyText(link.url)) {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2500);
    }
  }

  return (
    <Dialog
      open
      title={t('team_linkTitle')}
      description={link.expires_at ? t('team_linkText', { name, time: when(link.expires_at) }) : t('team_linkTextNoTime', { name })}
      closeLabel={t('close')}
      onClose={onClose}
      footer={
        <Button onClick={onClose} data-autofocus>
          {t('team_linkDone')}
        </Button>
      }
    >
      {link.url ? (
        <Field label={t('team_linkLabel')}>
          {(control) => (
            <div className="flex items-stretch gap-2">
              <input {...control} readOnly value={link.url} className={`${fieldClass} min-w-0 flex-1 font-mono text-footnote`} onFocus={(event) => event.currentTarget.select()} />
              <Button variant="secondary" icon={copied ? Check : Copy} aria-label={t('team_linkCopy')} onClick={() => void copy()}>
                {copied ? t('copied') : t('copy')}
              </Button>
            </div>
          )}
        </Field>
      ) : (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote font-bold text-danger-soft-fg">
          {t('team_linkMissing')}
        </p>
      )}
    </Dialog>
  );
}
