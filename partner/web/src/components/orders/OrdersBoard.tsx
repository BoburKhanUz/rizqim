'use client';

import { useCallback, useEffect, useId, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { Button, cx, fieldClass, IconButton, InlineError, Skeleton } from '@rizqim/ui';
import {
  boardKeys,
  getBoardActions,
  getApiErrorStatus,
  statusLabel as staffStatusLabel,
  useBoardAction,
  useStaffOrder,
  useStaffOrderPages,
  useStaffOrders,
  type OrderAction,
  type StaffCancelReason,
  type StaffOrder,
  type StaffOrdersQuery,
} from '@rizqim/staff';
import { formatOrderCode } from '@rizqim/ui';
import { orderTypeLabel } from '@/lib/labels';
import { readStored, writeStored } from '@/lib/storage';
import { useAccess } from '../AuthProvider';
import { useI18n } from '../I18nProvider';
import { PageHeader } from '../PageHeader';
import { useBranchScope } from '../shell/BranchScope';
import { useLiveOrders } from '../shell/LiveOrders';
import { CourierDialog, ReasonDialog } from './ActionDialogs';
import {
  actionErrorMessage,
  BOARD_CHANNELS,
  BOARD_COLUMNS,
  BOARD_TYPES,
  channelLabel,
  groupBoard,
  searchTerm,
  todayIn,
  type BoardColumnId,
} from './boardModel';
import { OrderCard } from './OrderCard';
import { OrderDrawer } from './OrderDrawer';
import { useNow } from './useNow';

// The live orders board (P2-21): the open orders of the chosen branch scope in columns by status (GET /staff/orders,
// scope=active, every page), today's finished ones in a collapsible last column (scope=history, date=today, a page at a
// time), an order's detail in a drawer (GET /staff/orders/{uuid}). Kept fresh by the shell's real-time connection (an
// event refetches the lists and that order) and by polling (faster while real-time is off). The actions are the ones the
// API offers for the order and the person's permissions; a refusal (403/404/422) is said in words and the board refetched.

const DONE_COLLAPSED_KEY = 'rz_board_done_collapsed';
const FINISHED_PAGE_SIZE = 30;
const DEFAULT_TIME_ZONE = 'Asia/Tashkent';
const NO_PERMISSIONS: readonly string[] = [];

type PendingDialog = { kind: 'reject' | 'cancel' | 'courier'; order: StaffOrder; action: OrderAction };

export function OrdersBoard() {
  const { t, language } = useI18n();
  const queryClient = useQueryClient();
  const access = useAccess();
  const permissions = access?.permissions ?? NO_PERMISSIONS;
  const { branchId, branches } = useBranchScope();
  const { pollInterval } = useLiveOrders();
  const now = useNow(30_000);
  const searchId = useId();
  const typeId = useId();
  const channelId = useId();

  // --- Filters --------------------------------------------------------------------------------------------------
  const [searchInput, setSearchInput] = useState('');
  const [term, setTerm] = useState<string | undefined>(undefined);
  const [type, setType] = useState('');
  const [channel, setChannel] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setTerm(searchTerm(searchInput)), 300);
    return () => clearTimeout(timer);
  }, [searchInput]);
  const filtered = term !== undefined || type !== '' || channel !== '';

  const filters = useMemo<StaffOrdersQuery>(
    () => ({
      ...(branchId === null ? {} : { branch_id: branchId }),
      ...(type ? { type: type as StaffOrdersQuery['type'] } : {}),
      ...(channel ? { channel } : {}),
      ...(term ? { q: term } : {}),
    }),
    [branchId, type, channel, term],
  );
  const timeZone = branches[0]?.working_hours.timezone ?? DEFAULT_TIME_ZONE;
  const today = todayIn(timeZone, new Date(now));

  // --- Data -----------------------------------------------------------------------------------------------------
  const active = useStaffOrders({ scope: 'active', ...filters }, { refetchInterval: pollInterval, refetchIntervalInBackground: true });
  const finished = useStaffOrderPages({ scope: 'history', date: today, per_page: FINISHED_PAGE_SIZE, ...filters }, { refetchInterval: pollInterval * 2 });
  const finishedOrders = useMemo(() => finished.data?.pages.flatMap((page) => page.orders) ?? [], [finished.data]);
  const groups = useMemo(() => groupBoard(active.data?.orders ?? [], finishedOrders), [active.data, finishedOrders]);

  const [selected, setSelected] = useState<string | null>(null);
  const detail = useStaffOrder(selected ?? undefined, { refetchInterval: pollInterval, retry: false });
  const selectedOrder = useMemo(() => {
    if (!selected) return undefined;
    return (Object.values(groups).flat() as StaffOrder[]).find((order) => order.uuid === selected) ?? detail.data;
  }, [selected, groups, detail.data]);

  const [doneCollapsed, setDoneCollapsed] = useState(() => readStored(DONE_COLLAPSED_KEY) === '1');
  const toggleDone = () =>
    setDoneCollapsed((previous) => {
      writeStored(DONE_COLLAPSED_KEY, previous ? '0' : '1');
      return !previous;
    });

  // --- Actions --------------------------------------------------------------------------------------------------
  const mutation = useBoardAction();
  const [busy, setBusy] = useState<{ uuid: string; id: string } | null>(null);
  const [dialog, setDialog] = useState<PendingDialog | null>(null);
  const [dialogError, setDialogError] = useState<string | null>(null);
  const [notice, setNotice] = useState<{ tone: 'error' | 'success'; text: string } | null>(null);

  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(null), notice.tone === 'error' ? 10_000 : 4_000);
    return () => clearTimeout(timer);
  }, [notice]);

  const send = useCallback(
    async (order: StaffOrder, action: OrderAction, extra: { reason?: StaffCancelReason; note?: string; courierId?: number } = {}, inDialog = false) => {
      setBusy({ uuid: order.uuid, id: action.id });
      setNotice(null);
      setDialogError(null);
      try {
        const result = await mutation.mutateAsync({ uuid: order.uuid, action, ...extra });
        setDialog(null);
        setNotice({ tone: 'success', text: t('board_done', { code: formatOrderCode(order), status: staffStatusLabel(result.status, language) }) });
      } catch (error) {
        const message = actionErrorMessage(error, t);
        if (inDialog) setDialogError(message);
        else setNotice({ tone: 'error', text: message });
        // The order changed under us (another screen, the kitchen): show how it is now
        const status = getApiErrorStatus(error);
        if (status === 404 || status === 409 || status === 422) void queryClient.invalidateQueries({ queryKey: boardKeys.all });
      } finally {
        setBusy(null);
      }
    },
    [mutation, t, language, queryClient],
  );

  const runAction = useCallback(
    (order: StaffOrder, action: OrderAction) => {
      if (action.needsReason) {
        setDialogError(null);
        setDialog({ kind: action.id === 'reject' ? 'reject' : 'cancel', order, action });
      } else if (action.needsCourier) {
        setDialogError(null);
        setDialog({ kind: 'courier', order, action });
      } else void send(order, action);
    },
    [send],
  );

  const showBranch = branchId === null && branches.length > 1;

  // --- View -----------------------------------------------------------------------------------------------------
  return (
    <>
      <PageHeader title={t('nav_orders')} subtitle={t('board_subtitle')} />

      <div className="mb-4 flex flex-wrap items-end gap-3" role="search">
        <div className="flex w-full flex-col gap-1 sm:w-64">
          <label htmlFor={searchId} className="text-footnote font-medium text-fg-muted">
            {t('board_search')}
          </label>
          <div className="relative">
            <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-fg-subtle" />
            <input
              id={searchId}
              type="search"
              inputMode="text"
              autoComplete="off"
              maxLength={24}
              placeholder={t('board_searchPlaceholder')}
              className={`${fieldClass} pl-10 text-body`}
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
            />
          </div>
        </div>
        <FilterSelect id={typeId} label={t('board_filterType')} value={type} onChange={setType} allLabel={t('board_filterAll')}>
          {BOARD_TYPES.map((value) => (
            <option key={value} value={value}>
              {orderTypeLabel(language, value)}
            </option>
          ))}
        </FilterSelect>
        <FilterSelect id={channelId} label={t('board_filterChannel')} value={channel} onChange={setChannel} allLabel={t('board_filterAll')}>
          {BOARD_CHANNELS.map((value) => (
            <option key={value} value={value}>
              {channelLabel(t, value)}
            </option>
          ))}
        </FilterSelect>
        {filtered && (
          <Button
            variant="ghost"
            icon={X}
            onClick={() => {
              setSearchInput('');
              setTerm(undefined);
              setType('');
              setChannel('');
            }}
          >
            {t('board_clearFilters')}
          </Button>
        )}
      </div>

      {active.isPending ? (
        <BoardSkeleton label={t('loading')} />
      ) : active.isError && !active.data ? (
        <InlineError title={t('loadErrorTitle')} message={t('loadErrorText')} retryLabel={t('retry')} retrying={active.isFetching} onRetry={() => void active.refetch()} />
      ) : (
        <>
          {filtered && Object.values(groups).every((list) => list.length === 0) && (
            <p role="status" className="mb-3 text-body text-fg-muted">
              {t('board_noMatch')}
            </p>
          )}
          <div className="flex gap-3 overflow-x-auto pb-3" data-testid="board">
            {BOARD_COLUMNS.map((column) => {
              const orders = groups[column.id];
              const collapsible = column.id === 'done';
              const collapsed = collapsible && doneCollapsed;
              return (
                <BoardColumn
                  key={column.id}
                  id={column.id}
                  title={t(column.label)}
                  count={orders.length}
                  collapsed={collapsed}
                  onToggle={collapsible ? toggleDone : undefined}
                  emptyText={t('board_col_empty')}
                  countLabel={t('board_col_count', { n: orders.length })}
                  toggleLabel={collapsed ? t('board_col_expand', { name: t(column.label) }) : t('board_col_collapse', { name: t(column.label) })}
                  footer={
                    collapsible && finished.hasNextPage ? (
                      <Button variant="secondary" loading={finished.isFetchingNextPage} onClick={() => void finished.fetchNextPage()}>
                        {t('board_loadMore')}
                      </Button>
                    ) : null
                  }
                >
                  {orders.map((order) => {
                    const actions = getBoardActions(order, permissions);
                    const primary = actions.find((action) => !action.destructive);
                    return (
                      <li key={order.uuid}>
                        <OrderCard
                          order={order}
                          now={now}
                          primary={primary}
                          busy={busy?.uuid === order.uuid && busy.id === primary?.id}
                          showBranch={showBranch}
                          onOpen={() => setSelected(order.uuid)}
                          onAction={(action) => runAction(order, action)}
                        />
                      </li>
                    );
                  })}
                </BoardColumn>
              );
            })}
          </div>
          {active.data?.truncated && <p className="mt-2 text-footnote text-fg-muted">{t('board_truncated')}</p>}
        </>
      )}

      {selected && selectedOrder && (
        <OrderDrawer
          order={selectedOrder}
          detail={detail.data}
          detailLoading={detail.isPending}
          detailError={detail.isError}
          onRetry={() => void detail.refetch()}
          actions={getBoardActions(detail.data ?? selectedOrder, permissions)}
          busyAction={busy?.uuid === selected ? busy.id : null}
          now={now}
          onAction={(action) => runAction(detail.data ?? selectedOrder, action)}
          onClose={() => setSelected(null)}
        />
      )}

      {dialog && dialog.kind !== 'courier' && (
        <ReasonDialog
          order={dialog.order}
          kind={dialog.kind}
          busy={busy !== null}
          error={dialogError}
          onClose={() => setDialog(null)}
          onConfirm={(reason, note) => void send(dialog.order, dialog.action, { reason, note: note || undefined }, true)}
        />
      )}
      {dialog?.kind === 'courier' && (
        <CourierDialog
          order={dialog.order}
          busy={busy !== null}
          error={dialogError}
          onClose={() => setDialog(null)}
          onConfirm={(courierId) => void send(dialog.order, dialog.action, { courierId }, true)}
        />
      )}

      {notice && (
        <div
          role={notice.tone === 'error' ? 'alert' : 'status'}
          className={cx(
            'fixed bottom-4 left-1/2 z-[130] flex w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 items-start gap-2 rounded-card px-4 py-3 text-body shadow-2xl',
            notice.tone === 'error' ? 'bg-danger text-on-danger' : 'bg-fg text-page',
          )}
        >
          <p className="min-w-0 flex-1 pt-2.5">{notice.text}</p>
          <IconButton icon={X} label={t('board_close')} className="text-inherit hover:bg-white/10" onClick={() => setNotice(null)} />
        </div>
      )}
    </>
  );
}

function FilterSelect({
  id,
  label,
  value,
  onChange,
  allLabel,
  children,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  allLabel: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex w-[calc(50%-0.375rem)] flex-col gap-1 sm:w-48">
      <label htmlFor={id} className="text-footnote font-medium text-fg-muted">
        {label}
      </label>
      <select id={id} className={`${fieldClass} text-body`} value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">{allLabel}</option>
        {children}
      </select>
    </div>
  );
}

interface BoardColumnProps {
  id: BoardColumnId;
  title: string;
  count: number;
  countLabel: string;
  emptyText: string;
  collapsed: boolean;
  onToggle?: () => void;
  toggleLabel: string;
  footer?: React.ReactNode;
  children: React.ReactNode;
}

function BoardColumn({ id, title, count, countLabel, emptyText, collapsed, onToggle, toggleLabel, footer, children }: BoardColumnProps) {
  const headingId = useId();
  if (collapsed) {
    return (
      <section aria-labelledby={headingId} data-column={id} className="flex w-14 shrink-0 flex-col items-center gap-3 rounded-card border border-line bg-surface-2 py-2">
        <IconButton icon={ChevronLeft} label={toggleLabel} aria-expanded={false} onClick={onToggle} />
        <h2 id={headingId} className="text-footnote font-bold text-fg [writing-mode:vertical-rl]">
          {title}
        </h2>
        <span className="rounded-full bg-surface px-2 py-0.5 text-caption font-bold text-fg">{count}</span>
      </section>
    );
  }
  return (
    <section
      aria-labelledby={headingId}
      data-column={id}
      className="flex w-72 shrink-0 flex-col gap-2 rounded-card border border-line bg-surface-2 p-2 2xl:w-auto 2xl:min-w-0 2xl:flex-1 2xl:basis-0"
    >
      <header className="flex min-h-11 items-center gap-2 px-1">
        <h2 id={headingId} className="flex-1 text-headline font-bold text-fg">
          {title}
        </h2>
        <span
          aria-label={countLabel}
          className={cx('min-w-7 rounded-full px-2 py-0.5 text-center text-caption font-bold', id === 'new' && count > 0 ? 'bg-warning text-on-warning' : 'bg-surface text-fg')}
        >
          {count}
        </span>
        {onToggle && <IconButton icon={ChevronRight} label={toggleLabel} aria-expanded onClick={onToggle} />}
      </header>
      {count === 0 ? <p className="px-1 py-6 text-center text-footnote text-fg-muted">{emptyText}</p> : <ul className="flex flex-col gap-2">{children}</ul>}
      {footer}
    </section>
  );
}

function BoardSkeleton({ label }: { label: string }) {
  return (
    <div className="flex gap-3 overflow-hidden" role="status" aria-busy="true" aria-label={label}>
      {BOARD_COLUMNS.map((column) => (
        <div key={column.id} className="flex w-72 shrink-0 flex-col gap-2 rounded-card border border-line bg-surface-2 p-2 2xl:flex-1">
          <Skeleton className="h-6 w-32" />
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      ))}
    </div>
  );
}
