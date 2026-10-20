import type * as SentryReact from '@sentry/react';
import type { Breadcrumb, Event } from '@sentry/react';

// Error reporting to Sentry, fully driven by env: with VITE_SENTRY_DSN empty nothing is loaded, nothing is sent and
// the app behaves exactly as before. The SDK is imported dynamically, so it is a separate chunk that only users of a
// build with a DSN download.
//
// Nothing personal leaves the device: every event, transaction and breadcrumb passes through scrubEvent() first
// (tokens, Telegram initData, phone numbers, OTP codes, card numbers, payment keys), and the user is never set.

export const MASK = '[Filtered]';

// A key containing one of these (lower-cased, letters and digits only) is masked ...
const KEY_FRAGMENTS = [
  'authorization', 'cookie', 'token', 'secret', 'password', 'passwd', 'initdata', 'phone', 'card', 'cvv', 'cvc',
  'apikey', 'privatekey', 'signature', 'signstring', 'xsrf', 'csrf', 'otp', 'smscode', 'verificationcode', 'tgwebapp',
];
// ... and so is a key equal to one of these (too short or too common to match as a fragment)
const KEY_EXACT = ['code', 'pan', 'key', 'hash', 'sign', 'auth', 'pin', 'expiry', 'exp'];
const URL_KEYS = ['url', 'to', 'from', 'referer', 'referrer', 'origin', 'href'];

export function isSensitiveKey(key: string): boolean {
  const normalized = key.toLowerCase().replace(/[^a-z0-9]/g, '');
  return KEY_EXACT.includes(normalized) || KEY_FRAGMENTS.some((fragment) => normalized.includes(fragment));
}

function passesLuhn(number: string): boolean {
  const digits = number.replace(/\D/g, '');
  if (digits.length < 13 || digits.length > 19) return false;
  let sum = 0;
  for (let i = 0; i < digits.length; i++) {
    let digit = Number(digits[digits.length - 1 - i]);
    if (i % 2 === 1) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
  }
  return sum % 10 === 0;
}

// Masks secrets inside free text (messages, URLs, breadcrumb text). No lookbehind (old Telegram WebViews), so the
// character in front of a number is captured and put back.
export function scrubString(value: string, isUrl = false): string {
  if (value === '') return value;
  let text = value;

  // A Mini App URL carries the whole signed initData in its fragment: #tgWebAppData=...
  if (isUrl || text.includes('tgWebApp')) text = text.replace(/#[\s\S]*$/, `#${MASK}`);

  // initData as a query string: query_id=...&user=%7B...%7D&auth_date=...&hash=...
  if (text.includes('auth_date=') || text.includes('tgWebApp')) {
    text = text.replace(
      /(^|[^A-Za-z0-9_])(tgWebApp\w*|query_id|user|chat|receiver|start_param|auth_date|signature|hash|chat_instance|chat_type)=[^&\s"']*/gi,
      `$1$2=${MASK}`,
    );
  }

  // Authorization header values and Sanctum personal access tokens (id|40 random chars)
  text = text.replace(/\b(Bearer|Basic)\s+[A-Za-z0-9._~+/=-]{8,}/gi, `$1 ${MASK}`);
  text = text.replace(/\b\d{1,10}\|[A-Za-z0-9]{30,}/g, MASK);

  // key=value / "key":"value" pairs of sensitive names inside free text (query strings, JSON snippets)
  text = text.replace(
    /(["']?\b(?:password|passwd|secret|token|api[_-]?key|authorization|init_?data|otp|sms_?code|verification_?code|secret_key|sign_string|card_?number|cvv|phone(?:_number)?)["']?\s*[:=]\s*)("[^"]*"|'[^']*'|[^\s,&;)}\]]+)/gi,
    `$1${MASK}`,
  );

  // A one-time code written out in text: "code 123456", "otp: 4821", "kod=1234"
  text = text.replace(/\b(code|kod|otp|sms)(\s*[:=#]?\s*)\d{4,8}\b/gi, `$1$2${MASK}`);

  // Phone numbers: +998 90 123-45-67, 998901234567, any +country-code number
  text = text.replace(/(^|[^\w.])\+?998[\s\-()]*(?:\d[\s\-()]*){9}(?!\w)/g, `$1${MASK}`);
  text = text.replace(/(^|[^\w.])\+\d(?:[\s\-()]*\d){8,14}(?!\w)/g, `$1${MASK}`);

  // Card numbers: 13-19 digits (spaces/dashes allowed) that pass the Luhn check, so ids and timestamps stay
  text = text.replace(/(^|[^\w.])(\d(?:[ -]?\d){12,18})(?!\w)/g, (match, prefix: string, number: string) =>
    passesLuhn(number) ? `${prefix}${MASK}` : match,
  );

  return text;
}

// Deep copy of any JSON-like value with sensitive keys masked and every string scrubbed.
export function scrubValue(value: unknown, key = '', depth = 0): unknown {
  if (key && isSensitiveKey(key)) return MASK;
  if (typeof value === 'string') return scrubString(value, URL_KEYS.includes(key.toLowerCase()));
  if (value === null || typeof value !== 'object') return value;
  if (depth > 12) return undefined;
  if (Array.isArray(value)) return value.map((item) => scrubValue(item, '', depth + 1));
  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).map(([k, v]) => [k, scrubValue(v, k, depth + 1)]),
  );
}

// What beforeSend / beforeSendTransaction return: the same event with everything scrubbed. The user (if any SDK
// integration set one) is cut down to an id.
export function scrubEvent<E extends Event>(event: E): E {
  const clean = scrubValue(event) as E;
  if (clean.user) clean.user = clean.user.id !== undefined ? { id: clean.user.id } : undefined;
  return clean;
}

export function scrubBreadcrumb(breadcrumb: Breadcrumb): Breadcrumb {
  return scrubValue(breadcrumb) as Breadcrumb;
}

// ---------------------------------------------------------------------------------------------------------------

const IGNORED_ERRORS = [/ResizeObserver loop/, 'Network Error', 'Request aborted', /timeout of \d+ms exceeded/];
const MAX_PENDING = 10;

let sentry: typeof SentryReact | null = null;
const pending: Array<() => void> = [];

export function isSentryConfigured(): boolean {
  return Boolean(import.meta.env.VITE_SENTRY_DSN?.trim());
}

function sampleRate(raw: string | undefined): number {
  const rate = Number(raw);
  return Number.isFinite(rate) ? Math.min(1, Math.max(0, rate)) : 0;
}

// Starts Sentry when VITE_SENTRY_DSN is set; otherwise does nothing. Never throws: a broken Sentry must not break the app.
export async function initSentry(): Promise<boolean> {
  const dsn = import.meta.env.VITE_SENTRY_DSN?.trim();
  if (!dsn || sentry) return Boolean(sentry);

  try {
    const sdk = await import('@sentry/react');
    const tracesSampleRate = sampleRate(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE);

    sdk.init({
      dsn,
      environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
      release: import.meta.env.VITE_SENTRY_RELEASE || undefined,
      // Collect as little as possible: no user info, cookies, headers, request/response bodies, query strings or
      // local variables (the SDK's defaults collect all of them); scrubEvent() below is the second line of defence.
      dataCollection: {
        userInfo: false,
        cookies: false,
        httpHeaders: false,
        httpBodies: [],
        urlQueryParams: false,
        databaseQueryData: false,
        queues: false,
        stackFrameVariables: false,
        genAI: { inputs: false, outputs: false },
        graphQL: { document: false, variables: false },
      },
      // 'static' (one transaction event) so that beforeSendTransaction below sees and scrubs what is sent
      traceLifecycle: 'static',
      tracesSampleRate,
      // Tracing instrumentation (extra requests and spans) only when traces are switched on
      integrations: tracesSampleRate > 0 ? [sdk.browserTracingIntegration()] : [],
      maxBreadcrumbs: 30,
      ignoreErrors: IGNORED_ERRORS,
      beforeSend: (event) => scrubEvent(event),
      beforeSendTransaction: (event) => scrubEvent(event),
      beforeBreadcrumb: (breadcrumb) => scrubBreadcrumb(breadcrumb),
    });

    sentry = sdk;
    pending.splice(0).forEach((send) => send());
    return true;
  } catch (err) {
    console.warn('Sentry could not be started:', err instanceof Error ? err.message : err);
    return false;
  }
}

// React 19 `onCaughtError` of createRoot: errors that an error boundary (RouteErrorBoundary, MapErrorBoundary) caught
// never reach window.onerror, so Sentry's global handlers cannot see them. The boundaries log the error themselves;
// this reports it and keeps React's default console output, which a custom handler would otherwise replace.
export function reportCaughtError(error: unknown, info?: { componentStack?: string }): void {
  console.error(error);
  if (info?.componentStack) console.error(info.componentStack);

  const send = () =>
    sentry?.captureException(error, {
      contexts: { react: { componentStack: info?.componentStack ?? '' } },
      tags: { handled: 'error-boundary' },
    });

  if (sentry) send();
  else if (isSentryConfigured() && pending.length < MAX_PENDING) pending.push(send);
}
