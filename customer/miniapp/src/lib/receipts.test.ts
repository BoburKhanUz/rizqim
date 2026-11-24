import { describe, expect, it } from 'vitest';
import { hasSellerDetails, isTaxReceiptUrl, receiptLabelKey, visibleSellerFields } from './receipts';
import type { OrderReceipt } from '../types';

const receipt = (overrides: Partial<OrderReceipt> = {}): OrderReceipt => ({
  uuid: 'r-1',
  type: 'sale',
  status: 'ready',
  provider: 'payme',
  url: 'https://ofd.soliq.uz/epi?t=EP1&r=1',
  issued_at: '2026-11-24T12:00:00+05:00',
  ...overrides,
});

describe('isTaxReceiptUrl', () => {
  it('takes only an https link on ofd.soliq.uz', () => {
    expect(isTaxReceiptUrl('https://ofd.soliq.uz/epi?t=EP1&r=1')).toBe(true);
    expect(isTaxReceiptUrl('https://OFD.soliq.uz/check?r=1')).toBe(true);
    for (const url of [
      'http://ofd.soliq.uz/epi',
      'https://ofd.soliq.uz:8443/epi',
      'https://user:pass@ofd.soliq.uz/epi',
      'https://ofd.soliq.uz.evil.example/epi',
      'https://evil.example/?u=https://ofd.soliq.uz',
      'javascript:alert(1)',
      'not a url',
      '',
      null,
    ]) {
      expect(isTaxReceiptUrl(url), String(url)).toBe(false);
    }
  });
});

describe('receiptLabelKey', () => {
  it('names the button or the state of each receipt', () => {
    expect(receiptLabelKey(receipt())).toBe('receiptSale');
    expect(receiptLabelKey(receipt({ type: 'refund' }))).toBe('receiptRefund');
    expect(receiptLabelKey(receipt({ status: 'pending', url: null }))).toBe('receiptPending');
    expect(receiptLabelKey(receipt({ type: 'refund', status: 'pending', url: null }))).toBe('receiptRefundPending');
    expect(receiptLabelKey(receipt({ status: 'unavailable', url: null }))).toBe('receiptUnavailable');
    expect(receiptLabelKey(receipt({ type: 'refund', status: 'unavailable', url: null }))).toBe('receiptRefundUnavailable');
    // A "ready" receipt whose link is not a tax receipt link is not offered as a button
    expect(receiptLabelKey(receipt({ url: 'https://evil.example/r' }))).toBe('receiptUnavailable');
  });
});

describe('seller fields', () => {
  it('lists only the fields that are filled', () => {
    const seller = { legal_name: '"Lazzat" MChJ', tin: '305123456', legal_address: '  ', phone: null, complaint_contact: '@lazzat' };
    expect(visibleSellerFields(seller)).toEqual({ legal_name: '"Lazzat" MChJ', tin: '305123456', complaint_contact: '@lazzat' });
    expect(hasSellerDetails(seller)).toBe(true);
    expect(hasSellerDetails({ legal_name: null, tin: null, legal_address: null, phone: null, complaint_contact: null })).toBe(false);
    expect(hasSellerDetails(null)).toBe(false);
    expect(hasSellerDetails(undefined)).toBe(false);
  });
});
