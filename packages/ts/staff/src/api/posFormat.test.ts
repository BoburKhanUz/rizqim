import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { applyFormatTemplate, fetchPosFormat, FORMAT_PROFILES, POS_MODULES, savePosFormat } from './posFormat';

afterEach(() => vi.unstubAllGlobals());

describe('the branch format (P6-11)', () => {
  it('reads the format of a branch, saves a profile and module switches, applies the template', async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/pos/format': () => ok({ branch_id: 3, profile: 'choyxona', modules: ['rooms'] }),
      'PUT /api/v1/owner/pos/format': () => ok({ branch_id: 3, profile: 'osh_markazi', modules: ['quick_counter'] }),
      'POST /api/v1/owner/pos/format/template': () => ok({ stations_created: ['Osh tarqatish'], dishes_created: [], format: { branch_id: 3 } }),
    });
    const api = testApi();
    expect((await fetchPosFormat(api, 3)).profile).toBe('choyxona');
    expect(new URL(calls[0]!.url).searchParams.get('branch_id')).toBe('3');
    expect((await savePosFormat(api, { branch_id: 3, profile: 'osh_markazi', modules: { kds: null, queue_numbers: true } })).profile).toBe('osh_markazi');
    expect(await calls[1]!.json()).toEqual({ branch_id: 3, profile: 'osh_markazi', modules: { kds: null, queue_numbers: true } });
    expect((await applyFormatTemplate(api, { branch_id: 3, menu: false })).stations_created).toEqual(['Osh tarqatish']);
    expect(await calls[2]!.json()).toEqual({ branch_id: 3, menu: false });
  });

  it("keeps the server's refusal of the stolovaya's service charge", async () => {
    mockFetch({
      'PUT /api/v1/owner/pos/format': () => json({ success: false, message: "Stolovayada xizmat haqi qonun bo'yicha olinmaydi (0%), uni yoqib bo'lmaydi.", code: 'service_charge_locked', data: { reason: 'service_charge_locked', module: 'service_charge' } }, 422),
    });
    const error = (await savePosFormat(testApi(), { branch_id: 3, modules: { service_charge: true } }).catch((e: unknown) => e)) as { reason?: string; status?: number };
    expect(error.status).toBe(422);
    expect(error.reason).toBe('service_charge_locked');
  });

  it('lists the 14 profiles and the module keys of the backend', () => {
    expect(FORMAT_PROFILES).toHaveLength(14);
    expect(POS_MODULES).toContain('terminal_card_flow');
    expect(POS_MODULES).toHaveLength(17);
  });
});
