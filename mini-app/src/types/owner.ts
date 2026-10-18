import type { Amount, Decimal } from './api';
import type { PaymentAccountType, StoreStatus } from './store';
import type { ApiUser } from './user';

// The branch an employee works at, as GET /owner/employees embeds it (the invite response carries the whole Branch).
export interface EmployeeBranch {
  id: number;
  name: string;
}

// A manager or courier of the owner's store (GET /owner/employees). `branch` is null for an employee without a
// branch and absent from responses that do not load it (the transfer endpoint, a backend older than the field):
// "Filialsiz" is only right for null.
export type Employee = Omit<ApiUser, 'role' | 'branch'> & {
  role: 'manager' | 'courier';
  branch?: EmployeeBranch | null;
};

// POST /owner/employees/invite
export interface EmployeeInvite {
  employee: Employee;
  invite_link: string;
}

// Mirrors App\Enums\StoreTransactionType.
export type StoreTransactionType = 'commission' | 'topup' | 'settlement' | 'payout' | 'refund' | 'adjustment';

// A row of the store's debt ledger (App\Models\StoreTransaction). A commission adds to the debt (positive
// `amount`), a top-up reduces it (negative); the DECIMAL columns arrive as strings.
export interface LedgerTransaction {
  id: number;
  store_id: number;
  type: StoreTransactionType;
  amount: Decimal;
  balance_before: Decimal;
  balance_after: Decimal;
  reference_id: string | null;
  order_id: number | null;
  payment_id: string | null;
  performed_by: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

// A row of the store's wallet of online payments (App\Models\StoreWalletTransaction, `float` casts).
export interface WalletTransaction {
  id: number;
  store_id: number;
  type: StoreTransactionType;
  amount: number;
  balance_before: number;
  balance_after: number;
  reference_id: string | null;
  order_id: number | null;
  payment_id: string | null;
  performed_by: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

// payout_requests.status
export type PayoutStatus = 'pending' | 'completed' | 'rejected';

// A request to pay the wallet balance out to a card (App\Models\PayoutRequest).
export interface PayoutRequest {
  id: number;
  store_id: number;
  amount: Decimal;
  card_number: string;
  status: PayoutStatus;
  processed_by: number | null;
  processed_at: string | null;
  notes: string | null;
  resolution_notes: string | null;
  created_at: string;
  updated_at: string;
}

// GET /owner/finance. `debt_balance` is what the store owes the platform (negative: it has paid in advance),
// `wallet_balance` what the platform owes the store. The balances are floats today; Amount keeps the page right if
// the API ever sends them as decimal strings like the rest of the money.
export interface OwnerFinance {
  debt_balance: Amount;
  wallet_balance: Amount;
  credit_limit: Amount;
  min_payout_reserve: Amount;
  payment_account_type: PaymentAccountType;
  commission_rate: Amount; // percent
  status: StoreStatus;
  is_order_locked: boolean;
  ledger_transactions: LedgerTransaction[];
  wallet_transactions: WalletTransaction[];
  payout_requests: PayoutRequest[];
}

// POST /owner/finance/top-up
export interface TopupInitiated {
  topup_uuid: string;
  payment_url: string;
}
