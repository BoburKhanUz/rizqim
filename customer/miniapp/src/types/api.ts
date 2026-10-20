// Envelope of the JSON responses of this API (App\Traits\ApiResponse).
// A few legacy endpoints answer `{ error: string }` for a 403 instead; those are read from the failed request.
export interface ApiResponse<T, M = undefined> {
  success: boolean;
  message?: string;
  data: T;
  meta?: M; // listings that are scoped or paginated describe themselves here (GET /orders?scope=...)
}

// Body of a failed request, as far as the client reads it (Laravel validation errors are 422 `errors.<field>`).
export interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[] | string | undefined>;
}

// Laravel paginator metadata as returned by GET /notifications.
export interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

// A DECIMAL column or `decimal:2` cast, which Laravel serialises as a string ("15000.00"). `+` on two of them
// concatenates, so read one with toAmount() (lib/money.ts) before any arithmetic or formatting.
export type Decimal = string;

// What the money helpers accept: a Decimal from the API, or a number the app has computed. Values the API computes
// in PHP (sums, float accessors such as Store.min_order) arrive as numbers.
export type Amount = Decimal | number;
