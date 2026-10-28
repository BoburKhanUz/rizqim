'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Ban, ClipboardList, ExternalLink, MessageSquare, RefreshCw, ShieldAlert, Undo2, Wrench } from 'lucide-react';
import { Button, Card, CardSkeletons, cx, EmptyState, formatOrderCode, IconButton, InlineError, Notice, PageHeader, Pill, Section, StatusBadge } from '@rizqim/ui';
import { ApiError } from '@/lib/api/client';
import { ORDER_KEYS, useOrder, type OrderCard, type RefundRequest } from '@/lib/api/orders';
import { errorText } from '@/lib/errors';
import { when } from '@/lib/format';
import { OPS_EVENTS, usePlatformOps } from '@/lib/realtime';
import { useCan } from '../AuthProvider';
import { useT } from '../I18nProvider';
import { CancelDialog, DecideRefundDialog, MessageDialog, RefundRequestDialog, StatusDialog } from './OrderDialogs';
import { exactMoney, money, STAGE_LABEL } from './ordersModel';
import { SlaBadge } from './SlaBadge';
import { useNow } from './useNow';

type Dialog =
  | { kind: 'cancel' }
  | { kind: 'status' }
  | { kind: 'message'; to: 'store' | 'customer' }
  | { kind: 'refund' }
  | { kind: 'decide'; request: RefundRequest; decision: 'approve' | 'reject' };

// The order card (/orders/[uuid]): everything about one marketplace order and what the member may do to it. The buttons are
// the ones the backend offers this member now (`actions`); each opens a dialog that asks for a reason. Refreshed by its
// ops.order.updated event and by polling.
export function OrderCardView({ uuid }: { uuid: string }) {
  const t = useT();
  const { ready, can } = useCan();
  const allowed = ready && can('orders.view');
  const queryClient = useQueryClient();
  const now = useNow(15_000);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const { live } = usePlatformOps(
    OPS_EVENTS.orderUpdated,
    (payload) => {
      const order = payload && typeof payload === 'object' ? (payload as { order?: { uuid?: unknown } }).order : undefined;
      if (order?.uuid === uuid) void queryClient.invalidateQueries({ queryKey: ORDER_KEYS.card(uuid) });
    },
    { enabled: allowed, pollMs: 0 },
  );
  const card = useOrder(uuid, { enabled: allowed, live });

  if (ready && !allowed) {
    return (
      <>
        <PageHeader title={t('nav_orders')} />
        <EmptyState icon={ShieldAlert} title={t('forbidden')} />
      </>
    );
  }

  const back = (
    <Link href="/orders" className="mb-3 inline-flex min-h-11 items-center gap-1.5 text-footnote font-bold text-brand hover:underline">
      <ArrowLeft aria-hidden="true" className="size-4" />
      {t('orders_back')}
    </Link>
  );

  if (card.isError) {
    const missing = card.error instanceof ApiError && card.error.status === 404;
    return (
      <>
        {back}
        {missing ? (
          <EmptyState icon={ClipboardList} title={t('orders_notFound')} text={t('orders_notFoundText')} />
        ) : (
          <InlineError title={t('orders_cardLoadError')} message={errorText(card.error, t)} retryLabel={t('retry')} retrying={card.isFetching} onRetry={() => void card.refetch()} />
        )}
      </>
    );
  }
  if (!card.isSuccess) {
    return (
      <>
        {back}
        <CardSkeletons count={3} lines={4} label={t('loading')} />
      </>
    );
  }

  const order = card.data;
  const code = formatOrderCode(order);
  const done = (message: string) => {
    setDialog(null);
    setNotice(message);
  };
  const actions = order.actions;
  const anyAction = actions.can_cancel || (actions.can_intervene && actions.transitions.length > 0) || actions.message_store || actions.message_customer || actions.can_request_refund;

  return (
    <>
      {back}
      <PageHeader
        title={t('orders_cardTitle', { code })}
        subtitle={t('orders_created', { time: when(order.created_at) })}
        action={<IconButton icon={RefreshCw} label={t('orders_refresh')} onClick={() => void card.refetch()} disabled={card.isFetching} className={cx(card.isFetching && 'animate-spin motion-reduce:animate-none')} />}
      />
      <Notice message={notice} />

      <div className="flex max-w-5xl flex-col gap-5">
        <Card className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={order.status} label={order.status_label} />
            <SlaBadge sla={order.sla} now={now} />
            {order.refund_pending && <Pill tone="warning">{t('orders_refundPending')}</Pill>}
            {order.manual_refund_required && <Pill tone="danger">{t('orders_manualRefund')}</Pill>}
          </div>
          <p className="break-words text-footnote text-fg">
            {[order.store?.name, order.branch?.name, order.region?.name, order.channel_label].filter(Boolean).join(' · ')} · <span className="font-bold">{money(order.total)}</span>
          </p>
          {order.cancel && <p className="text-footnote text-danger-soft-fg">{t('orders_cancelInfo', { reason: [order.cancel.reason_label, order.cancel.note].filter(Boolean).join(' — ') || '—' })}</p>}

          <div role="group" aria-label={t('orders_actions')} className="flex flex-wrap gap-2">
            {actions.can_cancel && (
              <Button variant="danger" icon={Ban} onClick={() => setDialog({ kind: 'cancel' })}>
                {t('orders_cancelAction')}
              </Button>
            )}
            {actions.can_intervene && actions.transitions.length > 0 && (
              <Button variant="secondary" icon={Wrench} onClick={() => setDialog({ kind: 'status' })}>
                {t('orders_fixStatusAction')}
              </Button>
            )}
            {actions.message_store && (
              <Button variant="secondary" icon={MessageSquare} onClick={() => setDialog({ kind: 'message', to: 'store' })}>
                {t('orders_messageStore')}
              </Button>
            )}
            {actions.message_customer && (
              <Button variant="secondary" icon={MessageSquare} onClick={() => setDialog({ kind: 'message', to: 'customer' })}>
                {t('orders_messageCustomer')}
              </Button>
            )}
            {actions.can_request_refund && (
              <Button variant="secondary" icon={Undo2} onClick={() => setDialog({ kind: 'refund' })}>
                {t('orders_requestRefund')}
              </Button>
            )}
          </div>
          {!anyAction && <p className="text-footnote text-fg-muted">{can('orders.intervene') ? t('orders_noActions') : t('orders_viewOnly')}</p>}
        </Card>

        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <HistorySection order={order} />
          <SlaSection order={order} />
          <ItemsSection order={order} />
          <MoneySection order={order} onDecide={(request, decision) => setDialog({ kind: 'decide', request, decision })} />
          <PeopleSection order={order} />
          <FiscalSection order={order} />
        </div>
        <AuditSection order={order} />
      </div>

      {dialog?.kind === 'cancel' && <CancelDialog order={order} onDone={done} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'status' && <StatusDialog order={order} onDone={done} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'message' && <MessageDialog order={order} to={dialog.to} onDone={done} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'refund' && <RefundRequestDialog order={order} onDone={done} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'decide' && <DecideRefundDialog orderUuid={order.uuid} request={dialog.request} decision={dialog.decision} onDone={done} onClose={() => setDialog(null)} />}
    </>
  );
}

function HistorySection({ order }: { order: OrderCard }) {
  const t = useT();
  return (
    <Section title={t('orders_history')}>
      <Card>
        <ol className="flex flex-col gap-3">
          {order.history.map((row, index) => (
            <li key={`${row.status}-${row.at}-${index}`} className="flex flex-col gap-0.5 border-l-2 border-line pl-3">
              <span className="flex flex-wrap items-center gap-2">
                <StatusBadge status={row.status} label={row.status_label} />
                <span className="text-caption text-fg-muted">{when(row.at)}</span>
              </span>
              <span className="text-footnote text-fg">{row.actor ? `${row.actor.name} (${row.actor.team ? t('orders_historyTeam') : row.actor.role})` : t('orders_historySystem')}</span>
              {row.notes && <span className="break-words text-footnote text-fg-muted">{row.notes}</span>}
            </li>
          ))}
        </ol>
      </Card>
    </Section>
  );
}

function SlaSection({ order }: { order: OrderCard }) {
  const t = useT();
  return (
    <Section title={t('orders_slaTimeline')}>
      <Card className="flex flex-col gap-3">
        {order.sla_timeline.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('orders_slaNone')}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {order.sla_timeline.map((stage) => {
              const label = t(STAGE_LABEL[stage.stage]);
              const verdict = stage.state === 'late' ? t('orders_slaStageLate', { late: stage.late_by_minutes }) : t('orders_slaStageOk');
              return (
                <li key={stage.stage} className="flex flex-wrap items-center gap-2 text-footnote">
                  <span className="text-fg">
                    {stage.running ? t('orders_slaStageRunning', { stage: label, limit: stage.limit_minutes }) : t('orders_slaStageDone', { stage: label, took: stage.took_minutes ?? 0, limit: stage.limit_minutes })}
                  </span>
                  {!stage.running && <Pill tone={stage.state === 'late' ? 'danger' : stage.state === 'warning' ? 'warning' : 'success'}>{verdict}</Pill>}
                </li>
              );
            })}
          </ul>
        )}
        {order.sla_events.length > 0 && (
          <ul className="flex flex-col gap-1 text-caption text-fg-muted">
            {order.sla_events.map((event) => (
              <li key={`${event.stage}-${event.state}`}>
                {t('orders_slaCrossed', { stage: t(STAGE_LABEL[event.stage]), state: event.state === 'late' ? t('orders_slaCrossedLate') : t('orders_slaCrossedWarning'), time: when(event.crossed_at) })}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function ItemsSection({ order }: { order: OrderCard }) {
  const t = useT();
  const totals: [string, string][] = [
    [t('orders_subtotal'), order.totals.subtotal],
    [t('orders_discount'), order.totals.discount],
    [t('orders_delivery'), order.totals.delivery_price],
    [t('orders_serviceCharge'), order.totals.service_charge],
    [t('orders_placeFee'), order.totals.place_fee],
  ];
  return (
    <Section title={t('orders_items')}>
      <Card className="flex flex-col gap-3">
        <ul className="flex flex-col gap-2">
          {order.items.map((item) => (
            <li key={item.id} className={cx('flex items-start justify-between gap-3 text-footnote', item.void && 'text-fg-muted line-through')}>
              <span className="min-w-0 break-words text-fg">
                {item.quantity} × {item.name}
                {item.variant ? ` (${item.variant})` : ''}
                {item.modifiers ? ` — ${item.modifiers}` : ''}
                {item.void ? ` · ${t('orders_itemVoid')}` : ''}
              </span>
              <span className="shrink-0 font-bold text-fg">{money(item.total)}</span>
            </li>
          ))}
        </ul>
        <dl className="flex flex-col gap-1 border-t border-line pt-3 text-footnote">
          {totals
            .filter(([, value]) => Number(value) !== 0)
            .map(([label, value]) => (
              <div key={label} className="flex justify-between gap-3">
                <dt className="text-fg-muted">{label}</dt>
                <dd className="text-fg">{exactMoney(value)}</dd>
              </div>
            ))}
          <div className="flex justify-between gap-3 font-bold">
            <dt className="text-fg">{t('orders_total')}</dt>
            <dd className="text-fg">{exactMoney(order.totals.total)}</dd>
          </div>
          <div className="flex justify-between gap-3">
            <dt className="text-fg-muted">{t('orders_commission')}</dt>
            <dd className="text-fg-muted">{money(order.totals.commission)}</dd>
          </div>
        </dl>
      </Card>
    </Section>
  );
}

function MoneySection({ order, onDecide }: { order: OrderCard; onDecide: (request: RefundRequest, decision: 'approve' | 'reject') => void }) {
  const t = useT();
  return (
    <Section title={t('orders_payments')}>
      <Card className="flex flex-col gap-3">
        <dl className="grid grid-cols-3 gap-2 text-footnote">
          {(
            [
              [t('orders_paid'), order.refunds.paid],
              [t('orders_refunded'), order.refunds.refunded],
              [t('orders_refundable'), order.refunds.refundable],
            ] as const
          ).map(([label, value]) => (
            <div key={label}>
              <dt className="text-fg-muted">{label}</dt>
              <dd className="font-bold text-fg">{exactMoney(value)}</dd>
            </div>
          ))}
        </dl>
        {order.payments.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('orders_noPayments')}</p>
        ) : (
          <ul className="flex flex-col gap-2">
            {order.payments.map((payment) => (
              <li key={payment.uuid} className="flex flex-col gap-1 text-footnote">
                <span className="flex flex-wrap items-center gap-2">
                  <span className="font-bold text-fg">{payment.provider}</span>
                  <span className="text-fg">{exactMoney(payment.amount)}</span>
                  <Pill tone={payment.status === 'paid' ? 'success' : payment.status === 'refunded' ? 'neutral' : 'warning'}>{payment.status}</Pill>
                  {payment.manual_refund_required && <Pill tone="danger">{t('orders_paymentManual')}</Pill>}
                </span>
                {payment.manual_refund_approved.map((row) => (
                  <span key={row.refund_request_id} className="text-caption text-fg-muted">
                    {t('orders_paymentManualDone', { amount: exactMoney(row.amount) })} · {when(row.approved_at)}
                  </span>
                ))}
              </li>
            ))}
          </ul>
        )}
        {order.refunds.requests.length > 0 && (
          <div className="flex flex-col gap-2 border-t border-line pt-3">
            <h3 className="text-footnote font-bold text-fg-muted">{t('orders_refundRequests')}</h3>
            <ul className="flex flex-col gap-3">
              {order.refunds.requests.map((request) => (
                <li key={request.id} className="flex flex-col gap-1.5 text-footnote" data-testid={`refund-${request.id}`}>
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="font-bold text-fg">{exactMoney(request.amount)}</span>
                    <Pill tone={request.status === 'approved' ? 'success' : request.status === 'rejected' ? 'danger' : 'warning'}>{request.status_label}</Pill>
                  </span>
                  <span className="break-words text-fg">{request.reason}</span>
                  <span className="text-caption text-fg-muted">{t('orders_refundBy', { name: request.requested_by?.name ?? '—', time: when(request.requested_at) })}</span>
                  {request.decided_by && (
                    <span className="text-caption text-fg-muted">
                      {t('orders_refundDecidedBy', { name: request.decided_by.name, time: when(request.decided_at) })}
                      {request.decision_note ? ` — ${request.decision_note}` : ''}
                    </span>
                  )}
                  {request.execution && Number(request.execution.manual_amount) > 0 && <span className="text-caption font-bold text-warning-soft-fg">{t('orders_refundManualLeft', { amount: exactMoney(request.execution.manual_amount) })}</span>}
                  {request.execution && Number(request.execution.automatic_amount) > 0 && <span className="text-caption text-fg-muted">{t('orders_refundAutomatic', { amount: exactMoney(request.execution.automatic_amount) })}</span>}
                  {request.status === 'pending' && request.mine && <span className="text-caption text-fg-muted">{t('orders_refundAwaiting')}</span>}
                  {request.can_decide && (
                    <span className="flex flex-wrap gap-2">
                      <Button onClick={() => onDecide(request, 'approve')}>{t('orders_approve')}</Button>
                      <Button variant="secondary" onClick={() => onDecide(request, 'reject')}>
                        {t('orders_reject')}
                      </Button>
                    </span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        )}
      </Card>
    </Section>
  );
}

function PeopleSection({ order }: { order: OrderCard }) {
  const t = useT();
  const rows: [string, string | null][] = [
    [t('orders_customer'), order.customer ? [order.customer.name, order.customer.phone].filter(Boolean).join(' · ') : null],
    [t('orders_courier'), order.courier ? [order.courier.name, order.courier.phone].filter(Boolean).join(' · ') : order.type === 'delivery' ? t('orders_courierNone') : null],
    [t('orders_store'), order.store ? [order.store.name, order.store.phone].filter(Boolean).join(' · ') : null],
    [t('orders_branch'), order.branch ? [order.branch.name, order.branch.address].filter(Boolean).join(' · ') : null],
    [t('orders_address'), order.delivery_address],
    [t('orders_notes'), order.notes],
  ];
  return (
    <Section title={t('orders_people')}>
      <Card>
        <dl className="flex flex-col gap-2 text-footnote">
          {rows
            .filter(([, value]) => value)
            .map(([label, value]) => (
              <div key={label} className="flex flex-col">
                <dt className="text-fg-muted">{label}</dt>
                <dd className="break-words text-fg">{value}</dd>
              </div>
            ))}
        </dl>
      </Card>
    </Section>
  );
}

function FiscalSection({ order }: { order: OrderCard }) {
  const t = useT();
  return (
    <Section title={t('orders_fiscal')}>
      <Card>
        {order.fiscal_receipts.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('orders_fiscalNone')}</p>
        ) : (
          <ul className="flex flex-col gap-2 text-footnote">
            {order.fiscal_receipts.map((receipt) => (
              <li key={receipt.uuid} className="flex flex-col gap-0.5">
                <span className="text-fg">{t('orders_fiscalReceipt', { type: receipt.type, status: receipt.status, number: receipt.receipt_number ? ` · №${receipt.receipt_number}` : '' })}</span>
                <span className="text-caption text-fg-muted">
                  {[receipt.provider, money(receipt.total), when(receipt.issued_at)].filter(Boolean).join(' · ')}
                </span>
                {receipt.error && <span className="break-words text-caption text-danger-soft-fg">{receipt.error}</span>}
                {receipt.qr_url && (
                  <a href={receipt.qr_url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 text-caption font-bold text-brand hover:underline">
                    {t('orders_fiscalOpen')}
                    <ExternalLink aria-hidden="true" className="size-3.5" />
                  </a>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}

function AuditSection({ order }: { order: OrderCard }) {
  const t = useT();
  return (
    <Section title={t('orders_audit')}>
      <Card>
        {order.audit.length === 0 ? (
          <p className="text-footnote text-fg-muted">{t('orders_auditEmpty')}</p>
        ) : (
          <ul className="flex flex-col gap-2 text-footnote">
            {order.audit.map((entry) => (
              <li key={entry.id} className="flex flex-col gap-0.5">
                <span className="break-all font-mono font-bold text-fg">{entry.action}</span>
                <span className="text-caption text-fg-muted">
                  {[entry.actor?.name, when(entry.created_at), entry.reason].filter(Boolean).join(' · ')}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </Section>
  );
}
