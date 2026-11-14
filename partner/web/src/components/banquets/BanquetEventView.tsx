'use client';

import { useState } from 'react';
import Link from 'next/link';
import { AlertTriangle, CalendarHeart, Lock, LockOpen, Pencil } from 'lucide-react';
import { Button, Card, CardSkeletons, Dialog, EmptyState, FormError, InlineError, Notice, PageHeader, cx, formatDateTime, formatMoney } from '@rizqim/ui';
import { useI18n } from '../I18nProvider';
import { useCan } from '../management/access';
import { errorText } from '../management/errors';
import { TextField } from '../pos/fields';
import { DataTable, Td, Th } from '../reports/ui';
import type { BanquetEvent } from './api';
import { BanquetEventDialog } from './BanquetEventDialog';
import { BanquetEventSections } from './sections';
import { STATUS_CLASS } from './BanquetsView';
import { useBanquetEvent, useFreezeEvent } from './hooks';

// /banquets/{uuid}: one event (P6-51/P6-52) — when and where, the customer and the guests, the lines with the formula's
// totals, warnings, the price freeze of the contract day (and lifting it with a reason) and the sections the other
// banquet screens add below (the payment schedule, P6-53; the production sheet and settlement, P6-54).
export function BanquetEventView({ uuid }: { uuid: string }) {
  const { t, language } = useI18n();
  const access = useCan();
  const query = useBanquetEvent(uuid);
  const [editing, setEditing] = useState(false);
  const [unfreezing, setUnfreezing] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const freeze = useFreezeEvent({
    onSuccess: (event) => {
      setUnfreezing(false);
      setActionError(null);
      setNotice(event.frozen ? t('bq_frozenDone') : t('bq_unfrozenDone'));
    },
    onError: (error) => setActionError(errorText(error, t)),
  });
  const canManage = access.can('banquets.manage');
  const event = query.data;
  const money = (value: string) => formatMoney(value, language);

  if (query.isError && !event) {
    return (
      <>
        <PageHeader title={t('bq_eventTitlePage')} />
        <InlineError title={t('loadErrorTitle')} message={errorText(query.error, t)} retryLabel={t('retry')} retrying={query.isFetching} onRetry={() => void query.refetch()} />
      </>
    );
  }
  if (!event) return <CardSkeletons count={2} lines={4} label={t('loading')} />;

  const editable = event.status === 'hold' || event.status === 'confirmed';

  return (
    <>
      <PageHeader
        title={event.title ?? `${event.event_type_label}: ${event.customer_name}`}
        subtitle={`${event.event_date} · ${event.slot_label} ${event.start_time}–${event.end_time} · ${event.halls.map((hall) => hall.name).join(' + ')}`}
        action={
          <Link href="/banquets" className="font-bold text-brand underline-offset-2 hover:underline">
            {t('bq_backToCalendar')}
          </Link>
        }
      />
      <Notice message={notice} />
      {actionError && <FormError message={actionError} />}

      <div className="flex flex-col gap-4">
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className={cx('rounded-control border px-2 py-1 text-footnote font-bold', STATUS_CLASS[event.status])}>{event.status_label}</span>
            {event.frozen && (
              <span className="inline-flex items-center gap-1 rounded-control border border-info px-2 py-1 text-footnote font-bold text-info-soft-fg">
                <Lock className="size-4" aria-hidden="true" />
                {t('bq_frozenBadge')}
              </span>
            )}
            {event.status === 'hold' && event.hold_expires_at && (
              <span className="text-footnote text-fg-muted">
                {t('bq_holdUntil', {
                  at: formatDateTime(event.hold_expires_at, language),
                })}
              </span>
            )}
          </div>
          <dl className="grid gap-x-6 gap-y-2 text-body sm:grid-cols-3">
            <Info label={t('bq_customer')} value={event.customer_name} />
            <Info label={t('bq_phone')} value={event.customer_phone ?? '—'} />
            <Info label={t('bq_type')} value={event.event_type_label} />
            <Info label={t('bq_guaranteed')} value={String(event.guests_guaranteed)} />
            <Info label={t('bq_minimum')} value={String(event.guests_minimum)} />
            <Info label={t('bq_billed')} value={String(event.guests_billed)} />
            <Info label={t('bq_package')} value={event.package_name ?? t('bq_noPackage')} />
            {event.contract_no && <Info label={t('bq_contractNo')} value={event.contract_no} />}
            {event.note && <Info label={t('bq_note')} value={event.note} />}
          </dl>
          {event.warnings.length > 0 && (
            <ul className="flex flex-col gap-1" aria-label={t('bq_warnings')}>
              {event.warnings.map((warning) => (
                <li key={warning.code} className="flex items-start gap-2 rounded-control bg-warning-soft px-3 py-2 text-footnote text-warning-soft-fg">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                  {warning.message}
                </li>
              ))}
            </ul>
          )}
          {canManage && editable && (
            <div className="flex flex-wrap gap-3">
              <Button variant="secondary" icon={Pencil} onClick={() => setEditing(true)}>
                {t('bq_edit')}
              </Button>
              {event.frozen ? (
                <Button variant="secondary" icon={LockOpen} onClick={() => setUnfreezing(true)}>
                  {t('bq_unfreeze')}
                </Button>
              ) : (
                <Button
                  icon={Lock}
                  loading={freeze.isPending}
                  onClick={() =>
                    freeze.mutate({
                      uuid: event.uuid,
                      version: event.version,
                      freeze: true,
                    })
                  }
                >
                  {t('bq_freeze')}
                </Button>
              )}
            </div>
          )}
        </Card>

        <DataTable caption={t('bq_lines')}>
          <thead>
            <tr>
              <Th>{t('bq_lineName')}</Th>
              <Th numeric>{t('bq_quantity')}</Th>
              <Th numeric>{t('bq_unitPrice')}</Th>
              <Th numeric>{t('bq_amount')}</Th>
            </tr>
          </thead>
          <tbody>
            {event.lines.map((line) => (
              <tr key={line.uuid}>
                <Td>
                  {line.name}
                  <span className="block text-caption text-fg-muted">
                    {line.kind_label}
                    {line.frozen ? ` · ${t('bq_frozenShort')}` : ''}
                  </span>
                </Td>
                <Td numeric>{line.per_person ? `${Number(line.quantity)} × ${t('bq_guestsShort', { n: line.persons ?? 0 })}` : Number(line.quantity)}</Td>
                <Td numeric>{money(line.unit_price)}</Td>
                <Td numeric>{money(line.amount)}</Td>
              </tr>
            ))}
            <tr className="font-bold">
              <Td>{t('bq_total')}</Td>
              <Td numeric />
              <Td numeric />
              <Td numeric>{money(event.total)}</Td>
            </tr>
          </tbody>
        </DataTable>
        <Card className="grid gap-2 text-body sm:grid-cols-4">
          <Info label={t('bq_packageTotal')} value={money(event.package_total)} />
          <Info label={t('bq_hallTotal')} value={money(event.hall_total)} />
          <Info label={t('bq_extrasTotal')} value={money(event.extras_total)} />
          <Info label={t('bq_total')} value={money(event.total)} />
          <Info label={t('bq_paid')} value={money(event.paid_total)} />
          <Info label={t('bq_outstanding')} value={money(event.outstanding)} />
          {event.quote && (
            <Info
              label={t('bq_quote')}
              value={t('bq_quoteValue', {
                total: event.quote.total,
                currency: event.quote.currency,
                rate: event.quote.rate,
              })}
            />
          )}
        </Card>

        <BanquetEventSections event={event} />
      </div>

      {editing && (
        <BanquetEventDialog
          target={{ kind: 'edit', event }}
          branchId={event.branch_id}
          onClose={() => setEditing(false)}
          onSaved={(message) => {
            setNotice(message);
            setEditing(false);
          }}
        />
      )}
      {unfreezing && (
        <UnfreezeDialog
          event={event}
          busy={freeze.isPending}
          error={actionError}
          onClose={() => setUnfreezing(false)}
          onConfirm={(reason) =>
            freeze.mutate({
              uuid: event.uuid,
              version: event.version,
              freeze: false,
              reason,
            })
          }
        />
      )}
    </>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <dt className="text-footnote text-fg-muted">{label}</dt>
      <dd className="font-bold text-fg">{value}</dd>
    </div>
  );
}

function UnfreezeDialog({ event, busy, error, onClose, onConfirm }: { event: BanquetEvent; busy: boolean; error: string | null; onClose: () => void; onConfirm: (reason: string) => void }) {
  const { t } = useI18n();
  const [reason, setReason] = useState('');
  const [local, setLocal] = useState<string | null>(null);
  return (
    <Dialog open title={t('bq_unfreezeTitle')} description={t('bq_unfreezeText', { name: event.customer_name })} busy={busy} closeLabel={t('mgmt_close')} onClose={onClose}>
      <form
        noValidate
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          if (reason.trim().length < 3) {
            setLocal(t('bq_err_reason'));
            return;
          }
          onConfirm(reason.trim());
        }}
      >
        <TextField label={t('bq_reason')} value={reason} onChange={setReason} maxLength={500} autoFocus error={local ?? undefined} />
        <FormError message={error} />
        <div className="flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            {t('mgmt_cancel')}
          </Button>
          <Button type="submit" loading={busy}>
            {t('bq_unfreeze')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

export function BanquetEventMissing() {
  const { t } = useI18n();
  return <EmptyState icon={CalendarHeart} title={t('mgmt_notFound')} text={t('bq_noAccess')} />;
}
