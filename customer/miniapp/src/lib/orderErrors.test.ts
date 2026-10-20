import { AxiosError, type AxiosResponse } from 'axios';
import { describe, expect, it } from 'vitest';
import { describeOrderFailure } from './orderErrors';

function failure(status: number, body: unknown): AxiosError {
  const response = { status, data: body, statusText: '', headers: {}, config: {} } as AxiosResponse;
  return new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, response);
}

describe('describeOrderFailure', () => {
  it('says there is no connection, and that a retry cannot double the order', () => {
    const result = describeOrderFailure(new AxiosError('Network Error'));
    expect(result.message).toContain("Internet bilan aloqa yo'q");
    expect(result.message).toContain('ikki marta yaratilmaydi');
  });

  it('turns outside_zone into "bu manzilga yetkazib berilmaydi"', () => {
    const result = describeOrderFailure(failure(422, { success: false, message: 'x', data: { reason: 'outside_zone' } }));
    expect(result.message).toMatch(/^Bu manzilga yetkazib berilmaydi/);
    expect(result.refreshQuote).toBe(true);
  });

  it('also reads the old radius refusal (400) of a store without zones', () => {
    const result = describeOrderFailure(failure(400, { success: false, message: 'Uzr, siz kiritgan manzil ushbu restoranning yetkazib berish hududidan tashqarida joylashgan.', data: [] }));
    expect(result.message).toMatch(/^Bu manzilga yetkazib berilmaydi/);
  });

  it('turns below_minimum_order into the zone\'s minimum in so\'m', () => {
    const result = describeOrderFailure(failure(422, { success: false, message: 'x', data: { reason: 'below_minimum_order', min_order_amount: '30000.00', subtotal: '20000.00' } }));
    expect(result.message).toContain("30\u00a0000\u00a0so'm");
    expect(result.message).toContain('eng kam buyurtma summasi');
  });

  it('turns branch_closed and branch_outside_zone into friendly sentences', () => {
    expect(describeOrderFailure(failure(422, { data: { reason: 'branch_closed' } })).message).toContain('filial hozir yopiq');
    expect(describeOrderFailure(failure(422, { data: { reason: 'branch_outside_zone' } })).message).toMatch(/^Bu manzilga yetkazib berilmaydi/);
  });

  it('shows the server\'s own sold-out sentence instead of the raw 422, and asks for the menu again', () => {
    const result = describeOrderFailure(
      failure(422, {
        message: 'The given data was invalid.',
        errors: { items: ["Uzr, 'Burger (Katta)' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang."] },
      }),
    );
    expect(result.message).toBe("Uzr, 'Burger (Katta)' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang.");
    expect(result.refreshMenu).toBe(true);
  });

  it('shows the required-modifier sentence', () => {
    const result = describeOrderFailure(failure(422, { message: 'x', errors: { 'items.0.modifiers': ["'Burger (Katta)': 'Sous' tanlovi majburiy."] } }));
    expect(result.message).toContain('tanlovi majburiy');
  });

  it('never shows Laravel\'s English defaults', () => {
    expect(describeOrderFailure(failure(422, { message: 'The given data was invalid.' })).message).toMatch(/Buyurtma berishda xatolik/);
    expect(describeOrderFailure(failure(500, { message: 'Server Error' })).message).toMatch(/Serverda xatolik/);
    expect(describeOrderFailure(failure(400, { message: 'Product variant not found or unavailable.' })).message).toMatch(/menyuda yo'q/);
  });

  it('explains a 409 and a 429', () => {
    expect(describeOrderFailure(failure(409, { message: 'x' })).message).toContain('yuborilmoqda');
    expect(describeOrderFailure(failure(429, { message: 'Too Many Attempts.' })).message).toContain("So'rovlar juda ko'p");
  });

  it('keeps an Uzbek server message it has no special case for', () => {
    expect(describeOrderFailure(failure(400, { message: "Eng kam buyurtma summasi: 30 000 UZS." })).message).toBe('Eng kam buyurtma summasi: 30 000 UZS.');
  });
});
