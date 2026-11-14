'use client';

import { useMemo, useState } from 'react';
import { Ban, CalendarClock, Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, Card, Dialog, Field, InlineError, Notice, Skeleton, fieldClass, formatDate, formatDateTime, formatMoney } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { DateField, SelectField, TextField } from '../pos/fields';
import { DataTable, Pill, Td, Th, type Tone } from '../reports/ui';
import { PAYMENT_KINDS, type PaymentKind, type PaymentStatus, type Schedule, type ScheduleRow, type ScheduleRowInput } from './api';
import { useCancelEvent, useCancellationQuote, useRecordTransfer, useSaveSchedule, useSchedule } from './hooks';
import { sumAmounts } from './money';

const TONES: Record<PaymentStatus, Tone> = { due: 'neutral', paid: 'success', refunded: 'info', retained: 'warning' };

const newKey = () => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);

// The payment schedule of one banquet event (P6-53) for the event page: the figures (paid, outstanding, overdue), the
// rows with their status and receipts, every money movement, and the manager's actions — a bank transfer against a
// row, editing the unpaid rows, cancelling with the computed refund and retained parts. Cash, card and QR are taken at
// the till only.
export function BanquetPaymentsSection({ eventUuid }: { eventUuid: string }) {
  const { t, language } = useI18n();
  const access = useCan();
  const canManage = access.can('banquets.manage');
  const schedule = useSchedule(eventUuid);
  const [transferRow, setTransferRow] = useState<ScheduleRow | null>(null);
  const [editing, setEditing] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const money = (value: string) => formatMoney(value, language);

  if (schedule.isError && !schedule.data) {
    return (
      <Card>
        <InlineError title={t('loadErrorTitle')} message={errorText(schedule.error, t)} retryLabel={t('retry')} retrying={schedule.isFetching} onRetry={() => void schedule.refetch()} />
      </Card>
    );
  }
  if (!schedule.data) {
    return (
      <Card>
        <Skeleton className="h-40 w-full" />
      </Card>
    );
  }

  const data = schedule.data;
  const { summary, event } = data;
  const live = event.status === 'hold' || event.status === 'confirmed' || event.status === 'in_progress';
  const editable = canManage && (event.status === 'hold' || event.status === 'confirmed' || event.status === 'in_progress');
  const cancellable = canManage && (event.status === 'hold' || event.status === 'confirmed');

  return (
    <Card className="flex flex-col gap-4" aria-labelledby="bqp-schedule-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 id="bqp-schedule-title" className="text-headline font-extrabold text-fg">
            {t('bqp_scheduleTitle')}
          </h2>
          <p className="text-footnote text-fg-muted">{t('bqp_scheduleHint')}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {editable && (
            <Button variant="secondary" icon={Pencil} onClick={() => setEditing(true)}>
              {t('bqp_edit')}
            </Button>
          )}
          {cancellable && (
            <Button variant="danger" icon={Ban} onClick={() => setCancelling(true)}>
              {t('bqp_cancel')}
            </Button>
          )}
        </div>
      </div>
      <Notice message={notice} />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: t('bqp_total'), amount: summary.total },
          { label: t('bqp_paid'), amount: summary.paid },
          { label: t('bqp_outstanding'), amount: summary.outstanding },
          { label: t('bqp_overdue'), amount: summary.overdue_amount, danger: summary.overdue },
        ].map((card) => (
          <div key={card.label} className="flex flex-col gap-1 rounded-control border border-line p-3">
            <span className="text-footnote font-bold text-fg-muted">{card.label}</span>
            <span className={card.danger ? 'text-headline font-extrabold text-danger-soft-fg' : 'text-headline font-extrabold text-fg'}>{money(card.amount)}</span>
          </div>
        ))}
      </div>
      {summary.overdue && (
        <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
          {t('bqp_overdueN', { n: summary.overdue_count, amount: money(summary.overdue_amount) })}
        </p>
      )}
      {live && summary.next_due && (
        <p className="flex items-center gap-2 text-body text-fg-muted">
          <CalendarClock aria-hidden="true" className="size-4" />
          {t('bqp_nextDue', { date: formatDate(summary.next_due.date, language), amount: money(summary.next_due.amount) })}
        </p>
      )}
      {data.cancellation && (
        <div className="flex flex-col gap-1 rounded-control border border-line bg-surface-2 p-3 text-body">
          <span className="font-bold">
            {t('bqp_cancelledSummary', { paid: money(data.cancellation.paid), retained: money(data.cancellation.retained), refund: money(data.cancellation.refund) })}
          </span>
          {data.cancellation.override_reason && <span className="text-footnote text-fg-muted">{t('bqp_overrideNote', { computed: money(data.cancellation.computed_retained), reason: data.cancellation.override_reason })}</span>}
        </div>
      )}

      {data.rows.length === 0 ? (
        <p className="text-body text-fg-muted">{t('bqp_noRows')}</p>
      ) : (
        <DataTable caption={t('bqp_rows')}>
          <thead>
            <tr>
              <Th>{t('bqp_colKind')}</Th>
              <Th>{t('bqp_colDue')}</Th>
              <Th numeric>{t('bqp_colAmount')}</Th>
              <Th>{t('bqp_colStatus')}</Th>
              <Th>{t('bqp_colTender')}</Th>
              <Th>{t('bqp_colReceipt')}</Th>
              {editable && <Th>{t('mgmt_actions')}</Th>}
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row) => (
              <tr key={row.uuid}>
                <Td className="whitespace-nowrap font-bold">{t(`bqp_kind_${row.kind}`)}</Td>
                <Td muted className="whitespace-nowrap">
                  {row.due_date ? formatDate(row.due_date, language) : '—'}
                </Td>
                <Td numeric className="whitespace-nowrap">
                  {money(row.amount)}
                </Td>
                <Td>
                  <span className="flex flex-wrap gap-1">
                    <Pill tone={TONES[row.status]}>{t(`bqp_status_${row.status}`)}</Pill>
                    {row.overdue && <Pill tone="danger">{t('bqp_overdue')}</Pill>}
                  </span>
                </Td>
                <Td muted>
                  {row.tender ? t(`bqp_tender_${row.tender}`) : '—'}
                  {row.reference ? ` · ${row.reference}` : ''}
                </Td>
                <Td muted>{row.fiscal.status ?? '—'}</Td>
                {editable && (
                  <Td>
                    {row.status === 'due' && (
                      <Button variant="secondary" icon={Landmark} aria-label={t('bqp_transferAria', { kind: t(`bqp_kind_${row.kind}`) })} onClick={() => setTransferRow(row)}>
                        {t('bqp_transfer')}
                      </Button>
                    )}
                  </Td>
                )}
              </tr>
            ))}
          </tbody>
        </DataTable>
      )}

      {data.entries.length > 0 && (
        <DataTable caption={t('bqp_entries')}>
          <thead>
            <tr>
              <Th>{t('bqp_entryDate')}</Th>
              <Th>{t('bqp_entryType')}</Th>
              <Th numeric>{t('bqp_colAmount')}</Th>
              <Th>{t('bqp_colTender')}</Th>
              <Th>{t('bqp_entryBy')}</Th>
            </tr>
          </thead>
          <tbody>
            {data.entries.map((entry) => (
              <tr key={entry.uuid}>
                <Td muted className="whitespace-nowrap">
                  {entry.created_at ? formatDateTime(entry.created_at, language) : (entry.business_date ?? '—')}
                </Td>
                <Td>
                  {entry.type_label}
                  {entry.transfer_pending ? ` (${t('bqp_transferPending')})` : ''}
                </Td>
                <Td numeric className="whitespace-nowrap">
                  {entry.type === 'refund' ? '−' : ''}
                  {money(entry.amount)}
                </Td>
                <Td muted>{t(`bqp_tender_${entry.tender}`)}</Td>
                <Td muted>{entry.user?.name ?? '—'}</Td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      )}

      {transferRow && (
        <TransferDialog
          eventUuid={eventUuid}
          row={transferRow}
          onClose={() => setTransferRow(null)}
          onDone={() => {
            setTransferRow(null);
            setNotice(t('bqp_transferDone'));
          }}
        />
      )}
      {editing && (
        <EditScheduleDialog
          schedule={data}
          onClose={() => setEditing(false)}
          onDone={() => {
            setEditing(false);
            setNotice(t('bqp_saved'));
          }}
        />
      )}
      {cancelling && (
        <CancelDialog
          eventUuid={eventUuid}
          onClose={() => setCancelling(false)}
          onDone={() => {
            setCancelling(false);
            setNotice(t('bqp_cancelled'));
          }}
        />
      )}
    </Card>
  );
}

// The manager records money the customer sent by bank against one unpaid row (all of it, or a part)
function TransferDialog({ eventUuid, row, onClose, onDone }: { eventUuid: string; row: ScheduleRow; onClose: () => void; onDone: () => void }) {
  const { t, language } = useI18n();
  const [reference, setReference] = useState('');
  const [paidOn, setPaidOn] = useState('');
  const [amount, setAmount] = useState('');
  const [missing, setMissing] = useState(false);
  const [key] = useState(newKey);
  const save = useRecordTransfer(eventUuid, onDone);

  return (
    <Dialog
      open
      variant="center"
      title={t('bqp_transferTitle')}
      description={t('bqp_transferText')}
      closeLabel={t('mgmt_close')}
      busy={save.isPending}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button
            loading={save.isPending}
            onClick={() => {
              if (reference.trim().length < 2) {
                setMissing(true);
                return;
              }
              save.mutate({ row: row.uuid, reference: reference.trim(), ...(paidOn ? { paid_on: paidOn } : {}), ...(amount.trim() ? { amount: amount.trim() } : {}), key });
            }}
          >
            {t('bqp_save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <p className="text-body font-bold">
          {t(`bqp_kind_${row.kind}`)} · {moneyIn(row.amount, language)}
        </p>
        <TextField label={t('bqp_reference')} value={reference} maxLength={120} onChange={(value) => (setReference(value), setMissing(false))} error={missing ? t('bqp_reasonRequired') : undefined} autoFocus />
        <DateField label={t('bqp_paidOn')} value={paidOn} onChange={setPaidOn} />
        <TextField label={t('bqp_amountOptional')} value={amount} inputMode="decimal" maxLength={14} onChange={setAmount} />
        {save.isError && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {errorText(save.error, t)}
          </p>
        )}
      </div>
    </Dialog>
  );
}

const moneyIn = (value: string, language: Parameters<typeof formatMoney>[1]) => formatMoney(value, language);

// The unpaid rows as the manager wants them: they must add up to what is left (the server checks it too)
function EditScheduleDialog({ schedule, onClose, onDone }: { schedule: Schedule; onClose: () => void; onDone: () => void }) {
  const { t, language } = useI18n();
  const [rows, setRows] = useState<ScheduleRowInput[]>(() =>
    schedule.rows.filter((row) => row.status === 'due').map((row) => ({ uuid: row.uuid, kind: row.kind, due_date: row.due_date ?? schedule.event.event_date, amount: row.amount })),
  );
  const [key] = useState(newKey);
  const save = useSaveSchedule(schedule.event.uuid, onDone);
  const expected = schedule.summary.outstanding;
  const sum = useMemo(() => sumAmounts(rows.map((row) => row.amount)), [rows]);
  const kinds = PAYMENT_KINDS.map((kind) => ({ value: kind, label: t(`bqp_kind_${kind}`) }));
  const update = (index: number, patch: Partial<ScheduleRowInput>) => setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));

  return (
    <Dialog
      open
      title={t('bqp_editTitle')}
      description={t('bqp_editText', { amount: moneyIn(expected, language) })}
      closeLabel={t('mgmt_close')}
      busy={save.isPending}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button loading={save.isPending} onClick={() => save.mutate({ rows: rows.map((row) => ({ ...row, amount: row.amount.trim() })), version: schedule.event.version, key })}>
            {t('bqp_save')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        {rows.map((row, index) => (
          <div key={row.uuid ?? `new-${index}`} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_1fr_auto]">
            <SelectField<PaymentKind> label={t('bqp_colKind')} value={row.kind} options={kinds} onChange={(kind) => update(index, { kind })} />
            <DateField label={t('bqp_colDue')} value={row.due_date} onChange={(due_date) => update(index, { due_date })} />
            <TextField label={t('bqp_colAmount')} value={row.amount} inputMode="decimal" maxLength={14} onChange={(amount) => update(index, { amount })} />
            <Button variant="secondary" icon={Trash2} aria-label={t('bqp_removeRow')} onClick={() => setRows((current) => current.filter((_, i) => i !== index))} />
          </div>
        ))}
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Button variant="secondary" icon={Plus} onClick={() => setRows((current) => [...current, { kind: 'interim', due_date: schedule.event.event_date, amount: '0' }])}>
            {t('bqp_addRow')}
          </Button>
          <span className={sum === Number(expected) ? 'text-footnote text-fg-muted' : 'text-footnote font-bold text-danger-soft-fg'}>{t('bqp_sumNow', { sum: moneyIn(String(sum), language), expected: moneyIn(expected, language) })}</span>
        </div>
        {save.isError && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {errorText(save.error, t)}
          </p>
        )}
      </div>
    </Dialog>
  );
}

// Cancelling: the server's quote (days left, the rule's %, retained and refunded parts, how each refund goes back), an
// optional override of the retained amount with its reason, and the cancellation reason
function CancelDialog({ eventUuid, onClose, onDone }: { eventUuid: string; onClose: () => void; onDone: () => void }) {
  const { t, language } = useI18n();
  const [override, setOverride] = useState(false);
  const [retained, setRetained] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [reason, setReason] = useState('');
  const [problem, setProblem] = useState<'reason' | 'override' | null>(null);
  const [key] = useState(newKey);
  const preview = override && retained.trim() !== '' && !Number.isNaN(Number(retained)) ? retained.trim() : '';
  const quote = useCancellationQuote(eventUuid, preview, true);
  const cancel = useCancelEvent(eventUuid, onDone);
  const q = quote.data;

  return (
    <Dialog
      open
      variant="center"
      title={t('bqp_cancelTitle')}
      description={t('bqp_cancelText')}
      closeLabel={t('mgmt_close')}
      busy={cancel.isPending}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={cancel.isPending}>
            {t('mgmt_cancel')}
          </Button>
          <Button
            variant="danger"
            loading={cancel.isPending}
            disabled={!q}
            onClick={() => {
              if (reason.trim().length < 3) {
                setProblem('reason');
                return;
              }
              if (override && preview !== '' && overrideReason.trim() === '') {
                setProblem('override');
                return;
              }
              cancel.mutate({ reason: reason.trim(), ...(override && preview !== '' ? { retained: preview, override_reason: overrideReason.trim() } : {}), key });
            }}
          >
            {t('bqp_confirmCancel')}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        {quote.isError ? (
          <InlineError title={t('loadErrorTitle')} message={errorText(quote.error, t)} retryLabel={t('retry')} retrying={quote.isFetching} onRetry={() => void quote.refetch()} />
        ) : !q ? (
          <Skeleton className="h-24 w-full" />
        ) : Number(q.paid) === 0 ? (
          <p className="text-body">{t('bqp_nothingPaid')}</p>
        ) : (
          <div className="flex flex-col gap-2" data-testid="bqp-quote">
            <p className="text-body">{t('bqp_daysBefore', { n: q.days_before, percent: q.rule_percent })}</p>
            <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-body">
              <dt className="text-fg-muted">{t('bqp_paid')}</dt>
              <dd className="font-bold">{moneyIn(q.paid, language)}</dd>
              <dt className="text-fg-muted">{t('bqp_retained')}</dt>
              <dd className="font-bold">{moneyIn(q.retained, language)}</dd>
              <dt className="text-fg-muted">{t('bqp_refund')}</dt>
              <dd className="font-bold">{moneyIn(q.refund, language)}</dd>
            </dl>
            <ul className="flex flex-col gap-1 text-footnote text-fg-muted">
              {q.rows
                .filter((row) => Number(row.refund) > 0)
                .map((row) => (
                  <li key={row.payment_uuid}>
                    {t(`bqp_kind_${row.kind}`)}: {moneyIn(row.refund, language)} — {row.refund_path ? t(`bqp_refundPath_${row.refund_path}`) : '—'}
                  </li>
                ))}
            </ul>
            {Number(q.cash_refund) > 0 && <p className="text-footnote font-bold text-warning-soft-fg">{t('bqp_cashRefund')}</p>}
            {Number(q.transfer_refund) > 0 && <p className="text-footnote">{t('bqp_transferRefund', { amount: moneyIn(q.transfer_refund, language) })}</p>}
            <label className="flex min-h-11 items-center gap-2 text-body">
              <input type="checkbox" checked={override} onChange={(event) => setOverride(event.target.checked)} className="size-5" />
              {t('bqp_override')}
            </label>
            {override && (
              <>
                <TextField label={t('bqp_overrideAmount')} value={retained} inputMode="decimal" maxLength={14} onChange={setRetained} />
                <TextField
                  label={t('bqp_overrideReason')}
                  value={overrideReason}
                  maxLength={500}
                  onChange={(value) => (setOverrideReason(value), setProblem(null))}
                  error={problem === 'override' ? t('bqp_overrideReasonRequired') : undefined}
                />
              </>
            )}
          </div>
        )}
        <Field label={t('bqp_reason')} error={problem === 'reason' ? t('bqp_reasonRequired') : undefined}>
          {(control) => (
            <textarea
              {...control}
              value={reason}
              maxLength={500}
              rows={3}
              onChange={(event) => {
                setReason(event.target.value);
                setProblem(null);
              }}
              className={fieldClass}
            />
          )}
        </Field>
        {cancel.isError && (
          <p role="alert" className="text-footnote font-bold text-danger-soft-fg">
            {errorText(cancel.error, t)}
          </p>
        )}
      </div>
    </Dialog>
  );
}
