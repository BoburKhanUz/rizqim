import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// The store's balances, the debt ledger, the wallet of online payments and the payout requests (GET /owner/finance).
// `debt_balance` is what the store owes the platform (negative: it paid in advance), `wallet_balance` what the platform
// owes the store.
export type OwnerFinance = OkData<'ownerFinance.index'>;

export async function fetchOwnerFinance(api: StaffApi): Promise<OwnerFinance> {
  const body = unwrap(await api.client.GET('/v1/owner/finance'));
  return body.data;
}

export type TopupInput = JsonRequest<'ownerFinance.initiateTopup'>;
export type TopupProvider = TopupInput['provider'];
export type TopupInitiated = OkData<'ownerFinance.initiateTopup'>;

// POST /owner/finance/top-up: answers the payment page to open (Click, Payme).
export async function initiateTopup(api: StaffApi, input: TopupInput): Promise<TopupInitiated> {
  const body = unwrap(await api.client.POST('/v1/owner/finance/top-up', { body: input }));
  return body.data;
}

export type PayoutInput = JsonRequest<'ownerFinance.requestPayout'>;
export type PayoutResult = OkData<'ownerFinance.requestPayout'>;

// POST /owner/payouts: asks to pay the wallet balance out to a card.
export async function requestPayout(api: StaffApi, input: PayoutInput): Promise<PayoutResult> {
  const body = unwrap(await api.client.POST('/v1/owner/payouts', { body: input }));
  return body.data;
}

export type Subscription = OkData<'ownerBilling.subscription'>;
export type InvoicePage = OkData<'ownerBilling.invoices'>;
export type InvoiceQuery = NonNullable<NonNullable<import('@rizqim/api-client').operations['ownerBilling.invoices']['parameters']['query']>>;

// GET /owner/subscription: the tariff plan and its current period.
export async function fetchSubscription(api: StaffApi): Promise<Subscription> {
  const body = unwrap(await api.client.GET('/v1/owner/subscription'));
  return body.data;
}

// GET /owner/invoices: the subscription invoices, a page at a time.
export async function fetchInvoices(api: StaffApi, query: InvoiceQuery = {}): Promise<InvoicePage> {
  const body = unwrap(await api.client.GET('/v1/owner/invoices', { params: { query } }));
  return body.data;
}
