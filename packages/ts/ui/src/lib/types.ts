// Interface languages of the Rizqim apps.
export type Language = 'uz' | 'ru' | 'en';

// A DECIMAL column the API serialises as a string ("15000.00"), or a number the app has computed. `+` on two strings
// concatenates: read one with toAmount() before any arithmetic or formatting.
export type Amount = string | number;

// Mirrors App\Enums\OrderStatus.
export type OrderStatus =
  | 'new'
  | 'accepted'
  | 'preparing'
  | 'ready'
  | 'courier_assigned'
  | 'on_the_way'
  | 'delivery' // legacy alias of on_the_way
  | 'delivered'
  | 'canceled'
  | 'rejected'
  | 'refunded';
