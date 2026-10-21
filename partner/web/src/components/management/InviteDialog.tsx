'use client';

import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { Button, Dialog } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';

// The Telegram link a new member joins through. The staff list does not carry it, so this is the one place to copy it.
export function InviteDialog({ name, link, phoneSet, onClose }: { name: string; link: string; phoneSet: boolean; onClose: () => void }) {
  const { t } = useI18n();
  const [copied, setCopied] = useState(false);
  const [copyFailed, setCopyFailed] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
      setCopyFailed(false);
    } catch {
      setCopyFailed(true);
    }
  };

  return (
    <Dialog
      open
      title={t('staff_inviteTitle', { name })}
      description={t('staff_inviteText')}
      closeLabel={t('mgmt_close')}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" icon={copied ? Check : Copy} onClick={() => void copy()}>
            {copied ? t('mgmt_copied') : t('mgmt_copy')}
          </Button>
          <Button onClick={onClose} data-autofocus>
            {t('mgmt_done')}
          </Button>
        </>
      }
    >
      <input readOnly aria-label={t('staff_inviteLink')} value={link} onFocus={(event) => event.currentTarget.select()} className="min-h-11 w-full rounded-control border border-line bg-surface-2 px-3 text-footnote text-fg" />
      {copyFailed && <p role="alert" className="text-footnote text-danger-soft-fg">{t('mgmt_copyFailed')}</p>}
      {phoneSet && <p className="text-footnote text-fg-muted">{t('staff_inviteSms')}</p>}
      <p className="text-footnote text-fg-muted">{t('staff_inviteOnce')}</p>
    </Dialog>
  );
}
