import type { OrderReceipt, StoreSeller } from '../types';

// Wave P (receipts): the seller's details and the fiscal receipts a customer is shown (consumer law art. 28-1,
// ZRU-792 art. 23). The server only ever sends an https ofd.soliq.uz link; the app checks it again before opening it.

const TAX_RECEIPT_HOSTS = ['ofd.soliq.uz'];

// An https link on the tax committee's receipt host, with no credentials or port: the only link a receipt button opens.
export function isTaxReceiptUrl(url: string | null | undefined): url is string {
  // No whitespace or control characters anywhere (the server refuses them the same way)
  if (!url || [...url].some((char) => char.trim() === '' || char.charCodeAt(0) < 0x20)) return false;
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return false;
  }
  return parsed.protocol === 'https:' && parsed.username === '' && parsed.password === '' && parsed.port === '' && TAX_RECEIPT_HOSTS.includes(parsed.hostname.toLowerCase());
}

export type ReceiptLabelKey =
  | 'receiptSale'
  | 'receiptRefund'
  | 'receiptPending'
  | 'receiptRefundPending'
  | 'receiptUnavailable'
  | 'receiptRefundUnavailable';

// Whether the receipt is a button (ready, with a tax receipt link) or a line of text saying where it stands.
export function isOpenableReceipt(receipt: OrderReceipt): boolean {
  return receipt.status === 'ready' && isTaxReceiptUrl(receipt.url);
}

export function receiptLabelKey(receipt: OrderReceipt): ReceiptLabelKey {
  const refund = receipt.type === 'refund';
  if (isOpenableReceipt(receipt)) return refund ? 'receiptRefund' : 'receiptSale';
  if (receipt.status === 'pending') return refund ? 'receiptRefundPending' : 'receiptPending';
  return refund ? 'receiptRefundUnavailable' : 'receiptUnavailable';
}

export type SellerField = keyof StoreSeller;

const SELLER_FIELDS: SellerField[] = ['legal_name', 'tin', 'legal_address', 'phone', 'complaint_contact'];

// The filled fields of the seller (a missing or blank one is simply not shown).
export function visibleSellerFields(seller: StoreSeller | null | undefined): Partial<Record<SellerField, string>> {
  const fields: Partial<Record<SellerField, string>> = {};
  if (!seller) return fields;
  for (const field of SELLER_FIELDS) {
    const value = seller[field]?.trim();
    if (value) fields[field] = value;
  }
  return fields;
}

export function hasSellerDetails(seller: StoreSeller | null | undefined): seller is StoreSeller {
  return Object.keys(visibleSellerFields(seller)).length > 0;
}
