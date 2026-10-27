import { formatMoney } from '@rizqim/ui';
import type { PillTone } from '@rizqim/ui';
import type { Translate } from '@/i18n';
import type { OrderSla, SlaStage, StatusGroup } from '@/lib/api/orders';

// Words and tones of the Buyurtmalar screens: the SLA said in plain Uzbek ("12 daqiqa kechikdi"), never by colour alone.

export const money = (value: string | number | null | undefined) => formatMoney(value ?? 0, 'uz');

export const GROUP_LABEL: Record<StatusGroup, Parameters<Translate>[0]> = {
  active: 'orders_group_active',
  new: 'orders_group_new',
  kitchen: 'orders_group_kitchen',
  en_route: 'orders_group_en_route',
  done: 'orders_group_done',
  canceled: 'orders_group_canceled',
};

export const STAGE_LABEL: Record<SlaStage, Parameters<Translate>[0]> = {
  accept: 'orders_stage_accept',
  prepare: 'orders_stage_prepare',
  deliver: 'orders_stage_deliver',
};

export interface SlaLook {
  tone: PillTone;
  text: string;
  state: OrderSla['state'];
}

// The SLA as of `now` (the server's clock: `skewMs` is server − browser). The server decided the stage and its deadlines;
// between two refetches the words keep counting from them.
export function slaLook(sla: OrderSla, t: Translate, now = Date.now(), skewMs = 0): SlaLook {
  const at = now + skewMs;
  const due = Date.parse(sla.due_at);
  const warning = Date.parse(sla.warning_at);
  const stage = t(STAGE_LABEL[sla.stage]);
  if (Number.isFinite(due) && at >= due) {
    const late = Math.floor((at - due) / 60_000);
    return { tone: 'danger', state: 'late', text: late > 0 ? t('orders_slaLateText', { stage, minutes: late }) : t('orders_slaLateNow', { stage }) };
  }
  const left = Number.isFinite(due) ? Math.max(1, Math.ceil((due - at) / 60_000)) : sla.minutes_left;
  if (Number.isFinite(warning) && at >= warning) return { tone: 'warning', state: 'warning', text: t('orders_slaWarningText', { stage, minutes: left }) };
  return { tone: 'success', state: 'ok', text: t('orders_slaOk', { stage, minutes: left }) };
}

// Server minus browser clock, from the list's server_time (0 when unknown)
export function clockSkew(serverTime: string | null | undefined, receivedAt: number): number {
  const server = serverTime ? Date.parse(serverTime) : NaN;
  return Number.isFinite(server) ? server - receivedAt : 0;
}

export function percent(rate: number | null): string | null {
  return rate === null ? null : String(Math.round(rate * 1000) / 10);
}

// A decimal amount typed by a person ("50 000", "50000,5") as the API takes it ("50000.5"), or null
export function parseAmountInput(input: string): string | null {
  const text = input.replace(/[\s ]/g, '').replace(',', '.');
  if (!/^\d{1,10}(\.\d{1,2})?$/.test(text)) return null;
  return Number(text) > 0 ? text : null;
}
