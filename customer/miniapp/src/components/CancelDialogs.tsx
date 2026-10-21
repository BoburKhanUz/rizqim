import { useId, useState } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { CANCEL_NOTE_MAX_LENGTH, getStaffCancelReasonLabel, getStaffCancelReasons } from '../lib/cancelReasons';
import { hapticFeedback } from '../lib/telegram';
import { Button, cx } from '@rizqim/ui';
import { Dialog } from './ui/Dialog';
import type { StaffCancelReason } from '@rizqim/staff';
import type { Order } from '../types';

// The two "cancel an order" dialogs: the customer's (an optional note) and the staff's (a reason is required). They
// look the same: colours come from the tokens, so they are right in both themes and for both audiences.

const pick = (language: string, text: { uz: string; ru: string; en: string }) =>
  language === 'ru' ? text.ru : language === 'en' ? text.en : text.uz;

interface ActionsProps {
  isPending: boolean;
  canConfirm: boolean;
  confirmLabel: string;
  onClose: () => void;
  onConfirm: () => void;
}

function Actions({ isPending, canConfirm, confirmLabel, onClose, onConfirm }: ActionsProps) {
  const language = useLanguageStore((state) => state.language);
  return (
    <>
      <Button variant="secondary" onClick={onClose} disabled={isPending}>
        {pick(language, { uz: 'Yopish', ru: 'Закрыть', en: 'Close' })}
      </Button>
      <Button variant="danger" onClick={onConfirm} loading={isPending} disabled={!canConfirm}>
        {isPending ? pick(language, { uz: 'Yuborilmoqda...', ru: 'Отправка...', en: 'Sending...' }) : confirmLabel}
      </Button>
    </>
  );
}

function NoteField({ value, onChange, id }: { value: string; onChange: (value: string) => void; id: string }) {
  const language = useLanguageStore((state) => state.language);
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-footnote font-bold text-fg-muted">
        {pick(language, { uz: 'Izoh (ixtiyoriy)', ru: 'Комментарий (необязательно)', en: 'Note (optional)' })}
      </label>
      <textarea
        id={id}
        value={value}
        maxLength={CANCEL_NOTE_MAX_LENGTH}
        onChange={(event) => onChange(event.target.value)}
        className="h-24 w-full resize-none rounded-control border border-line bg-surface-2 px-3 py-2.5 text-headline text-fg focus:border-brand focus:outline-none"
      />
    </div>
  );
}

function ErrorLine({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <p role="alert" className="text-footnote font-medium text-danger-soft-fg">
      {message}
    </p>
  );
}

interface CustomerCancelDialogProps {
  isPending: boolean;
  error: string | null;
  onConfirm: (note: string) => void;
  onClose: () => void;
}

// "Cancel your order?" for the customer: just an optional note, the reason is always customer_request.
export function CustomerCancelDialog({ isPending, error, onConfirm, onClose }: CustomerCancelDialogProps) {
  const language = useLanguageStore((state) => state.language);
  const [note, setNote] = useState('');
  const noteId = useId();

  return (
    <Dialog
      open
      hideClose // the footer has "Yopish"; Escape and the backdrop close it too
      busy={isPending}
      onClose={onClose}
      title={pick(language, { uz: 'Buyurtmani bekor qilish', ru: 'Отмена заказа', en: 'Cancel order' })}
      description={pick(language, {
        uz: "Buyurtmani bekor qilmoqchimisiz? Bu amalni qaytarib bo'lmaydi.",
        ru: 'Вы уверены, что хотите отменить заказ? Это действие нельзя отменить.',
        en: 'Do you want to cancel this order? This cannot be undone.',
      })}
      footer={
        <Actions
          isPending={isPending}
          canConfirm
          onClose={onClose}
          onConfirm={() => onConfirm(note.trim())}
          confirmLabel={pick(language, { uz: 'Bekor qilish', ru: 'Отменить заказ', en: 'Cancel order' })}
        />
      }
    >
      <NoteField id={noteId} value={note} onChange={setNote} />
      <ErrorLine message={error} />
    </Dialog>
  );
}

interface StaffCancelDialogProps {
  order: Pick<Order, 'status' | 'order_type' | 'short_code'>;
  mode: 'cancel' | 'reject';
  isPending: boolean;
  error: string | null;
  onConfirm: (payload: { reason: StaffCancelReason; note: string }) => void;
  onClose: () => void;
}

// The reason picker for staff who cancel or reject an order: a reason is required, a note is optional.
export function StaffCancelDialog({ order, mode, isPending, error, onConfirm, onClose }: StaffCancelDialogProps) {
  const language = useLanguageStore((state) => state.language);
  const [reason, setReason] = useState<StaffCancelReason | null>(null);
  const [note, setNote] = useState('');
  const noteId = useId();
  const groupName = useId();

  const reasons = getStaffCancelReasons(order);
  const title =
    mode === 'reject'
      ? pick(language, { uz: 'Buyurtmani rad etish', ru: 'Отклонить заказ', en: 'Reject order' })
      : pick(language, { uz: 'Buyurtmani bekor qilish', ru: 'Отменить заказ', en: 'Cancel order' });

  return (
    <Dialog
      open
      hideClose // the footer has "Yopish"; Escape and the backdrop close it too
      busy={isPending}
      onClose={onClose}
      title={order.short_code ? `${title} #${order.short_code}` : title}
      footer={
        <Actions
          isPending={isPending}
          canConfirm={reason !== null}
          onClose={onClose}
          onConfirm={() => reason && onConfirm({ reason, note: note.trim() })}
          confirmLabel={mode === 'reject' ? pick(language, { uz: 'Rad etish', ru: 'Отклонить', en: 'Reject' }) : pick(language, { uz: 'Bekor qilish', ru: 'Отменить', en: 'Cancel order' })}
        />
      }
    >
      <fieldset className="flex flex-col gap-2" disabled={isPending}>
        <legend className="mb-2 text-footnote font-bold text-fg-muted">
          {pick(language, { uz: 'Sababni tanlang', ru: 'Выберите причину', en: 'Choose a reason' })}
        </legend>
        {reasons.map((value) => (
          <label
            key={value}
            className={cx(
              'flex min-h-11 cursor-pointer items-center gap-3 rounded-control border px-4 py-2.5 text-body font-medium',
              reason === value ? 'border-danger bg-danger-soft text-danger-soft-fg' : 'border-line bg-surface text-fg',
            )}
          >
            <input
              type="radio"
              name={groupName}
              value={value}
              checked={reason === value}
              className="size-4 accent-danger"
              onChange={() => {
                hapticFeedback('light');
                setReason(value);
              }}
            />
            <span>{getStaffCancelReasonLabel(value, language)}</span>
          </label>
        ))}
      </fieldset>
      <NoteField id={noteId} value={note} onChange={setNote} />
      <ErrorLine message={error} />
    </Dialog>
  );
}
