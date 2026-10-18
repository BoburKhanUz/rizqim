import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';

// Things that must never reach the console or the screen from a failed request
export const SECRETS = {
  token: '12|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789SECRET',
  otp: '482913',
  phone: '+998901234567',
  initData: 'query_id=AAH&hash=deadbeefcafe',
} as const;

// An axios error as a real failed request produces it: the config carries the Authorization header and the body
// (phone number, OTP, Telegram initData), so logging it as is would leak them all.
export function failedRequest(status: number | undefined, body?: unknown, url = '/phone/verify-code'): AxiosError {
  const config: InternalAxiosRequestConfig = {
    url,
    method: 'post',
    headers: new AxiosHeaders({ Authorization: `Bearer ${SECRETS.token}` }),
    data: JSON.stringify({ phone: SECRETS.phone, code: SECRETS.otp, initData: SECRETS.initData }),
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

// Fails the test if any of the secrets appears anywhere in `value` (serialised).
export function secretsIn(value: unknown): string[] {
  const text = JSON.stringify(value) ?? String(value);
  return [...Object.values(SECRETS), 'SECRET', 'Authorization'].filter((secret) => text.includes(secret));
}
