'use client';

import { useState, type FormEvent, type ReactNode } from 'react';
import { Check, Copy } from 'lucide-react';
import { Button, Dialog, Field, fieldClass, FormError, Notice, Switch } from '@rizqim/ui';
import { copyText } from '@/lib/clipboard';
import { useMessageOwner, useStoreAction, useTelegramLink, type StoreCard } from '@/lib/api/stores';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';

// The action dialogs of a store's 360° card. Each asks for a reason (3–500 characters, it goes into the audit log); the
// step-up (a fresh 2FA code) is asked by the shell when the backend wants it (403 reauth_required).

const reasonOk = (reason: string) => reason.trim().length >= 3;

export function ReasonForm({
  title,
  text,
  submitLabel,
  tone = 'primary',
  busy,
  error,
  reasonError,
  canSubmit = true,
  onSubmit,
  onClose,
  children,
}: {
  title: string;
  text?: ReactNode;
  submitLabel: string;
  tone?: 'primary' | 'danger';
  busy: boolean;
  error: string | null;
  reasonError?: string;
  canSubmit?: boolean;
  onSubmit: (reason: string) => void;
  onClose: () => void;
  children?: ReactNode;
}) {
  const t = useT();
  const [reason, setReason] = useState('');
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!busy && canSubmit && reasonOk(reason)) onSubmit(reason.trim());
  };
  return (
    <Dialog open title={title} description={text} busy={busy} closeLabel={t('close')} onClose={onClose}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        {children}
        <Field label={t('stores_reason')} hint={t('stores_reasonHint')} error={reasonError}>
          {(control) => <textarea {...control} required minLength={3} maxLength={500} rows={2} className={fieldClass} value={reason} onChange={(event) => setReason(event.target.value)} />}
        </Field>
        <FormError message={error} />
        <div className="flex gap-2 [&>*]:flex-1">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('cancel')}
          </Button>
          <Button type="submit" variant={tone} loading={busy} disabled={!canSubmit || !reasonOk(reason)}>
            {submitLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

export type CardDialog = 'suspend' | 'activate' | 'credit_limit' | 'display' | 'telegram_link' | 'message_owner';

export function StoreActionDialog({ kind, store, onDone, onClose }: { kind: CardDialog; store: StoreCard; onDone: (message: string) => void; onClose: () => void }) {
  switch (kind) {
    case 'suspend':
    case 'activate':
      return <StatusDialog kind={kind} store={store} onDone={onDone} onClose={onClose} />;
    case 'credit_limit':
      return <CreditLimitDialog store={store} onDone={onDone} onClose={onClose} />;
    case 'display':
      return <DisplayDialog store={store} onDone={onDone} onClose={onClose} />;
    case 'telegram_link':
      return <TelegramLinkDialog store={store} onClose={onClose} />;
    case 'message_owner':
      return <MessageOwnerDialog store={store} onDone={onDone} onClose={onClose} />;
  }
}

function StatusDialog({ kind, store, onDone, onClose }: { kind: 'suspend' | 'activate'; store: StoreCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const action = useStoreAction(store.profile.id);
  const suspend = kind === 'suspend';
  return (
    <ReasonForm
      title={t(suspend ? 'stores_suspendTitle' : 'stores_activateTitle')}
      text={t(suspend ? 'stores_suspendText' : 'stores_activateText')}
      submitLabel={t(suspend ? 'stores_suspendConfirm' : 'stores_activate')}
      tone={suspend ? 'danger' : 'primary'}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ kind, reason }, { onSuccess: () => onDone(t(suspend ? 'stores_suspendDone' : 'stores_activateDone')) })}
    />
  );
}

function CreditLimitDialog({ store, onDone, onClose }: { store: StoreCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const action = useStoreAction(store.profile.id);
  const [limit, setLimit] = useState(String(Math.round(Number(store.finance.credit_limit))));
  const amount = limit.replace(/\s+/g, '').replace(',', '.');
  const valid = /^\d{1,9}(\.\d{1,2})?$/.test(amount);
  return (
    <ReasonForm
      title={t('stores_creditTitle')}
      text={t('stores_creditText')}
      submitLabel={t('save')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={valid}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ kind: 'credit_limit', credit_limit: amount, reason }, { onSuccess: () => onDone(t('stores_creditDone')) })}
    >
      <Field label={t('stores_creditLabel')} error={!valid && limit !== '' ? t('stores_creditInvalid') : fieldError(action.error, 'credit_limit')}>
        {(control) => <input {...control} data-autofocus type="text" inputMode="decimal" className={fieldClass} value={limit} onChange={(event) => setLimit(event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}

function DisplayDialog({ store, onDone, onClose }: { store: StoreCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const action = useStoreAction(store.profile.id);
  const [premium, setPremium] = useState(store.profile.is_premium);
  const [featured, setFeatured] = useState(store.profile.is_featured);
  return (
    <ReasonForm
      title={t('stores_displayTitle')}
      submitLabel={t('save')}
      busy={action.isPending}
      error={action.error ? errorText(action.error, t) : null}
      reasonError={fieldError(action.error, 'reason')}
      canSubmit={premium !== store.profile.is_premium || featured !== store.profile.is_featured}
      onClose={onClose}
      onSubmit={(reason) => action.mutate({ kind: 'display', is_premium: premium, is_featured: featured, reason }, { onSuccess: () => onDone(t('stores_displayDone')) })}
    >
      <Switch checked={premium} onChange={setPremium} label={t('stores_displayPremium')} />
      <Switch checked={featured} onChange={setFeatured} label={t('stores_displayFeatured')} />
    </ReasonForm>
  );
}

function TelegramLinkDialog({ store, onClose }: { store: StoreCard; onClose: () => void }) {
  const t = useT();
  const link = useTelegramLink(store.profile.id);
  const [copied, setCopied] = useState(false);
  if (link.data && link.data.invite_link === null) {
    return (
      <Dialog open title={t('stores_linkSentTitle')} description={t('stores_linkSent')} closeLabel={t('close')} onClose={onClose}>
        <Button onClick={onClose}>{t('stores_close')}</Button>
      </Dialog>
    );
  }
  if (link.data?.invite_link) {
    const inviteLink = link.data.invite_link;
    return (
      <Dialog open title={t('stores_linkReady')} closeLabel={t('close')} onClose={onClose}>
        <div className="flex flex-col gap-4">
          <Field label={t('stores_telegramLink')}>{(control) => <input {...control} readOnly className={fieldClass} value={inviteLink} onFocus={(event) => event.target.select()} />}</Field>
          <Notice message={copied ? t('stores_linkCopied') : null} />
          <div className="flex gap-2 [&>*]:flex-1">
            <Button variant="secondary" onClick={onClose}>
              {t('stores_close')}
            </Button>
            <Button
              icon={copied ? Check : Copy}
              onClick={() => {
                void copyText(inviteLink).then((done) => setCopied(done));
              }}
            >
              {t('stores_linkCopy')}
            </Button>
          </div>
        </div>
      </Dialog>
    );
  }
  return (
    <ReasonForm
      title={t('stores_linkTitle')}
      text={t('stores_linkText')}
      submitLabel={t('stores_linkCreate')}
      busy={link.isPending}
      error={link.error ? errorText(link.error, t) : null}
      reasonError={fieldError(link.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) => link.mutate(reason)}
    />
  );
}

function MessageOwnerDialog({ store, onDone, onClose }: { store: StoreCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const send = useMessageOwner(store.profile.id);
  const [text, setText] = useState('');
  return (
    <ReasonForm
      title={t('stores_messageTitle')}
      text={t('stores_messageText')}
      submitLabel={t('stores_messageSend')}
      busy={send.isPending}
      error={send.error ? errorText(send.error, t) : null}
      reasonError={fieldError(send.error, 'reason')}
      canSubmit={text.trim() !== ''}
      onClose={onClose}
      onSubmit={(reason) => send.mutate({ text: text.trim(), reason }, { onSuccess: () => onDone(t('stores_messageDone')) })}
    >
      <Field label={t('stores_messageLabel')} error={fieldError(send.error, 'text')}>
        {(control) => <textarea {...control} data-autofocus rows={4} maxLength={1000} className={fieldClass} value={text} onChange={(event) => setText(event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}
