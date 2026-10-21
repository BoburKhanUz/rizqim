'use client';

import type { ReactNode } from 'react';
import { Button, Dialog } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';

// A question the person has to answer before something is deleted or switched off. The server's refusal (a branch in
// use ...) is shown inside, so the dialog stays open and the reason is read where the click was.
export function ConfirmDialog({
  title,
  text,
  confirmLabel,
  busy,
  error,
  extra,
  onConfirm,
  onClose,
}: {
  title: string;
  text: string;
  confirmLabel: string;
  busy: boolean;
  error?: string | null;
  extra?: ReactNode;
  onConfirm: () => void;
  onClose: () => void;
}) {
  const { t } = useI18n();
  return (
    <Dialog
      open
      variant="center"
      role="alertdialog"
      title={title}
      description={text}
      busy={busy}
      closeLabel={t('mgmt_close')}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy} data-autofocus>
            {t('mgmt_cancel')}
          </Button>
          <Button variant="danger" onClick={onConfirm} loading={busy}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      {error && (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote font-bold text-danger-soft-fg">
          {error}
        </p>
      )}
      {extra}
    </Dialog>
  );
}
