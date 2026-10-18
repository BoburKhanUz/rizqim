// Envelope of the JSON responses of this API (App\Traits\ApiResponse).
// A few legacy endpoints answer `{ error: string }` for a 403 instead; those are read from the failed request.
export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
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

// Laravel serializes DECIMAL columns as strings ("12000.00") but casted/computed values as numbers.
export type Decimal = string | number;
