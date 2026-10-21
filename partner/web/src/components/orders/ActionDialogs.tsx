'use client';

import { useId, useState } from 'react';
import { Button, Dialog, fieldClass, InlineError, Spinner } from '@rizqim/ui';
import {
  CANCEL_NOTE_MAX_LENGTH,
  cancelReasonLabel,
  getStaffCancelReasons,
  useCouriers,
  type Order,
  type StaffCancelReason,
  type StaffOrder,
} from '@rizqim/staff';
import { useI18n } from '../I18nProvider';

// The questions an action asks before it is sent: why an order is rejected or cancelled, and which courier takes it.

interface ReasonDialogProps {
  order: StaffOrder;
  kind: 'reject' | 'cancel';
  busy: boolean;
  error: string | null;
  onConfirm: (reason: StaffCancelReason, note: string) => void;
  onClose: () => void;
}

export function ReasonDialog({ order, kind, busy, error, onConfirm, onClose }: ReasonDialogProps) {
  const { t, language } = useI18n();
  const noteId = useId();
  const [reason, setReason] = useState<StaffCancelReason | null>(null);
  const [note, setNote] = useState('');
  const [missing, setMissing] = useState(false);
  // The reasons the API accepts for this order (no_show only for pickup, courier_unavailable only for delivery)
  const reasons = getStaffCancelReasons({ status: order.status as Order['status'], order_type: order.type as Order['order_type'] });

  return (
    <Dialog
      open
      variant="center"
      role="alertdialog"
      busy={busy}
      closeLabel={t('board_close')}
      title={kind === 'reject' ? t('board_rejectTitle') : t('board_cancelTitle')}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('board_back')}
          </Button>
          <Button
            variant="danger"
            loading={busy}
            onClick={() => {
              if (!reason) return setMissing(true);
              onConfirm(reason, note.trim());
            }}
          >
            {kind === 'reject' ? t('board_confirmReject') : t('board_confirmCancel')}
          </Button>
        </>
      }
    >
      <fieldset className="flex flex-col gap-1">
        <legend className="mb-1 text-footnote font-bold text-fg-muted">{t('board_reasonLabel')}</legend>
        {reasons.map((value) => (
          <label key={value} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-2 text-body text-fg hover:bg-surface-2">
            <input
              type="radio"
              name="reason"
              value={value}
              checked={reason === value}
              onChange={() => {
                setReason(value);
                setMissing(false);
              }}
              className="size-5 accent-[var(--rz-brand)]"
            />
            {cancelReasonLabel(value, language)}
          </label>
        ))}
        {missing && (
          <p role="alert" className="text-footnote text-danger">
            {t('board_reasonRequired')}
          </p>
        )}
      </fieldset>
      <div className="flex flex-col gap-1">
        <label htmlFor={noteId} className="text-footnote font-bold text-fg-muted">
          {t('board_noteLabel')}
        </label>
        <textarea id={noteId} className={`${fieldClass} min-h-20 text-body`} maxLength={CANCEL_NOTE_MAX_LENGTH} value={note} onChange={(event) => setNote(event.target.value)} />
      </div>
      {error && (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
          {error}
        </p>
      )}
    </Dialog>
  );
}

interface CourierDialogProps {
  order: StaffOrder;
  busy: boolean;
  error: string | null;
  onConfirm: (courierId: number) => void;
  onClose: () => void;
}

export function CourierDialog({ order, busy, error, onConfirm, onClose }: CourierDialogProps) {
  const { t } = useI18n();
  const [courierId, setCourierId] = useState<number | null>(null);
  // GET /couriers: the couriers of the order's branch (a branch manager always gets their own branch's)
  const couriers = useCouriers(order.branch_id ?? undefined);

  return (
    <Dialog
      open
      variant="center"
      busy={busy}
      closeLabel={t('board_close')}
      title={t('board_courierTitle')}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('board_back')}
          </Button>
          <Button loading={busy} disabled={courierId === null} onClick={() => courierId !== null && onConfirm(courierId)}>
            {t('board_courierConfirm')}
          </Button>
        </>
      }
    >
      {couriers.isPending ? (
        <div className="flex justify-center py-6">
          <Spinner className="size-6" />
        </div>
      ) : couriers.isError ? (
        <InlineError title={t('board_courierLoadError')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={couriers.isFetching} onRetry={() => void couriers.refetch()} />
      ) : couriers.data.length === 0 ? (
        <p className="py-4 text-center text-body text-fg-muted">{t('board_courierEmpty')}</p>
      ) : (
        <fieldset className="flex flex-col gap-1">
          <legend className="sr-only">{t('board_courierTitle')}</legend>
          {couriers.data.map((courier) => (
            <label key={courier.id} className="flex min-h-11 cursor-pointer items-center gap-3 rounded-control px-2 text-body text-fg hover:bg-surface-2">
              <input type="radio" name="courier" checked={courierId === courier.id} onChange={() => setCourierId(courier.id)} className="size-5 accent-[var(--rz-brand)]" />
              <span className="flex flex-col">
                <span className="font-medium">{courier.name}</span>
                {courier.phone && <span className="text-caption text-fg-muted">{courier.phone}</span>}
              </span>
            </label>
          ))}
        </fieldset>
      )}
      {error && (
        <p role="alert" className="rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
          {error}
        </p>
      )}
    </Dialog>
  );
}
