import { useState } from 'react';
import { Bike, CheckCircle2, Clock, MapPin, Navigation, PackageCheck, Phone, StickyNote, Wallet } from 'lucide-react';
import {
  actionLabel,
  formatLineQuantity,
  getShippingAddress,
  GROUP_STATUSES,
  paymentMethodLabel,
  staffText,
  useCompleteOrder,
  useUpdateOrderStatus,
  type Order,
} from '@rizqim/staff';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { Button, CardSkeletons, EmptyState, InlineError, PageHeader, StatusBadge, cx } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { Pill } from '../components/staff/Pill';
import { useMainButton } from '../hooks/useMainButton';
import { getApiErrorMessage, logApiError } from '../lib/apiError';
import { sumAmounts, toAmount } from '../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { cleanAddress, linkInAddress, mapRouteUrl, openExternalLink } from '../lib/openMap';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, useStaffOrderHistory, useStaffOrders } from '../hooks/useStaffOrders';
import { TruncatedOrdersNotice } from '../components/OrderListParts';

// The deliveries a courier works on: assigned to him, or already out with the order (@rizqim/staff's group)
const ACTIVE_STATUSES: readonly string[] = GROUP_STATUSES.courier;

// A delivery already on its way comes first (the courier is out with it), then the oldest waiting one
const activeRank = (order: Order) => (order.status === 'courier_assigned' ? 1 : 0);
const byDeliveryPriority = (a: Order, b: Order) => activeRank(a) - activeRank(b) || new Date(a.created_at ?? 0).getTime() - new Date(b.created_at ?? 0).getTime();

interface DeliveryCardProps {
  order: Order;
  // The order the screen's main button (Telegram's MainButton) acts on: its action is not drawn in the card
  isCurrent: boolean;
  showCurrentMark: boolean;
  onPickUp: (order: Order) => void;
  onDeliver: (order: Order) => void;
  busy: boolean;
}

function DeliveryCard({ order, isCurrent, showCurrentMark, onPickUp, onDeliver, busy }: DeliveryCardProps) {
  const { language } = useLanguageStore();
  const code = formatOrderCode(order);
  const address = cleanAddress(order.address, staffText('gpsLocation', language));
  const shipping = getShippingAddress(order);
  const mapUrl = mapRouteUrl(shipping?.latitude, shipping?.longitude) ?? linkInAddress(order.address);
  const items = order.items ?? [];
  const phone = order.user?.phone;
  const waiting = order.status === 'courier_assigned';
  const paid = order.payment_status === 'paid';

  return (
    <article
      aria-label={`${staffText('orderWord', language)} ${code}`}
      className={cx('flex flex-col gap-3 rounded-card border bg-surface p-3', isCurrent && showCurrentMark ? 'border-brand' : 'border-line')}
    >
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        {isCurrent && showCurrentMark && <Pill tone="brand">{staffText('currentMark', language)}</Pill>}
        <time dateTime={order.created_at ?? undefined} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.created_at)}
        </time>
      </div>

      <div className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-body font-bold text-fg">{order.user?.name}</h2>
          <p className="truncate text-footnote text-fg-muted">{phone || staffText('noPhone', language)}</p>
        </div>
        {phone && (
          <a
            href={`tel:${phone}`}
            aria-label={`${staffText('call', language)}: ${order.user?.name ?? phone}`}
            onClick={() => hapticFeedback('light')}
            className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-soft-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            <Phone aria-hidden="true" className="size-5" />
          </a>
        )}
      </div>

      {order.address && (
        <div className="flex flex-col gap-2 rounded-control bg-surface-2 p-3">
          <p className="flex items-start gap-2 text-body font-medium text-fg">
            <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
            <span className="line-clamp-3">{address}</span>
          </p>
          {mapUrl && (
            <Button variant="secondary" icon={Navigation} onClick={() => { hapticFeedback('light'); openExternalLink(mapUrl); }} fullWidth>
              {staffText('openOnMap', language)}
            </Button>
          )}
        </div>
      )}

      {order.notes && (
        <p className="flex items-start gap-1.5 text-footnote text-fg-muted">
          <StickyNote aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
          <span className="line-clamp-2">{order.notes}</span>
        </p>
      )}

      {items.length > 0 && (
        <ul aria-label={staffText('itemsList', language)} className="flex flex-wrap gap-1.5">
          {items.map((item, index) => (
            <li key={index} className="rounded-control bg-surface-2 px-2 py-1 text-footnote text-fg">
              {item.product_name} {formatLineQuantity(item.quantity, item.unit, language)}
            </li>
          ))}
        </ul>
      )}

      <div className="flex items-center justify-between gap-3 border-t border-line pt-2.5">
        <span className="inline-flex items-center gap-1.5 text-footnote text-fg-muted">
          <Wallet aria-hidden="true" className="size-3.5" />
          {paymentMethodLabel(order.payment_method, language)}
          {paid ? (
            <Pill tone="success">{staffText('paid', language)}</Pill>
          ) : order.payment_method === 'cash' ? (
            <Pill tone="warning">{staffText('cashDue', language)}</Pill>
          ) : null}
        </span>
        <span className="shrink-0 text-headline font-bold text-brand">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>

      {/* Every delivery but the current one has its own action; the current one is on the main button */}
      {!isCurrent &&
        (waiting ? (
          <Button icon={Navigation} loading={busy} onClick={() => onPickUp(order)} fullWidth aria-label={`${actionLabel('pick_up', language, 'courier')} ${code}`}>
            {actionLabel('pick_up', language, 'courier')}
          </Button>
        ) : (
          <Button variant="success" icon={CheckCircle2} loading={busy} onClick={() => onDeliver(order)} fullWidth aria-label={`${actionLabel('confirm_delivery', language, 'courier')} ${code}`}>
            {actionLabel('confirm_delivery', language, 'courier')}
          </Button>
        ))}
    </article>
  );
}

export default function CourierDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');

  // Courier assigned orders: polled while the screen is visible (active deliveries change quickly) and pushed over the socket
  const { data: activeData, isLoading, error, dataUpdatedAt, refetch: refetchActive, isRefetching: isRefetchingActive } = useStaffOrders('courier', POLL_INTERVAL_MS.courierOrders);
  const allOrders = activeData?.orders ?? NO_ORDERS;

  // What was delivered today: the finished orders, loaded back to the start of the day. "Today" is the day of the last
  // refresh, so a screen left open over midnight starts the new day with the next poll
  const [openedAt] = useState(() => Date.now());
  const startOfToday = new Date(Math.max(dataUpdatedAt, openedAt)).setHours(0, 0, 0, 0);
  const history = useStaffOrderHistory('courier', { since: startOfToday, pageSize: 50 });

  // Complete order mutation (the package refetches the order lists after it)
  const completeMutation = useCompleteOrder({
    onMutate: () => hapticFeedback('medium'),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to complete order:', err);
      notificationOccurred('error');
      void showAlert(staffText('completeFailed', language));
    }
  });

  // Update status mutation (e.g. for marking as on_the_way)
  const updateStatusMutation = useUpdateOrderStatus({
    onMutate: () => hapticFeedback('medium'),
    onSuccess: () => notificationOccurred('success'),
    onError: (err) => {
      logApiError('Failed to update status:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || staffText('genericFailed', language));
    }
  });

  // Filter active and completed orders
  const activeOrders = allOrders.filter((order) => ACTIVE_STATUSES.includes(order.status)).sort(byDeliveryPriority);
  const completedOrders = history.orders.filter(
    (order) => order.status === 'delivered' && order.updated_at !== null && new Date(order.updated_at).getTime() >= startOfToday,
  );
  const hasNothingToShow = !!error && allOrders.length === 0;

  const pickUp = (order: Order) => updateStatusMutation.mutate({ uuid: order.uuid, status: 'on_the_way' });
  const deliver = (order: Order) => completeMutation.mutate(order.uuid);

  // The screen's one main action is for the current delivery: always at the bottom, above the navigation, never
  // scrolled away. With several deliveries it is the first card, marked "Hozirgi" (current).
  const current = activeOrders[0];
  const currentWaiting = current?.status === 'courier_assigned';
  const mainText = actionLabel(currentWaiting ? 'pick_up' : 'confirm_delivery', language, 'courier');
  useMainButton({
    text: mainText,
    onClick: () => {
      if (!current) return;
      if (currentWaiting) pickUp(current);
      else deliver(current);
    },
    visible: activeTab === 'active' && !!current && !hasNothingToShow,
    progress: updateStatusMutation.isPending || completeMutation.isPending,
  });

  // Compute statistics
  const todayTotalEarnings = sumAmounts(completedOrders.map((order) => order.delivery_price));

  // A failed background refresh keeps showing the last deliveries; the error screen is only for "nothing to show"
  if (hasNothingToShow) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={user?.name} subtitle={staffText('courierSubtitle', language)} />
        <InlineError onRetry={() => void refetchActive()} retrying={isRefetchingActive} />
      </div>
    );
  }

  const tabs = [
    { value: 'active' as const, label: staffText('tabActive', language, { n: activeOrders.length }) },
    { value: 'completed' as const, label: staffText('tabHandedOver', language, { n: completedOrders.length }) },
  ];
  const busyUuid = updateStatusMutation.isPending ? updateStatusMutation.variables?.uuid : completeMutation.isPending ? completeMutation.variables : undefined;

  return (
    <div className="flex flex-col gap-3 px-4 pt-2">
      <PageHeader title={user?.name} subtitle={staffText('courierSubtitle', language)} />

      <div className="grid grid-cols-2 gap-2">
        <div className="flex flex-col gap-0.5 rounded-card border border-line bg-surface px-3 py-2.5">
          <span className="flex items-center gap-1.5 text-caption font-medium text-fg-muted">
            <Wallet aria-hidden="true" className="size-3.5 text-brand" />
            {staffText('todayEarnings', language)}
          </span>
          <span className="text-headline font-bold text-fg">{formatMoney(todayTotalEarnings, language)}</span>
        </div>
        <div className="flex flex-col gap-0.5 rounded-card border border-line bg-surface px-3 py-2.5">
          <span className="flex items-center gap-1.5 text-caption font-medium text-fg-muted">
            <PackageCheck aria-hidden="true" className="size-3.5 text-success" />
            {staffText('deliveredCount', language)}
          </span>
          <span className="text-headline font-bold text-fg">
            {completedOrders.length} {staffText('piecesShort', language)}
          </span>
        </div>
      </div>

      {activeData?.truncated && <TruncatedOrdersNotice />}

      <SegmentedControl segments={tabs} value={activeTab} onChange={setActiveTab} label={staffText('ordersSection', language)} />

      <div className="flex flex-col gap-3">
        {(activeTab === 'active' ? isLoading && allOrders.length === 0 : history.isLoading) ? (
          <CardSkeletons count={2} lines={3} label={staffText('loading', language)} />
        ) : activeTab === 'active' ? (
          activeOrders.length === 0 ? (
            <EmptyState
              icon={Bike}
              title={staffText('noAssignedTitle', language)}
              text={staffText('noAssignedText', language)}
            />
          ) : (
            activeOrders.map((order, index) => (
              <DeliveryCard
                key={order.id}
                order={order}
                isCurrent={index === 0}
                showCurrentMark={activeOrders.length > 1}
                onPickUp={pickUp}
                onDeliver={deliver}
                busy={busyUuid === order.uuid}
              />
            ))
          )
        ) : completedOrders.length === 0 ? (
          <EmptyState icon={CheckCircle2} title={staffText('noDeliveredToday', language)} />
        ) : (
          completedOrders.map((order) => (
            <article key={order.id} aria-label={`${staffText('orderWord', language)} ${formatOrderCode(order)}`} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-3">
              <div className="flex items-center gap-2">
                <span className="text-headline font-bold text-fg">{formatOrderCode(order)}</span>
                <StatusBadge status={order.status} audience="staff" />
                <span className="ml-auto inline-flex items-center gap-1 text-footnote text-fg-muted">
                  <Clock aria-hidden="true" className="size-3.5" />
                  {formatTime(order.updated_at)}
                </span>
              </div>
              <div className="flex items-baseline justify-between gap-3">
                <span className="min-w-0 truncate text-body text-fg-muted">{order.user?.name}</span>
                <span className="shrink-0 text-body font-bold text-fg">{formatMoney(toAmount(order.total_price), language)}</span>
              </div>
            </article>
          ))
        )}
      </div>
    </div>
  );
}
