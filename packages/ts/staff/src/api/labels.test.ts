import { afterEach, describe, expect, it, vi } from 'vitest';
import { json, mockFetch, ok, testApi } from '../test/helpers';
import { fetchLabelSettings, LABEL_SCOPES, LABEL_SIZES, saveLabelSettings } from './labels';
import { saveModifierGroup, MODIFIER_DISPLAY_MODES } from './menuAdmin';

afterEach(() => vi.unstubAllGlobals());

const SETTINGS = {
  branch_id: 3,
  configured: true,
  size: '58x40',
  size_label: '58 × 40 mm',
  width_mm: 58,
  height_mm: 40,
  scope: 'categories',
  scope_label: 'Tanlangan kategoriyalarga (masalan, ichimliklar)',
  category_ids: [5],
  per_unit: true,
  bag_label: false,
  updated_at: '2026-11-20T10:00:00Z',
};

describe('the sticker and label settings (P6-32)', () => {
  it("reads a branch's settings and saves only what changed", async () => {
    const { calls } = mockFetch({
      'GET /api/v1/owner/branches/3/label-settings': () => ok(SETTINGS),
      'PUT /api/v1/owner/branches/3/label-settings': () => ok({ ...SETTINGS, bag_label: true }),
    });
    const api = testApi();
    expect((await fetchLabelSettings(api, 3)).category_ids).toEqual([5]);
    expect((await saveLabelSettings(api, 3, { bag_label: true })).bag_label).toBe(true);
    expect(await calls[1]!.json()).toEqual({ bag_label: true });
  });

  it("keeps the server's refusal of a labels module that is off", async () => {
    mockFetch({
      'GET /api/v1/owner/branches/3/label-settings': () => json({ success: false, message: "Stiker moduli o'chiq.", data: { reason: 'module_disabled' } }, 403),
    });
    const error = (await fetchLabelSettings(testApi(), 3).catch((e: unknown) => e)) as { reason?: string; status?: number };
    expect(error.status).toBe(403);
    expect(error.reason).toBe('module_disabled');
  });

  it('names the sizes and scopes the server knows', () => {
    expect([...LABEL_SIZES]).toEqual(['40x30', '58x40']);
    expect([...LABEL_SCOPES]).toEqual(['all', 'categories']);
  });
});

describe('the modifier group quick hints (P6-32)', () => {
  it('sends the display mode and the default option with the group', async () => {
    const group = { id: 3, uuid: 'g', name: 'Hajm', name_i18n: null, min_select: 1, max_select: 1, is_required: true, display_mode: 'size', sort_order: 0, is_active: true, modifiers: [], product_ids: [] };
    const { calls } = mockFetch({ 'POST /api/v1/owner/modifier-groups': () => json({ success: true, message: 'ok', data: group }, 201) });
    const input = { name: 'Hajm', max_select: 1, display_mode: 'size' as const, modifiers: [{ name: "O'rta", price_delta: 5000, is_default: true }] };
    const saved = await saveModifierGroup(testApi(), null, input);
    expect(saved.display_mode).toBe('size');
    expect(await calls[0]!.json()).toEqual(input);
    expect([...MODIFIER_DISPLAY_MODES]).toEqual(['dialog', 'inline', 'size']);
  });
});
