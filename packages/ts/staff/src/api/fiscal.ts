import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { Product } from '../types';
import type { ProductFiscalInput } from './menu';
import { productEditorFormData } from './menuAdmin';
import type { JsonRequest, OkData } from './types';

// Fiscal receipts and the fiscal setup (P3-11, P3-14; docs/API.md "Fiskal cheklar", "Onlayn to'lov fiskal cheki"). The
// server decides what a receipt holds, whether a product may be sold and what a problem code means for the payment; these
// functions only call the endpoints.

// --- Codes -----------------------------------------------------------------------------------------------------------------

// MXIK: 17 digits (tasnif.soliq.uz). Package code: digits only, up to 20. The server checks both again.
export const MXIK_PATTERN = /^\d{17}$/;
export const PACKAGE_CODE_PATTERN = /^\d{1,20}$/;
export const isMxik = (value: string): boolean => MXIK_PATTERN.test(value);
export const isPackageCode = (value: string): boolean => PACKAGE_CODE_PATTERN.test(value);

// The services a receipt carries that are not on the menu (stores.fiscal_settings.charges)
export const FISCAL_CHARGES = ['room_fee', 'place_fee', 'min_spend_topup', 'service_charge', 'deposit', 'booking_fee', 'credit', 'delivery'] as const;
export type FiscalChargeKey = (typeof FISCAL_CHARGES)[number];

export const TAX_REGIMES = ['vat', 'vat_simplified', 'turnover'] as const;
export type TaxRegime = (typeof TAX_REGIMES)[number];

// The drivers the receipts can go through. Neither is a real tax-office connection yet (the real one is P3-32).
export const FISCAL_DRIVERS = ['sandbox', 'null'] as const;
export type FiscalDriver = (typeof FISCAL_DRIVERS)[number];

export const FISCAL_RECEIPT_STATUSES = ['pending', 'issued', 'failed'] as const;
export const FISCAL_RECEIPT_TYPES = ['sale', 'refund', 'advance', 'credit'] as const;
export const FISCAL_PAYMENT_FORMS = ['cash', 'card', 'qr', 'mixed'] as const;

// `fiscal.status` of an online payment (GET /owner/payments) and the problems the owner has to fix (docs/API.md P3-14)
export const ONLINE_FISCAL_STATUSES = ['blocked', 'pending', 'failed', 'recorded'] as const;
export type OnlineFiscalStatus = (typeof ONLINE_FISCAL_STATUSES)[number];
export const ONLINE_FISCAL_PROBLEMS = [
  'mxik_missing',
  'package_code_missing',
  'store_tin_missing',
  'charge_mxik_missing',
  'delivery_mxik_missing',
  'items_missing',
  'sum_mismatch',
  'fiscal_unavailable',
  'payme_platform_cashbox',
  'payme_ofd_rejected',
  'click_credentials_missing',
  'click_submit_failed',
  'click_qr_missing',
] as const;
export type OnlineFiscalProblem = (typeof ONLINE_FISCAL_PROBLEMS)[number];
export const isOnlineFiscalProblem = (code: string): code is OnlineFiscalProblem => (ONLINE_FISCAL_PROBLEMS as readonly string[]).includes(code);

// --- Readiness ("Fiskal tayyorlik") ------------------------------------------------------------------------------------------

// GET /owner/fiscal/readiness (menu.view or settings.manage): the products without a valid MXIK, the charges without a
// code, whether the TIN is set.
export type FiscalReadiness = OkData<'fiscalSettings.readiness'>;
export type ProductMissingMxik = FiscalReadiness['products_missing_mxik'][number];

export async function fetchFiscalReadiness(api: StaffApi): Promise<FiscalReadiness> {
  return unwrap(await api.client.GET('/v1/owner/fiscal/readiness')).data;
}

// A product update must carry the whole product (POST /owner/products/{id} sets the name, category, status and variants
// from the request; a variant left out is deleted), so a change of fiscal fields only goes out as the product as it is
// plus the new fiscal values. The picture, the variants' stock and everything not sent stay.
export function productFiscalFormData(product: Product, fiscal: ProductFiscalInput): FormData {
  const number = (value: string | null | undefined) => (value === null || value === undefined ? null : Number(value));
  return productEditorFormData({
    name: product.name,
    description: product.description,
    category_id: product.category_id,
    is_active: product.is_active,
    batch_tracked: product.batch_tracked,
    sort_order: product.sort_order,
    variants: (product.variants ?? []).map((variant) => ({
      id: variant.id,
      name: variant.name,
      price: Number(variant.price),
      unit: variant.unit,
      quantity_step: number(variant.quantity_step),
      min_quantity: number(variant.min_quantity),
      batch_consumption: Number(variant.batch_consumption),
      unit_label: variant.unit_label,
    })),
    ...fiscal,
  });
}

// --- Settings ----------------------------------------------------------------------------------------------------------------

// GET /owner/fiscal/settings (settings.manage, not from a terminal): TIN, tax regime, the fiscal settings with their defaults,
// the drivers and regimes the server knows.
export type FiscalSettingsView = OkData<'fiscalSettings.show'>;
export type FiscalSettings = FiscalSettingsView['fiscal_settings'];
export type FiscalCharge = FiscalSettings['charges'][FiscalChargeKey];
export type FiscalSettingsInput = JsonRequest<'fiscalSettings.update'>;

export async function fetchFiscalSettings(api: StaffApi): Promise<FiscalSettingsView> {
  return unwrap(await api.client.GET('/v1/owner/fiscal/settings')).data;
}

// PUT: partial (what is not sent stays). A charge field sent as null goes back to the default. 422 `errors.tin` etc.
export async function saveFiscalSettings(api: StaffApi, input: FiscalSettingsInput): Promise<FiscalSettingsView> {
  return unwrap(await api.client.PUT('/v1/owner/fiscal/settings', { body: input })).data;
}

// --- Receipts ----------------------------------------------------------------------------------------------------------------

export type FiscalReceiptsPage = OkData<'fiscalReceipt.index'>;
export type FiscalReceipt = FiscalReceiptsPage['data'][number];

export interface FiscalReceiptsQuery {
  status?: (typeof FISCAL_RECEIPT_STATUSES)[number];
  type?: (typeof FISCAL_RECEIPT_TYPES)[number];
  payment_form?: (typeof FISCAL_PAYMENT_FORMS)[number];
  branch_id?: number;
  subject_type?: string;
  subject_id?: number;
  // Days of the store's timezone (YYYY-MM-DD)
  from?: string;
  to?: string;
  page?: number;
  per_page?: number;
}

// GET /owner/fiscal/receipts (finance.view or reports.view): newest first, Laravel pages
export async function fetchFiscalReceipts(api: StaffApi, query: FiscalReceiptsQuery = {}): Promise<FiscalReceiptsPage> {
  return unwrap(await api.client.GET('/v1/owner/fiscal/receipts', { params: { query } })).data;
}

export async function fetchFiscalReceipt(api: StaffApi, uuid: string): Promise<FiscalReceipt> {
  return unwrap(await api.client.GET('/v1/owner/fiscal/receipts/{uuid}', { params: { path: { uuid } } })).data;
}

// POST /owner/fiscal/receipts/{uuid}/retry (settings.manage): a pending or failed receipt goes out again at once.
// 422 `already_issued` for an issued one (or a Payme/Click receipt).
export async function retryFiscalReceipt(api: StaffApi, uuid: string): Promise<FiscalReceipt> {
  return unwrap(await api.client.POST('/v1/owner/fiscal/receipts/{uuid}/retry', { params: { path: { uuid } } })).data;
}
