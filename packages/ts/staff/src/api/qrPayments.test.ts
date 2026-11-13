import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { fetchQrPaymentSettings, QR_PROVIDERS, saveQrPaymentSettings } from './qrPayments';

afterEach(() => vi.unstubAllGlobals());

const VIEW = {
  settings: { branch_id: 3, enabled: true, providers: ['click', 'payme', 'uzqr'], default_provider: 'click', expiry_minutes: 5, updated_at: null },
  merchants: [
    { provider: 'click', label: 'Click', configured: true, driver: 'click' },
    { provider: 'payme', label: 'Payme', configured: false, driver: 'payme' },
    { provider: 'uzqr', label: 'UzQR', configured: false, driver: 'uzqr:none' },
  ],
  options: { providers: [{ key: 'click', label: 'Click' }, { key: 'payme', label: 'Payme' }, { key: 'uzqr', label: 'UzQR' }], expiry_min: 1, expiry_max: 30 },
};

describe('the QR payment settings (P6-41)', () => {
  it("reads a branch's settings and saves only what changed with the branch", async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/pos/qr-payments': () => ok(VIEW),
      'PUT /api/v1/owner/pos/qr-payments': () => ok({ ...VIEW, settings: { ...VIEW.settings, expiry_minutes: 10 } }),
    });
    const api = testApi();
    const view = await fetchQrPaymentSettings(api, 3);
    expect(view.merchants.map((row) => row.configured)).toEqual([true, false, false]);
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');
    expect((await saveQrPaymentSettings(api, 3, { expiry_minutes: 10 })).settings.expiry_minutes).toBe(10);
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, expiry_minutes: 10 });
  });

  it("keeps the server's refusal of a module that is off", async () => {
    mockFetch({
      'PUT /api/v1/owner/pos/qr-payments': () => json({ success: false, message: "Modul o'chiq.", data: { reason: 'module_disabled' } }, 403),
    });
    const error = (await saveQrPaymentSettings(testApi(), 3, { providers: ['click'] }).catch((e: unknown) => e)) as { reason?: string; status?: number };
    expect(error.status).toBe(403);
    expect(error.reason).toBe('module_disabled');
  });

  it('names the providers the server knows', () => {
    expect([...QR_PROVIDERS]).toEqual(['click', 'payme', 'uzqr']);
  });
});
