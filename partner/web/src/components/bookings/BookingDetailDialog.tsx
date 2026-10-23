'use client';

import { useState } from 'react';
import { Ban, CalendarCheck, Pencil, UserX } from 'lucide-react';
import { Button, Dialog, InlineError, Skeleton, formatDateTime, formatMoney } from '@rizqim/ui';
import { useBooking, useCancelBooking, useConfirmBooking, useMarkBookingNoShow, type Booking, type BookingCalendar } from '@rizqim/staff';
import { inZone } from '@/lib/storeTime';
import { useI18n } from '../I18nProvider';
import { errorText } from '../management/errors';
import { Pill } from '../management/Pill';
import { TextField } from '../pos/fields';
import { labelFor } from '../reports/format';
import { DataTable, Td, Th } from '../reports/ui';
import { isOpenBooking, STATUS_TONE } from './calendarModel';

// One booking with its deposits and pre-ordered lines, and what can be done to it: confirm, edit, mark "did not come" or cancel
// (with a reason). Taking, returning or keeping a deposit is done at the till; here it is shown.
export function BookingDetailDialog({
  uuid,
  rooms,
  timeZone,
  onClose,
  onEdit,
  onDone,
}: {
  uuid: string;
  rooms: BookingCalendar['rooms'];
  timeZone: string;
  onClose: () => void;
  onEdit: (booking: Booking) => void;
  onDone: (message: string) => void;
}) {
  const { t, language } = useI18n();
  const query = useBooking(uuid);
  const confirm = useConfirmBooking();
  const noShow = useMarkBookingNoShow();
  const cancel = useCancelBooking();
  const [canceling, setCanceling] = useState(false);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);
  const busy = confirm.isPending || noShow.isPending || cancel.isPending;
  const booking = query.data;

  const roomNames = booking ? booking.table_ids.map((id) => rooms.find((room) => room.id === id)?.name ?? `#${id}`) : [];
  const fail = (error: unknown) => setFailure(errorText(error, t));
  const starts = booking ? inZone(booking.starts_at, timeZone) : null;
  const ends = booking ? inZone(booking.ends_at, timeZone) : null;

  return (
    <Dialog
      open
      title={booking ? booking.contact_name : t('book_detail')}
      description={booking ? `${starts?.day ?? ''} · ${starts?.time ?? '—'}–${ends?.time ?? '—'}` : undefined}
      closeLabel={t('mgmt_close')}
      busy={busy}
      onClose={onClose}
      footer={
        booking ? (
          <>
            <Button variant="secondary" onClick={onClose} disabled={busy}>
              {t('mgmt_close')}
            </Button>
            {isOpenBooking(booking.status) && !canceling && (
              <>
                <Button variant="secondary" icon={Pencil} onClick={() => onEdit(booking)} disabled={busy}>
                  {t('book_edit')}
                </Button>
                {booking.status === 'requested' && (
                  <Button variant="success" icon={CalendarCheck} loading={confirm.isPending} disabled={busy} onClick={() => confirm.mutate(uuid, { onSuccess: () => onDone(t('book_confirmed', { name: booking.contact_name })), onError: fail })}>
                    {t('book_confirm')}
                  </Button>
                )}
                {booking.status === 'confirmed' && (
                  <Button variant="secondary" icon={UserX} loading={noShow.isPending} disabled={busy} onClick={() => noShow.mutate(uuid, { onSuccess: () => onDone(t('book_noShown', { name: booking.contact_name })), onError: fail })}>
                    {t('book_noShow')}
                  </Button>
                )}
                <Button variant="danger" icon={Ban} disabled={busy} onClick={() => setCanceling(true)}>
                  {t('book_cancel')}
                </Button>
              </>
            )}
          </>
        ) : undefined
      }
    >
      {!booking ? (
        query.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
        ) : (
          <Skeleton className="h-40 w-full" />
        )
      ) : (
        <div className="flex flex-col gap-4">
          {failure && (
            <p role="alert" className="rounded-control bg-danger-soft px-3 py-2.5 text-footnote font-bold text-danger-soft-fg">
              {failure}
            </p>
          )}
          <div className="flex flex-wrap items-center gap-2">
            <Pill tone={STATUS_TONE[booking.status] ?? 'neutral'}>{labelFor(t, 'book_st_', booking.status)}</Pill>
            <Pill tone="neutral">{labelFor(t, 'book_ev_', booking.event_type)}</Pill>
            {booking.deposit_unsettled && <Pill tone="warning">{t('book_depositUnsettled')}</Pill>}
          </div>

          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-footnote">
            <Item label={t('book_room')} value={roomNames.join(', ') || t('book_noRoom')} />
            <Item label={t('book_phone')} value={booking.contact_phone} />
            <Item label={t('book_guests')} value={String(booking.guests_planned)} />
            <Item label={t('book_source')} value={labelFor(t, 'book_src_', booking.source)} />
            <Item label={t('book_minSpend')} value={booking.min_spend && Number(booking.min_spend) > 0 ? formatMoney(booking.min_spend, language) : null} />
            <Item label={t('book_note')} value={booking.note} />
            <Item label={t('book_depositTotal')} value={formatMoney(booking.deposit_total, language)} />
            <Item label={t('book_depositAvailable')} value={formatMoney(booking.deposit_available, language)} />
            <Item label={t('book_cancelReason')} value={booking.cancel_reason} />
            <Item label={t('book_createdAt')} value={booking.created_at ? formatDateTime(booking.created_at, language) : null} />
          </dl>

          <section aria-label={t('book_deposits')}>
            <h3 className="mb-1 text-body font-bold text-fg">{t('book_deposits')}</h3>
            {booking.payments.length === 0 ? (
              <p className="text-footnote text-fg-muted">{t('book_noDeposits')}</p>
            ) : (
              <DataTable caption={t('book_deposits')}>
                <thead>
                  <tr>
                    <Th>{t('book_depDate')}</Th>
                    <Th numeric>{t('book_depAmount')}</Th>
                    <Th>{t('book_depTender')}</Th>
                    <Th numeric>{t('book_depLeft')}</Th>
                    <Th>{t('book_depStatus')}</Th>
                    <Th>{t('book_depReceipt')}</Th>
                  </tr>
                </thead>
                <tbody>
                  {booking.payments.map((payment) => (
                    <tr key={payment.uuid}>
                      <Td muted className="whitespace-nowrap">
                        {payment.created_at ? formatDateTime(payment.created_at, language) : '—'}
                      </Td>
                      <Td numeric className="whitespace-nowrap font-bold">
                        {formatMoney(payment.amount, language)}
                      </Td>
                      <Td>{labelFor(t, 'book_tender_', payment.tender)}</Td>
                      <Td numeric className="whitespace-nowrap">
                        {formatMoney(payment.remaining, language)}
                      </Td>
                      <Td>
                        {payment.status_label}
                        {Number(payment.refunded_amount) > 0 && <span className="block text-caption text-fg-muted">{t('book_depRefunded', { amount: formatMoney(payment.refunded_amount, language) })}</span>}
                        {Number(payment.retained_amount) > 0 && <span className="block text-caption text-fg-muted">{t('book_depRetained', { amount: formatMoney(payment.retained_amount, language) })}</span>}
                      </Td>
                      <Td muted>{payment.fiscal_status ?? '—'}</Td>
                    </tr>
                  ))}
                </tbody>
              </DataTable>
            )}
            <p className="mt-1 text-caption text-fg-muted">{t('book_depositsTill')}</p>
          </section>

          {booking.items.length > 0 && (
            <section aria-label={t('book_items')}>
              <h3 className="mb-1 text-body font-bold text-fg">{t('book_items')}</h3>
              <ul className="flex flex-col gap-1 text-body text-fg">
                {booking.items.map((item) => (
                  <li key={item.id} className="flex flex-wrap justify-between gap-2">
                    <span>
                      {item.quantity} × {[item.product_name, item.variant_name].filter(Boolean).join(' · ')}
                    </span>
                    <span className="text-fg-muted">{item.due_at ? (inZone(item.due_at, timeZone)?.time ?? '') : ''}</span>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {canceling && (
            <form
              noValidate
              className="flex flex-col gap-3 rounded-card border border-line p-3"
              onSubmit={(event) => {
                event.preventDefault();
                if (reason.trim() === '') return setReasonError(t('book_err_reason'));
                setReasonError(null);
                setFailure(null);
                cancel.mutate({ uuid, reason: reason.trim(), version: booking.version }, { onSuccess: () => onDone(t('book_canceled', { name: booking.contact_name })), onError: fail });
              }}
            >
              <TextField label={t('book_cancelWhy')} value={reason} onChange={setReason} maxLength={500} autoFocus error={reasonError} />
              <div className="flex flex-wrap justify-end gap-3">
                <Button variant="secondary" onClick={() => setCanceling(false)} disabled={cancel.isPending}>
                  {t('book_keep')}
                </Button>
                <Button type="submit" variant="danger" loading={cancel.isPending}>
                  {t('book_cancelDo')}
                </Button>
              </div>
            </form>
          )}
        </div>
      )}
    </Dialog>
  );
}

function Item({ label, value }: { label: string; value: string | null | undefined }) {
  if (value === null || value === undefined || value === '') return null;
  return (
    <>
      <dt className="text-fg-muted">{label}</dt>
      <dd className="text-fg">{value}</dd>
    </>
  );
}
