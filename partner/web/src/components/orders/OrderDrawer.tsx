'use client';

import { useEffect, useId, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Phone, X } from 'lucide-react';
import { Button, cx, formatDateTime, formatMoney, formatOrderCode, formatTime, IconButton, InlineError, parseAmount, Skeleton, StatusBadge } from '@rizqim/ui';
import { actionLabel, cancelReasonLabel, paymentMethodLabel, type OrderAction, type StaffOrder, type StaffOrderDetail } from '@rizqim/staff';
import type { MessageKey } from '@/i18n';
import { orderTypeLabel, statusLabel } from '@/lib/labels';
import { useI18n } from '../I18nProvider';
import { channelLabel, waitingOf } from './boardModel';
import { actionVariant } from './OrderCard';

interface OrderDrawerProps {
  // What the board already knows (shown at once), and the full order once GET /staff/orders/{uuid} answers
  order: StaffOrder;
  detail: StaffOrderDetail | undefined;
  detailLoading: boolean;
  detailError: boolean;
  onRetry: () => void;
  actions: OrderAction[];
  busyAction: string | null;
  now: number;
  onAction: (action: OrderAction) => void;
  onClose: () => void;
}

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

// The detail of one order in a panel on the right: its actions, items with modifiers and weights and their kitchen state,
// the note, customer and delivery (when the person may see them), money, and the status timeline.
export function OrderDrawer({ order, detail, detailLoading, detailError, onRetry, actions, busyAction, now, onAction, onClose }: OrderDrawerProps) {
  const { t, language } = useI18n();
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  });

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    const panel = panelRef.current;
    panel?.querySelector<HTMLElement>(FOCUSABLE)?.focus();
    const onKeyDown = (event: KeyboardEvent) => {
      // A dialog over the drawer (reason, courier) handles its own Escape
      if (event.key === 'Escape' && !document.querySelector('[role="alertdialog"], [role="dialog"][aria-modal="true"]:not([data-drawer])')) onCloseRef.current();
    };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      previous?.focus?.();
    };
  }, []);

  const shown: StaffOrder = detail ?? order;
  const code = formatOrderCode(shown);
  const waiting = waitingOf(shown, now);
  const money = (value: string) => formatMoney(value, language);
  const nonZero = (value: string) => (parseAmount(value) ?? 0) !== 0;

  return createPortal(
    <div className="fixed inset-0 z-[110] flex justify-end bg-scrim animate-fade-in" onClick={(event) => event.target === event.currentTarget && onClose()}>
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        data-drawer=""
        aria-labelledby={titleId}
        className="flex h-full w-full max-w-lg flex-col overflow-hidden bg-surface text-fg shadow-2xl"
      >
        <header className="flex items-start gap-2 border-b border-line p-4">
          <div className="min-w-0 flex-1">
            <h2 id={titleId} className="text-title font-bold text-fg">
              {t('board_detailTitle', { code })}
            </h2>
            <p className="mt-1 flex flex-wrap items-center gap-2 text-footnote text-fg-muted">
              <StatusBadge status={shown.status} label={statusLabel(language, shown.status)} />
              <span>{orderTypeLabel(language, shown.type)}</span>
              <span aria-hidden="true">·</span>
              <span>{channelLabel(t, shown.channel)}</span>
              {waiting && (
                <>
                  <span aria-hidden="true">·</span>
                  <span>{t('board_waiting', { time: t('board_waitingShort', { n: waiting.minutes }) })}</span>
                </>
              )}
            </p>
            {shown.branch_name && <p className="mt-1 text-caption text-fg-muted">{t('board_branch', { name: shown.branch_name })}</p>}
          </div>
          <IconButton icon={X} label={t('board_close')} onClick={onClose} />
        </header>

        <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-4">
          {actions.length > 0 && (
            <section aria-label={t('board_actions')} className="flex flex-wrap gap-2">
              {actions.map((action) => (
                <Button key={action.id} variant={actionVariant(action)} loading={busyAction === action.id} disabled={busyAction !== null && busyAction !== action.id} onClick={() => onAction(action)}>
                  {actionLabel(action.id, language)}
                </Button>
              ))}
            </section>
          )}

          {(shown.status === 'canceled' || shown.status === 'rejected') && shown.cancel_reason && (
            <p className="rounded-control bg-danger-soft px-3 py-2 text-footnote text-danger-soft-fg">
              {t('board_cancelReason', { reason: cancelReasonLabel(shown.cancel_reason, language) })}
              {shown.cancel_note ? ` — ${shown.cancel_note}` : ''}
            </p>
          )}

          {detailError && !detail && <InlineError title={t('board_detailError')} message={t('loadErrorText')} retryLabel={t('retry')} onRetry={onRetry} />}

          <DrawerSection title={t('board_items')}>
            <ul className="flex flex-col divide-y divide-line">
              {shown.items.map((item) => (
                <li key={item.id} className="flex flex-col gap-1 py-2">
                  <div className="flex items-start justify-between gap-3">
                    <span className="min-w-0 font-medium text-fg">
                      {item.product_name}
                      {item.variant_name ? <span className="text-fg-muted"> ({item.variant_name})</span> : null}
                    </span>
                    <span className="shrink-0 font-bold text-fg">{item.quantity_label}</span>
                  </div>
                  {item.modifiers.length > 0 && (
                    <p className="text-footnote text-fg-muted">
                      {item.modifiers.map((modifier) => (modifier.quantity > 1 ? `${modifier.name} ×${modifier.quantity}` : modifier.name)).join(', ')}
                    </p>
                  )}
                  <div className="flex items-center justify-between gap-3 text-caption">
                    <span
                      className={cx(
                        'rounded-full px-2 py-0.5 font-bold',
                        item.kitchen_status === 'ready' ? 'bg-success-soft text-success-soft-fg' : 'bg-surface-2 text-fg-muted',
                      )}
                    >
                      {item.kitchen_status === 'ready' ? t('board_kitchen_ready') : t('board_kitchen_pending')}
                      {item.ready_at ? ` · ${formatTime(item.ready_at)}` : ''}
                    </span>
                    {item.total_price && <span className="text-fg-muted">{money(item.total_price)}</span>}
                  </div>
                </li>
              ))}
            </ul>
          </DrawerSection>

          {shown.notes && (
            <DrawerSection title={t('board_note')}>
              <p className="whitespace-pre-wrap rounded-control bg-warning-soft px-3 py-2 text-body text-warning-soft-fg">{shown.notes}</p>
            </DrawerSection>
          )}

          {shown.customer && (shown.customer.name || shown.customer.phone) && (
            <DrawerSection title={t('board_customer')}>
              <p className="text-body text-fg">{shown.customer.name || t('board_noCustomer')}</p>
              {shown.customer.phone && (
                <a
                  href={`tel:${shown.customer.phone}`}
                  className="inline-flex min-h-11 items-center gap-2 text-body font-medium text-brand underline-offset-2 hover:underline"
                  aria-label={t('board_call', { phone: shown.customer.phone })}
                >
                  <Phone aria-hidden="true" className="size-4" />
                  {shown.customer.phone}
                </a>
              )}
              {shown.guests_count ? <p className="text-footnote text-fg-muted">{t('board_guests', { n: shown.guests_count })}</p> : null}
            </DrawerSection>
          )}

          {shown.delivery && shown.delivery.address && (
            <DrawerSection title={t('board_delivery')}>
              <p className="text-body text-fg">{shown.delivery.address}</p>
              {shown.delivery.zone_name && <p className="text-footnote text-fg-muted">{t('board_zone', { name: shown.delivery.zone_name })}</p>}
              {shown.courier?.name && (
                <p className="text-footnote text-fg">
                  {t('board_courier', { name: shown.courier.name })}
                  {shown.courier.phone ? ` · ${shown.courier.phone}` : ''}
                </p>
              )}
            </DrawerSection>
          )}

          <DrawerSection title={t('board_payment')}>
            <p className="text-footnote text-fg-muted">
              {paymentMethodLabel(shown.payment_method, language) || shown.payment_method} · {paymentStatusLabel(t, shown.payment_status)}
            </p>
            <dl className="mt-1 flex flex-col gap-1 text-body">
              <MoneyRow label={t('board_subtotal')} value={money(shown.subtotal)} />
              {nonZero(shown.discount) && <MoneyRow label={t('board_discount')} value={`−${money(shown.discount)}`} />}
              {nonZero(shown.delivery_price) && <MoneyRow label={t('board_deliveryPrice')} value={money(shown.delivery_price)} />}
              {nonZero(shown.service_charge_amount) && <MoneyRow label={t('board_serviceCharge')} value={money(shown.service_charge_amount)} />}
              {nonZero(shown.place_fee_amount) && <MoneyRow label={t('board_placeFee')} value={money(shown.place_fee_amount)} />}
              <MoneyRow label={t('board_total')} value={money(shown.total_price)} strong />
            </dl>
          </DrawerSection>

          <DrawerSection title={t('board_timeline')}>
            {detail ? (
              <ol className="flex flex-col gap-2 border-l-2 border-line pl-4">
                {detail.status_history.map((entry, index) => (
                  <li key={`${entry.status}-${index}`} className="flex items-center justify-between gap-3 text-footnote">
                    <span className="text-fg">{statusLabel(language, entry.status)}</span>
                    <span className="text-fg-muted">{entry.created_at ? formatDateTime(entry.created_at, language) : '—'}</span>
                  </li>
                ))}
              </ol>
            ) : detailLoading ? (
              <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-4 w-48" />
                <Skeleton className="h-4 w-40" />
              </div>
            ) : null}
          </DrawerSection>
        </div>
      </div>
    </div>,
    document.body,
  );
}

function paymentStatusLabel(t: (key: MessageKey) => string, status: string): string {
  const key = `board_paymentStatus_${status}`;
  return ['pending', 'paid', 'failed', 'refunded'].includes(status) ? t(key as MessageKey) : status;
}

function DrawerSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-1">
      <h3 className="text-footnote font-bold text-fg-muted">{title}</h3>
      {children}
    </section>
  );
}

function MoneyRow({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className={cx('flex justify-between gap-3', strong ? 'border-t border-line pt-1 font-bold text-fg' : 'text-fg')}>
      <dt>{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}
