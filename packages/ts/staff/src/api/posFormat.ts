import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// A branch's format profile (P6-11; docs/API.md "Filial formati"): the profile (choyxona, osh markazi, stolovaya...), the
// till's mode and first screen, the module switches over the profile's defaults with the plan's ("Asosiy tarifda bor") and the
// law's (stolovaya: no service charge) locks, and the sample stations and menu. settings.manage (the owner), never from a
// terminal's PIN session. The server works out the effective modules; these functions only call the endpoints.

export const FORMAT_PROFILES = [
  'choyxona',
  'osh_markazi',
  'lagmonxona',
  'somsaxona',
  'tamaddixona',
  'stolovaya',
  'kafe',
  'restoran',
  'kabobxona',
  'baliqxona',
  'fast_food',
  'qahvaxona',
  'toyxona',
  'dark_kitchen',
] as const;
export type FormatProfileKey = (typeof FORMAT_PROFILES)[number];

export const POS_MODULES = [
  'quick_counter',
  'phone_mode',
  'queue_numbers',
  'queue_calling',
  'customer_display',
  'daily_menu',
  'combo_meal',
  'batches',
  'batch_writeoff',
  'terminal_card_flow',
  'rooms',
  'room_fee',
  'guest_items',
  'bookings',
  'customer_accounts',
  'service_charge',
  'kds',
  // Wave 2 "Restoran va kafe"
  'courses',
  'tips',
  'staff_pay',
  'guest_split',
  'waiter_transfer',
  'hostess',
  'table_booking',
  'skewer_buttons',
  'weight_later',
  'table_service',
  'service_charge_rules',
] as const;
export type PosModuleKey = (typeof POS_MODULES)[number];

// GET /owner/pos/format?branch_id= : the format, one row per module, the profiles to pick from and the template preview
export type PosFormat = OkData<'posFormat.show'>;
export type PosModuleRow = PosFormat['module_rows'][number];
export type FormatProfileOption = PosFormat['profiles'][number];
export type FormatTemplatePreview = PosFormat['template'];

export async function fetchPosFormat(api: StaffApi, branchId: number): Promise<PosFormat> {
  return unwrap(await api.client.GET('/v1/owner/pos/format', { params: { query: { branch_id: branchId } } })).data;
}

// PUT /owner/pos/format: what is sent changes. A new `profile` starts from its own mode and modules (send `modules` with it
// to keep some); `modules` {key: true | false | null} — null goes back to the profile's default. Switching on what the plan
// lacks: 403 plan_feature_missing; a stolovaya's service charge: 422 service_charge_locked.
export type PosFormatPatch = JsonRequest<'posFormat.update'>;

export async function savePosFormat(api: StaffApi, patch: PosFormatPatch): Promise<PosFormat> {
  return unwrap(await api.client.PUT('/v1/owner/pos/format', { body: patch })).data;
}

// POST /owner/pos/format/template: the profile's sample stations (on the branch) and dishes (switched off, 0 price), never
// over existing ones; running it again adds nothing
export type FormatTemplateInput = JsonRequest<'posFormat.applyTemplate'>;
export type FormatTemplateResult = OkData<'posFormat.applyTemplate'>;

export async function applyFormatTemplate(api: StaffApi, input: FormatTemplateInput): Promise<FormatTemplateResult> {
  return unwrap(await api.client.POST('/v1/owner/pos/format/template', { body: input })).data;
}
