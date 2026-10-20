import { useId, useState, type ReactNode } from 'react';
import { useLanguageStore } from '../store/languageStore';
import { CANCEL_NOTE_MAX_LENGTH, getStaffCancelReasonLabel, getStaffCancelReasons } from '../lib/cancelReasons';
import { hapticFeedback } from '../lib/telegram';
import type { CancelReason, Order } from '../types';

type Variant = 'customer' | 'staff';

const THEME: Record<Variant, { sheet: string; title: string; text: string; field: string; ghost: string; option: string; optionOn: string }> = {
  customer: {
    sheet: 'bg-white border border-slate-100 text-slate-800',
    title: 'text-slate-800',
    text: 'text-slate-500',
    field: 'bg-slate-50 border border-slate-200 text-slate-800 focus:border-amber-500',
    ghost: 'bg-slate-100 text-slate-600',
    option: 'border border-slate-200 text-slate-700',
    optionOn: 'border border-red-400 bg-red-50 text-red-600',
  },
  staff: {
    sheet: 'bg-secondary border border-hint/10 text-text',
    title: 'text-fg',
    text: 'text-hint',
    field: 'bg-bg border border-hint/15 text-fg focus:border-primary',
    ghost: 'bg-bg text-hint border border-hint/10',
    option: 'border border-hint/15 text-fg',
    optionOn: 'border border-red-500/50 bg-red-500/10 text-red-500',
  },
};

const pick = (language: string, text: { uz: string; ru: string; en: string }) =>
  language === 'ru' ? text.ru : language === 'en' ? text.en : text.uz;

interface ShellProps {
  variant: Variant;
  title: string;
  onClose: () => void;
  titleId: string;
  children: ReactNode;
}

function DialogShell({ variant, title, onClose, titleId, children }: ShellProps) {
  const theme = THEME[variant];
  return (
    <div className="fixed inset-0 z-[120] bg-black/60 backdrop-blur-sm flex items-end justify-center p-4 animate-fade-in">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={`${theme.sheet} w-full max-w-sm mx-auto mb-10 rounded-3xl p-6 flex flex-col gap-4 shadow-2xl animate-slide-up`}
      >
        <div className="flex justify-between items-center">
          <h3 id={titleId} className={`${theme.title} text-xs font-black uppercase tracking-wide`}>
            {title}
          </h3>
          <button type="button" aria-label="Close" onClick={onClose} className={`${theme.text} font-bold p-1`}>
            ✕
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

interface ActionsProps {
  variant: Variant;
  isPending: boolean;
  canConfirm: boolean;
  confirmLabel: string;
  onClose: () => void;
  onConfirm: () => void;
}

function Actions({ variant, isPending, canConfirm, confirmLabel, onClose, onConfirm }: ActionsProps) {
  const language = useLanguageStore((state) => state.language);
  const theme = THEME[variant];
  return (
    <div className="flex gap-2">
      <button type="button" onClick={onClose} disabled={isPending} className={`${theme.ghost} flex-1 rounded-2xl py-3 text-xs font-black disabled:opacity-50`}>
        {pick(language, { uz: 'Yopish', ru: 'Закрыть', en: 'Close' })}
      </button>
      <button
        type="button"
        onClick={onConfirm}
        disabled={isPending || !canConfirm}
        className="flex-1 rounded-2xl py-3 text-xs font-black bg-red-500 text-white active:scale-95 transition disabled:opacity-50 disabled:pointer-events-none"
      >
        {isPending ? pick(language, { uz: 'Yuborilmoqda...', ru: 'Отправка...', en: 'Sending...' }) : confirmLabel}
      </button>
    </div>
  );
}

function NoteField({ variant, value, onChange, id }: { variant: Variant; value: string; onChange: (value: string) => void; id: string }) {
  const language = useLanguageStore((state) => state.language);
  const theme = THEME[variant];
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className={`${theme.text} text-[10px] font-black uppercase tracking-wider`}>
        {pick(language, { uz: 'Izoh (ixtiyoriy)', ru: 'Комментарий (необязательно)', en: 'Note (optional)' })}
      </label>
      <textarea
        id={id}
        value={value}
        maxLength={CANCEL_NOTE_MAX_LENGTH}
        onChange={(event) => onChange(event.target.value)}
        className={`${theme.field} w-full text-xs rounded-2xl px-4 py-3 h-20 focus:outline-none font-semibold`}
      />
    </div>
  );
}

function ErrorLine({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <p role="alert" className="text-[11px] font-bold text-red-500 leading-relaxed">
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
  const titleId = useId();
  const noteId = useId();

  return (
    <DialogShell
      variant="customer"
      titleId={titleId}
      onClose={onClose}
      title={pick(language, { uz: 'Buyurtmani bekor qilish', ru: 'Отмена заказа', en: 'Cancel order' })}
    >
      <p className={`${THEME.customer.text} text-xs font-medium leading-relaxed`}>
        {pick(language, {
          uz: 'Buyurtmani bekor qilmoqchimisiz? Bu amalni qaytarib bo‘lmaydi.',
          ru: 'Вы уверены, что хотите отменить заказ? Это действие нельзя отменить.',
          en: 'Do you want to cancel this order? This cannot be undone.',
        })}
      </p>
      <NoteField variant="customer" id={noteId} value={note} onChange={setNote} />
      <ErrorLine message={error} />
      <Actions
        variant="customer"
        isPending={isPending}
        canConfirm
        onClose={onClose}
        onConfirm={() => onConfirm(note.trim())}
        confirmLabel={pick(language, { uz: 'Bekor qilish', ru: 'Отменить заказ', en: 'Cancel order' })}
      />
    </DialogShell>
  );
}

interface StaffCancelDialogProps {
  order: Pick<Order, 'status' | 'order_type' | 'short_code'>;
  mode: 'cancel' | 'reject';
  isPending: boolean;
  error: string | null;
  onConfirm: (payload: { reason: CancelReason; note: string }) => void;
  onClose: () => void;
}

// The reason picker for staff who cancel or reject an order: a reason is required, a note is optional.
export function StaffCancelDialog({ order, mode, isPending, error, onConfirm, onClose }: StaffCancelDialogProps) {
  const language = useLanguageStore((state) => state.language);
  const [reason, setReason] = useState<CancelReason | null>(null);
  const [note, setNote] = useState('');
  const titleId = useId();
  const noteId = useId();
  const theme = THEME.staff;

  const reasons = getStaffCancelReasons(order);
  const title =
    mode === 'reject'
      ? pick(language, { uz: 'Buyurtmani rad etish', ru: 'Отклонить заказ', en: 'Reject order' })
      : pick(language, { uz: 'Buyurtmani bekor qilish', ru: 'Отменить заказ', en: 'Cancel order' });

  return (
    <DialogShell variant="staff" titleId={titleId} onClose={onClose} title={order.short_code ? `${title} #${order.short_code}` : title}>
      <fieldset className="flex flex-col gap-2" disabled={isPending}>
        <legend className={`${theme.text} text-[10px] font-black uppercase tracking-wider mb-2`}>
          {pick(language, { uz: 'Sababni tanlang', ru: 'Выберите причину', en: 'Choose a reason' })}
        </legend>
        {reasons.map((value) => (
          <label
            key={value}
            className={`${reason === value ? theme.optionOn : theme.option} flex items-center gap-3 rounded-2xl px-4 py-3 text-xs font-bold cursor-pointer`}
          >
            <input
              type="radio"
              name="cancel-reason"
              value={value}
              checked={reason === value}
              onChange={() => {
                hapticFeedback('light');
                setReason(value);
              }}
            />
            <span>{getStaffCancelReasonLabel(value, language)}</span>
          </label>
        ))}
      </fieldset>
      <NoteField variant="staff" id={noteId} value={note} onChange={setNote} />
      <ErrorLine message={error} />
      <Actions
        variant="staff"
        isPending={isPending}
        canConfirm={reason !== null}
        onClose={onClose}
        onConfirm={() => reason && onConfirm({ reason, note: note.trim() })}
        confirmLabel={mode === 'reject' ? pick(language, { uz: 'Rad etish', ru: 'Отклонить', en: 'Reject' }) : pick(language, { uz: 'Bekor qilish', ru: 'Отменить', en: 'Cancel order' })}
      />
    </DialogShell>
  );
}
