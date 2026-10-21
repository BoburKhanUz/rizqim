import { isAxiosError } from 'axios';
import { formatMoney } from './format';
import { toAmount } from './money';
import type { ApiErrorBody, DeliveryRefusalReason } from '../types';

// What to tell the customer when the server refuses a delivery quote or an order. The server's own messages are Uzbek and
// fit to show for the domain refusals (`data.reason`, 422 `errors`); what is not (Laravel's English defaults, a
// bare "Server Error", a dropped connection) is replaced here by a clear sentence, never shown raw.

export const OUTSIDE_ZONE_MESSAGE = "Bu manzilga yetkazib berilmaydi. Boshqa manzilni tanlang yoki olib ketishni tanlang.";
export const BRANCH_CLOSED_MESSAGE = "Bu manzilga xizmat ko'rsatadigan filial hozir yopiq. Keyinroq urinib ko'ring yoki olib ketishni tanlang.";

const MENU_CHANGED_MESSAGE = "Savatdagi taomlardan biri menyuda yo'q yoki hozir sotilmaydi. Iltimos, savatni yangilang.";

export function belowMinimumMessage(minOrderAmount: string | number | null | undefined): string {
  return minOrderAmount === null || minOrderAmount === undefined
    ? "Savatdagi summa bu hudud uchun eng kam buyurtma summasidan kam. Yana taom qo'shing."
    : `Bu hududda eng kam buyurtma summasi ${formatMoney(toAmount(minOrderAmount))}. Yana taom qo'shing.`;
}

// The refusal reasons that make a delivery address unusable, with the sentence for each.
export function refusalMessage(reason: string | undefined, minOrderAmount?: string | null): string | null {
  switch (reason as DeliveryRefusalReason | undefined) {
    case 'outside_zone':
    case 'branch_outside_zone':
      return OUTSIDE_ZONE_MESSAGE;
    case 'branch_closed':
      return BRANCH_CLOSED_MESSAGE;
    case 'below_minimum_order':
      return belowMinimumMessage(minOrderAmount);
    default:
      return null;
  }
}

export function isDeliveryRefusalReason(reason: unknown): reason is DeliveryRefusalReason {
  return reason === 'outside_zone' || reason === 'zones_not_configured' || reason === 'branch_closed' || reason === 'branch_outside_zone' || reason === 'below_minimum_order';
}

// The reason of a refusal body (`data.reason`), if it has one.
export function refusalReasonOf(body: ApiErrorBody | undefined): string | undefined {
  const data = body?.data;
  return data && !Array.isArray(data) && typeof data.reason === 'string' ? data.reason : undefined;
}

// Laravel's English defaults and framework messages: not for the customer.
function isGenericEnglish(text: string): boolean {
  return /^(the given data was invalid|server error|unauthenticated|this action is unauthorized|too many attempts|error occurred)/i.test(text) || /^the .+ (field|must|is required)/i.test(text);
}

// The first message of a 422's `errors`, `items*` first (they are the dish-level ones: sold out, modifiers, quantity).
function firstFieldError(body: ApiErrorBody | undefined): string | undefined {
  const errors = body?.errors;
  if (!errors) return undefined;
  const fields = Object.keys(errors).sort((a, b) => Number(b.startsWith('items')) - Number(a.startsWith('items')));
  for (const field of fields) {
    const messages = errors[field];
    const first = Array.isArray(messages) ? messages[0] : messages;
    if (typeof first === 'string' && first.trim() !== '' && !isGenericEnglish(first)) return first;
  }
  return undefined;
}

// Whether the dishes the customer sees may be out of date: the menu and its availability are asked for again.
const MENU_TROUBLE = /tugadi|tugagan|mavjud emas|sotilmaydi|savatni yangilang|menyu|qoldi/i;

export interface OrderFailure {
  message: string;
  // The refusal is about the menu (sold out, stopped, changed): refresh it so the cart shows what is wrong
  refreshMenu: boolean;
  // The refusal is about the delivery address / zone: ask for the quote again
  refreshQuote: boolean;
}

// The sentence for a failed POST /orders.
export function describeOrderFailure(error: unknown): OrderFailure {
  const response = isAxiosError<ApiErrorBody>(error) ? error.response : undefined;
  const failure = (message: string, flags: Partial<Omit<OrderFailure, 'message'>> = {}): OrderFailure => ({
    message,
    refreshMenu: false,
    refreshQuote: false,
    ...flags,
  });

  if (!response) {
    return failure("Internet bilan aloqa yo'q. Buyurtma yuborilganmi-yo'qmi, aniq emas: qaytadan urinib ko'ring, buyurtma ikki marta yaratilmaydi.");
  }

  const body = response.data;
  const reason = refusalReasonOf(body);
  const data = body?.data && !Array.isArray(body.data) ? body.data : undefined;
  const byReason = refusalMessage(reason, data?.min_order_amount);
  if (byReason) return failure(byReason, { refreshQuote: true });

  const { status } = response;
  if (status === 409) return failure("Bu buyurtma hozir yuborilmoqda. Bir necha soniyadan keyin «Buyurtmalarim» bo'limini tekshiring.");
  if (status === 429) return failure("So'rovlar juda ko'p. Bir ozdan keyin qayta urinib ko'ring.");
  if (status >= 500) return failure("Serverda xatolik yuz berdi. Birozdan keyin qayta urinib ko'ring.");

  const fieldError = status === 422 ? firstFieldError(body) : undefined;
  const text = fieldError ?? (typeof body?.message === 'string' && !isGenericEnglish(body.message) ? body.message : undefined);

  if (text && /yetkazib berish hududidan tashqarida/i.test(text)) return failure(OUTSIDE_ZONE_MESSAGE, { refreshQuote: true });
  if (text && /not found or unavailable/i.test(text)) return failure(MENU_CHANGED_MESSAGE, { refreshMenu: true });
  if (text) return failure(text, { refreshMenu: MENU_TROUBLE.test(text) });

  return failure("Buyurtma berishda xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.");
}
