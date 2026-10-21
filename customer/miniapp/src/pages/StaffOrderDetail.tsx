import type { ReactNode } from 'react';
import { useParams } from 'react-router-dom';
import { ClipboardList, Clock, MapPin, Navigation, Phone, StickyNote } from 'lucide-react';
import {
  actionLabel,
  getOrderActions,
  getStageDurations,
  localize,
  orderTypeLabel,
  paymentMethodLabel,
  stageRows,
  staffText,
  statusLabel,
  useBoardAction,
  useStaffOrder,
  type OrderAction,
  type StaffOrderDetail as StaffOrderDetailData,
} from '@rizqim/staff';
import { useLanguageStore } from '../store/languageStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { useMainButton } from '../hooks/useMainButton';
import { useNow } from '../hooks/useNow';
import { useStaffText } from '../hooks/useStaffText';
import { Button, Card, CardSkeletons, EmptyState, InlineError, PageHeader, Section, StatusBadge } from '../components/ui';
import { Pill } from '../components/staff/Pill';
import { OrderLines } from '../components/staff/OrderLines';
import { CHANNEL_LABELS, ORDER_TEXT } from '../components/staff/orderText';
import { getApiErrorMessage, getApiErrorStatus, logApiError } from '../lib/apiError';
import { getPollInterval, POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import { toAmount } from '../lib/money';
import { formatDateTime, formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { cleanAddress, mapRouteUrl, openExternalLink } from '../lib/openMap';
import { formatWaiting, isWaiting, kitchenProgress, toOrderRef, waitingMinutes, waitingTone } from '../lib/staffOrders';
import { getStaffCancelReasonLabel } from '../lib/cancelReasons';

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-baseline justify-between gap-3 text-body">
      <dt className="text-fg-muted">{label}</dt>
      <dd className="text-right font-medium text-fg">{children}</dd>
    </div>
  );
}

// The step the main button takes: the next one that needs neither a courier nor a reason (those are chosen on the list)
function primaryAction(order: StaffOrderDetailData): OrderAction | null {
  return getOrderActions(toOrderRef(order), 'manager').find((action) => !action.needsCourier && !action.needsReason && !action.destructive) ?? null;
}

function OrderDetailBody({ order }: { order: StaffOrderDetailData }) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(ORDER_TEXT);
  const now = useNow();
  const code = formatOrderCode(order);
  const money = (value: string | number | null | undefined) => formatMoney(toAmount(value), language);

  const board = useBoardAction({
    onMutate: () => hapticFeedback('medium'),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to update status:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || staffText('statusUpdateFailed', language));
    },
  });

  const action = primaryAction(order);
  // The one next step of the order on Telegram's main button (the fallback button elsewhere)
  useMainButton({
    text: action ? actionLabel(action.id, language) : '',
    onClick: () => {
      if (action) board.mutate({ uuid: order.uuid, action });
    },
    progress: board.isPending,
    visible: action !== null,
  });

  const minutes = waitingMinutes(order, now);
  const progress = kitchenProgress(order);
  const address = cleanAddress(order.delivery?.address, staffText('gpsLocation', language));
  const mapUrl = mapRouteUrl(order.delivery?.latitude, order.delivery?.longitude);
  const phone = order.customer?.phone;
  const timings = getStageDurations({ status_histories: order.status_history.map((entry) => ({ status: entry.status, created_at: entry.created_at })) } as never);
  const stages = timings ? stageRows(timings, language) : [];
  const channel = CHANNEL_LABELS[order.channel];
  const table = [order.hall_ref ? tx('hall', { hall: order.hall_ref }) : '', order.table_ref ? tx('table', { table: order.table_ref }) : ''].filter(Boolean).join(' · ');
  const moments: Array<[string, string | null]> = [
    [tx('createdAt'), order.created_at],
    [tx('acceptedAt'), order.accepted_at],
    [tx('preparingAt'), order.preparing_at],
    [tx('readyAt'), order.ready_at],
    [tx('closedAt'), order.closed_at],
  ];

  return (
    <div className="flex flex-col gap-4 px-4 pt-2 animate-fade-in">
      <PageHeader title={tx('orderTitle', { code })} subtitle={order.branch_name ?? undefined} back />

      <Card className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <StatusBadge status={order.status} audience="staff" />
          <span className="text-footnote text-fg-muted">
            {orderTypeLabel(order.type, language)}
            {table && ` · ${table}`}
            {order.guests_count ? ` · ${tx('guests', { n: order.guests_count })}` : ''}
          </span>
          {minutes !== null && isWaiting(order) && (
            <Pill tone={waitingTone(minutes, order.status)} icon={Clock} className="ml-auto">
              {tx('waiting', { time: formatWaiting(minutes, language) })}
            </Pill>
          )}
        </div>
        {progress && (
          <p className="text-footnote text-fg-muted">{tx('kitchenProgress', { ready: progress.ready, total: progress.total })}</p>
        )}
        {order.cancel_reason && (
          <div className="flex flex-col gap-0.5 rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
            <span className="font-bold">{getStaffCancelReasonLabel(order.cancel_reason, language)}</span>
            {order.cancel_note && <span>"{order.cancel_note}"</span>}
          </div>
        )}
        {order.notes && (
          <p className="flex items-start gap-1.5 rounded-control bg-warning-soft px-2.5 py-2 text-body text-warning-soft-fg">
            <StickyNote aria-hidden="true" className="mt-1 size-4 shrink-0" />
            <span className="min-w-0">
              <span className="sr-only">{tx('note')}: </span>
              {order.notes}
            </span>
          </p>
        )}
      </Card>

      {order.customer ? (
        <Section title={tx('customer')}>
          <Card className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-body font-bold text-fg">{order.customer.name}</p>
                <p className="truncate text-footnote text-fg-muted">{phone || staffText('noPhone', language)}</p>
              </div>
              {phone && (
                <a
                  href={`tel:${phone}`}
                  aria-label={`${tx('call')}: ${order.customer.name ?? phone}`}
                  onClick={() => hapticFeedback('light')}
                  className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-soft-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                  <Phone aria-hidden="true" className="size-5" />
                </a>
              )}
            </div>
            {order.delivery?.address && (
              <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
                <p className="flex items-start gap-2 text-body font-medium text-fg">
                  <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                  <span>{address}</span>
                </p>
                {order.delivery.zone_name && <p className="text-footnote text-fg-muted">{tx('zone', { zone: order.delivery.zone_name })}</p>}
                {mapUrl && (
                  <Button
                    variant="secondary"
                    icon={Navigation}
                    onClick={() => {
                      hapticFeedback('light');
                      openExternalLink(mapUrl);
                    }}
                    fullWidth
                  >
                    {tx('openMap')}
                  </Button>
                )}
              </div>
            )}
            <p className="text-footnote text-fg-muted">
              {tx('courier')}: <span className="font-bold text-fg">{order.courier ? order.courier.name || staffText('courierFallback', language) : tx('noCourier')}</span>
            </p>
          </Card>
        </Section>
      ) : (
        <p className="px-1 text-footnote text-fg-muted">{tx('hiddenForRole')}</p>
      )}

      <Section title={tx('items')}>
        <Card>
          <OrderLines items={order.items} prices formatPrice={money} />
        </Card>
      </Section>

      <Section title={tx('sums')}>
        <Card>
          <dl className="flex flex-col gap-2">
            <Row label={tx('subtotal')}>{money(order.subtotal)}</Row>
            {toAmount(order.discount) > 0 && <Row label={tx('discount')}>−{money(order.discount)}</Row>}
            {toAmount(order.delivery_price) > 0 && <Row label={tx('deliveryPrice')}>{money(order.delivery_price)}</Row>}
            {toAmount(order.service_charge_amount) > 0 && <Row label={tx('serviceCharge')}>{money(order.service_charge_amount)}</Row>}
            {toAmount(order.place_fee_amount) > 0 && <Row label={tx('placeFee')}>{money(order.place_fee_amount)}</Row>}
            <div className="flex items-baseline justify-between gap-3 border-t border-line pt-2">
              <dt className="text-headline font-bold text-fg">{tx('total')}</dt>
              <dd className="text-headline font-bold text-brand">{money(order.total_price)}</dd>
            </div>
            <Row label={tx('payment')}>
              {paymentMethodLabel(order.payment_method, language)}
              {order.payment_status === 'paid' && <span className="font-bold text-success-soft-fg">{staffText('paidSuffix', language)}</span>}
            </Row>
            {channel && <Row label={tx('channel')}>{localize(channel, language)}</Row>}
          </dl>
        </Card>
      </Section>

      <Section title={tx('history')}>
        <Card className="flex flex-col gap-3">
          <ol className="flex flex-col gap-1.5">
            {order.status_history.map((entry, index) => (
              <li key={`${entry.status}-${index}`} className="flex items-baseline justify-between gap-3 text-body">
                <span className="font-medium text-fg">{statusLabel(entry.status, language)}</span>
                <time dateTime={entry.created_at ?? undefined} className="text-footnote text-fg-muted">
                  {formatDateTime(entry.created_at, language)}
                </time>
              </li>
            ))}
          </ol>
          {moments.some(([, value]) => value) && (
            <dl className="flex flex-col gap-1 border-t border-line pt-3 text-footnote">
              {moments
                .filter(([, value]) => value)
                .map(([label, value]) => (
                  <div key={label} className="flex justify-between gap-3">
                    <dt className="text-fg-muted">{label}</dt>
                    <dd className="font-medium text-fg">{formatTime(value)}</dd>
                  </div>
                ))}
            </dl>
          )}
          {stages.length > 0 && (
            <dl className="flex flex-col gap-1 border-t border-line pt-3 text-footnote">
              <dt className="font-bold text-fg-muted">{tx('stages')}</dt>
              {stages.map(([label, value]) => (
                <div key={label} className="flex justify-between gap-3">
                  <dt className="text-fg-muted">{label}</dt>
                  <dd className="font-bold text-fg">{value}</dd>
                </div>
              ))}
            </dl>
          )}
        </Card>
      </Section>
    </div>
  );
}

// The order of GET /staff/orders/{uuid}: every line with its modifiers, weight and kitchen state, the notes, the sums, the
// times and the history. Kept fresh by the same polling and socket events as the list.
export default function StaffOrderDetail() {
  const { uuid } = useParams();
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(ORDER_TEXT);
  const realtimeConnected = useRealtimeStore((state) => state.connected);
  const pollMs = POLL_INTERVAL_MS.managerOrders;
  const { data: order, isLoading, error, refetch, isRefetching } = useStaffOrder(uuid, {
    ...pollingQueryOptions(getPollInterval(pollMs, realtimeConnected)),
    staleTime: pollMs,
    // An order that is not there (another branch's, deleted) will not appear by asking again
    retry: (failures, err) => getApiErrorStatus(err) !== 404 && failures < 2,
  });

  if (!order && isLoading) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={staffText('orderWord', language)} back />
        <CardSkeletons count={3} lines={3} label={staffText('loading', language)} />
      </div>
    );
  }
  if (!order) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={staffText('orderWord', language)} back />
        {getApiErrorStatus(error) === 404 ? (
          <EmptyState icon={ClipboardList} title={tx('notFound')} text={tx('notFoundText')} />
        ) : (
          <InlineError onRetry={() => void refetch()} retrying={isRefetching} />
        )}
      </div>
    );
  }
  return <OrderDetailBody order={order} />;
}
