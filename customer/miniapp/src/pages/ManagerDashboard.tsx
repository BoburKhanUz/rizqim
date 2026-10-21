import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Bell, BellOff, ClipboardList, Flag } from 'lucide-react';
import {
  boardKeys,
  GROUP_STATUSES,
  isCancellation,
  OPEN_STATUSES,
  someOrderLeft,
  staffText,
  useBoardAction,
  useBranchList,
  useCouriers,
  useStaffOrderPages,
  useStaffOrders,
  type OrderAction,
  type StaffOrder,
  type Localized,
  type StaffOrdersQuery,
} from '@rizqim/staff';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { useRealtimeStore } from '../store/realtimeStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { Button, CardSkeletons, EmptyState, IconButton, InlineError, PageHeader, StatusBadge, cx, fieldClass } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { StaffOrderCard } from '../components/staff/StaffOrderCard';
import { OrderDetailsLink } from '../components/staff/OrderDetailsLink';
import { ORDER_TEXT } from '../components/staff/orderText';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { getPollInterval, POLL_INTERVAL_MS, pollingQueryOptions } from '../lib/polling';
import { toOrderRef } from '../lib/staffOrders';
import { useNow } from '../hooks/useNow';
import { useStaffText } from '../hooks/useStaffText';
import { useNewOrderChime } from '../hooks/useNewOrderChime';
import { StaffCancelDialog } from '../components/CancelDialogs';
import { LoadMoreOrders, TruncatedOrdersNotice } from '../components/OrderListParts';
import { getStaffCancelReasonLabel } from '../lib/cancelReasons';

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
  order: StaffOrder;
  mode: 'cancel' | 'reject';
}

const NO_ORDERS: StaffOrder[] = [];

const SOUND_TEXT = {
  soundOn: { uz: "Yangi buyurtma ovozi yoqilgan. O'chirish", ru: 'Звук новых заказов включён. Выключить' },
  soundOff: { uz: "Yangi buyurtma ovozi o'chirilgan. Yoqish", ru: 'Звук новых заказов выключен. Включить' },
  soundHint: { uz: 'Yangi buyurtma ovozi uchun ekranga bir marta teging.', ru: 'Чтобы слышать новые заказы, коснитесь экрана один раз.' },
} satisfies Record<string, Localized>;
const NO_COURIER_CHOICES: Record<string, number> = {};

function CompletedOrderCard({ order }: { order: StaffOrder }) {
  const { language } = useLanguageStore();
  const code = formatOrderCode(order);
  return (
    <article aria-label={`${staffText('orderWord', language)} ${code}`} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.closed_at ?? order.updated_at ?? undefined} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.closed_at ?? order.updated_at)}
        </time>
      </div>
      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-body text-fg-muted">{order.customer?.name}</span>
        <span className="shrink-0 text-body font-bold text-fg">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>
      {isCancellation(order.status as never) && (order.cancel_reason || order.cancel_note) && (
        <div className="flex flex-col gap-0.5 rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
          {order.cancel_reason && <span className="font-bold">{getStaffCancelReasonLabel(order.cancel_reason, language)}</span>}
          {order.cancel_note && <span>"{order.cancel_note}"</span>}
        </div>
      )}
      <OrderDetailsLink uuid={order.uuid} code={code} />
    </article>
  );
}

export default function ManagerDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const tx = useStaffText(ORDER_TEXT);
  const soundText = useStaffText(SOUND_TEXT);
  const queryClient = useQueryClient();
  const realtimeConnected = useRealtimeStore((state) => state.connected);
  const now = useNow();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');
  const [filter, setFilter] = useState<OrderFilter | null>(null);
  const [selectedCourierId, setSelectedCourierId] = useState<Record<string, number>>(NO_COURIER_CHOICES);
  const [cancelRequest, setCancelRequest] = useState<CancelRequest | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  // The owner can work with several branches: a picker narrows the board to one of them. A manager is bound to a branch
  // by the backend, which answers with that branch's orders whatever is asked.
  const isOwner = user?.role === 'owner';
  const { data: branches = [] } = useBranchList({ enabled: isOwner });
  const showBranches = isOwner && branches.length > 1;
  const [chosenBranch, setChosenBranch] = useState<number | null>(null);
  const branchId = showBranches && chosenBranch !== null && branches.some((branch) => branch.id === chosenBranch) ? chosenBranch : null;
  const branchQuery = branchId === null ? {} : { branch_id: branchId };

  // 1. The open orders (GET /staff/orders): polled while the screen is visible, slowly while the socket is up (it pushes
  // the changes, lib/realtime.ts)
  const activeQuery: StaffOrdersQuery = { scope: 'active', ...branchQuery };
  const pollMs = POLL_INTERVAL_MS.managerOrders;
  const {
    data: activeData,
    isLoading,
    error,
    refetch: refetchActive,
    isRefetching: isRefetchingActive,
  } = useStaffOrders(activeQuery, { ...pollingQueryOptions(getPollInterval(pollMs, realtimeConnected)), staleTime: pollMs });
  const orders = activeData?.orders ?? NO_ORDERS;

  // An order that leaves the open list has finished: the archive is refetched so it shows up there
  const seen = useRef<StaffOrder[] | null>(null);
  useEffect(() => {
    if (!activeData) return;
    if (seen.current && someOrderLeft(seen.current, activeData.orders)) void queryClient.invalidateQueries({ queryKey: boardKeys.all });
    seen.current = activeData.orders;
  }, [activeData, queryClient]);
  // ... and a list of another branch is a new list
  useEffect(() => {
    seen.current = null;
  }, [branchId]);

  // A chime for each new order (P2-33: as on the partner web board); switching the branch announces nothing
  const sound = useNewOrderChime(activeData?.orders, String(branchId));

  // The finished orders (the archive tab) come a page at a time, once the tab is opened
  const history = useStaffOrderPages({ scope: 'history', per_page: 20, ...branchQuery }, { enabled: activeTab === 'completed' });
  const historyPages = history.data?.pages;
  const completedOrders = useMemo(() => historyPages?.flatMap((page) => page.orders) ?? NO_ORDERS, [historyPages]);

  // 2. Active couriers
  const { data: couriers = [] } = useCouriers();

  // 3. A button of an order (the package says which endpoint carries it; the backend says whether it may)
  const board = useBoardAction({
    onMutate: () => hapticFeedback('medium'),
    onSuccess: (_data, variables) => {
      notificationOccurred('success');
      if (variables.action.to === 'canceled' || variables.action.to === 'rejected') {
        setCancelRequest(null);
        setCancelError(null);
      }
    },
    onError: (err, variables) => {
      logApiError('Failed to update status:', err);
      notificationOccurred('error');
      const message = getApiFieldError(err, 'reason') ?? getApiErrorMessage(err);
      if (variables.action.to === 'canceled' || variables.action.to === 'rejected') {
        // Shown in the reason picker, which stays open
        setCancelError(message ?? staffText('cancelFailed', language));
        return;
      }
      void showAlert(message || staffText('statusUpdateFailed', language));
    },
  });

  const openCancelDialog = (order: StaffOrder, mode: CancelRequest['mode']) => {
    hapticFeedback('light');
    board.reset();
    setCancelError(null);
    setCancelRequest({ order, mode });
  };

  const closeCancelDialog = () => {
    if (board.isPending) return;
    setCancelRequest(null);
    setCancelError(null);
  };

  const onAction = (order: StaffOrder, action: OrderAction) =>
    board.mutate({ uuid: order.uuid, action, ...(action.needsCourier ? { courierId: selectedCourierId[order.uuid] } : {}) });

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
  const countOf = (key: OrderFilter) => activeOrders.filter((order) => FILTER_STATUSES[key].includes(order.status)).length;
  const shownOrders = filter ? activeOrders.filter((order) => FILTER_STATUSES[filter].includes(order.status)) : activeOrders;
  const busyUuid = board.isPending ? board.variables?.uuid : undefined;

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
      <PageHeader
        title={user?.name}
        subtitle={staffText('managerSubtitle', language)}
        action={
          <IconButton
            icon={sound.muted ? BellOff : Bell}
            label={soundText(sound.muted ? 'soundOff' : 'soundOn')}
            aria-pressed={!sound.muted}
            onClick={() => {
              selectionChanged();
              sound.toggleMuted();
            }}
          />
        }
      />
      {sound.needsGesture && <p className="text-footnote text-fg-muted">{soundText('soundHint')}</p>}

      {showBranches && (
        <select
          aria-label={tx('branchFilter')}
          value={branchId ?? ''}
          onChange={(e) => {
            selectionChanged();
            setChosenBranch(e.target.value === '' ? null : Number(e.target.value));
          }}
          className={cx(fieldClass, 'cursor-pointer bg-surface')}
        >
          <option value="">{tx('allBranches')}</option>
          {branches.map((branch) => (
            <option key={branch.id} value={branch.id}>
              {branch.name}
            </option>
          ))}
        </select>
      )}

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

      {activeTab === 'active' && activeData?.truncated && <TruncatedOrdersNotice />}

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
              <StaffOrderCard
                key={order.uuid}
                order={order}
                now={now}
                showBranch={showBranches}
                couriers={couriers}
                courierId={selectedCourierId[order.uuid] || undefined}
                onPickCourier={(courierId) => setSelectedCourierId((prev) => ({ ...prev, [order.uuid]: courierId }))}
                onAction={onAction}
                onCancel={openCancelDialog}
                busy={busyUuid === order.uuid}
              />
            ))
          )
        ) : completedOrders.length === 0 ? (
          <EmptyState icon={Flag} title={staffText('noCompletedOrders', language)} />
        ) : (
          completedOrders.map((order) => <CompletedOrderCard key={order.uuid} order={order} />)
        )}
        {activeTab === 'completed' && (
          <LoadMoreOrders
            hasNextPage={history.hasNextPage}
            isFetchingNextPage={history.isFetchingNextPage}
            isFetching={history.isFetching}
            isError={history.isFetchNextPageError}
            loadedCount={completedOrders.length}
            onLoadMore={() => void history.fetchNextPage({ cancelRefetch: false })}
          />
        )}
      </div>

      {cancelRequest && (
        <StaffCancelDialog
          order={toOrderRef(cancelRequest.order)}
          mode={cancelRequest.mode}
          isPending={board.isPending}
          error={cancelError}
          onClose={closeCancelDialog}
          onConfirm={({ reason, note }) =>
            board.mutate({
              uuid: cancelRequest.order.uuid,
              action: { to: cancelRequest.mode === 'reject' ? 'rejected' : 'canceled', via: 'status' },
              reason,
              note,
            })
          }
        />
      )}
    </div>
  );
}
