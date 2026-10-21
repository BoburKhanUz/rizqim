import { getApiErrorMessage, getOrderWaiting, isStaffApiError, statusGroup, type StaffOrder, type WaitTone } from '@rizqim/staff';
import type { MessageKey, Translate } from '@/i18n';

// How the board lays out what the API returns: which column an order sits in (by its status group, the same groups as
// the Mini App's counters), the order inside a column, the waiting time shown on a card, and the words of a refusal.
// No business rule lives here: the server decides the status and what can follow.

export type BoardColumnId = 'new' | 'preparing' | 'ready' | 'courier' | 'done';

export const BOARD_COLUMNS: readonly { id: BoardColumnId; label: MessageKey }[] = [
  { id: 'new', label: 'board_col_new' },
  { id: 'preparing', label: 'board_col_preparing' },
  { id: 'ready', label: 'board_col_ready' },
  { id: 'courier', label: 'board_col_courier' },
  { id: 'done', label: 'board_col_doneToday' },
];

export type BoardGroups = Record<BoardColumnId, StaffOrder[]>;

const time = (value: string | null | undefined) => (value ? Date.parse(value) || 0 : 0);

// The column of an order: open statuses by their group; delivered, canceled, rejected, refunded under "done".
export function columnOf(status: string): BoardColumnId | null {
  const group = statusGroup(status);
  if (group === null) return null;
  return group === 'canceled' ? 'done' : group;
}

// Open orders oldest first (the one waiting longest on top); finished ones newest first. An order that is in both lists
// (it finished between the two requests) is shown once, where its newest copy puts it.
export function groupBoard(active: readonly StaffOrder[], finished: readonly StaffOrder[]): BoardGroups {
  const groups: BoardGroups = { new: [], preparing: [], ready: [], courier: [], done: [] };
  const latest = new Map<string, StaffOrder>();
  for (const order of [...active, ...finished]) {
    const known = latest.get(order.uuid);
    if (!known || time(order.updated_at) >= time(known.updated_at)) latest.set(order.uuid, order);
  }
  for (const order of latest.values()) {
    const column = columnOf(order.status);
    if (column) groups[column].push(order);
  }
  for (const id of ['new', 'preparing', 'ready', 'courier'] as const) groups[id].sort((a, b) => time(a.created_at) - time(b.created_at));
  groups.done.sort((a, b) => time(b.closed_at ?? b.updated_at) - time(a.closed_at ?? a.updated_at));
  return groups;
}

export type { WaitTone } from '@rizqim/staff';

// How long the order has waited and how worrying that is, by the rule the Mini App's staff screens share
// (@rizqim/staff getOrderWaiting: the server's `waiting_seconds`, ticking on while the clock runs; the thresholds of
// WAITING_THRESHOLDS). Finished orders show none.
export function waitingOf(order: Pick<StaffOrder, 'status' | 'created_at' | 'accepted_at' | 'waiting_seconds'>, now: number): { minutes: number; tone: WaitTone } | null {
  const column = columnOf(order.status);
  if (column === null || column === 'done') return null;
  return getOrderWaiting(order, now);
}

// "Today" for the finished column: the date in the store's time zone (the API counts days there)
export function todayIn(timeZone: string, now: Date = new Date()): string {
  try {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  } catch {
    return new Intl.DateTimeFormat('en-CA', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(now);
  }
}

// The search box: the API takes the start of the order number, 1–20 letters or digits ("#A105" → "A105")
export function searchTerm(input: string): string | undefined {
  const term = input.replace(/[^\p{L}\p{N}]/gu, '').slice(0, 20);
  return term === '' ? undefined : term;
}

// The message of a refused action, in the person's language: a 403 or 404 in our words, a 422 the server's own
// (Uzbek, it says what went wrong) or ours, anything else the general one.
export function actionErrorMessage(error: unknown, t: Translate): string {
  if (!isStaffApiError(error)) return error instanceof TypeError ? t('networkError') : t('unknownError');
  switch (error.status) {
    case 403:
      return error.reason === 'staff_session_not_allowed' ? t('board_err_staffSession') : t('board_err_forbidden');
    case 404:
      return t('board_err_notFound');
    case 409:
      return t('board_err_conflict');
    case 422: {
      const field = error.fieldError('reason') ?? error.fieldError('status') ?? error.fieldError('courier_id') ?? error.fieldError('note');
      return field ?? getApiErrorMessage(error) ?? t('board_err_transition');
    }
    case 429:
      return t('rateLimited');
    default:
      return t('unknownError');
  }
}

// The words of a channel the board knows; an unknown one as the API sent it
export const BOARD_CHANNELS = ['telegram', 'mobile', 'pos_hall', 'pos_takeaway', 'aggregator'] as const;

export function channelLabel(t: Translate, channel: string): string {
  return (BOARD_CHANNELS as readonly string[]).includes(channel) ? t(`board_channel_${channel}` as MessageKey) : channel;
}

export const BOARD_TYPES = ['delivery', 'pickup', 'dine_in'] as const;
