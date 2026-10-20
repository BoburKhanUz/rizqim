import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Bike, CheckCircle2, Clock, MapPin, Navigation, PackageCheck, Phone, StickyNote, Wallet } from 'lucide-react';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { hapticFeedback } from '../lib/telegram';
import { notificationOccurred } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import { Button, EmptyState, InlineError, PageHeader, StatusBadge, cx } from '../components/ui';
import { CardSkeletons } from '../components/staff/Skeleton';
import { SegmentedControl } from '../components/staff/SegmentedControl';
import { Pill } from '../components/staff/Pill';
import { useMainButton } from '../hooks/useMainButton';
import api from '../services/api';
import { getApiErrorMessage, logApiError } from '../lib/apiError';
import { sumAmounts, toAmount } from '../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../lib/format';
import { getPaymentMethodLabel } from '../lib/paymentMethod';
import { cleanAddress, linkInAddress, mapRouteUrl, openExternalLink } from '../lib/openMap';
import { POLL_INTERVAL_MS } from '../lib/polling';
import { NO_ORDERS, ORDERS_KEY, useOrderHistory, useStaffOrders } from '../hooks/useOrders';
import { TruncatedOrdersNotice } from '../components/OrderListParts';
import type { ApiResponse, Order, OrderStatus } from '../types';

const ACTIVE_STATUSES: readonly OrderStatus[] = ['courier_assigned', 'on_the_way', 'delivery'];

// A delivery already on its way comes first (the courier is out with it), then the oldest waiting one
const activeRank = (order: Order) => (order.status === 'courier_assigned' ? 1 : 0);
const byDeliveryPriority = (a: Order, b: Order) => activeRank(a) - activeRank(b) || new Date(a.created_at).getTime() - new Date(b.created_at).getTime();

interface DeliveryCardProps {
  order: Order;
  uz: boolean;
  // The order the screen's main button (Telegram's MainButton) acts on: its action is not drawn in the card
  isCurrent: boolean;
  showCurrentMark: boolean;
  onPickUp: (order: Order) => void;
  onDeliver: (order: Order) => void;
  busy: boolean;
}

function DeliveryCard({ order, uz, isCurrent, showCurrentMark, onPickUp, onDeliver, busy }: DeliveryCardProps) {
  const { language, t } = useLanguageStore();
  const code = formatOrderCode(order);
  const address = cleanAddress(order.address, uz ? 'GPS lokatsiya' : 'GPS локация');
  const mapUrl = mapRouteUrl(order.shipping_address?.latitude, order.shipping_address?.longitude) ?? linkInAddress(order.address);
  const items = order.items ?? [];
  const phone = order.user?.phone;
  const waiting = order.status === 'courier_assigned';
  const paid = order.payment_status === 'paid';

  return (
    <article
      aria-label={`${uz ? 'Buyurtma' : 'Заказ'} ${code}`}
      className={cx('flex flex-col gap-3 rounded-card border bg-surface p-3', isCurrent && showCurrentMark ? 'border-brand' : 'border-line')}
    >
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        {isCurrent && showCurrentMark && <Pill tone="brand">{uz ? 'Hozirgi' : 'Текущий'}</Pill>}
        <time dateTime={order.created_at} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.created_at)}
        </time>
      </div>

      <div className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-body font-bold text-fg">{order.user?.name}</h2>
          <p className="truncate text-footnote text-fg-muted">{phone || (uz ? 'Telefon kiritilmagan' : 'Телефон не указан')}</p>
        </div>
        {phone && (
          <a
            href={`tel:${phone}`}
            aria-label={`${uz ? "Qo'ng'iroq qilish" : 'Позвонить'}: ${order.user?.name ?? phone}`}
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
              {uz ? 'Xaritada ochish' : 'Открыть на карте'}
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
        <ul aria-label={uz ? 'Mahsulotlar' : 'Содержимое'} className="flex flex-wrap gap-1.5">
          {items.map((item, index) => (
            <li key={index} className="rounded-control bg-surface-2 px-2 py-1 text-footnote text-fg">
              {item.product_name} x{item.quantity}
            </li>
          ))}
        </ul>
      )}

      <div className="flex items-center justify-between gap-3 border-t border-line pt-2.5">
        <span className="inline-flex items-center gap-1.5 text-footnote text-fg-muted">
          <Wallet aria-hidden="true" className="size-3.5" />
          {getPaymentMethodLabel(order.payment_method, t)}
          {paid ? (
            <Pill tone="success">{uz ? "To'langan" : 'Оплачено'}</Pill>
          ) : order.payment_method === 'cash' ? (
            <Pill tone="warning">{uz ? 'Naqd olinadi' : 'Оплата наличными'}</Pill>
          ) : null}
        </span>
        <span className="shrink-0 text-headline font-bold text-brand">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>

      {/* Every delivery but the current one has its own action; the current one is on the main button */}
      {!isCurrent &&
        (waiting ? (
          <Button icon={Navigation} loading={busy} onClick={() => onPickUp(order)} fullWidth aria-label={`${uz ? "Olib ketdim (Yo'lga chiqdim)" : 'Забрал (В пути)'} ${code}`}>
            {uz ? "Olib ketdim (Yo'lga chiqdim)" : 'Забрал (В пути)'}
          </Button>
        ) : (
          <Button variant="success" icon={CheckCircle2} loading={busy} onClick={() => onDeliver(order)} fullWidth aria-label={`${uz ? 'Yetkazib berdim (Yakunlash)' : 'Доставил (Завершить)'} ${code}`}>
            {uz ? 'Yetkazib berdim (Yakunlash)' : 'Доставил (Завершить)'}
          </Button>
        ))}
    </article>
  );
}

export default function CourierDashboard() {
  const { user } = useAuthStore();
  const { language } = useLanguageStore();
  const uz = language === 'uz';
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');

  // Courier assigned orders: polled while the screen is visible (active deliveries change quickly) and pushed over the socket
  const { data: activeData, isLoading, error, dataUpdatedAt, refetch: refetchActive, isRefetching: isRefetchingActive } = useStaffOrders('courier', POLL_INTERVAL_MS.courierOrders);
  const allOrders = activeData?.orders ?? NO_ORDERS;

  // What was delivered today: the finished orders, loaded back to the start of the day. "Today" is the day of the last
  // refresh, so a screen left open over midnight starts the new day with the next poll
  const [openedAt] = useState(() => Date.now());
  const startOfToday = new Date(Math.max(dataUpdatedAt, openedAt)).setHours(0, 0, 0, 0);
  const history = useOrderHistory('courier', { since: startOfToday, pageSize: 50 });

  // Complete order mutation
  const completeMutation = useMutation({
    mutationFn: async (orderUuid: string) => {
      hapticFeedback('medium');
      const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/complete`);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
      notificationOccurred('success');
    },
    onError: (err) => {
      logApiError('Failed to complete order:', err);
      notificationOccurred('error');
      void showAlert(uz ? 'Buyurtmani topshirishda xatolik yuz berdi!' : 'Ошибка при завершении заказа!');
    }
  });

  // Update status mutation (e.g. for marking as on_the_way)
  const updateStatusMutation = useMutation({
    mutationFn: async ({ orderUuid, status }: { orderUuid: string; status: OrderStatus }) => {
      hapticFeedback('medium');
      const response = await api.post<ApiResponse<Order>>(`/orders/${orderUuid}/status`, {
        status
      });
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
      notificationOccurred('success');
    },
    onError: (err) => {
      logApiError('Failed to update status:', err);
      notificationOccurred('error');
      void showAlert(getApiErrorMessage(err) || (uz ? 'Holatni yangilashda xatolik yuz berdi!' : 'Ошибка!'));
    }
  });

  // Filter active and completed orders
  const activeOrders = allOrders.filter((order) => ACTIVE_STATUSES.includes(order.status)).sort(byDeliveryPriority);
  const completedOrders = history.orders.filter(
    (order) => order.status === 'delivered' && new Date(order.updated_at).getTime() >= startOfToday,
  );
  const hasNothingToShow = !!error && allOrders.length === 0;

  const pickUp = (order: Order) => updateStatusMutation.mutate({ orderUuid: order.uuid, status: 'on_the_way' });
  const deliver = (order: Order) => completeMutation.mutate(order.uuid);

  // The screen's one main action is for the current delivery: always at the bottom, above the navigation, never
  // scrolled away. With several deliveries it is the first card, marked "Hozirgi" (current).
  const current = activeOrders[0];
  const currentWaiting = current?.status === 'courier_assigned';
  const mainText = currentWaiting ? (uz ? "Olib ketdim (Yo'lga chiqdim)" : 'Забрал (В пути)') : uz ? 'Yetkazib berdim (Yakunlash)' : 'Доставил (Завершить)';
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
        <PageHeader title={user?.name} subtitle={uz ? 'Kuryer kabineti' : 'Кабинет курьера'} />
        <InlineError onRetry={() => void refetchActive()} retrying={isRefetchingActive} />
      </div>
    );
  }

  const tabs = [
    { value: 'active' as const, label: uz ? `Faol (${activeOrders.length})` : `Активные (${activeOrders.length})` },
    { value: 'completed' as const, label: uz ? `Topshirilganlar (${completedOrders.length})` : `Завершенные (${completedOrders.length})` },
  ];
  const busyUuid = updateStatusMutation.isPending ? updateStatusMutation.variables?.orderUuid : completeMutation.isPending ? completeMutation.variables : undefined;

  return (
    <div className="flex flex-col gap-3 px-4 pt-2">
      <PageHeader title={user?.name} subtitle={uz ? 'Kuryer kabineti' : 'Кабинет курьера'} />

      <div className="grid grid-cols-2 gap-2">
        <div className="flex flex-col gap-0.5 rounded-card border border-line bg-surface px-3 py-2.5">
          <span className="flex items-center gap-1.5 text-caption font-medium text-fg-muted">
            <Wallet aria-hidden="true" className="size-3.5 text-brand" />
            {uz ? 'Bugungi daromad' : 'Сегодня заработано'}
          </span>
          <span className="text-headline font-bold text-fg">{formatMoney(todayTotalEarnings, language)}</span>
        </div>
        <div className="flex flex-col gap-0.5 rounded-card border border-line bg-surface px-3 py-2.5">
          <span className="flex items-center gap-1.5 text-caption font-medium text-fg-muted">
            <PackageCheck aria-hidden="true" className="size-3.5 text-success" />
            {uz ? 'Yetkazilganlar' : 'Доставлено'}
          </span>
          <span className="text-headline font-bold text-fg">
            {completedOrders.length} {uz ? 'ta' : 'шт'}
          </span>
        </div>
      </div>

      {activeData?.truncated && <TruncatedOrdersNotice />}

      <SegmentedControl segments={tabs} value={activeTab} onChange={setActiveTab} label={uz ? "Buyurtmalar bo'limi" : 'Раздел заказов'} />

      <div className="flex flex-col gap-3">
        {(activeTab === 'active' ? isLoading && allOrders.length === 0 : history.isLoading) ? (
          <CardSkeletons count={2} lines={3} label={uz ? 'Yuklanmoqda' : 'Загрузка'} />
        ) : activeTab === 'active' ? (
          activeOrders.length === 0 ? (
            <EmptyState
              icon={Bike}
              title={uz ? 'Sizga hali buyurtma biriktirilmagan.' : 'У вас пока нет активных заказов.'}
              text={uz ? 'Menejer buyurtmani biriktirsa, shu yerda paydo bo‘ladi.' : 'Когда менеджер назначит заказ, он появится здесь.'}
            />
          ) : (
            activeOrders.map((order, index) => (
              <DeliveryCard
                key={order.id}
                order={order}
                uz={uz}
                isCurrent={index === 0}
                showCurrentMark={activeOrders.length > 1}
                onPickUp={pickUp}
                onDeliver={deliver}
                busy={busyUuid === order.uuid}
              />
            ))
          )
        ) : completedOrders.length === 0 ? (
          <EmptyState icon={CheckCircle2} title={uz ? "Bugun hali yetkazib berilgan buyurtmalar yo'q." : 'Сегодня еще нет доставленных заказов.'} />
        ) : (
          completedOrders.map((order) => (
            <article key={order.id} aria-label={`${uz ? 'Buyurtma' : 'Заказ'} ${formatOrderCode(order)}`} className="flex flex-col gap-2 rounded-card border border-line bg-surface p-3">
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
