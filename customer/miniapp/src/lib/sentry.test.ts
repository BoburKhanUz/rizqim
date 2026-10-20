import { describe, expect, it, vi } from 'vitest';
import { MASK, initSentry, isSentryConfigured, reportCaughtError, scrubBreadcrumb, scrubEvent, scrubString, scrubValue } from './sentry';

describe('scrubValue', () => {
  it('masks sensitive keys at any depth and keeps the rest', () => {
    const clean = scrubValue({
      request: {
        headers: { Authorization: 'Bearer 12|abcdefghijklmnopqrstuvwxyz0123456789ABCD', Accept: 'application/json' },
        data: { phone: '+998901234567', code: '123456', otp: '1', initData: 'query_id=A&hash=b', address: 'Tashkent' },
      },
      extra: { token: 'x', cardNumber: '4111 1111 1111 1111', orderId: 17 },
    }) as Record<string, Record<string, Record<string, unknown>>>;

    expect(clean.request.headers.Authorization).toBe(MASK);
    expect(clean.request.headers.Accept).toBe('application/json');
    for (const key of ['phone', 'code', 'otp', 'initData']) expect(clean.request.data[key]).toBe(MASK);
    expect(clean.request.data.address).toBe('Tashkent');
    expect(clean.extra.token).toBe(MASK);
    expect(clean.extra.cardNumber).toBe(MASK);
    expect(clean.extra.orderId).toBe(17);
  });

  it('does not mutate its input', () => {
    const input = { phone: '+998901234567', nested: { token: 'abc' } };
    scrubValue(input);
    expect(input).toEqual({ phone: '+998901234567', nested: { token: 'abc' } });
  });
});

describe('scrubString', () => {
  it('masks phones, tokens, card numbers and codes inside text', () => {
    const text =
      'Failed for +998 90 123-45-67 / 998901234567, token 15|AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcd, ' +
      'card 4111 1111 1111 1111, Authorization: Bearer abcdef1234567890, code 482913, "otp":"7777"';
    const clean = scrubString(text);

    for (const secret of ['998 90 123', '998901234567', 'AbCdEfGhIjKl', '4111', 'abcdef1234567890', '482913', '7777']) {
      expect(clean).not.toContain(secret);
    }
    expect(clean).toContain('Failed for');
  });

  it('removes the Telegram initData fragment of a URL', () => {
    const url = 'https://food.example.uz/orders?tab=active#tgWebAppData=query_id%3DAAH%26user%3D%257B%257D%26auth_date%3D1%26hash%3Dabc&tgWebAppVersion=8.0';

    expect(scrubString(url, true)).toBe(`https://food.example.uz/orders?tab=active#${MASK}`);
    const text = scrubString(`opened ${url}`);
    expect(text).not.toContain('query_id');
    expect(text).not.toContain('hash%3Dabc');
  });

  it('masks initData given as a query string', () => {
    const clean = scrubString('query_id=AAHdF6IQ&user=%7B%22id%22%3A279058397%7D&auth_date=1700000000&hash=deadbeef00');
    for (const secret of ['AAHdF6IQ', '279058397', '1700000000', 'deadbeef00']) expect(clean).not.toContain(secret);
  });

  it('keeps ordinary numbers and ids', () => {
    const text = 'Order 1700000000123 total 150000 sum, trace 0123456789012345abcdef0123456789, lat 41.311081 lng 69.240562, status 500';
    expect(scrubString(text)).toBe(text);
  });

  it('masks only Luhn-valid 13-19 digit numbers', () => {
    expect(scrubString('8600 4954 7331 6478')).toBe(MASK);
    expect(scrubString('1234567890123')).toBe('1234567890123');
  });
});

describe('scrubEvent', () => {
  it('scrubs request, breadcrumbs, exception text and cuts the user down to an id', () => {
    const event = scrubEvent({
      request: { url: 'https://x.uz/p#tgWebAppData=abc', headers: { Cookie: 'a=b', Referer: 'https://x.uz/#tgWebAppData=secret' } },
      exception: { values: [{ type: 'Error', value: 'Cannot send code 123456 to +998 90 123 45 67' }] },
      breadcrumbs: [{ category: 'navigation', data: { from: '/a#tgWebAppData=1', to: '/b' }, message: 'phone=+998901234567' }],
      user: { id: '42', email: 'a@b.uz', ip_address: '1.2.3.4' },
    });
    const json = JSON.stringify(event);

    for (const secret of ['tgWebAppData=abc', 'secret', 'a=b', '123456', '998 90 123', '998901234567', 'a@b.uz', '1.2.3.4']) {
      expect(json).not.toContain(secret);
    }
    expect(event.user).toEqual({ id: '42' });
    expect(event.request?.url).toBe(`https://x.uz/p#${MASK}`);
  });

  it('scrubs a breadcrumb', () => {
    const crumb = scrubBreadcrumb({ category: 'xhr', data: { url: '/api/v1/phone/verify-code?phone=%2B998901234567', authorization: 'x' } });
    expect(JSON.stringify(crumb)).not.toContain('998901234567');
    expect(crumb.data?.authorization).toBe(MASK);
  });
});

describe('without a DSN', () => {
  it('does not initialise Sentry and still logs caught errors like React does', async () => {
    vi.stubEnv('VITE_SENTRY_DSN', '');
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(isSentryConfigured()).toBe(false);
    await expect(initSentry()).resolves.toBe(false);

    const error = new Error('boom');
    reportCaughtError(error, { componentStack: '\n at Page' });
    expect(consoleError).toHaveBeenCalledWith(error);
  });

  it('treats a blank DSN as not configured', () => {
    vi.stubEnv('VITE_SENTRY_DSN', '   ');
    expect(isSentryConfigured()).toBe(false);
  });
});
