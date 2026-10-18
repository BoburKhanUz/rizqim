import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  getApiErrorMessage,
  getApiFieldError,
  getErrorMessage,
  logApiError,
  sanitizeError,
} from './apiError';

// Things that must never reach the console or the screen from a failed request
const TOKEN = '12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789SECRET';
const OTP = '482913';
const PHONE = '+998901234567';
const INIT_DATA = 'query_id=AAH&hash=deadbeefcafe';

function failedRequest(status: number | undefined, body?: unknown, url = '/phone/verify-code'): AxiosError {
  const config: InternalAxiosRequestConfig = {
    url,
    method: 'post',
    headers: new AxiosHeaders({ Authorization: `Bearer ${TOKEN}` }),
    data: JSON.stringify({ phone: PHONE, code: OTP, initData: INIT_DATA }),
  };
  const response =
    status === undefined
      ? undefined
      : { data: body, status, statusText: String(status), headers: {}, config };
  return new AxiosError(
    status === undefined ? 'Network Error' : `Request failed with status code ${status}`,
    status === undefined ? AxiosError.ERR_NETWORK : AxiosError.ERR_BAD_REQUEST,
    config,
    {},
    response,
  );
}

function expectNoSecrets(value: unknown) {
  const text = JSON.stringify(value) ?? String(value);
  for (const secret of [TOKEN, 'SECRET', OTP, PHONE, INIT_DATA, 'Authorization']) {
    expect(text).not.toContain(secret);
  }
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('sanitizeError', () => {
  it('keeps only the status and the server message of a failed request', () => {
    const result = sanitizeError(failedRequest(422, { message: 'Tasdiqlash kodi noto\'g\'ri.' }));
    expect(result).toBe('HTTP 422: Tasdiqlash kodi noto\'g\'ri.');
  });

  it('never carries the request (token, OTP, phone, initData) along', () => {
    expectNoSecrets(sanitizeError(failedRequest(422, { message: 'Kod xato.' })));
    expectNoSecrets(sanitizeError(failedRequest(401, {})));
    expectNoSecrets(sanitizeError(failedRequest(500, '<html>Server Error</html>')));
    expectNoSecrets(sanitizeError(failedRequest(undefined)));
  });

  it('falls back to the library message without a server message', () => {
    expect(sanitizeError(failedRequest(500, {}))).toBe('HTTP 500: Request failed with status code 500');
    expect(sanitizeError(failedRequest(502, null))).toBe('HTTP 502: Request failed with status code 502');
  });

  it('returns just the message when the request never got an answer', () => {
    expect(sanitizeError(failedRequest(undefined))).toBe('Network Error');
  });

  it('returns anything that is not an axios error unchanged', () => {
    const error = new Error('boom');
    expect(sanitizeError(error)).toBe(error);
    expect(sanitizeError('plain')).toBe('plain');
    expect(sanitizeError(undefined)).toBeUndefined();
  });
});

describe('logApiError', () => {
  it('logs the context with the sanitized error only', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    logApiError('Failed to verify code:', failedRequest(422, { message: 'Kod xato.' }));

    expect(spy).toHaveBeenCalledTimes(1);
    expect(spy).toHaveBeenCalledWith('Failed to verify code:', 'HTTP 422: Kod xato.');
    expectNoSecrets(spy.mock.calls);
  });
});

describe('getApiErrorMessage', () => {
  it('returns the message of the server response', () => {
    expect(getApiErrorMessage(failedRequest(403, { message: 'Ruxsat etilmagan.' }))).toBe('Ruxsat etilmagan.');
  });

  it('is undefined without one, or for errors that are not from a request', () => {
    expect(getApiErrorMessage(failedRequest(500, {}))).toBeUndefined();
    expect(getApiErrorMessage(failedRequest(500, { message: '' }))).toBeUndefined();
    expect(getApiErrorMessage(failedRequest(undefined))).toBeUndefined();
    expect(getApiErrorMessage(new Error('x'))).toBeUndefined();
    expect(getApiErrorMessage(null)).toBeUndefined();
  });
});

describe('getApiFieldError', () => {
  const validation = (errors: Record<string, string[] | string>) =>
    failedRequest(422, { message: 'The given data was invalid.', errors });

  it('returns the first Laravel validation message of the field', () => {
    const err = validation({ phone: ['Telefon noto\'g\'ri.', 'Boshqa xato.'] });
    expect(getApiFieldError(err, 'phone')).toBe('Telefon noto\'g\'ri.');
  });

  it('accepts a single string message', () => {
    expect(getApiFieldError(validation({ phone: 'Telefon noto\'g\'ri.' }), 'phone')).toBe('Telefon noto\'g\'ri.');
  });

  it('is undefined for another field, an empty message or a missing list', () => {
    expect(getApiFieldError(validation({ name: ['x'] }), 'phone')).toBeUndefined();
    expect(getApiFieldError(validation({ phone: ['   '] }), 'phone')).toBeUndefined();
    expect(getApiFieldError(validation({ phone: [] }), 'phone')).toBeUndefined();
    expect(getApiFieldError(failedRequest(422, { message: 'x' }), 'phone')).toBeUndefined();
  });

  it('only looks at 422 responses', () => {
    const err = failedRequest(400, { errors: { phone: ['Telefon noto\'g\'ri.'] } });
    expect(getApiFieldError(err, 'phone')).toBeUndefined();
    expect(getApiFieldError(new Error('x'), 'phone')).toBeUndefined();
  });
});

describe('getErrorMessage', () => {
  it('returns the message of an Error, if it has one', () => {
    expect(getErrorMessage(new Error('Network Error'))).toBe('Network Error');
    expect(getErrorMessage(new Error(''))).toBeUndefined();
    expect(getErrorMessage('Network Error')).toBeUndefined();
    expect(getErrorMessage(undefined)).toBeUndefined();
  });
});
