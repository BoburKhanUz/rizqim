import { describe, expect, it } from 'vitest';
import { failedRequest } from '../test/httpErrors';
import {
  getCancelErrorMessage,
  getCustomerCancelReasonLabel,
  getStaffCancelReasonLabel,
  getStaffCancelReasons,
  isCancellableByCustomer,
} from './cancelReasons';

describe('labels', () => {
  it.each([
    ['customer_request', "Mijoz so'rovi"],
    ['no_show', 'Mijoz kelmadi'],
    ['out_of_stock', 'Mahsulot qolmagan'],
    ['store_closed', "Do'kon yopiq"],
    ['courier_unavailable', "Kuryer yo'q"],
    ['payment_timeout', "To'lov muddati o'tdi"],
    ['other', 'Boshqa sabab'],
  ])('staff read %s as "%s"', (reason, label) => {
    expect(getStaffCancelReasonLabel(reason, 'uz')).toBe(label);
  });

  it.each([
    ['customer_request', "Sizning so'rovingizga ko'ra"],
    ['no_show', 'Buyurtma olib ketilmadi'],
    ['out_of_stock', 'Mahsulot qolmagan'],
    ['store_closed', "Do'kon hozir ishlamayapti"],
    ['courier_unavailable', "Bo'sh kuryer topilmadi"],
    ['payment_timeout', "To'lov belgilangan vaqtda amalga oshirilmadi"],
    ['other', 'Boshqa sabab'],
  ])('customers read %s as "%s"', (reason, label) => {
    expect(getCustomerCancelReasonLabel(reason, 'uz')).toBe(label);
  });

  it('translates them, and falls back to Uzbek for a language it does not know', () => {
    expect(getCustomerCancelReasonLabel('no_show', 'ru')).toBe('Заказ не был забран');
    expect(getCustomerCancelReasonLabel('no_show', 'en')).toBe('The order was not picked up');
    expect(getCustomerCancelReasonLabel('no_show', 'de')).toBe('Buyurtma olib ketilmadi');
  });

  it('shows a reason it has never heard of as it came', () => {
    expect(getCustomerCancelReasonLabel('weather', 'uz')).toBe('weather');
    expect(getStaffCancelReasonLabel('toString', 'uz')).toBe('toString');
  });
});

describe('getStaffCancelReasons', () => {
  const reasonsOf = (status: string, order_type: string) =>
    getStaffCancelReasons({ status, order_type } as Parameters<typeof getStaffCancelReasons>[0]);

  it('never offers the system-only payment_timeout', () => {
    expect(reasonsOf('new', 'delivery')).not.toContain('payment_timeout');
  });

  it.each(['accepted', 'preparing'])('offers no_show for a %s pickup order', (status) => {
    expect(reasonsOf(status, 'pickup')).toContain('no_show');
  });

  it.each([
    ['new', 'pickup'],
    ['ready', 'pickup'],
    ['accepted', 'delivery'],
    ['preparing', 'dine_in'],
  ])('offers no_show for nothing else: %s %s', (status, type) => {
    expect(reasonsOf(status, type)).not.toContain('no_show');
  });

  it('keeps the other reasons in their order', () => {
    expect(reasonsOf('accepted', 'pickup')).toEqual([
      'customer_request',
      'no_show',
      'out_of_stock',
      'store_closed',
      'courier_unavailable',
      'other',
    ]);
  });
});

describe('isCancellableByCustomer', () => {
  it.each([
    ['new', 'pending', true],
    ['new', 'failed', true],
    ['new', 'expired', true],
    ['new', 'paid', false],
    ['new', 'refunded', false],
    ['accepted', 'pending', false],
    ['canceled', 'pending', false],
    ['delivered', 'paid', false],
  ])('%s + %s payment: %s', (status, payment_status, expected) => {
    expect(isCancellableByCustomer({ status, payment_status } as Parameters<typeof isCancellableByCustomer>[0])).toBe(expected);
  });
});

describe('getCancelErrorMessage', () => {
  it('speaks plainly about the throttle', () => {
    expect(getCancelErrorMessage(failedRequest(429, { message: 'Too Many Attempts.' }), 'uz')).toBe("Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.");
    expect(getCancelErrorMessage(failedRequest(429, {}), 'ru')).toBe('Слишком много попыток. Повторите чуть позже.');
  });

  it('passes on the server message of a 422', () => {
    const err = failedRequest(422, { message: "Buyurtma allaqachon qabul qilingan.", errors: {} });
    expect(getCancelErrorMessage(err, 'uz')).toBe('Buyurtma allaqachon qabul qilingan.');
  });

  it('prefers the message of the field that failed', () => {
    const err = failedRequest(422, { message: 'The given data was invalid.', errors: { reason: ['Bekor qilish sababini tanlang.'] } });
    expect(getCancelErrorMessage(err, 'uz')).toBe('Bekor qilish sababini tanlang.');
    const noteErr = failedRequest(422, { message: 'invalid', errors: { note: ["Izoh ko'pi bilan 500 belgi bo'lishi mumkin."] } });
    expect(getCancelErrorMessage(noteErr, 'uz')).toBe("Izoh ko'pi bilan 500 belgi bo'lishi mumkin.");
  });

  it('falls back to a generic sentence when there is nothing to say', () => {
    expect(getCancelErrorMessage(failedRequest(undefined), 'en')).toBe('Could not cancel the order. Please try again.');
    expect(getCancelErrorMessage(new Error('x'), 'uz')).toBe("Buyurtmani bekor qilib bo'lmadi. Qayta urinib ko'ring.");
  });
});
