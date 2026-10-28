import type { MessageKey } from '@/i18n';
import type { AdjustmentFilter, AdjustmentKind, BalanceFilter, PayoutFilter, TopupStatus } from '@/lib/api/finance';
import { exactMoney, parseAmountInput } from '../orders/ordersModel';

// Words of the Moliya screens. Every amount is shown exactly (with the tiyin when there are any): money moves here.
export { exactMoney as uzs, parseAmountInput };

export const FINANCE_TABS = ['payouts', 'topups', 'settlements', 'balances', 'adjustments', 'report'] as const;
export type FinanceTab = (typeof FINANCE_TABS)[number];

export const TAB_LABEL: Record<FinanceTab, MessageKey> = {
  payouts: 'finance_tab_payouts',
  topups: 'finance_tab_topups',
  settlements: 'finance_tab_settlements',
  balances: 'finance_tab_balances',
  adjustments: 'finance_tab_adjustments',
  report: 'finance_tab_report',
};

export const PAYOUT_FILTER_LABEL: Record<PayoutFilter, MessageKey> = {
  open: 'finance_payout_open',
  pending: 'finance_payout_pending',
  awaiting_second: 'finance_payout_awaiting_second',
  completed: 'finance_payout_completed',
  rejected: 'finance_payout_rejected',
  all: 'finance_payout_all',
};

export const TOPUP_STATUS_LABEL: Record<TopupStatus, MessageKey> = {
  all: 'finance_topup_all',
  pending: 'finance_topup_pending',
  paid: 'finance_topup_paid',
  cancelled: 'finance_topup_cancelled',
};

export const BALANCE_FILTER_LABEL: Record<BalanceFilter, MessageKey> = {
  all: 'finance_balance_all',
  over_limit: 'finance_balance_over_limit',
  debt: 'finance_balance_debt',
  subscription_debt: 'finance_balance_subscription_debt',
  wallet: 'finance_balance_wallet',
};

export const ADJUSTMENT_FILTER_LABEL: Record<AdjustmentFilter, MessageKey> = {
  all: 'finance_adjustment_all',
  pending: 'finance_adjustment_pending',
  applied: 'finance_adjustment_applied',
  rejected: 'finance_adjustment_rejected',
};

export const KIND_LABEL: Record<AdjustmentKind, MessageKey> = {
  debt_charge: 'finance_kind_debt_charge',
  debt_credit: 'finance_kind_debt_credit',
  debt_payment: 'finance_kind_debt_payment',
  wallet_credit: 'finance_kind_wallet_credit',
  wallet_debit: 'finance_kind_wallet_debit',
};

const LEDGER_TYPES = ['commission', 'topup', 'settlement', 'payout', 'refund', 'adjustment', 'subscription'] as const;
export function ledgerTypeLabel(type: string): MessageKey | null {
  return (LEDGER_TYPES as readonly string[]).includes(type) ? (`finance_type_${type}` as MessageKey) : null;
}

// Whether an amount (a decimal string) reaches the four-eyes threshold
export function reachesThreshold(amount: string | null, threshold: string | undefined): boolean {
  if (amount === null || !threshold) return false;
  return Number(amount) >= Number(threshold);
}

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export function payoutTone(status: string): Tone {
  if (status === 'completed') return 'success';
  if (status === 'rejected') return 'neutral';
  if (status === 'awaiting_second') return 'info';
  return 'warning';
}

export function adjustmentTone(status: string): Tone {
  if (status === 'applied') return 'success';
  if (status === 'rejected') return 'neutral';
  return 'warning';
}

// A signed amount: "+150 000 so'm" / "−50 000 so'm"
export function signed(value: string): string {
  const negative = value.trim().startsWith('-');
  return `${negative ? '−' : '+'}${exactMoney(negative ? value.trim().slice(1) : value)}`;
}

export const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;
