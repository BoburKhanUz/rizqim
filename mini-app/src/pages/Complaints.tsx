import { useId, useMemo, useState, type FormEvent } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocation } from 'react-router-dom';
import { CheckCircle2, CircleCheck, Clock, Eye, MessageSquare, MessageSquareWarning, Send, ShieldAlert, TriangleAlert, Lock, type LucideIcon } from 'lucide-react';
import api from '../services/api';
import { hapticFeedback } from '../lib/telegram';
import { selectionChanged } from '../lib/twa';
import { showAlert, showConfirm } from '../lib/dialogs';
import { getApiErrorMessage } from '../lib/apiError';
import { formatDateTime, formatMoney, formatOrderCode } from '../lib/format';
import { TONE_BADGE, type StatusTone } from '../lib/orderStatus';
import { mergeOrders, useOrderHistory, useOrders } from '../hooks/useOrders';
import { useLanguageStore } from '../store/languageStore';
import { useAuthStore } from '../store/authStore';
import { useCopy, type CopyKey } from '../lib/customerCopy';
import { Button, Card, EmptyState, InlineError, PageHeader, Section, cx } from '../components/ui';
import type { ApiResponse, Complaint, ComplaintType, NewComplaint } from '../types';

const COMPLAINT_TYPES: readonly ComplaintType[] = ['WRONG_ITEM', 'ORDER_NOT_RECEIVED', 'QUALITY_ISSUE', 'COURIER_ISSUE', 'PAYMENT_ISSUE', 'OTHER'];

// Status of a complaint: tone + icon + label (the label says it, the colour only helps)
const STATUS_LOOK: Record<string, { tone: StatusTone | 'brand'; icon: LucideIcon }> = {
  pending: { tone: 'warning', icon: Clock },
  owner_replied: { tone: 'info', icon: MessageSquare },
  in_review: { tone: 'brand', icon: Eye },
  resolved: { tone: 'success', icon: CircleCheck },
  closed: { tone: 'neutral', icon: Lock },
};

const BADGE: Record<StatusTone | 'brand', string> = { ...TONE_BADGE, brand: 'bg-brand-soft text-brand-soft-fg' };

const FIELD =
  'w-full rounded-control border border-line bg-surface-2 px-3 py-2.5 text-headline text-fg focus:border-brand focus:outline-none';

// The order the customer came from ("Shikoyat qilish" on an order passes it as router state)
function getOrderIdFromState(state: unknown): string | undefined {
  if (typeof state === 'object' && state !== null && 'orderId' in state) {
    const orderId = state.orderId;
    if (typeof orderId === 'number' || typeof orderId === 'string') return orderId.toString();
  }
  return undefined;
}

function ComplaintStatusBadge({ status }: { status: string }) {
  const c = useCopy();
  const look = STATUS_LOOK[status] ?? { tone: 'neutral' as const, icon: Clock };
  const key = `complaintStatus_${status}` as CopyKey;
  const label = status in STATUS_LOOK ? c(key) : status;
  const Icon = look.icon;
  return (
    <span className={cx('inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-caption font-bold', BADGE[look.tone])}>
      <Icon aria-hidden="true" className="size-3.5 shrink-0" />
      {label}
    </span>
  );
}

function ComplaintsSkeleton() {
  return (
    <div className="flex flex-col gap-3 animate-pulse" aria-hidden="true">
      {[1, 2].map((n) => (
        <Card key={n} className="flex flex-col gap-3">
          <div className="flex justify-between gap-3">
            <div className="flex flex-col gap-2">
              <div className="h-3 w-16 rounded-control bg-surface-2" />
              <div className="h-4 w-28 rounded-control bg-surface-2" />
            </div>
            <div className="h-6 w-24 rounded-full bg-surface-2" />
          </div>
          <div className="h-14 rounded-control bg-surface-2" />
        </Card>
      ))}
    </div>
  );
}

// The customer's complaints and the form to send one; the owner and the manager read and answer them here.
export default function Complaints() {
  const location = useLocation();
  const { language } = useLanguageStore();
  const c = useCopy();
  const { user } = useAuthStore();
  const queryClient = useQueryClient();
  const orderFieldId = useId();
  const descriptionId = useId();

  // Retrieve initial orderId from location state or URL search parameters
  const searchParams = new URLSearchParams(location.search);
  const initialOrderId = getOrderIdFromState(location.state) || searchParams.get('orderId') || '';

  const [selectedOrderId, setSelectedOrderId] = useState<string>(initialOrderId);
  const [complaintType, setComplaintType] = useState<ComplaintType>('QUALITY_ISSUE');
  const [description, setDescription] = useState<string>('');
  const [replyText, setReplyText] = useState<Record<number, string>>({});

  // Fetch customer's complaints
  const {
    data: complaints = [],
    isLoading: loadingComplaints,
    isError: complaintsFailed,
    refetch: refetchComplaints,
    isRefetching: refetchingComplaints,
  } = useQuery({
    queryKey: ['complaints'],
    queryFn: async () => {
      const res = await api.get<ApiResponse<Complaint[]>>('/complaints');
      return res.data?.data || [];
    },
  });

  // Recent orders for selection: the open ones and the newest finished ones
  const { data: activeOrders } = useOrders();
  const { orders: finishedOrders } = useOrderHistory('customer');
  const orders = useMemo(() => mergeOrders(activeOrders?.orders ?? [], finishedOrders), [activeOrders, finishedOrders]);

  const userRole = user?.role || 'customer';
  const isBusinessUser = userRole === 'owner' || userRole === 'manager';

  const submitComplaint = useMutation({
    mutationFn: async (payload: NewComplaint) => {
      const res = await api.post<ApiResponse<Complaint>>('/complaints', payload);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      setDescription('');
      setSelectedOrderId('');
      void queryClient.invalidateQueries({ queryKey: ['complaints'] });
      void showAlert(c('complaintSent'));
    },
    onError: (err) => {
      hapticFeedback('rigid');
      void showAlert(getApiErrorMessage(err) || c('genericError'));
    },
  });

  const replyComplaint = useMutation({
    mutationFn: async ({ id, reply }: { id: number; reply: string }) => {
      const res = await api.post<ApiResponse<Complaint>>(`/complaints/${id}/reply`, { reply });
      return res.data;
    },
    onSuccess: (_, variables) => {
      hapticFeedback('success');
      setReplyText((prev) => ({ ...prev, [variables.id]: '' }));
      void queryClient.invalidateQueries({ queryKey: ['complaints'] });
      void showAlert(c('complaintReplySaved'));
    },
    onError: (err) => {
      hapticFeedback('rigid');
      void showAlert(getApiErrorMessage(err) || c('genericError'));
    },
  });

  const escalateComplaint = useMutation({
    mutationFn: async (id: number) => {
      const res = await api.post<ApiResponse<Complaint>>(`/complaints/${id}/escalate`);
      return res.data;
    },
    onSuccess: () => {
      hapticFeedback('success');
      void queryClient.invalidateQueries({ queryKey: ['complaints'] });
      void showAlert(c('complaintEscalated'));
    },
    onError: (err) => {
      hapticFeedback('rigid');
      void showAlert(getApiErrorMessage(err) || c('genericError'));
    },
  });

  const handleCreateComplaint = (e: FormEvent) => {
    e.preventDefault();
    if (!selectedOrderId) {
      void showAlert(c('complaintPickOrder'));
      return;
    }
    if (!description.trim()) {
      void showAlert(c('complaintEnterText'));
      return;
    }
    submitComplaint.mutate({
      order_id: parseInt(selectedOrderId),
      type: complaintType,
      description: description.trim(),
    });
  };

  const handleEscalate = async (id: number) => {
    if (await showConfirm(c('complaintEscalateAsk'), { confirmText: c('complaintEscalate') })) escalateComplaint.mutate(id);
  };

  return (
    <div className="flex flex-col gap-5 px-4 pb-4 pt-2">
      <PageHeader
        title={isBusinessUser ? c('complaintsTitleStaff') : c('complaintsTitleCustomer')}
        subtitle={isBusinessUser ? c('complaintsSubtitleStaff') : c('complaintsSubtitleCustomer')}
        back
      />

      {/* The form to send a complaint (customers only) */}
      {!isBusinessUser && (
        <Card className="flex flex-col gap-4">
          <div className="flex items-center gap-2">
            <TriangleAlert aria-hidden="true" className="size-5 text-brand" />
            <h2 className="text-headline font-bold text-fg">{c('complaintSend')}</h2>
          </div>

          <form onSubmit={handleCreateComplaint} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <label htmlFor={orderFieldId} className="text-footnote font-bold text-fg-muted">
                {c('complaintOrder')}
              </label>
              <select id={orderFieldId} value={selectedOrderId} onChange={(e) => setSelectedOrderId(e.target.value)} className={FIELD}>
                <option value="">{c('complaintOrderPlaceholder')}</option>
                {orders.map((order) => (
                  <option key={order.id} value={order.id}>
                    {formatOrderCode(order)} ({formatMoney(order.total_price, language)})
                  </option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <span id={`${orderFieldId}-type`} className="text-footnote font-bold text-fg-muted">
                {c('complaintType')}
              </span>
              <div role="radiogroup" aria-labelledby={`${orderFieldId}-type`} className="grid grid-cols-2 gap-2">
                {COMPLAINT_TYPES.map((type) => {
                  const selected = complaintType === type;
                  return (
                    <button
                      key={type}
                      type="button"
                      role="radio"
                      aria-checked={selected}
                      onClick={() => {
                        selectionChanged();
                        setComplaintType(type);
                      }}
                      className={cx(
                        'min-h-11 cursor-pointer rounded-control border px-3 py-2 text-center text-footnote font-bold transition-colors',
                        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                        selected ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface-2 text-fg',
                      )}
                    >
                      {c(`complaintType_${type}`)}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <label htmlFor={descriptionId} className="text-footnote font-bold text-fg-muted">
                {c('complaintDescription')}
              </label>
              <textarea
                id={descriptionId}
                rows={3}
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder={c('complaintPlaceholder')}
                className={cx(FIELD, 'resize-none')}
              />
            </div>

            <Button type="submit" size="lg" fullWidth icon={Send} loading={submitComplaint.isPending}>
              {c('complaintSubmit')}
            </Button>
          </form>
        </Card>
      )}

      <Section title={isBusinessUser ? c('complaintListStaff') : c('complaintListCustomer')}>
        {loadingComplaints ? (
          <ComplaintsSkeleton />
        ) : complaintsFailed && complaints.length === 0 ? (
          <InlineError retrying={refetchingComplaints} onRetry={() => void refetchComplaints()} />
        ) : complaints.length === 0 ? (
          <EmptyState
            icon={MessageSquareWarning}
            title={c('complaintsEmptyTitle')}
            text={isBusinessUser ? c('complaintsEmptyStaff') : c('complaintsEmptyCustomer')}
            className="py-8"
          />
        ) : (
          <div className="flex flex-col gap-3">
            {complaints.map((complaint) => {
              const reply = replyText[complaint.id] ?? '';
              return (
                <Card key={complaint.id} className="flex flex-col gap-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-0.5">
                      <span className="select-text text-footnote font-bold text-fg-muted">
                        {complaint.order?.short_code ? formatOrderCode(complaint.order) : `#${complaint.order_id}`}
                        <span className="font-normal"> · {formatDateTime(complaint.created_at, language)}</span>
                      </span>
                      <span className="text-body font-bold text-fg">{c(`complaintType_${complaint.type}`)}</span>
                    </div>
                    <ComplaintStatusBadge status={complaint.status} />
                  </div>

                  <p className="select-text break-words rounded-control bg-surface-2 p-3 text-footnote text-fg">{complaint.description}</p>

                  {complaint.reply ? (
                    <div className="flex flex-col gap-1 rounded-control bg-info-soft p-3 text-info-soft-fg">
                      <span className="flex items-center gap-1 text-caption font-bold">
                        <MessageSquare aria-hidden="true" className="size-3.5" />
                        {c('complaintReplyBy')}
                      </span>
                      <p className="select-text break-words text-footnote">{complaint.reply}</p>
                    </div>
                  ) : isBusinessUser ? (
                    <div className="flex flex-col gap-2 border-t border-line pt-3">
                      <textarea
                        rows={2}
                        aria-label={c('complaintReplyPlaceholder')}
                        value={reply}
                        onChange={(e) => setReplyText((prev) => ({ ...prev, [complaint.id]: e.target.value }))}
                        placeholder={c('complaintReplyPlaceholder')}
                        className={cx(FIELD, 'resize-none')}
                      />
                      <Button
                        icon={CheckCircle2}
                        className="self-end"
                        loading={replyComplaint.isPending && replyComplaint.variables?.id === complaint.id}
                        disabled={replyComplaint.isPending}
                        onClick={() => {
                          if (!reply.trim()) return;
                          replyComplaint.mutate({ id: complaint.id, reply: reply.trim() });
                        }}
                      >
                        {c('complaintReplySend')}
                      </Button>
                    </div>
                  ) : (
                    <p className="text-footnote text-fg-muted">{c('complaintWaiting')}</p>
                  )}

                  {/* The customer may take an answered complaint to a moderator */}
                  {!isBusinessUser && complaint.status === 'owner_replied' && (
                    <div className="flex justify-end border-t border-line pt-3">
                      <Button variant="secondary" icon={ShieldAlert} onClick={() => void handleEscalate(complaint.id)} disabled={escalateComplaint.isPending}>
                        {c('complaintEscalate')}
                      </Button>
                    </div>
                  )}
                </Card>
              );
            })}
          </div>
        )}
      </Section>
    </div>
  );
}
