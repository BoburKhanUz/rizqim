import { useId, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { ChevronDown, ChevronUp, CreditCard, MapPin, MessageSquare, PackageOpen, Star, TriangleAlert, Wallet, X } from 'lucide-react';
import { NO_ORDERS, ORDERS_KEY, useOrderHistory, useOrders } from '../hooks/useOrders';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { CustomerCancelDialog } from '../components/CancelDialogs';
import { LoadMoreOrders, TruncatedOrdersNotice } from '../components/OrderListParts';
import { Button, Card, Dialog, EmptyState, InlineError, PageHeader, Section, StatusBadge, buttonClasses, cx } from '../components/ui';
import { getCancelErrorMessage, getCustomerCancelReasonLabel, isCancellableByCustomer } from '../lib/cancelReasons';
import { hapticFeedback } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { showAlert } from '../lib/dialogs';
import api from '../services/api';
import { getApiErrorMessage, logApiError } from '../lib/apiError';
import { toAmount } from '../lib/money';
import { formatLineQuantity } from '../lib/quantity';
import { formatDateTime, formatMoney, formatOrderCode } from '../lib/format';
import { getPaymentMethodLabel } from '../lib/paymentMethod';
import type { Order, PaymentStatus } from '../types';

// The colour of the payment status text (a *-soft-fg token: readable on the card in both themes)
const PAYMENT_STATUS_TEXT: Record<PaymentStatus, { labelKey: `paymentStatus_${PaymentStatus}`; color: string }> = {
  pending: { labelKey: 'paymentStatus_pending', color: 'text-warning-soft-fg' },
  paid: { labelKey: 'paymentStatus_paid', color: 'text-success-soft-fg' },
  failed: { labelKey: 'paymentStatus_failed', color: 'text-danger-soft-fg' },
  cancelled: { labelKey: 'paymentStatus_cancelled', color: 'text-danger-soft-fg' },
  expired: { labelKey: 'paymentStatus_expired', color: 'text-fg-muted' },
  refunded: { labelKey: 'paymentStatus_refunded', color: 'text-info-soft-fg' },
};

// The same height as an order card, so the page does not jump when the orders arrive
function OrdersSkeleton() {
  return (
    <div className="flex flex-col gap-3 animate-pulse" aria-hidden="true">
      {[1, 2, 3].map((n) => (
        <Card key={n} className="flex flex-col gap-3">
          <div className="flex items-start justify-between gap-3">
            <div className="flex w-1/2 flex-col gap-2">
              <div className="h-3 w-16 rounded-control bg-surface-2" />
              <div className="h-4 w-28 rounded-control bg-surface-2" />
              <div className="h-3 w-20 rounded-control bg-surface-2" />
            </div>
            <div className="flex w-1/3 flex-col items-end gap-2">
              <div className="h-6 w-24 rounded-full bg-surface-2" />
              <div className="h-4 w-16 rounded-control bg-surface-2" />
            </div>
          </div>
          <div className="h-11 rounded-control bg-surface-2" />
        </Card>
      ))}
    </div>
  );
}

const chipClasses = (tone: 'neutral' | 'danger' | 'brand') =>
  cx(
    'inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-card px-4 text-body font-bold',
    'transition-transform motion-safe:active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
    tone === 'neutral' && 'bg-surface-2 text-fg',
    tone === 'danger' && 'bg-danger-soft text-danger-soft-fg',
    tone === 'brand' && 'bg-brand-soft text-brand-soft-fg',
  );

export default function Orders() {
  const { t, language } = useLanguageStore();
  const queryClient = useQueryClient();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const [expandedOrderId, setExpandedOrderId] = useState<number | null>(null);
  const panelIdPrefix = useId();

  // The open orders (polled / pushed) and, below them, the finished ones a page at a time
  const { data: active, isLoading: isLoadingActive, error, refetch: refetchActive, isRefetching: isRefetchingActive } = useOrders();
  const history = useOrderHistory('customer', { enabled: isAuthenticated });
  const activeOrders = active?.orders ?? NO_ORDERS;
  const historyOrders = history.orders;
  const hasOrders = activeOrders.length > 0 || historyOrders.length > 0;
  const isLoading = isLoadingActive || history.isLoading;
  const failed = !!error || history.isError;

  // Cancelling an order of one's own
  const [cancelTarget, setCancelTarget] = useState<Order | null>(null);
  const [cancelError, setCancelError] = useState<string | null>(null);
  const cancelMutation = useMutation({
    mutationFn: async ({ uuid, note }: { uuid: string; note: string }) => {
      const response = await api.post(`/orders/${uuid}/cancel`, { reason: 'customer_request', ...(note ? { note } : {}) });
      return response.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      setCancelTarget(null);
      setCancelError(null);
      void queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
    },
    onError: (err) => {
      logApiError('Failed to cancel order:', err);
      hapticFeedback('error');
      setCancelError(getCancelErrorMessage(err, language));
      // The order may have moved on (accepted, paid) since the list was loaded: show what it is now
      void queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
    },
  });

  const openCancelDialog = (order: Order) => {
    hapticFeedback('light');
    cancelMutation.reset();
    setCancelError(null);
    setCancelTarget(order);
  };

  const closeCancelDialog = () => {
    if (cancelMutation.isPending) return;
    setCancelTarget(null);
    setCancelError(null);
  };

  // Rating
  const [showRatingModal, setShowRatingModal] = useState(false);
  const [ratingStoreId, setRatingStoreId] = useState<number | null>(null);
  const [ratingOrderId, setRatingOrderId] = useState<number | null>(null);
  const [selectedRating, setSelectedRating] = useState(5);
  const [ratingComment, setRatingComment] = useState('');
  const [isSubmittingRating, setIsSubmittingRating] = useState(false);
  const commentId = useId();

  const handleOpenRatingModal = (orderId: number, storeId: number) => {
    hapticFeedback('medium');
    setRatingOrderId(orderId);
    setRatingStoreId(storeId);
    setSelectedRating(5);
    setRatingComment('');
    setShowRatingModal(true);
  };

  const handleSubmitRating = async () => {
    if (!ratingStoreId) return;
    hapticFeedback('medium');
    setIsSubmittingRating(true);
    try {
      await api.post(`/stores/${ratingStoreId}/rate`, {
        rating: selectedRating,
        comment: ratingComment,
        order_id: ratingOrderId,
      });
      hapticFeedback('success');
      setShowRatingModal(false);
      void showAlert(t('rateThanks'));
    } catch (err) {
      logApiError('Failed to submit rating:', err);
      hapticFeedback('error');
      void showAlert(getApiErrorMessage(err) || t('genericError'));
    } finally {
      setIsSubmittingRating(false);
    }
  };

  const toggleExpand = (orderId: number) => {
    selectionChanged();
    setExpandedOrderId((prev) => (prev === orderId ? null : orderId));
  };

  // Tapping the summary opens the details, but not when the customer was selecting the number or address to copy it
  const toggleFromSummary = (orderId: number) => {
    if (window.getSelection()?.toString()) return;
    toggleExpand(orderId);
  };

  const renderOrder = (order: Order) => {
    const paymentStatus = PAYMENT_STATUS_TEXT[order.payment_status]; // undefined for a status this app doesn't know yet
    const isExpanded = expandedOrderId === order.id;
    const code = formatOrderCode(order);
    const panelId = `${panelIdPrefix}-${order.id}`;
    const typeLabel = order.type === 'delivery' ? t('delivery') : order.type === 'pickup' ? t('pickup') : t('dineIn');
    const hasReason = (order.status === 'canceled' || order.status === 'rejected') && (order.cancel_reason || order.cancel_note);

    return (
      <Card key={order.id} padded={false} className="p-4">
        {/* Summary: tapping it toggles the details (the button below does the same for the keyboard and screen readers) */}
        <div className="flex cursor-pointer items-start justify-between gap-3" onClick={() => toggleFromSummary(order.id)}>
          <div className="flex min-w-0 flex-col gap-0.5">
            <span className="select-text text-footnote font-bold text-fg-muted">{code}</span>
            <span className="text-body font-bold text-fg">{formatDateTime(order.created_at, language)}</span>
            <span className="text-footnote text-fg-muted">{typeLabel}</span>
            {/* P6-13: the number the counter calls ("Buyurtmangiz tayyor, raqam N") */}
            {order.queue_number != null && (
              <span className="text-footnote font-bold text-fg" data-testid="order-queue-number">
                {t('queueNumberLabel')}: <span className="text-headline text-brand">{order.queue_number}</span>
              </span>
            )}
          </div>

          <div className="flex shrink-0 flex-col items-end gap-2">
            <StatusBadge status={order.status} audience="customer" />
            <span className="text-body font-bold text-brand">{formatMoney(order.total_price, language)}</span>
          </div>
        </div>

        {/* Why a canceled or rejected order was */}
        {hasReason && (
          <div className="mt-3 flex flex-col gap-1 rounded-control bg-danger-soft p-3 text-footnote text-danger-soft-fg">
            <span className="text-caption font-bold">{t(order.status === 'rejected' ? 'orderRejectReason' : 'orderCancelReason')}</span>
            {order.cancel_reason && <span className="font-medium">{getCustomerCancelReasonLabel(order.cancel_reason, language)}</span>}
            {order.cancel_note && <span className="select-text">"{order.cancel_note}"</span>}
          </div>
        )}

        {isExpanded && (
          <div id={panelId} className="mt-3 flex flex-col gap-3 border-t border-line pt-3 animate-fade-in">
            {/* Items in three columns (name | x1 | price): the name wraps, the quantity and the price never do */}
            <ul className="flex flex-col gap-2.5 rounded-control bg-surface-2 p-3 text-footnote">
              {order.items?.map((item, idx) => (
                <li key={idx} className="grid grid-cols-[minmax(0,1fr)_auto_auto] items-baseline gap-x-3">
                  <span className="min-w-0 break-words font-medium text-fg">
                    {item.product_name || t('orderItemFallback')}
                    {item.variant_name && <span className="block text-caption font-normal text-fg-muted">{item.variant_name}</span>}
                    {item.modifiers?.map((modifier) => (
                      <span key={modifier.uuid} className="block text-caption font-normal text-fg-muted">
                        {modifier.name}
                        {modifier.quantity > 1 ? ` × ${modifier.quantity}` : ''}
                      </span>
                    ))}
                  </span>
                  <span className="whitespace-nowrap text-fg-muted">{formatLineQuantity(item.quantity, item.unit, language)}</span>
                  <span className="whitespace-nowrap text-right font-bold text-fg">
                    {formatMoney(item.total_price ? toAmount(item.total_price) : toAmount(item.unit_price) * item.quantity, language)}
                  </span>
                </li>
              ))}
            </ul>

            {order.address && (
              <div className="flex items-start gap-2.5 rounded-control bg-surface-2 p-3">
                <MapPin aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand" />
                <div className="min-w-0">
                  <span className="block text-caption text-fg-muted">{t('deliveryAddress')}</span>
                  <span className="select-text break-words text-body text-fg">{order.address}</span>
                </div>
              </div>
            )}

            <dl className="flex flex-col gap-2 text-footnote">
              <div className="flex items-center justify-between gap-3">
                <dt className="flex items-center gap-1.5 text-fg-muted">
                  <CreditCard aria-hidden="true" className="size-4 text-brand" />
                  {t('orderPaymentMethod')}
                </dt>
                <dd className="font-bold text-fg">{getPaymentMethodLabel(order.payment_method, t)}</dd>
              </div>
              <div className="flex items-center justify-between gap-3">
                <dt className="flex items-center gap-1.5 text-fg-muted">
                  <Wallet aria-hidden="true" className="size-4 text-brand" />
                  {t('orderPaymentStatus')}
                </dt>
                <dd className={cx('font-bold', paymentStatus?.color ?? 'text-fg')}>
                  {paymentStatus ? t(paymentStatus.labelKey) : order.payment_status}
                </dd>
              </div>
            </dl>

            {order.notes && (
              <div className="flex items-start gap-2 rounded-control bg-brand-soft p-3 text-footnote text-brand-soft-fg">
                <MessageSquare aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                <span className="select-text break-words">"{order.notes}"</span>
              </div>
            )}

            {/* Complaint, cancel and rating */}
            <div className="flex flex-wrap gap-2">
              <Link
                to={`/complaints?orderId=${order.id}`}
                state={{ orderId: order.id }}
                onClick={() => hapticFeedback('light')}
                className={chipClasses('neutral')}
              >
                <TriangleAlert aria-hidden="true" className="size-4" />
                {t('orderComplain')}
              </Link>

              {isCancellableByCustomer(order) && (
                <button type="button" onClick={() => openCancelDialog(order)} className={chipClasses('danger')}>
                  <X aria-hidden="true" className="size-4" />
                  {t('orderCancel')}
                </button>
              )}

              {order.status === 'delivered' && (
                <button type="button" onClick={() => handleOpenRatingModal(order.id, order.store_id)} className={chipClasses('brand')}>
                  <Star aria-hidden="true" className="size-4" />
                  {t('orderRate')}
                </button>
              )}
            </div>
          </div>
        )}

        <div className="mt-3 border-t border-line">
          <button
            type="button"
            aria-expanded={isExpanded}
            aria-controls={isExpanded ? panelId : undefined}
            aria-label={`${code}: ${isExpanded ? t('orderCollapse') : t('orderDetails')}`}
            onClick={() => toggleExpand(order.id)}
            className="mt-1 flex min-h-11 w-full cursor-pointer items-center justify-center gap-1 rounded-control text-footnote font-medium text-fg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
          >
            {isExpanded ? t('orderCollapse') : t('orderDetails')}
            {isExpanded ? <ChevronUp aria-hidden="true" className="size-4" /> : <ChevronDown aria-hidden="true" className="size-4" />}
          </button>
        </div>
      </Card>
    );
  };

  return (
    <div className="flex flex-col gap-5 px-4 pb-4 pt-2">
      <PageHeader title={t('myOrders')} subtitle={t('ordersSubtitle')} />

      {failed && !hasOrders ? (
        <InlineError
          retrying={isRefetchingActive || history.isRefetching}
          onRetry={() => {
            void refetchActive();
            void history.refetch();
          }}
        />
      ) : isLoading && !hasOrders ? (
        <OrdersSkeleton />
      ) : !hasOrders ? (
        <EmptyState
          icon={PackageOpen}
          title={t('ordersEmptyTitle')}
          text={t('ordersEmptyText')}
          action={
            <Link to="/" onClick={() => hapticFeedback('light')} className={buttonClasses()}>
              {t('ordersToCatalog')}
            </Link>
          }
        />
      ) : (
        <div className="flex flex-col gap-5">
          {active?.truncated && <TruncatedOrdersNotice variant="customer" />}

          {activeOrders.length > 0 && <Section title={t('ordersActive')}>{activeOrders.map(renderOrder)}</Section>}

          {historyOrders.length > 0 && (
            <Section title={t('ordersHistory')} label={t('ordersHistoryLabel')}>
              {historyOrders.map(renderOrder)}
            </Section>
          )}

          <LoadMoreOrders
            variant="customer"
            hasNextPage={history.hasNextPage}
            isFetchingNextPage={history.isFetchingNextPage}
            isFetching={history.isFetching}
            isError={history.isFetchNextPageError}
            loadedCount={historyOrders.length}
            onLoadMore={() => void history.fetchNextPage()}
          />
        </div>
      )}

      {cancelTarget && (
        <CustomerCancelDialog
          isPending={cancelMutation.isPending}
          error={cancelError}
          onClose={closeCancelDialog}
          onConfirm={(note) => cancelMutation.mutate({ uuid: cancelTarget.uuid, note })}
        />
      )}

      <Dialog
        open={showRatingModal}
        busy={isSubmittingRating}
        onClose={() => {
          hapticFeedback('light');
          setShowRatingModal(false);
        }}
        title={t('rateTitle')}
        footer={
          <Button onClick={handleSubmitRating} loading={isSubmittingRating}>
            {isSubmittingRating ? t('rateSending') : t('rateSend')}
          </Button>
        }
      >
        <div role="radiogroup" aria-label={t('rateTitle')} className="flex justify-center gap-1">
          {[1, 2, 3, 4, 5].map((star) => {
            const on = star <= selectedRating;
            return (
              <button
                key={star}
                type="button"
                role="radio"
                aria-checked={star === selectedRating}
                aria-label={t('rateStars', { n: star })}
                onClick={() => {
                  selectionChanged();
                  setSelectedRating(star);
                }}
                className="flex size-12 cursor-pointer items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-brand"
              >
                <Star aria-hidden="true" className={cx('size-9 transition-colors', on ? 'fill-accent text-accent' : 'text-fg-subtle')} />
              </button>
            );
          })}
        </div>

        <div className="flex flex-col gap-1.5">
          <label htmlFor={commentId} className="text-footnote font-bold text-fg-muted">
            {t('rateComment')}
          </label>
          <textarea
            id={commentId}
            value={ratingComment}
            onChange={(event) => setRatingComment(event.target.value)}
            placeholder={t('rateCommentPlaceholder')}
            className="h-24 w-full resize-none rounded-control border border-line bg-surface-2 px-3 py-2.5 text-headline text-fg focus:border-brand focus:outline-none"
          />
        </div>
      </Dialog>
    </div>
  );
}
