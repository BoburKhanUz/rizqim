'use client';

import type { ReactNode } from 'react';
import { Button } from './Button';
import { Dialog } from './Dialog';

// A question the person has to answer before something is deleted or switched off. The server's refusal (a branch in
// use ...) is shown inside, so the dialog stays open and the reason is read where the click was. The words of the two
// stock buttons come from the app (this package has no language).
export function ConfirmDialog({
  title,
  text,
  confirmLabel,
  cancelLabel,
  closeLabel,
  busy,
  error,
  extra,
  tone = 'danger',
  onConfirm,
  onClose,
}: {
  title: string;
  text: string;
  confirmLabel: string;
  cancelLabel: string;
  closeLabel: string;
  busy: boolean;
  error?: string | null;
  extra?: ReactNode;
  // danger: delete / switch off (red button); primary: a safe confirmation
  tone?: 'danger' | 'primary';
  onConfirm: () => void;
  onClose: () => void;
}) {
  return (
    <Dialog
      open
      variant="center"
      role="alertdialog"
      title={title}
      description={text}
      busy={busy}
      closeLabel={closeLabel}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy} data-autofocus>
            {cancelLabel}
          </Button>
          <Button variant={tone} onClick={onConfirm} loading={busy}>
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
