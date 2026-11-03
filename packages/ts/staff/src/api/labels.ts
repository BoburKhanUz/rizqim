import type { StaffApi } from './client';
import { unwrap } from './errors';

// A branch's sticker and label settings (P6-32; docs/API.md "Stiker va yorliq"): the label roll size, which items get a cup
// sticker (every item, or only the dishes of chosen categories), one sticker per piece, a bag label for takeaway orders.
// settings.manage (the owner), never from a terminal's PIN session; the `labels` module (403 module_disabled). The tills read
// the same block from GET /pos/bootstrap `labels`.
// The route is not in the generated spec yet: its path is borrowed from a route that is (the cast keeps the call typed) and the
// answer is read as the contract says. Replace by the generated types once the spec has it.

export const LABEL_SIZES = ['40x30', '58x40'] as const;
export type LabelSizeKey = (typeof LABEL_SIZES)[number];

export const LABEL_SCOPES = ['all', 'categories'] as const;
export type LabelScopeKey = (typeof LABEL_SCOPES)[number];

export interface LabelSettings {
  branch_id: number;
  // false: nothing was saved, the values are the defaults
  configured: boolean;
  size: LabelSizeKey;
  size_label: string;
  width_mm: number;
  height_mm: number;
  // `all`: every item gets a sticker; `categories`: only the dishes of `category_ids`
  scope: LabelScopeKey;
  scope_label: string;
  category_ids: number[];
  // One sticker per piece (3 cups on a line: 3 stickers); false: one per line
  per_unit: boolean;
  // A takeaway order also gets a bag label
  bag_label: boolean;
  updated_at: string | null;
}

// PUT body: only what changes. `scope` `categories` needs at least one `category_ids` entry; `all` clears them.
export type LabelSettingsPatch = Partial<Pick<LabelSettings, 'size' | 'scope' | 'category_ids' | 'per_unit' | 'bag_label'>>;

const LABEL_SETTINGS_PATH = '/v1/owner/branches/{id}/label-settings' as '/v1/owner/branches/{id}/pos-settings';

export async function fetchLabelSettings(api: StaffApi, branchId: number): Promise<LabelSettings> {
  return unwrap(await api.client.GET(LABEL_SETTINGS_PATH, { params: { path: { id: branchId } } })).data as unknown as LabelSettings;
}

export async function saveLabelSettings(api: StaffApi, branchId: number, patch: LabelSettingsPatch): Promise<LabelSettings> {
  return unwrap(await api.client.PUT(LABEL_SETTINGS_PATH, { params: { path: { id: branchId } }, body: patch as never })).data as unknown as LabelSettings;
}
