import { describe, expect, it } from 'vitest';
import {
  ACTION_LABELS,
  actionLabel,
  CANCEL_REASON_LABELS,
  cancelReasonLabel,
  normalizePaymentMethod,
  ORDER_TYPE_LABELS,
  orderTypeLabel,
  PAYMENT_METHOD_LABELS,
  paymentMethodLabel,
  STAFF_MESSAGES,
  staffText,
} from './messages';
import { fillParams, localize } from './localize';

describe('localize', () => {
  it('picks the language, reading Russian where an app has no English', () => {
    expect(localize({ uz: 'Yangi', ru: 'Новый' }, 'uz')).toBe('Yangi');
    expect(localize({ uz: 'Yangi', ru: 'Новый' }, 'ru')).toBe('Новый');
    expect(localize({ uz: 'Yangi', ru: 'Новый' }, 'en')).toBe('Новый');
    expect(localize({ uz: 'Yangi', ru: 'Новый', en: 'New' }, 'en')).toBe('New');
  });

  it('reads Uzbek for a language it does not know', () => {
    expect(localize({ uz: 'Yangi', ru: 'Новый' }, 'de')).toBe('Yangi');
  });

  it('fills {{name}} placeholders', () => {
    expect(fillParams('Faol ({{n}})', { n: 3 })).toBe('Faol (3)');
    expect(fillParams('Faol ({{n}})')).toBe('Faol ({{n}})');
    expect(fillParams('{{a}}-{{b}}', { a: 'x' })).toBe('x-');
  });
});

describe('the staff messages', () => {
  it('has an Uzbek and a Russian text for each key, written in the right script', () => {
    for (const [key, text] of Object.entries(STAFF_MESSAGES)) {
      expect(text.uz.trim(), `${key} uz`).not.toBe('');
      expect(text.ru.trim(), `${key} ru`).not.toBe('');
      expect(text.ru, `${key} ru is Cyrillic`).toMatch(/[А-Яа-яЁё]/);
      expect(text.uz, `${key} uz is Latin`).not.toMatch(/[А-Яа-яЁё]/);
    }
  });

  it('keeps the placeholders of both languages the same', () => {
    const names = (text: string) => [...text.matchAll(/\{\{(\w+)\}\}/g)].map((match) => match[1]).sort();
    for (const [key, text] of Object.entries(STAFF_MESSAGES)) {
      expect(names(text.uz), key).toEqual(names(text.ru));
    }
  });

  it('prints a tab with its count', () => {
    expect(staffText('tabActive', 'uz', { n: 4 })).toBe('Faol (4)');
    expect(staffText('tabActive', 'ru', { n: 4 })).toBe('Активные (4)');
    expect(staffText('tabActive', 'en', { n: 4 })).toBe('Активные (4)');
  });
});

describe('order types and payment methods', () => {
  it('names the order types', () => {
    expect(orderTypeLabel('delivery', 'uz')).toBe('Yetkazish');
    expect(orderTypeLabel('dine_in', 'ru')).toBe('В зале');
    expect(orderTypeLabel('pickup', 'ru')).toBe('Самовывоз');
    expect(orderTypeLabel('pickup', 'en')).toBe('Pickup');
    expect(Object.keys(ORDER_TYPE_LABELS).sort()).toEqual(['delivery', 'dine_in', 'pickup']);
  });

  it('shows an order type it does not know as sent, and nothing for none', () => {
    expect(orderTypeLabel('drone', 'uz')).toBe('drone');
    expect(orderTypeLabel(null, 'uz')).toBe('');
  });

  it('reads the payment method however the API spells it', () => {
    expect(normalizePaymentMethod('CASH')).toBe('cash');
    expect(normalizePaymentMethod(' Click ')).toBe('click');
    expect(normalizePaymentMethod('naqd')).toBe('cash');
    expect(normalizePaymentMethod('terminal')).toBe('card');
    expect(normalizePaymentMethod('barter')).toBeNull();
    expect(normalizePaymentMethod(null)).toBeNull();
  });

  it('names the payment methods in each language and shows an unknown one as sent', () => {
    expect(paymentMethodLabel('cash', 'uz')).toBe('Naqd pul');
    expect(paymentMethodLabel('cash', 'ru')).toBe('Наличные');
    expect(paymentMethodLabel('keshbek', 'en')).toBe('Cashback');
    expect(paymentMethodLabel('barter', 'uz')).toBe('barter');
    expect(paymentMethodLabel(null, 'uz')).toBe('');
    for (const labels of Object.values(PAYMENT_METHOD_LABELS)) expect(labels.uz && labels.ru && labels.en).toBeTruthy();
  });
});

describe('cancel reasons and buttons', () => {
  it('names every cancel reason in each language', () => {
    expect(cancelReasonLabel('out_of_stock', 'uz')).toBe('Mahsulot qolmagan');
    expect(cancelReasonLabel('store_closed', 'ru')).toBe('Магазин закрыт');
    expect(cancelReasonLabel('no_show', 'en')).toBe('Customer did not show up');
    expect(cancelReasonLabel('weather', 'uz')).toBe('weather');
    expect(Object.keys(CANCEL_REASON_LABELS)).toHaveLength(7);
  });

  it('words the buttons, and the courier\'s own ones differently', () => {
    expect(actionLabel('accept', 'uz')).toBe('Qabul qilish');
    expect(actionLabel('accept', 'ru')).toBe('Принять');
    expect(actionLabel('pick_up', 'uz')).toBe("Yo'lga chiqdi");
    expect(actionLabel('pick_up', 'uz', 'courier')).toBe("Olib ketdim (Yo'lga chiqdim)");
    expect(actionLabel('confirm_delivery', 'ru', 'courier')).toBe('Доставил (Завершить)');
    // a role without its own wording uses the shared one
    expect(actionLabel('cancel', 'uz', 'courier')).toBe('Bekor qilish');
    expect(actionLabel('unknown_action', 'uz')).toBe('unknown_action');
    expect(Object.keys(ACTION_LABELS)).toContain('assign_courier');
  });
});
