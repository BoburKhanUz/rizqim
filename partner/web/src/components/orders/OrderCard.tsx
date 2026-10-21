'use client';

import { Bike, Clock, ShoppingBag, Utensils } from 'lucide-react';
import { Button, cx, formatMoney, formatOrderCode, StatusBadge, type ButtonVariant } from '@rizqim/ui';
import { actionLabel, type OrderAction, type StaffOrder } from '@rizqim/staff';
import { orderTypeLabel, statusLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { channelLabel, waitingOf, type WaitTone } from './boardModel';

const TYPE_ICON = { delivery: Bike, pickup: ShoppingBag, dine_in: Utensils } as const;

const WAIT_CLASS: Record<WaitTone, string> = {
  calm: 'bg-surface-2 text-fg-muted',
  warning: 'bg-warning-soft text-warning-soft-fg',
  late: 'bg-danger-soft text-danger-soft-fg',
};

const ITEMS_SHOWN = 3;

interface OrderCardProps {
  order: StaffOrder;
  now: number;
  // The next step offered on the card itself (the rest are in the detail)
  primary?: OrderAction;
  busy: boolean;
  showBranch: boolean;
  onOpen: () => void;
  onAction: (action: OrderAction) => void;
}

export function actionVariant(action: OrderAction): ButtonVariant {
  if (action.destructive) return 'danger';
  return action.id === 'mark_ready' || action.id === 'hand_over' || action.id === 'confirm_delivery' ? 'success' : 'primary';
}

// One order on the board: number, type and channel, how long it has waited, the items, the total and its next step.
export function OrderCard({ order, now, primary, busy, showBranch, onOpen, onAction }: OrderCardProps) {
  const { t, language } = useI18n();
  const code = formatOrderCode(order);
  const waiting = waitingOf(order, now);
  const TypeIcon = TYPE_ICON[order.type as keyof typeof TYPE_ICON] ?? ShoppingBag;
  const extra = order.items.length - ITEMS_SHOWN;
  const inKitchen = order.status === 'accepted' || order.status === 'preparing';

  return (
    <article
      aria-label={code}
      data-status={order.status}
      className={cx(
        'flex cursor-pointer flex-col gap-2 rounded-card border border-line bg-surface p-3 transition-shadow hover:shadow-md',
        order.status === 'new' && 'border-l-4 border-l-warning',
      )}
      onClick={onOpen}
    >
      <div className="flex items-start justify-between gap-2">
        <button
          type="button"
          className="min-h-11 rounded-control text-left text-headline font-bold text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          aria-label={t('board_open', { code })}
          onClick={(event) => {
            event.stopPropagation();
            onOpen();
          }}
        >
          {code}
        </button>
        {waiting && (
          <span
            className={cx('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-1 text-caption font-bold', WAIT_CLASS[waiting.tone])}
            title={t('board_waiting', { time: t('board_waitingShort', { n: waiting.minutes }) })}
            data-tone={waiting.tone}
          >
            <Clock aria-hidden="true" className="size-3.5" />
            {waiting.minutes < 1 ? t('board_justNow') : t('board_waitingShort', { n: waiting.minutes })}
          </span>
        )}
      </div>

      <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-footnote text-fg-muted">
        <span className="inline-flex items-center gap-1 text-fg">
          <TypeIcon aria-hidden="true" className="size-4" />
          {orderTypeLabel(language, order.type)}
        </span>
        <span aria-hidden="true">·</span>
        <span>{channelLabel(t, order.channel)}</span>
        {order.table_ref && (
          <>
            <span aria-hidden="true">·</span>
            <span>{t('board_table', { table: order.table_ref })}</span>
          </>
        )}
      </p>

      {(order.customer?.name || showBranch) && (
        <p className="truncate text-footnote text-fg">
          {order.customer?.name}
          {order.customer?.name && showBranch && order.branch_name ? ' · ' : ''}
          {showBranch && order.branch_name ? <span className="text-fg-muted">{order.branch_name}</span> : null}
        </p>
      )}

      <ul className="flex flex-col gap-0.5 text-footnote text-fg">
        {order.items.slice(0, ITEMS_SHOWN).map((item) => (
          <li key={item.id} className="flex justify-between gap-2">
            <span className="min-w-0 truncate">
              {item.product_name}
              {item.variant_name ? ` (${item.variant_name})` : ''}
            </span>
            <span className="shrink-0 font-medium">{item.quantity_label}</span>
          </li>
        ))}
        {extra > 0 && <li className="text-fg-muted">{t('board_itemsMore', { n: extra })}</li>}
      </ul>

      {inKitchen && order.items_total > 0 && (
        <p className="text-caption font-medium text-fg-muted">{t('board_kitchenProgress', { ready: order.items_ready, total: order.items_total })}</p>
      )}
      {order.courier?.name && <p className="text-caption text-fg-muted">{t('board_courier', { name: order.courier.name })}</p>}

      <div className="flex items-center justify-between gap-2">
        <span className="font-bold text-fg">{formatMoney(order.total_price, language)}</span>
        {(order.status === 'canceled' || order.status === 'rejected' || order.status === 'refunded' || order.status === 'delivered') && (
          <StatusBadge status={order.status} label={statusLabel(language, order.status)} />
        )}
      </div>

      {primary && (
        <Button
          variant={actionVariant(primary)}
          loading={busy}
          onClick={(event) => {
            event.stopPropagation();
            onAction(primary);
          }}
        >
          {actionLabel(primary.id, language)}
        </Button>
      )}
    </article>
  );
}
