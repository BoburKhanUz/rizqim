// The admin API caller. Every call goes to this app's own proxy (/api/backend/..., app/api/backend), which holds the
// session in an httpOnly cookie. Things every call shares: the server's own words for a refusal (ApiError), one retry
// after a step-up confirmation (403 reauth_required), the signal "the session is over" (401) and the time of the last
// call (the backend ends a session after 60 minutes without one: the idle warning counts from here).

export const BACKEND_PREFIX = '/api/backend/v1/admin';

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    // The backend's machine-readable reason ("reauth_required", "challenge_expired", ...), empty when it sent none
    readonly code: string = '',
    // Field → messages of a 422
    readonly fields: Record<string, string[]> = {},
    // Seconds, from Retry-After (429 lockouts)
    readonly retryAfter: number | null = null,
  ) {
    super(message);
    this.name = 'ApiError';
  }

  get unreachable(): boolean {
    return this.status === 0;
  }
}

export const REAUTH_REQUIRED = 'reauth_required';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null && !Array.isArray(value);

function toFields(value: unknown): Record<string, string[]> {
  if (!isRecord(value)) return {};
  const fields: Record<string, string[]> = {};
  for (const [name, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) fields[name] = messages.filter((m): m is string => typeof m === 'string');
    else if (typeof messages === 'string') fields[name] = [messages];
  }
  return fields;
}

// `code` may be a top-level key ({code: "reauth_required"}) or the backend's usual data.reason / data.code
export function errorFrom(status: number, body: unknown, retryAfter: string | null): ApiError {
  const record = isRecord(body) ? body : {};
  const data = isRecord(record.data) ? record.data : {};
  const code = [record.code, data.code, data.reason, record.reason].find((value): value is string => typeof value === 'string' && value !== '') ?? '';
  const message = typeof record.message === 'string' ? record.message : '';
  // The lockout says how long in the header and in data.retry_after
  const seconds = retryAfter ? Number(retryAfter) : typeof data.retry_after === 'number' ? data.retry_after : NaN;
  return new ApiError(message, status, code, toFields(record.errors ?? data.errors), Number.isFinite(seconds) ? seconds : null);
}

// --- Last call and "session over" signals --------------------------------------------------------------------------------

let lastCallAt = Date.now();
const activityListeners = new Set<() => void>();
const endedListeners = new Set<() => void>();

export const getLastCallAt = (): number => lastCallAt;

export function markActive(at = Date.now()): void {
  lastCallAt = at;
  activityListeners.forEach((listener) => listener());
}

export function subscribeActivity(listener: () => void): () => void {
  activityListeners.add(listener);
  return () => activityListeners.delete(listener);
}

// Called when a call finds the session over (401 on a call that needs one)
export function subscribeSessionEnded(listener: () => void): () => void {
  endedListeners.add(listener);
  return () => endedListeners.delete(listener);
}

// --- Step-up ---------------------------------------------------------------------------------------------------------------

type StepUp = () => Promise<boolean>;
let stepUp: StepUp | null = null;

// The step-up dialog registers itself here; resolves true when the person confirmed with a code
export function setStepUpHandler(handler: StepUp | null): void {
  stepUp = handler;
}

// --- Calls -----------------------------------------------------------------------------------------------------------------

export interface CallOptions {
  query?: Record<string, string | number | undefined | null>;
  body?: unknown;
  signal?: AbortSignal;
  // A call made without a session (sign-in steps): a 401 is a wrong credential, not an ended session
  anonymous?: boolean;
  // Extra request headers (P4-14: Idempotency-Key on money actions)
  headers?: Record<string, string>;
}

export interface Envelope<T> {
  data: T;
  meta: Record<string, unknown>;
}

function urlOf(path: string, query?: CallOptions['query']): string {
  const url = `${BACKEND_PREFIX}${path}`;
  if (!query) return url;
  const params = new URLSearchParams();
  for (const [name, value] of Object.entries(query)) if (value !== undefined && value !== null && value !== '') params.set(name, String(value));
  const text = params.toString();
  return text ? `${url}?${text}` : url;
}

async function send(method: string, path: string, options: CallOptions, retried: boolean): Promise<Envelope<unknown>> {
  let response: Response;
  // P4-23: a FormData body (a file with its fields) goes as multipart; the browser sets its Content-Type with the boundary
  const form = typeof FormData !== 'undefined' && options.body instanceof FormData;
  try {
    response = await fetch(urlOf(path, options.query), {
      method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json', ...(options.body !== undefined && !form ? { 'Content-Type': 'application/json' } : {}), ...options.headers },
      body: options.body === undefined ? undefined : form ? (options.body as FormData) : JSON.stringify(options.body),
      signal: options.signal,
    });
  } catch (failure) {
    if (failure instanceof DOMException && failure.name === 'AbortError') throw failure;
    throw new ApiError('', 0, 'unreachable');
  }
  if (!options.anonymous) markActive();

  const text = await response.text().catch(() => '');
  let body: unknown = null;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = null;
  }

  if (!response.ok) {
    const error = errorFrom(response.status, body, response.headers.get('Retry-After'));
    if (error.status === 403 && error.code === REAUTH_REQUIRED && !retried && stepUp) {
      // A sensitive action: ask for a fresh 2FA code, then say it again once
      if (await stepUp()) return send(method, path, options, true);
    }
    if (error.status === 401 && !options.anonymous) endedListeners.forEach((listener) => listener());
    throw error;
  }

  const record = isRecord(body) ? body : {};
  return { data: 'data' in record ? record.data : body, meta: isRecord(record.meta) ? record.meta : {} };
}

export const call = (method: string, path: string, options: CallOptions = {}) => send(method, path, options, false);

export async function get<T>(path: string, options: CallOptions = {}): Promise<T> {
  return (await call('GET', path, options)).data as T;
}

export async function post<T>(path: string, body?: unknown, options: CallOptions = {}): Promise<T> {
  return (await call('POST', path, { ...options, body: body ?? {} })).data as T;
}

export async function put<T>(path: string, body?: unknown, options: CallOptions = {}): Promise<T> {
  return (await call('PUT', path, { ...options, body: body ?? {} })).data as T;
}

export async function patch<T>(path: string, body?: unknown, options: CallOptions = {}): Promise<T> {
  return (await call('PATCH', path, { ...options, body: body ?? {} })).data as T;
}

export async function del<T>(path: string, options: CallOptions = {}): Promise<T> {
  return (await call('DELETE', path, options)).data as T;
}

// --- Files (P4-23) ---------------------------------------------------------------------------------------------------------

export interface DownloadedFile {
  blob: Blob;
  name: string;
  // P4-31: an Excel export says how many rows it holds and whether the list was cut at the cap (X-Export-* headers)
  rows?: number;
  truncated?: boolean;
}

// The file name of a Content-Disposition header (filename*=UTF-8'' first, then filename=)
export function fileNameOf(disposition: string | null, fallback: string): string {
  if (!disposition) return fallback;
  const encoded = /filename\*=UTF-8''([^;]+)/i.exec(disposition)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded.trim());
    } catch {
      // fall through to the plain name
    }
  }
  const plain = /filename="?([^";]+)"?/i.exec(disposition)?.[1];
  return plain?.trim() || fallback;
}

// A GET that answers with a file (the Excel export, a document): the bytes and the name the backend gave. A refusal is
// still the admin envelope, thrown as an ApiError like any call.
export async function download(path: string, query: CallOptions['query'], fallbackName: string): Promise<DownloadedFile> {
  let response: Response;
  try {
    response = await fetch(urlOf(path, query), { method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: { Accept: '*/*' } });
  } catch {
    throw new ApiError('', 0, 'unreachable');
  }
  markActive();
  if (!response.ok || (response.headers.get('content-type') ?? '').includes('application/json')) {
    const text = await response.text().catch(() => '');
    let body: unknown = null;
    try {
      body = text ? JSON.parse(text) : null;
    } catch {
      body = null;
    }
    const error = errorFrom(response.ok ? 502 : response.status, body, response.headers.get('Retry-After'));
    if (error.status === 401) endedListeners.forEach((listener) => listener());
    throw error;
  }
  const rows = Number(response.headers.get('x-export-rows'));
  const exported = response.headers.has('x-export-rows') && Number.isInteger(rows) ? { rows, truncated: response.headers.get('x-export-truncated') === '1' } : {};
  return { blob: await response.blob(), name: fileNameOf(response.headers.get('content-disposition'), fallbackName), ...exported };
}

// Hands a downloaded file to the browser to save
export function saveFile(file: DownloadedFile): void {
  const url = URL.createObjectURL(file.blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = file.name;
  link.rel = 'noopener';
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1_000);
}
