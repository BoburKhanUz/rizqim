import type { Order, OrderStatus } from '../types';
import { localize, type Language } from '../i18n/localize';
import { STAFF_MESSAGES, type StaffMessageKey } from '../i18n/messages';

// How long each stage of an order took, from its status history. A stage whose two timestamps are missing is null.
export interface StageDurations {
  acceptTime: string | null;
  prepTime: string | null;
  readyTime: string | null;
  transitTime: string | null;
  deliveryTime: string | null;
}

// "5m 3s" / "42s": minutes and seconds between two moments, null when either is missing.
export function formatDuration(start?: Date, end?: Date): string | null {
  if (!start || !end) return null;
  const diffMs = end.getTime() - start.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffSecs = Math.floor((diffMs % 60000) / 1000);
  return diffMins > 0 ? `${diffMins}m ${diffSecs}s` : `${diffSecs}s`;
}

export function getStageDurations(order: Pick<Order, 'status_histories'>): StageDurations | null {
  const histories = order.status_histories;
  if (!histories || histories.length === 0) return null;

  // The last time the order entered each status
  const times: Partial<Record<OrderStatus, Date>> = {};
  for (const history of histories) {
    if (history.created_at) times[history.status] = new Date(history.created_at);
  }

  const onTheWay = times.on_the_way || times.delivery;
  return {
    acceptTime: formatDuration(times.new, times.accepted),
    prepTime: formatDuration(times.accepted, times.preparing),
    readyTime: formatDuration(times.preparing, times.ready),
    transitTime: formatDuration(times.ready || times.preparing, onTheWay),
    deliveryTime: formatDuration(onTheWay || times.ready, times.delivered),
  };
}

const STAGE_LABEL_KEYS: ReadonlyArray<[keyof StageDurations, StaffMessageKey]> = [
  ['acceptTime', 'stageAccept'],
  ['prepTime', 'stagePrepare'],
  ['readyTime', 'stageReady'],
  ['transitTime', 'stageHandToCourier'],
  ['deliveryTime', 'stageDelivery'],
];

// The stages that have a time, with their names: [["Qabul qilish", "1m 3s"], ...]
export function stageRows(durations: StageDurations, language: Language | string): Array<[label: string, value: string]> {
  const rows: Array<[string, string]> = [];
  for (const [field, key] of STAGE_LABEL_KEYS) {
    const value = durations[field];
    if (value !== null) rows.push([localize(STAFF_MESSAGES[key], language), value]);
  }
  return rows;
}
