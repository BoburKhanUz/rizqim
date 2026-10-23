import type { StaffApi } from './client';
import { unwrap } from './errors';
import { newIdempotencyKey, withIdempotency } from './idempotency';
import type { JsonRequest, OkData } from './types';

// Customer accounts (nasiya) of P3-17 (docs/API.md "Nasiya hisoblari"): the people and groups that eat on credit. The routes
// are the POS ones (/pos/customer-accounts); the web panel and the Mini App use them with a normal owner or manager sign-in.
// Reading needs `customer_accounts.manage` or `pos.take_payment`, opening and editing `customer_accounts.manage`. The debt
// changes only through entries (a credit sale, a repayment at the till, a manager's adjustment): there is no way to set it here.

export type CustomerAccounts = OkData<'customerAccount.index'>;
export type CustomerAccount = CustomerAccounts['accounts'][number];
export type Debtors = OkData<'customerAccount.debtors'>;
export type AccountDetail = OkData<'customerAccount.show'>;
export type AccountEntry = AccountDetail['entries'][number];
export type AccountStatement = OkData<'customerAccount.statement'>;
export type CustomerAccountInput = JsonRequest<'customerAccount.store'>;
export type CustomerAccountPatch = NonNullable<JsonRequest<'customerAccount.update'>>;

export interface CustomerAccountsQuery {
  // Name, group name or phone digits
  q?: string;
  debtors?: boolean;
  per_page?: number;
  page?: number;
}

// GET /pos/customer-accounts (customer_accounts.manage or pos.take_payment): paged search
export async function fetchCustomerAccounts(api: StaffApi, query: CustomerAccountsQuery = {}): Promise<CustomerAccounts> {
  return unwrap(await api.client.GET('/v1/pos/customer-accounts', { params: { query: query as never } })).data;
}

// GET /pos/customer-accounts/debtors: everyone who owes, the biggest debt first, and the total
export async function fetchDebtors(api: StaffApi): Promise<Debtors> {
  return unwrap(await api.client.GET('/v1/pos/customer-accounts/debtors')).data;
}

// GET /pos/customer-accounts/{uuid}: the account and its last 20 entries
export async function fetchCustomerAccount(api: StaffApi, uuid: string): Promise<AccountDetail> {
  return unwrap(await api.client.GET('/v1/pos/customer-accounts/{uuid}', { params: { path: { uuid } } })).data;
}

// GET .../statement: the debt at the start and the end of the range, totals by kind and the entries
export async function fetchAccountStatement(api: StaffApi, uuid: string, range: { from?: string; to?: string } = {}): Promise<AccountStatement> {
  return unwrap(await api.client.GET('/v1/pos/customer-accounts/{uuid}/statement', { params: { path: { uuid }, query: range } })).data;
}

// POST: `credit_limit` null is no limit, 0 is no credit at all. The phone is unique in the store.
export async function createCustomerAccount(api: StaffApi, input: CustomerAccountInput, key: string = newIdempotencyKey()): Promise<CustomerAccount> {
  return unwrap(await api.client.POST('/v1/pos/customer-accounts', { body: input, ...withIdempotency(key) })).data;
}

// PATCH: the credit limit, the block (new credit stops, repayments are still taken), the name, the group, the note
export async function updateCustomerAccount(api: StaffApi, uuid: string, patch: CustomerAccountPatch, key: string = newIdempotencyKey()): Promise<CustomerAccount> {
  return unwrap(await api.client.PATCH('/v1/pos/customer-accounts/{uuid}', { params: { path: { uuid } }, body: patch, ...withIdempotency(key) })).data;
}
