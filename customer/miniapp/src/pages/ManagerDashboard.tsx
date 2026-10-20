import { useId, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
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
} from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred, selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { Button, CardSkeletons, EmptyState, InlineError, PageHeader, StatusBadge, cx, fieldClass } from '../components/ui';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import api from '../services/api';
import { getApiErrorMessage, getApiFieldError, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { getPaymentMethodLabel } from '../lib/paymentMethod';
import { cleanAddress } from '../lib/openMap';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, ORDERS_KEY, useOrderHistory, useStaffOrders } from '../hooks/useOrders';
import { StaffCancelDialog } from '../components/CancelDialogs';
import { LoadMoreOrders, TruncatedOrdersNotice } from '../components/OrderListParts';
import { getStaffCancelReasonLabel } from '../lib/cancelReasons';
import type { ApiResponse, AssignableStaff, CancelReason, Order, OrderStatus } from '../types';

const ACTIVE_STATUSES: readonly OrderStatus[] = ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivery'];
const WITH_COURIER_STATUSES: readonly OrderStatus[] = ['courier_assigned', 'on_the_way', 'delivery'];
// Statuses a manager can still cancel from the kitchen (a new order is rejected instead)
const CANCELABLE_STATUSES: readonly OrderStatus[] = ['accepted', 'preparing', 'ready'];

// The counters above the list are filters too: a tap shows only those orders, a second tap shows all again
type OrderFilter = 'new' | 'preparing' | 'ready' | 'courier';
const FILTERS: readonly OrderFilter[] = ['new', 'preparing', 'ready', 'courier'];
const FILTER_STATUSES: Record<OrderFilter, readonly OrderStatus[]> = {
  new: ['new'],
  preparing: ['accepted', 'preparing'],
  ready: ['ready'],
  courier: WITH_COURIER_STATUSES,
};
const FILTER_COUNT_TONE: Record<OrderFilter, string> = {
  new: 'text-warning-soft-fg',
  preparing: 'text-info-soft-fg',
  ready: 'text-success-soft-fg',
  courier: 'text-brand-soft-fg',
};

interface StatusChange {
  orderUuid: string;
  status: OrderStatus;
  courierId?: number;
  reason?: CancelReason; // required by the API for canceled and rejected
  note?: string;
}

interface CancelRequest {
  order: Order;
  mode: 'cancel' | 'reject';
}

const isCancellation = (status: OrderStatus) => status === 'canceled' || status === 'rejected';

// How long each stage of an order took, from its status history (null where a stage has no two timestamps)
function getStageDurations(order: Order) {
  const histories = order.status_histories;
  if (!histories || histories.length === 0) return null;

  const times: Partial<Record<OrderStatus, Date>> = {};
  histories.forEach((h) => {
    times[h.status] = new Date(h.created_at);
  });

  const formatDiff = (start?: Date, end?: Date) => {
    if (!start || !end) return null;
    const diffMs = end.getTime() - start.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffSecs = Math.floor((diffMs % 60000) / 1000);
    return diffMins > 0 ? `${diffMins}m ${diffSecs}s` : `${diffSecs}s`;
  };

  return {
    acceptTime: formatDiff(times['new'], times['accepted']),
    prepTime: formatDiff(times['accepted'], times['preparing']),
    readyTime: formatDiff(times['preparing'], times['ready']),
    transitTime: formatDiff(times['ready'] || times['preparing'], times['on_the_way'] || times['delivery']),
    deliveryTime: formatDiff((times['on_the_way'] || times['delivery']) || times['ready'], times['delivered']),
  };
}

type StageDurations = NonNullable<ReturnType<typeof getStageDurations>>;

// "Qabul 1m 3s · Tayyorlash 5m 0s ...": the stages that have a time
function StageTimes({ timings, uz }: { timings: StageDurations; uz: boolean }) {
  const stages: Array<[string, string | null]> = [
    [uz ? 'Qabul qilish' : 'Принятие', timings.acceptTime],
    [uz ? 'Tayyorlash' : 'Готовка', timings.prepTime],
    [uz ? "Tayyor bo'lishi" : 'Готовность', timings.readyTime],
    [uz ? 'Kuryerga topshirish' : 'Передача курьеру', timings.transitTime],
    [uz ? 'Yetkazish' : 'Доставка', timings.deliveryTime],
  ];
  const known = stages.filter((stage): stage is [string, string] => stage[1] !== null);
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
  uz: boolean;
  couriers: AssignableStaff[];
  courierId: number | undefined;
  onPickCourier: (courierId: number) => void;
  onChange: (change: StatusChange) => void;
  onCancel: (order: Order, mode: CancelRequest['mode']) => void;
  busy: boolean;
}

function ActiveOrderCard({ order, uz, couriers, courierId, onPickCourier, onChange, onCancel, busy }: ActiveOrderCardProps) {
  const { language, t } = useLanguageStore();
  const [open, setOpen] = useState(false);
  const itemsId = useId();
  const courierSelectId = useId();
  const timings = getStageDurations(order);
  const items = order.items ?? [];
  const address = cleanAddress(order.address, uz ? 'GPS lokatsiya' : 'GPS локация');
  const isDelivery = order.order_type === 'delivery';
  const TypeIcon = isDelivery ? Bike : ShoppingBag;
  const typeLabel = isDelivery ? (uz ? 'Yetkazish' : 'Доставка') : order.order_type === 'dine_in' ? (uz ? 'Zalda' : 'В зале') : uz ? 'Olib ketish' : 'Самовывоз';
  const preview = items.map((item) => `${item.product_name} x${item.quantity}`).join(', ');
  const code = formatOrderCode(order);
  const courierName = order.courier?.name || (uz ? 'Kuryer' : 'Курьер');

  const change = (status: OrderStatus, extra: Partial<StatusChange> = {}) => onChange({ orderUuid: order.uuid, status, ...extra });

  // Next to the next step, never alone on a row of its own unless there is no room
  const cancelButton = CANCELABLE_STATUSES.includes(order.status) && (
    <Button variant="danger" disabled={busy} onClick={() => onCancel(order, 'cancel')} className="grow whitespace-nowrap basis-28">
      {uz ? 'Bekor qilish' : 'Отменить заказ'}
    </Button>
  );

  return (
    <article aria-label={`${uz ? 'Buyurtma' : 'Заказ'} ${code}`} className="flex flex-col gap-2.5 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.created_at} className="ml-auto text-footnote text-fg-muted">
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
          {getPaymentMethodLabel(order.payment_method, t)}
          {order.payment_status === 'paid' && <span className="font-bold text-success-soft-fg">{uz ? " · To'langan" : ' · Оплачено'}</span>}
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
            <span className="shrink-0 font-bold text-fg">{uz ? 'Tarkibi' : 'Состав'}</span>
            {!open && <span className="min-w-0 flex-1 truncate">{preview}</span>}
            {open && <span className="flex-1" />}
            <ChevronDown aria-hidden="true" className={cx('size-5 shrink-0 transition-transform', open && 'rotate-180')} />
          </button>
          {open && (
            <div id={itemsId} className="flex flex-col gap-2 px-1 pb-1">
              <ul className="flex flex-col gap-1 text-body text-fg">
                {items.map((item, index) => (
                  <li key={index}>
                    <span>{item.product_name} x{item.quantity}</span>
                    {item.variant_name && <span className="ml-1.5 text-footnote text-fg-muted">{item.variant_name}</span>}
                  </li>
                ))}
              </ul>
              {timings && <StageTimes timings={timings} uz={uz} />}
            </div>
          )}
        </div>
      )}

      {/* Actions: the next step of the order, and the way out of it */}
      {order.status === 'new' && (
        <div className="flex flex-wrap gap-2">
          <Button icon={Check} disabled={busy} onClick={() => change('accepted')} className="grow whitespace-nowrap basis-36">
            {uz ? 'Qabul qilish' : 'Принять'}
          </Button>
          <Button variant="danger" disabled={busy} onClick={() => onCancel(order, 'reject')} className="grow whitespace-nowrap basis-28">
            {uz ? 'Rad etish' : 'Отклонить'}
          </Button>
        </div>
      )}

      {(order.status === 'accepted' || order.status === 'preparing') && (
        <div className="flex flex-wrap gap-2">
          {order.status === 'accepted' ? (
            <Button icon={ChefHat} disabled={busy} onClick={() => change('preparing')} className="grow whitespace-nowrap basis-40">
              {uz ? 'Tayyorlashni boshlash' : 'Начать готовку'}
            </Button>
          ) : (
            <Button variant="success" icon={PackageCheck} disabled={busy} onClick={() => change('ready')} className="grow whitespace-nowrap basis-40">
              {uz ? "Tayyor bo'ldi" : 'Готово'}
            </Button>
          )}
          {cancelButton}
        </div>
      )}

      {order.status === 'ready' && (
        <div className="flex flex-col gap-2">
          {isDelivery && (
            <>
              <label htmlFor={courierSelectId} className="flex items-center gap-1.5 text-footnote font-bold text-fg-muted">
                <Bike aria-hidden="true" className="size-3.5 text-brand" />
                {uz ? 'Kuryerni biriktirish' : 'Назначить курьера'}
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
                <option value="">{uz ? 'Kuryerni tanlang' : 'Выбрать курьера'}</option>
                {couriers.map((courier) => (
                  <option key={courier.id} value={courier.id}>
                    {courier.name} {courier.telegram_username ? `(@${courier.telegram_username})` : ''}
                  </option>
                ))}
              </select>
            </>
          )}
          <div className="flex flex-wrap gap-2">
            {isDelivery ? (
              <Button icon={Bike} disabled={!courierId || busy} onClick={() => change('courier_assigned', { courierId })} className="grow whitespace-nowrap basis-40">
                {uz ? 'Kuryerni tayinlash' : 'Назначить курьера'}
              </Button>
            ) : (
              <Button variant="success" icon={Check} disabled={busy} onClick={() => change('delivered')} className="grow whitespace-nowrap basis-40">
                {uz ? 'Mijozga topshirish' : 'Выдать заказ'}
              </Button>
            )}
            {cancelButton}
          </div>
        </div>
      )}

      {order.status === 'courier_assigned' && (
        <div className="flex flex-col gap-2">
          <p className="text-footnote text-fg-muted">
            {uz ? 'Biriktirilgan kuryer' : 'Назначенный курьер'}: <span className="font-bold text-fg">{courierName}</span>
            <br />
            {uz ? "Kuryer buyurtmani olib yo'lga chiqishini kutmoqda." : 'Ожидание, пока курьер заберёт заказ.'}
          </p>
          <Button icon={Bike} disabled={busy} onClick={() => change('on_the_way')} fullWidth>
            {uz ? "Yo'lga chiqdi" : 'В пути'}
          </Button>
        </div>
      )}

      {(order.status === 'on_the_way' || order.status === 'delivery') && (
        <div className="flex flex-col gap-2">
          <p className="text-footnote text-fg-muted">
            {uz ? "Mas'ul kuryer" : 'Курьер'}: <span className="font-bold text-fg">{courierName}</span>
          </p>
          <Button variant="success" icon={Check} disabled={busy} onClick={() => change('delivered')} fullWidth>
            {uz ? 'Yetkazilganini tasdiqlash' : 'Подтвердить доставку'}
          </Button>
        </div>
      )}

    </article>
  );
}

function CompletedOrderCard({ order, uz }: { order: Order; uz: boolean }) {
  const { language } = useLanguageStore();
  const timings = getStageDurations(order);
  return (
    <article aria-label={`${uz ? 'Buyurtma' : 'Заказ'} ${formatOrderCode(order)}`} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{formatOrderCode(order)}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.updated_at} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.updated_at)}
        </time>
      </div>
      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-body text-fg-muted">{order.user?.name}</span>
        <span className="shrink-0 text-body font-bold text-fg">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>
      {(order.status === 'canceled' || order.status === 'rejected') && (order.cancel_reason || order.cancel_note) && (
        <div className="flex flex-col gap-0.5 rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
          {order.cancel_reason && <span className="font-bold">{getStaffCancelReasonLabel(order.cancel_reason, language)}</span>}
          {order.cancel_note && <span>"{order.cancel_note}"</span>}
        </div>
      )}
      {timings && <StageTimes timings={timings} uz={uz} />}
    </article>
  );
}

export default function ManagerDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');
  const [filter, setFilter] = useState<OrderFilter | null>(null);
  const [selectedCourierId, setSelectedCourierId] = useState<Record<string, number>>({});
  const [cancelRequest, setCancelRequest] = useState<CancelRequest | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);

  // 1. Fetch the open store orders: polled while the screen is visible, pushed over the socket when it is up
  const { data: activeData, isLoading, error, refetch: refetchActive, isRefetching: isRefetchingActive } = useStaffOrders('manager', POLL_INTERVAL_MS.managerOrders);
  const orders = activeData?.orders ?? NO_ORDERS;

  // The finished orders (the archive tab) come a page at a time, once the tab is opened
  const history = useOrderHistory('manager', { enabled: activeTab === 'completed' });
  const completedOrders = history.orders;

  // 2. Fetch active couriers
  const { data: couriers = [] } = useQuery({
    queryKey: ['storeCouriers'],
    queryFn: async () => {
      const response = await api.get<ApiResponse<AssignableStaff[]>>('/couriers');
      return response.data?.data || [];
    }
  });

  // 3. Update order status mutation
  const updateStatusMutation = useMutation({
    mutationFn: async ({ orderUuid, status, courierId, reason, note }: StatusChange) => {
      hapticFeedback('medium');
      if (status === 'courier_assigned') {
        const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/assign-courier`, {
          courier_id: courierId
        });
        return response.data;
      } else {
        const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/status`, {
          status,
          ...(isCancellation(status) ? { reason, ...(note ? { note } : {}) } : {})
        });
        return response.data;
      }
    },
    onSuccess: (_data, variables) => {
      // The order moves between the open list and the archive
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
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
        setCancelError(message ?? (uz ? 'Bekor qilishda xatolik yuz berdi!' : 'Ошибка при отмене заказа!'));
        return;
      }
      void showAlert(message || (uz ? 'Holatni yangilashda xatolik yuz berdi!' : 'Ошибка при обновлении статуса!'));
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
        <PageHeader title={user?.name} subtitle={uz ? 'Menejer boshqaruvi' : 'Панель менеджера'} />
        <InlineError onRetry={() => void refetchActive()} retrying={isRefetchingActive} />
      </div>
    );
  }

  const activeOrders = orders.filter((order) => ACTIVE_STATUSES.includes(order.status));
  const countOf = (key: OrderFilter) => orders.filter((order) => FILTER_STATUSES[key].includes(order.status)).length;
  const shownOrders = filter ? activeOrders.filter((order) => FILTER_STATUSES[filter].includes(order.status)) : activeOrders;
  const busyUuid = updateStatusMutation.isPending ? updateStatusMutation.variables?.orderUuid : undefined;

  const filterLabel: Record<OrderFilter, string> = {
    new: uz ? 'Yangi' : 'Новые',
    preparing: uz ? 'Tayyorlash' : 'Готовка',
    ready: uz ? 'Tayyor' : 'Готовы',
    courier: uz ? 'Kuryer' : 'Курьер',
  };

  const tabs = [
    { value: 'active' as const, label: uz ? `Faol (${activeOrders.length})` : `Активные (${activeOrders.length})` },
    {
      value: 'completed' as const,
      label: `${uz ? 'Yakunlanganlar' : 'Завершенные'}${completedOrders.length > 0 ? ` (${completedOrders.length}${history.hasNextPage ? '+' : ''})` : ''}`,
    },
  ];

  const loadingList = activeTab === 'active' ? isLoading && orders.length === 0 : history.isLoading;

  return (
    <div className="flex flex-col gap-3 px-4 pt-2">
      <PageHeader title={user?.name} subtitle={uz ? 'Menejer boshqaruvi' : 'Панель менеджера'} />

      <SegmentedControl segments={tabs} value={activeTab} onChange={setActiveTab} label={uz ? "Buyurtmalar bo'limi" : 'Раздел заказов'} />

      {activeTab === 'active' && (
        <div role="group" aria-label={uz ? 'Holat bo\'yicha saralash' : 'Фильтр по статусу'} className="grid grid-cols-4 gap-2">
          {FILTERS.map((key) => {
            const selected = filter === key;
            return (
              <button
                key={key}
                type="button"
                aria-pressed={selected}
                aria-label={`${filterLabel[key]}: ${countOf(key)}`}
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
                  {filterLabel[key]}
                </span>
              </button>
            );
          })}
        </div>
      )}

      {activeData?.truncated && <TruncatedOrdersNotice />}

      <div className="flex flex-col gap-3">
        {loadingList ? (
          <CardSkeletons count={2} lines={3} label={uz ? 'Yuklanmoqda' : 'Загрузка'} />
        ) : activeTab === 'active' ? (
          activeOrders.length === 0 ? (
            <EmptyState icon={ClipboardList} title={uz ? "Hozircha faol buyurtmalar yo'q" : 'Активных заказов пока нет'} text={uz ? "Yangi buyurtma kelganda shu yerda paydo bo'ladi." : 'Новые заказы появятся здесь.'} />
          ) : shownOrders.length === 0 ? (
            <EmptyState
              icon={ClipboardList}
              title={uz ? "Bu holatda buyurtma yo'q" : 'В этом статусе заказов нет'}
              action={
                <Button variant="secondary" onClick={() => setFilter(null)}>
                  {uz ? "Hammasini ko'rsatish" : 'Показать все'}
                </Button>
              }
            />
          ) : (
            shownOrders.map((order) => (
              <ActiveOrderCard
                key={order.id}
                order={order}
                uz={uz}
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
          <EmptyState icon={Flag} title={uz ? "Hozircha yakunlangan buyurtmalar yo'q" : 'Завершенных заказов пока нет'} />
        ) : (
          completedOrders.map((order) => <CompletedOrderCard key={order.id} order={order} uz={uz} />)
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
              orderUuid: cancelRequest.order.uuid,
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
