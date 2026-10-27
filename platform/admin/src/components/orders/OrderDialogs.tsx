'use client';

import { useState, type FormEvent, type ReactNode } from 'react';
import { Button, Dialog, Field, fieldClass, FormError } from '@rizqim/ui';
import {
  idempotencyKey,
  useCancelOrder,
  useDecideRefund,
  useFixStatus,
  useMessageOrder,
  useRequestRefund,
  useUpdateSla,
  type OrderCard,
  type RefundRequest,
  type SlaSettings,
} from '@/lib/api/orders';
import { errorText, fieldError } from '@/lib/errors';
import { useT } from '../I18nProvider';
import { money, parseAmountInput } from './ordersModel';

// The action dialogs of Buyurtmalar. Each asks for a reason (3–500 characters, it goes into the audit log); the step-up
// (a fresh 2FA code) is asked by the shell when the backend wants it. Money dialogs send one Idempotency-Key per dialog.

const reasonOk = (reason: string) => reason.trim().length >= 3;

function ReasonForm({
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
        <Field label={t('orders_reason')} hint={t('orders_reasonHint')} error={reasonError}>
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

export function CancelDialog({ order, onDone, onClose }: { order: OrderCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const cancel = useCancelOrder();
  const [cancelReason, setCancelReason] = useState(order.actions.cancel_reasons[0]?.value ?? '');
  const [note, setNote] = useState('');
  return (
    <ReasonForm
      title={t('orders_cancelTitle')}
      text={t('orders_cancelText')}
      submitLabel={t('orders_cancelConfirm')}
      tone="danger"
      busy={cancel.isPending}
      error={cancel.error ? errorText(cancel.error, t) : null}
      reasonError={fieldError(cancel.error, 'reason')}
      canSubmit={cancelReason !== ''}
      onClose={onClose}
      onSubmit={(reason) =>
        cancel.mutate({ uuid: order.uuid, cancel_reason: cancelReason, note: note.trim() || undefined, reason }, { onSuccess: () => onDone(t('orders_cancelDone')) })
      }
    >
      <Field label={t('orders_cancelReason')} error={fieldError(cancel.error, 'cancel_reason')}>
        {(control) => (
          <select {...control} data-autofocus className={fieldClass} value={cancelReason} onChange={(event) => setCancelReason(event.target.value)}>
            {order.actions.cancel_reasons.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        )}
      </Field>
      <Field label={t('orders_cancelNote')}>
        {(control) => <input {...control} type="text" maxLength={500} className={fieldClass} value={note} onChange={(event) => setNote(event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}

export function StatusDialog({ order, onDone, onClose }: { order: OrderCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const fix = useFixStatus();
  const [status, setStatus] = useState(order.actions.transitions[0]?.status ?? '');
  return (
    <ReasonForm
      title={t('orders_statusTitle')}
      text={t('orders_statusText')}
      submitLabel={t('save')}
      busy={fix.isPending}
      error={fix.error ? errorText(fix.error, t) : null}
      reasonError={fieldError(fix.error, 'reason')}
      canSubmit={status !== ''}
      onClose={onClose}
      onSubmit={(reason) => fix.mutate({ uuid: order.uuid, status, reason }, { onSuccess: () => onDone(t('orders_statusDone')) })}
    >
      {order.actions.transitions.length === 0 ? (
        <p className="text-footnote text-fg-muted">{t('orders_statusNone')}</p>
      ) : (
        <Field label={t('orders_statusNew')}>
          {(control) => (
            <select {...control} data-autofocus className={fieldClass} value={status} onChange={(event) => setStatus(event.target.value)}>
              {order.actions.transitions.map((option) => (
                <option key={option.status} value={option.status}>
                  {option.label}
                </option>
              ))}
            </select>
          )}
        </Field>
      )}
    </ReasonForm>
  );
}

export function MessageDialog({ order, to, onDone, onClose }: { order: OrderCard; to: 'store' | 'customer'; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const message = useMessageOrder();
  const [text, setText] = useState('');
  return (
    <ReasonForm
      title={to === 'store' ? t('orders_messageTitleStore') : t('orders_messageTitleCustomer')}
      text={to === 'store' ? t('orders_messageStoreText') : t('orders_messageCustomerText')}
      submitLabel={t('orders_messageSend')}
      busy={message.isPending}
      error={message.error ? errorText(message.error, t) : null}
      reasonError={fieldError(message.error, 'reason')}
      canSubmit={text.trim() !== ''}
      onClose={onClose}
      onSubmit={(reason) => message.mutate({ uuid: order.uuid, to, text: text.trim(), reason }, { onSuccess: () => onDone(t('orders_messageSent')) })}
    >
      <Field label={t('orders_messageText')} error={fieldError(message.error, 'text')}>
        {(control) => <textarea {...control} data-autofocus required maxLength={1000} rows={4} className={fieldClass} value={text} onChange={(event) => setText(event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}

export function RefundRequestDialog({ order, onDone, onClose }: { order: OrderCard; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const request = useRequestRefund();
  const [key] = useState(idempotencyKey);
  const [amount, setAmount] = useState(String(Number(order.refunds.refundable)));
  const parsed = parseAmountInput(amount);
  const tooMuch = parsed !== null && Number(parsed) > Number(order.refunds.refundable);
  const amountError = amount !== '' && (parsed === null || tooMuch) ? t('orders_refundAmountInvalid', { amount: money(order.refunds.refundable) }) : fieldError(request.error, 'amount');
  return (
    <ReasonForm
      title={t('orders_refundTitle')}
      text={t('orders_refundText', { amount: money(order.refunds.refundable) })}
      submitLabel={t('orders_refundSend')}
      busy={request.isPending}
      error={request.error ? errorText(request.error, t) : null}
      reasonError={fieldError(request.error, 'reason')}
      canSubmit={parsed !== null && !tooMuch}
      onClose={onClose}
      onSubmit={(reason) => parsed && request.mutate({ uuid: order.uuid, amount: parsed, reason, key }, { onSuccess: () => onDone(t('orders_refundSent')) })}
    >
      <Field label={t('orders_refundAmount')} error={amountError}>
        {(control) => <input {...control} data-autofocus type="text" inputMode="decimal" className={fieldClass} value={amount} onChange={(event) => setAmount(event.target.value)} />}
      </Field>
    </ReasonForm>
  );
}

export function DecideRefundDialog({
  orderUuid,
  request,
  decision,
  onDone,
  onClose,
}: {
  orderUuid?: string;
  request: RefundRequest;
  decision: 'approve' | 'reject';
  onDone: (message: string) => void;
  onClose: () => void;
}) {
  const t = useT();
  const decide = useDecideRefund();
  const [key] = useState(idempotencyKey);
  const amount = money(request.amount);
  return (
    <ReasonForm
      title={decision === 'approve' ? t('orders_approveTitle') : t('orders_rejectTitle')}
      text={decision === 'approve' ? t('orders_approveText', { amount }) : t('orders_rejectText', { amount })}
      submitLabel={decision === 'approve' ? t('orders_approve') : t('orders_reject')}
      tone={decision === 'approve' ? 'primary' : 'danger'}
      busy={decide.isPending}
      error={decide.error ? errorText(decide.error, t) : null}
      reasonError={fieldError(decide.error, 'reason')}
      onClose={onClose}
      onSubmit={(reason) =>
        decide.mutate(
          { id: request.id, decision, reason, key, uuid: orderUuid },
          { onSuccess: () => onDone(decision === 'approve' ? t('orders_approved') : t('orders_rejected')) },
        )
      }
    />
  );
}

export function SlaSettingsDialog({ settings, onDone, onClose }: { settings: SlaSettings; onDone: (message: string) => void; onClose: () => void }) {
  const t = useT();
  const update = useUpdateSla();
  const [values, setValues] = useState({ accept: String(settings.limits.accept), prepare: String(settings.limits.prepare), deliver: String(settings.limits.deliver) });
  const stages = [
    { key: 'accept', label: t('orders_slaAccept'), hint: t('orders_slaAcceptHint') },
    { key: 'prepare', label: t('orders_slaPrepare'), hint: t('orders_slaPrepareHint') },
    { key: 'deliver', label: t('orders_slaDeliver'), hint: t('orders_slaDeliverHint') },
  ] as const;
  const valid = stages.every(({ key }) => {
    const value = Number(values[key]);
    const [min, max] = settings.bounds[key];
    return Number.isInteger(value) && value >= min && value <= max;
  });
  return (
    <ReasonForm
      title={t('orders_slaSettings')}
      text={t('orders_slaSettingsText')}
      submitLabel={t('save')}
      busy={update.isPending}
      error={update.error ? errorText(update.error, t) : null}
      reasonError={fieldError(update.error, 'reason')}
      canSubmit={valid}
      onClose={onClose}
      onSubmit={(reason) =>
        update.mutate({ accept: Number(values.accept), prepare: Number(values.prepare), deliver: Number(values.deliver), reason }, { onSuccess: () => onDone(t('orders_slaSaved')) })
      }
    >
      {stages.map(({ key, label, hint }, index) => (
        <Field key={key} label={label} hint={`${hint} · ${t('orders_slaBounds', { min: settings.bounds[key][0], max: settings.bounds[key][1] })}`} error={fieldError(update.error, key)}>
          {(control) => (
            <input
              {...control}
              {...(index === 0 ? { 'data-autofocus': true } : {})}
              type="number"
              inputMode="numeric"
              min={settings.bounds[key][0]}
              max={settings.bounds[key][1]}
              className={fieldClass}
              value={values[key]}
              onChange={(event) => setValues((previous) => ({ ...previous, [key]: event.target.value }))}
            />
          )}
        </Field>
      ))}
    </ReasonForm>
  );
}
