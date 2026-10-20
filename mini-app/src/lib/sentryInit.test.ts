import { beforeEach, describe, expect, it, vi } from 'vitest';

const sdk = vi.hoisted(() => ({
  init: vi.fn(),
  captureException: vi.fn(),
  browserTracingIntegration: vi.fn(() => ({ name: 'BrowserTracing' })),
}));
vi.mock('@sentry/react', () => sdk);

// The module keeps the loaded SDK in a variable, so every test imports a fresh copy
async function loadModule() {
  vi.resetModules();
  return import('./sentry');
}

describe('initSentry with a DSN', () => {
  beforeEach(() => {
    vi.stubEnv('VITE_SENTRY_DSN', 'https://public@o0.ingest.sentry.io/1');
    vi.stubEnv('VITE_SENTRY_RELEASE', 'abc1234');
    vi.stubEnv('VITE_SENTRY_ENVIRONMENT', 'production');
    vi.stubEnv('VITE_SENTRY_TRACES_SAMPLE_RATE', '');
  });

  it('starts the SDK with a privacy-first configuration and scrubbing hooks', async () => {
    const { initSentry, MASK } = await loadModule();

    await expect(initSentry()).resolves.toBe(true);

    const options = sdk.init.mock.calls[0][0];
    expect(options).toMatchObject({
      dsn: 'https://public@o0.ingest.sentry.io/1',
      release: 'abc1234',
      environment: 'production',
      tracesSampleRate: 0,
      traceLifecycle: 'static',
      integrations: [],
      dataCollection: { userInfo: false, cookies: false, httpHeaders: false, httpBodies: [], urlQueryParams: false },
    });
    expect(options.beforeSend({ request: { headers: { Authorization: 'Bearer abcdefghijkl' } } }).request.headers.Authorization).toBe(MASK);
    expect(options.beforeSendTransaction({ transaction: '/orders', extra: { phone: '+998901234567' } }).extra.phone).toBe(MASK);
    expect(options.beforeBreadcrumb({ category: 'x', message: 'otp: 123456' }).message).not.toContain('123456');
    expect(sdk.browserTracingIntegration).not.toHaveBeenCalled();
  });

  it('adds tracing only when a trace sample rate is set, and clamps it', async () => {
    vi.stubEnv('VITE_SENTRY_TRACES_SAMPLE_RATE', '5');
    const { initSentry } = await loadModule();

    await initSentry();

    expect(sdk.init.mock.calls[0][0]).toMatchObject({ tracesSampleRate: 1, integrations: [{ name: 'BrowserTracing' }] });
  });

  it('reports caught boundary errors, also those raised before the SDK finished loading', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    const { initSentry, reportCaughtError } = await loadModule();

    const early = new Error('early');
    reportCaughtError(early, { componentStack: '\n at Page' });
    expect(sdk.captureException).not.toHaveBeenCalled();

    await initSentry();
    expect(sdk.captureException).toHaveBeenCalledWith(early, expect.objectContaining({ tags: { handled: 'error-boundary' } }));

    const late = new Error('late');
    reportCaughtError(late);
    expect(sdk.captureException).toHaveBeenCalledWith(late, expect.anything());
  });

  it('survives an SDK that fails to start', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    sdk.init.mockImplementationOnce(() => {
      throw new Error('bad dsn');
    });
    const { initSentry } = await loadModule();

    await expect(initSentry()).resolves.toBe(false);
  });
});
