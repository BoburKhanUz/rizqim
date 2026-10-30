import { unwrap, type StaffApi } from '@rizqim/staff';

// The combo constructor of P6-31 (docs/API.md "Kombo"): GET/POST /owner/combos, PUT/DELETE /owner/combos/{id} and the
// receipt preview POST /owner/combos/{id}/preview. Called through the generated client's untyped door and read into the
// types below, so this area does not wait for the spec to be regenerated; the server does every sum (price, shares).

interface Init {
  body?: unknown;
}
interface Result {
  data?: unknown;
  error?: unknown;
  response: Response;
}
interface Door {
  GET(path: string, init?: Init): Promise<Result>;
  POST(path: string, init?: Init): Promise<Result>;
  PUT(path: string, init?: Init): Promise<Result>;
  DELETE(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export type ComboFiscalMode = 'single' | 'split';

export interface ComboModifier {
  id: number;
  uuid: string;
  group_id: number | null;
  group_name: string | null;
  name: string;
  quantity: number;
  price_delta: string;
}

export interface ComboOption {
  id: number;
  uuid: string;
  variant_id: number;
  variant_uuid: string;
  product_id: number | null;
  name: string;
  variant_name: string | null;
  quantity: number;
  price_delta: string;
  modifiers: ComboModifier[];
  is_default: boolean;
  label: string | null;
  // The text under the combo on the bill and receipt (no price)
  receipt_label: string;
}

export interface ComboSlot {
  id: number;
  uuid: string;
  name: string;
  min_picks: number;
  max_picks: number;
  is_required: boolean;
  allow_modifiers: boolean;
  options: ComboOption[];
}

export interface Combo {
  id: number;
  uuid: string;
  product_id: number;
  product_name: string | null;
  prices: string[];
  is_active: boolean;
  slots: ComboSlot[];
}

export interface ComboList {
  combos: Combo[];
  fiscal_mode: ComboFiscalMode;
}

export interface ComboOptionInput {
  variant_id: number;
  quantity: number;
  price_delta: string;
  modifiers: { modifier_id: number; quantity: number }[];
  is_default: boolean;
  label: string | null;
}

export interface ComboSlotInput {
  name: string;
  min_picks: number;
  max_picks: number;
  allow_modifiers: boolean;
  options: ComboOptionInput[];
}

export interface ComboPick {
  option_id: string;
  quantity?: number;
}

export interface ComboPreviewLine {
  name: string;
  mxik_code: string | null;
  quantity: number;
  amount: string;
}

export interface ComboPreview {
  name: string;
  quantity: number;
  base_price: string;
  delta: string;
  price: string;
  parts: { slot_name: string; name: string; variant_name: string | null; quantity: number; label: string }[];
  fiscal_mode: ComboFiscalMode;
  fiscal: { single: ComboPreviewLine[]; split: ComboPreviewLine[] };
}

export const fetchCombos = (api: StaffApi) => data<ComboList>(door(api).GET('/v1/owner/combos'));

export const createCombo = (api: StaffApi, input: { productId: number; slots: ComboSlotInput[]; isActive?: boolean }) =>
  data<Combo>(door(api).POST('/v1/owner/combos', { body: { product_id: input.productId, slots: input.slots, ...(input.isActive === undefined ? {} : { is_active: input.isActive }) } }));

export const updateCombo = (api: StaffApi, id: number, input: { slots?: ComboSlotInput[]; isActive?: boolean }) =>
  data<Combo>(door(api).PUT(`/v1/owner/combos/${id}`, { body: { ...(input.slots ? { slots: input.slots } : {}), ...(input.isActive === undefined ? {} : { is_active: input.isActive }) } }));

export const deleteCombo = (api: StaffApi, id: number) => data<null>(door(api).DELETE(`/v1/owner/combos/${id}`));

export const previewCombo = (api: StaffApi, id: number, picks: ComboPick[]) => data<ComboPreview>(door(api).POST(`/v1/owner/combos/${id}/preview`, { body: { picks } }));

// The store's choice (stores.fiscal_settings.combo_fiscal_mode, settings.manage): one fiscal line per combo or its parts
export const saveComboFiscalMode = (api: StaffApi, mode: ComboFiscalMode) =>
  data<unknown>(door(api).PUT('/v1/owner/fiscal/settings', { body: { fiscal_settings: { combo_fiscal_mode: mode } } }));
