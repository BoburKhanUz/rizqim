// A request the server refused or could not answer. Every function of the API layer throws this for a non-2xx answer
// (a network failure stays the fetch's own TypeError), so screens and hooks have one error type to read:
//   err.status           HTTP status (0: never reached the server is a TypeError instead, not this)
//   err.message          the server's own `message` (Uzbek), else its legacy `error`, else "HTTP 422"
//   err.fieldError('x')  the first validation message of field x (422 `errors.x`)
//   err.reason           `data.reason` of a domain refusal ("outside_zone", ...)
export class StaffApiError extends Error {
  readonly status: number;
  readonly body: unknown;
  readonly errors: Record<string, string[] | string | undefined> | undefined;
  readonly reason: string | undefined;

  constructor(status: number, body: unknown) {
    super(messageOf(body) ?? `HTTP ${status}`);
    this.name = 'StaffApiError';
    this.status = status;
    this.body = body;
    const record = isRecord(body) ? body : {};
    this.errors = isRecord(record.errors) ? (record.errors as Record<string, string[] | string | undefined>) : undefined;
    const data = record.data;
    this.reason = isRecord(data) && typeof data.reason === 'string' ? data.reason : undefined;
  }

  // The server said something in `message` (not just a status line)
  get hasServerMessage(): boolean {
    return messageOf(this.body) !== undefined;
  }

  // First validation message (422 `errors.<field>`) for a field.
  fieldError(field: string): string | undefined {
    if (this.status !== 422) return undefined;
    const messages = this.errors?.[field];
    const first = Array.isArray(messages) ? messages[0] : messages;
    return typeof first === 'string' && first.trim() !== '' ? first : undefined;
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

// `message` of an error body; a few legacy endpoints answer `{ error: "Unauthorized" }` instead.
function messageOf(body: unknown): string | undefined {
  if (!isRecord(body)) return typeof body === 'string' && body.trim() !== '' && body.length < 300 && !body.trimStart().startsWith('<') ? body : undefined;
  if (typeof body.message === 'string' && body.message.trim() !== '') return body.message;
  if (typeof body.error === 'string' && body.error.trim() !== '') return body.error;
  return undefined;
}

export const isStaffApiError = (err: unknown): err is StaffApiError => err instanceof StaffApiError;

// The server's `message` of a failed request, if there is one.
export function getApiErrorMessage(err: unknown): string | undefined {
  return isStaffApiError(err) && err.hasServerMessage ? err.message : undefined;
}

// First validation message for a field, if there is one.
export function getApiFieldError(err: unknown, field: string): string | undefined {
  return isStaffApiError(err) ? err.fieldError(field) : undefined;
}

export const getApiErrorStatus = (err: unknown): number | undefined => (isStaffApiError(err) ? err.status : undefined);

// The shape of an openapi-fetch answer that the helper below reads.
interface FetchResult<T> {
  data?: T;
  error?: unknown;
  response: Response;
}

// The data of a successful answer; a refused one throws a StaffApiError carrying the body.
export function unwrap<T>(result: FetchResult<T>): T {
  if (!result.response.ok) throw new StaffApiError(result.response.status, result.error);
  return result.data as T;
}
