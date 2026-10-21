import { useId, useState } from 'react';
import {
  Bike,
  Check,
  ChefHat,
  ChevronDown,
  ClipboardList,
  Flag,
  MapPin,
  PackageCheck,
  ShoppingBag,
  StickyNote,
  Wallet,
  type LucideIcon,
} from 'lucide-react';
import {
  actionLabel,
  formatLineQuantity,
  formatModifiers,
  getItemModifiers,
  getOrderActions,
  getStageDurations,
  GROUP_STATUSES,
  isCancellation,
  OPEN_STATUSES,
  orderTypeLabel,
  paymentMethodLabel,
  stageRows,
  staffText,
  useCouriers,
  useUpdateOrderStatus,
  type AssignableCourier,
  type Order,
  type OrderAction,
  type OrderChange,
  type StageDurations,
} from '@rizqim/staff';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { Button, CardSkeletons, EmptyState, InlineError, PageHeader, StatusBadge, cx, fieldClass } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { cleanAddress } from '../lib/openMap';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, useStaffOrderHistory, useStaffOrders } from '../hooks/useStaffOrders';
import { StaffCancelDialog } from '../components/CancelDialogs';
import { LoadMoreOrders, TruncatedOrdersNotice } from '../components/OrderListParts';
import { getStaffCancelReasonLabel } from '../lib/cancelReasons';
import type { Language } from '../store/languageStore';

// The counters above the list are filters too: a tap shows only those orders, a second tap shows all again. The groups
// of statuses are @rizqim/staff's (the web board's columns are the same groups).
type OrderFilter = 'new' | 'preparing' | 'ready' | 'courier';
const FILTERS: readonly OrderFilter[] = ['new', 'preparing', 'ready', 'courier'];
const FILTER_STATUSES: Record<OrderFilter, readonly string[]> = {
  new: GROUP_STATUSES.new,
  preparing: GROUP_STATUSES.preparing,
  ready: GROUP_STATUSES.ready,
  courier: GROUP_STATUSES.courier,
};
const FILTER_COUNT_TONE: Record<OrderFilter, string> = {
  new: 'text-warning-soft-fg',
  preparing: 'text-info-soft-fg',
  ready: 'text-success-soft-fg',
  courier: 'text-brand-soft-fg',
};
const FILTER_LABEL_KEY = { new: 'filterNew', preparing: 'filterPreparing', ready: 'filterReady', courier: 'filterCourier' } as const;

interface CancelRequest {
  order: Order;
  mode: 'cancel' | 'reject';
}

// How each action of @rizqim/staff's getOrderActions() is drawn on this screen. Which actions an order has, and their
// words, are the package's; the icons, colours and sizes are this screen's.
const ACTION_LOOK: Record<OrderAction['id'], { icon?: LucideIcon; variant?: 'danger' | 'success'; basis: string }> = {
  accept: { icon: Check, basis: 'basis-36' },
  reject: { variant: 'danger', basis: 'basis-28' },
  start_preparing: { icon: ChefHat, basis: 'basis-40' },
  mark_ready: { icon: PackageCheck, variant: 'success', basis: 'basis-40' },
  assign_courier: { icon: Bike, basis: 'basis-40' },
  hand_over: { icon: Check, variant: 'success', basis: 'basis-40' },
  pick_up: { icon: Bike, basis: 'basis-40' },
  confirm_delivery: { icon: Check, variant: 'success', basis: 'basis-40' },
  cancel: { variant: 'danger', basis: 'basis-28' },
};

// "Qabul 1m 3s · Tayyorlash 5m 0s ...": the stages that have a time
function StageTimes({ timings, language }: { timings: StageDurations; language: Language }) {
  const known = stageRows(timings, language);
  if (known.length === 0) return null;
  return (
    <dl className="flex flex-wrap gap-x-4 gap-y-1 text-footnote">
      {known.map(([label, value]) => (
        <div key={label} className="flex gap-1.5">
          <dt className="text-fg-muted">{label}:</dt>
          <dd className="font-bold text-fg">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

interface ActiveOrderCardProps {
  order: Order;
  couriers: AssignableCourier[];
  courierId: number | undefined;
  onPickCourier: (courierId: number) => void;
  onChange: (change: OrderChange) => void;
  onCancel: (order: Order, mode: CancelRequest['mode']) => void;
  busy: boolean;
}

function ActiveOrderCard({ order, couriers, courierId, onPickCourier, onChange, onCancel, busy }: ActiveOrderCardProps) {
  const { language } = useLanguageStore();
  const [open, setOpen] = useState(false);
  const itemsId = useId();
  const courierSelectId = useId();
  const timings = getStageDurations(order);
  const items = order.items ?? [];
  const address = cleanAddress(order.address, staffText('gpsLocation', language));
  const isDelivery = order.order_type === 'delivery';
  const TypeIcon = isDelivery ? Bike : ShoppingBag;
  const typeLabel = orderTypeLabel(order.order_type, language);
  const preview = items.map((item) => `${item.product_name} ${formatLineQuantity(item.quantity, item.unit, language)}`).join(', ');
  const code = formatOrderCode(order);
  const courierName = order.courier?.name || staffText('courierFallback', language);
  const orderWord = staffText('orderWord', language);

  // What the order can do next (@rizqim/staff): the next step first, the way out last (cancel sits next to it, and only from
  // the statuses the kitchen can still cancel from)
  const actions = getOrderActions(order, 'manager');
  const stepOn = (action: OrderAction) => onChange({ uuid: order.uuid, status: action.to, ...(action.needsCourier ? { courierId } : {}) });
  const actionButton = (action: OrderAction, extra: { fullWidth?: boolean } = {}) => {
    const look = ACTION_LOOK[action.id];
    const isCancel = action.id === 'cancel' || action.id === 'reject';
    return (
      <Button
        key={action.id}
        variant={look.variant}
        icon={look.icon}
        disabled={busy || (action.needsCourier && !courierId)}
        onClick={() => (isCancel ? onCancel(order, action.id === 'reject' ? 'reject' : 'cancel') : stepOn(action))}
        fullWidth={extra.fullWidth}
        className={extra.fullWidth ? undefined : cx('grow whitespace-nowrap', look.basis)}
      >
        {actionLabel(action.id, language)}
      </Button>
    );
  };

  return (
    <article aria-label={`${orderWord} ${code}`} className="flex flex-col gap-2.5 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.created_at ?? undefined} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.created_at)}
        </time>
      </div>

      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-body font-medium text-fg">{order.user?.name}</span>
        <span className="shrink-0 text-headline font-bold text-brand">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-footnote text-fg-muted">
        <span className="inline-flex items-center gap-1">
          <TypeIcon aria-hidden="true" className="size-3.5" />
          {typeLabel}
        </span>
        <span className="inline-flex items-center gap-1">
          <Wallet aria-hidden="true" className="size-3.5" />
          {paymentMethodLabel(order.payment_method, language)}
          {order.payment_status === 'paid' && <span className="font-bold text-success-soft-fg">{staffText('paidSuffix', language)}</span>}
        </span>
      </div>

      {address && (
        <p className="flex items-start gap-1.5 text-footnote text-fg">
          <MapPin aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-brand" />
          <span className="line-clamp-2">{address}</span>
        </p>
      )}

      {order.notes && (
        <p className="flex items-start gap-1.5 text-footnote text-fg-muted">
          <StickyNote aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
          <span className="line-clamp-2">{order.notes}</span>
        </p>
      )}

      {(items.length > 0 || timings) && (
        <div className="-mx-1 border-t border-line pt-1">
          <button
            type="button"
            aria-expanded={open}
            aria-controls={itemsId}
            onClick={() => {
              selectionChanged();
              setOpen((value) => !value);
            }}
            className="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-control px-1 text-left text-footnote text-fg-muted focus-visible:outline-2 focus-visible:outline-brand"
          >
            <span className="shrink-0 font-bold text-fg">{staffText('itemsLabel', language)}</span>
            {!open && <span className="min-w-0 flex-1 truncate">{preview}</span>}
            {open && <span className="flex-1" />}
            <ChevronDown aria-hidden="true" className={cx('size-5 shrink-0 transition-transform', open && 'rotate-180')} />
          </button>
          {open && (
            <div id={itemsId} className="flex flex-col gap-2 px-1 pb-1">
              <ul className="flex flex-col gap-1 text-body text-fg">
                {items.map((item, index) => {
                  const modifiers = getItemModifiers(item);
                  return (
                    <li key={index}>
                      <span>{item.product_name} {formatLineQuantity(item.quantity, item.unit, language)}</span>
                      {item.variant_name && <span className="ml-1.5 text-footnote text-fg-muted">{item.variant_name}</span>}
                      {modifiers.length > 0 && <span className="block text-footnote text-fg-muted">{formatModifiers(modifiers)}</span>}
                    </li>
                  );
                })}
              </ul>
              {timings && <StageTimes timings={timings} language={language} />}
            </div>
          )}
        </div>
      )}

      {/* Actions: the next step of the order, and the way out of it */}
      {(order.status === 'new' || order.status === 'accepted' || order.status === 'preparing') && (
        <div className="flex flex-wrap gap-2">{actions.map((action) => actionButton(action))}</div>
      )}

      {order.status === 'ready' && (
        <div className="flex flex-col gap-2">
          {isDelivery && (
            <>
              <label htmlFor={courierSelectId} className="flex items-center gap-1.5 text-footnote font-bold text-fg-muted">
                <Bike aria-hidden="true" className="size-3.5 text-brand" />
                {staffText('assignCourierLabel', language)}
              </label>
              <select
                id={courierSelectId}
                value={courierId ?? ''}
                onChange={(e) => {
                  hapticFeedback('light');
                  onPickCourier(parseInt(e.target.value));
                }}
                className={fieldClass}
              >
                <option value="">{staffText('chooseCourier', language)}</option>
                {couriers.map((courier) => (
                  <option key={courier.id} value={courier.id}>
                    {courier.name} {courier.telegram_username ? `(@${courier.telegram_username})` : ''}
                  </option>
                ))}
              </select>
            </>
          )}
          <div className="flex flex-wrap gap-2">{actions.map((action) => actionButton(action))}</div>
        </div>
      )}

      {order.status === 'courier_assigned' && (
        <div className="flex flex-col gap-2">
          <p className="text-footnote text-fg-muted">
            {staffText('assignedCourier', language)}: <span className="font-bold text-fg">{courierName}</span>
            <br />
            {staffText('waitingForPickup', language)}
          </p>
          {actions.map((action) => actionButton(action, { fullWidth: true }))}
        </div>
      )}

      {(order.status === 'on_the_way' || order.status === 'delivery') && (
        <div className="flex flex-col gap-2">
          <p className="text-footnote text-fg-muted">
            {staffText('responsibleCourier', language)}: <span className="font-bold text-fg">{courierName}</span>
          </p>
          {actions.map((action) => actionButton(action, { fullWidth: true }))}
        </div>
      )}

    </article>
  );
}

function CompletedOrderCard({ order }: { order: Order }) {
  const { language } = useLanguageStore();
  const timings = getStageDurations(order);
  return (
    <article aria-label={`${staffText('orderWord', language)} ${formatOrderCode(order)}`} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{formatOrderCode(order)}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.updated_at ?? undefined} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.updated_at)}
        </time>
      </div>
      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-body text-fg-muted">{order.user?.name}</span>
        <span className="shrink-0 text-body font-bold text-fg">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>
      {isCancellation(order.status) && (order.cancel_reason || order.cancel_note) && (
        <div className="flex flex-col gap-0.5 rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
          {order.cancel_reason && <span className="font-bold">{getStaffCancelReasonLabel(order.cancel_reason, language)}</span>}
          {order.cancel_note && <span>"{order.cancel_note}"</span>}
        </div>
      )}
      {timings && <StageTimes timings={timings} language={language} />}
    </article>
  );
}

export default function ManagerDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');
  const [filter, setFilter] = useState<OrderFilter | null>(null);
  const [selectedCourierId, setSelectedCourierId] = useState<Record<string, number>>({});
  const [cancelRequest, setCancelRequest] = useState<CancelRequest | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  // 1. Fetch the open store orders: polled while the screen is visible, pushed over the socket when it is up
  const { data: activeData, isLoading, error, refetch: refetchActive, isRefetching: isRefetchingActive } = useStaffOrders('manager', POLL_INTERVAL_MS.managerOrders);
  const orders = activeData?.orders ?? NO_ORDERS;

  // The finished orders (the archive tab) come a page at a time, once the tab is opened
  const history = useStaffOrderHistory('manager', { enabled: activeTab === 'completed' });
  const completedOrders = history.orders;

  // 2. Fetch active couriers
  const { data: couriers = [] } = useCouriers();

  // 3. Update order status mutation (an order moves between the open list and the archive: the package refetches them)
  const updateStatusMutation = useUpdateOrderStatus({
    onMutate: () => hapticFeedback('medium'),
    onSuccess: (_data, variables) => {
      notificationOccurred('success');
      if (isCancellation(variables.status)) {
        setCancelRequest(null);
        setCancelError(null);
      }
    },
    onError: (err, variables) => {
      logApiError('Failed to update status:', err);
      notificationOccurred('error');
      const message = getApiFieldError(err, 'reason') ?? getApiErrorMessage(err);
      if (isCancellation(variables.status)) {
        // Shown in the reason picker, which stays open
        setCancelError(message ?? staffText('cancelFailed', language));
        return;
      }
      void showAlert(message || staffText('statusUpdateFailed', language));
    }
  });

  const openCancelDialog = (order: Order, mode: CancelRequest['mode']) => {
    hapticFeedback('light');
    updateStatusMutation.reset();
    setCancelError(null);
    setCancelRequest({ order, mode });
  };

  const closeCancelDialog = () => {
    if (updateStatusMutation.isPending) return;
    setCancelRequest(null);
    setCancelError(null);
  };

  // A failed background refresh keeps showing the last orders; the error screen is only for "nothing to show"
  if (error && orders.length === 0) {
    return (
      <div className="flex flex-col gap-3 px-4 pt-2">
        <PageHeader title={user?.name} subtitle={staffText('managerSubtitle', language)} />
        <InlineError onRetry={() => void refetchActive()} retrying={isRefetchingActive} />
      </div>
    );
  }

  const activeOrders = orders.filter((order) => (OPEN_STATUSES as readonly string[]).includes(order.status));
  const countOf = (key: OrderFilter) => orders.filter((order) => FILTER_STATUSES[key].includes(order.status)).length;
  const shownOrders = filter ? activeOrders.filter((order) => FILTER_STATUSES[filter].includes(order.status)) : activeOrders;
  const busyUuid = updateStatusMutation.isPending ? updateStatusMutation.variables?.uuid : undefined;

  const filterLabel = (key: OrderFilter) => staffText(FILTER_LABEL_KEY[key], language);

  const tabs = [
    { value: 'active' as const, label: staffText('tabActive', language, { n: activeOrders.length }) },
    {
      value: 'completed' as const,
      label: `${staffText('tabCompleted', language)}${completedOrders.length > 0 ? ` (${completedOrders.length}${history.hasNextPage ? '+' : ''})` : ''}`,
    },
  ];

  const loadingList = activeTab === 'active' ? isLoading && orders.length === 0 : history.isLoading;

  return (
    <div className="flex flex-col gap-3 px-4 pt-2">
      <PageHeader title={user?.name} subtitle={staffText('managerSubtitle', language)} />

      <SegmentedControl segments={tabs} value={activeTab} onChange={setActiveTab} label={staffText('ordersSection', language)} />

      {activeTab === 'active' && (
        <div role="group" aria-label={staffText('filterAria', language)} className="grid grid-cols-4 gap-2">
          {FILTERS.map((key) => {
            const selected = filter === key;
            return (
              <button
                key={key}
                type="button"
                aria-pressed={selected}
                aria-label={`${filterLabel(key)}: ${countOf(key)}`}
                onClick={() => {
                  selectionChanged();
                  setFilter(selected ? null : key);
                }}
                className={cx(
                  'flex min-h-14 cursor-pointer flex-col items-center justify-center rounded-card border px-1 py-1.5 transition-colors',
                  'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                  selected ? 'border-brand bg-brand-soft' : 'border-line bg-surface',
                )}
              >
                <span aria-hidden="true" className={cx('text-headline font-bold', FILTER_COUNT_TONE[key])}>
                  {countOf(key)}
                </span>
                <span aria-hidden="true" className="max-w-full truncate text-caption font-medium text-fg-muted">
                  {filterLabel(key)}
                </span>
              </button>
            );
          })}
        </div>
      )}

      {activeData?.truncated && <TruncatedOrdersNotice />}

      <div className="flex flex-col gap-3">
        {loadingList ? (
          <CardSkeletons count={2} lines={3} label={staffText('loading', language)} />
        ) : activeTab === 'active' ? (
          activeOrders.length === 0 ? (
            <EmptyState icon={ClipboardList} title={staffText('noActiveOrdersTitle', language)} text={staffText('noActiveOrdersText', language)} />
          ) : shownOrders.length === 0 ? (
            <EmptyState
              icon={ClipboardList}
              title={staffText('noOrdersInStatus', language)}
              action={
                <Button variant="secondary" onClick={() => setFilter(null)}>
                  {staffText('showAll', language)}
                </Button>
              }
            />
          ) : (
            shownOrders.map((order) => (
              <ActiveOrderCard
                key={order.id}
                order={order}
                couriers={couriers}
                courierId={selectedCourierId[order.uuid] || undefined}
                onPickCourier={(courierId) => setSelectedCourierId((prev) => ({ ...prev, [order.uuid]: courierId }))}
                onChange={(change) => updateStatusMutation.mutate(change)}
                onCancel={openCancelDialog}
                busy={busyUuid === order.uuid}
              />
            ))
          )
        ) : completedOrders.length === 0 ? (
          <EmptyState icon={Flag} title={staffText('noCompletedOrders', language)} />
        ) : (
          completedOrders.map((order) => <CompletedOrderCard key={order.id} order={order} />)
        )}
        {activeTab === 'completed' && (
          <LoadMoreOrders
            hasNextPage={history.hasNextPage}
            isFetchingNextPage={history.isFetchingNextPage}
            isFetching={history.isFetching}
            isError={history.isFetchNextPageError}
            loadedCount={completedOrders.length}
            onLoadMore={() => void history.fetchNextPage()}
          />
        )}
      </div>

      {cancelRequest && (
        <StaffCancelDialog
          order={cancelRequest.order}
          mode={cancelRequest.mode}
          isPending={updateStatusMutation.isPending}
          error={cancelError}
          onClose={closeCancelDialog}
          onConfirm={({ reason, note }) =>
            updateStatusMutation.mutate({
              uuid: cancelRequest.order.uuid,
              status: cancelRequest.mode === 'reject' ? 'rejected' : 'canceled',
              reason,
              note,
            })
          }
        />
      )}
    </div>
  );
}
