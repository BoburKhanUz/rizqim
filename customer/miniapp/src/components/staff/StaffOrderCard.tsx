import { useId, useState } from 'react';
import { Bike, ChefHat, ChevronDown, Clock, MapPin, ShoppingBag, StickyNote, Store, Utensils, Wallet } from 'lucide-react';
import {
  actionLabel,
  getOrderActions,
  orderTypeLabel,
  paymentMethodLabel,
  staffText,
  type AssignableCourier,
  type OrderAction,
  type StaffOrder,
} from '@rizqim/staff';
import { useLanguageStore } from '../../store/languageStore';
import { hapticFeedback } from '../../lib/telegram';
import { selectionChanged } from '../../lib/twa';
import { toAmount } from '../../lib/money';
import { formatMoney, formatOrderCode, formatTime } from '../../lib/format';
import { cleanAddress } from '../../lib/openMap';
import { formatWaiting, KITCHEN_STATUSES, kitchenProgress, lineQuantity, toOrderRef, waitingMinutes, waitingTone } from '../../lib/staffOrders';
import { useStaffText } from '../../hooks/useStaffText';
import { Button, StatusBadge, cx, fieldClass } from '../ui';
import { Pill } from './Pill';
import { OrderLines } from './OrderLines';
import { ORDER_TEXT } from './orderText';
import { ACTION_LOOK } from './orderActions';
import { OrderDetailsLink } from './OrderDetailsLink';

interface StaffOrderCardProps {
  order: StaffOrder;
  now: number;
  // Said on the card when the owner works with several branches
  showBranch: boolean;
  couriers: AssignableCourier[];
  courierId: number | undefined;
  onPickCourier: (courierId: number) => void;
  onAction: (order: StaffOrder, action: OrderAction) => void;
  onCancel: (order: StaffOrder, mode: 'cancel' | 'reject') => void;
  busy: boolean;
}

// An open order of GET /staff/orders: who and what, how long it has been waiting, how far the kitchen is, and the buttons
// that move it on (the package decides which; the backend still checks each press).
export function StaffOrderCard({ order, now, showBranch, couriers, courierId, onPickCourier, onAction, onCancel, busy }: StaffOrderCardProps) {
  const language = useLanguageStore((state) => state.language);
  const tx = useStaffText(ORDER_TEXT);
  // The lines of an order the kitchen is cooking are open at once: that is what the cook and the manager look for
  const [open, setOpen] = useState(order.status === 'new' || KITCHEN_STATUSES.includes(order.status));
  const itemsId = useId();
  const courierSelectId = useId();

  const items = order.items;
  const isDelivery = order.type === 'delivery';
  const TypeIcon = order.type === 'dine_in' ? Utensils : isDelivery ? Bike : ShoppingBag;
  const address = cleanAddress(order.delivery?.address, staffText('gpsLocation', language));
  const code = formatOrderCode(order);
  const courierName = order.courier?.name || staffText('courierFallback', language);
  const preview = items.map((item) => `${item.product_name} ${lineQuantity(item, language)}`).join(', ');
  const progress = kitchenProgress(order);
  const minutes = waitingMinutes(order, now);
  const tone = minutes === null ? 'neutral' : waitingTone(minutes, order.status);
  const table = [order.hall_ref ? tx('hall', { hall: order.hall_ref }) : '', order.table_ref ? tx('table', { table: order.table_ref }) : ''].filter(Boolean).join(' · ');

  const actions = getOrderActions(toOrderRef(order), 'manager');
  const actionButton = (action: OrderAction, extra: { fullWidth?: boolean } = {}) => {
    const look = ACTION_LOOK[action.id];
    const isCancel = action.id === 'cancel' || action.id === 'reject';
    return (
      <Button
        key={action.id}
        variant={look.variant}
        icon={look.icon}
        disabled={busy || (action.needsCourier && !courierId)}
        onClick={() => (isCancel ? onCancel(order, action.id === 'reject' ? 'reject' : 'cancel') : onAction(order, action))}
        fullWidth={extra.fullWidth}
        className={extra.fullWidth ? undefined : cx('grow whitespace-nowrap', look.basis)}
      >
        {actionLabel(action.id, language)}
      </Button>
    );
  };

  return (
    <article aria-label={`${staffText('orderWord', language)} ${code}`} className="flex flex-col gap-2.5 rounded-card border border-line bg-surface p-3">
      <div className="flex items-center gap-2">
        <span className="text-headline font-bold text-fg">{code}</span>
        <StatusBadge status={order.status} audience="staff" />
        <time dateTime={order.created_at ?? undefined} className="ml-auto text-footnote text-fg-muted">
          {formatTime(order.created_at)}
        </time>
      </div>

      <div className="flex items-baseline justify-between gap-3">
        <span className="min-w-0 truncate text-body font-medium text-fg">{order.customer?.name}</span>
        <span className="shrink-0 text-headline font-bold text-brand">{formatMoney(toAmount(order.total_price), language)}</span>
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-footnote text-fg-muted">
        <span className="inline-flex items-center gap-1">
          <TypeIcon aria-hidden="true" className="size-3.5" />
          {orderTypeLabel(order.type, language)}
          {table && ` · ${table}`}
        </span>
        <span className="inline-flex items-center gap-1">
          <Wallet aria-hidden="true" className="size-3.5" />
          {paymentMethodLabel(order.payment_method, language)}
          {order.payment_status === 'paid' && <span className="font-bold text-success-soft-fg">{staffText('paidSuffix', language)}</span>}
        </span>
        {showBranch && order.branch_name && (
          <span className="inline-flex items-center gap-1">
            <Store aria-hidden="true" className="size-3.5" />
            {order.branch_name}
          </span>
        )}
      </div>

      {(minutes !== null || progress) && (
        <div className="flex flex-wrap items-center gap-2">
          {minutes !== null && (
            <Pill tone={tone} icon={Clock}>
              {tx('waiting', { time: formatWaiting(minutes, language) })}
            </Pill>
          )}
          {progress && KITCHEN_STATUSES.includes(order.status) && (
            <Pill tone={progress.ready === progress.total ? 'success' : 'info'} icon={ChefHat}>
              {tx('kitchenProgress', { ready: progress.ready, total: progress.total })}
            </Pill>
          )}
        </div>
      )}

      {address && (
        <p className="flex items-start gap-1.5 text-footnote text-fg">
          <MapPin aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-brand" />
          <span className="line-clamp-2">{address}</span>
        </p>
      )}

      {order.notes && (
        <p className="flex items-start gap-1.5 rounded-control bg-warning-soft px-2.5 py-1.5 text-footnote text-warning-soft-fg">
          <StickyNote aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
          <span className="min-w-0">
            <span className="sr-only">{tx('note')}: </span>
            {order.notes}
          </span>
        </p>
      )}

      {items.length > 0 && (
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
            <div id={itemsId} className="px-1 pb-1">
              <OrderLines items={items} />
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

      <OrderDetailsLink uuid={order.uuid} code={code} />
    </article>
  );
}
