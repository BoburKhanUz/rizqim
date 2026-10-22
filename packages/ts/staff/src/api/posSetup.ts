import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// The setup of the till and the kitchen (P3-13; docs/API.md "POS sozlamalari"): kitchen stations, which categories and
// dishes go to which station, printers and the branch's POS settings. All of it is `settings.manage` (the owner) and never
// from a terminal's PIN session. The OpenAPI spec lists only `branch_id` as the body of the station and printer creation and
// of the route assignment; the real bodies are written out below from docs/API.md.

// --- Kitchen stations ------------------------------------------------------------------------------------------------------

export type KitchenStations = OkData<'kitchenStation.index'>;
export type KitchenStation = KitchenStations['stations'][number];

export const STATION_KINDS = ['osh', 'mangal', 'salad_bar', 'tea', 'kitchen', 'other'] as const;
export type StationKind = (typeof STATION_KINDS)[number];

export interface KitchenStationInput {
  branch_id: number;
  // Unique in the branch
  name: string;
  kind: StationKind;
  sort_order?: number;
  is_active?: boolean;
  show_on_kds?: boolean;
}

export type KitchenStationPatch = Partial<Omit<KitchenStationInput, 'branch_id'>>;

// GET /owner/kitchen-stations: the branch's stations (inactive ones too) and the kinds the server knows
export async function fetchKitchenStations(api: StaffApi, branchId?: number): Promise<KitchenStations> {
  return unwrap(await api.client.GET('/v1/owner/kitchen-stations', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } })).data;
}

export async function createKitchenStation(api: StaffApi, input: KitchenStationInput): Promise<KitchenStation> {
  return unwrap(await api.client.POST('/v1/owner/kitchen-stations', { body: input as never })).data as KitchenStation;
}

// An inactive station is not given to the terminals and its routes are ignored
export async function updateKitchenStation(api: StaffApi, id: number, patch: KitchenStationPatch): Promise<KitchenStation> {
  return unwrap(await api.client.PATCH('/v1/owner/kitchen-stations/{id}', { params: { path: { id } }, body: patch })).data as KitchenStation;
}

// 422 `station_in_use` once an order line was sent to it: switch it off instead
export async function deleteKitchenStation(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/kitchen-stations/{id}', { params: { path: { id } } }));
}

// --- Routes: which station cooks what ---------------------------------------------------------------------------------------------

export type StationRoute = OkData<'kitchenStationRoute.index'>[number];

export async function fetchStationRoutes(api: StaffApi, branchId?: number): Promise<StationRoute[]> {
  const body = unwrap(await api.client.GET('/v1/owner/kitchen-station-routes', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

export interface StationRouteAssignment {
  branch_id: number;
  // null: take the listed categories and dishes off any station
  station_id: number | null;
  category_ids?: number[];
  product_ids?: number[];
}

// PUT /owner/kitchen-station-routes: a bulk assignment (up to 200 categories and 500 dishes). One that sits on another
// station moves. Answers all the routes of the branch. A dish's own route wins over its category's.
export async function assignStationRoutes(api: StaffApi, input: StationRouteAssignment): Promise<StationRoute[]> {
  const body = unwrap(await api.client.PUT('/v1/owner/kitchen-station-routes', { body: input as never }));
  return (body.data ?? []) as StationRoute[];
}

export async function deleteStationRoute(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/kitchen-station-routes/{id}', { params: { path: { id } } }));
}

// --- Printers --------------------------------------------------------------------------------------------------------------------

export type Printer = OkData<'printer.index'>[number];

export const PRINTER_PURPOSES = ['receipt', 'kitchen', 'label'] as const;
export type PrinterPurpose = (typeof PRINTER_PURPOSES)[number];
export const PRINTER_CONNECTIONS = ['lan', 'usb', 'builtin', 'bluetooth'] as const;
export type PrinterConnection = (typeof PRINTER_CONNECTIONS)[number];
export const PAPER_WIDTHS = [58, 80] as const;
export type PaperWidth = (typeof PAPER_WIDTHS)[number];

export interface PrinterInput {
  branch_id: number;
  name: string;
  purpose: PrinterPurpose;
  connection: PrinterConnection;
  // lan and bluetooth: the IP, name or MAC (required). usb and builtin keep no address
  address?: string | null;
  // lan: 9100 when empty
  port?: number | null;
  paper_width?: PaperWidth;
  // Kitchen and label printers: the station it serves
  station_id?: number | null;
  // usb and builtin: the uuid of the POS terminal it is plugged into (required)
  device_id?: string | null;
  // A printer that wants a code page, e.g. "cp866"; null: UTF-8
  encoding?: string | null;
  print_as_image?: boolean;
  is_active?: boolean;
}

export type PrinterPatch = Partial<Omit<PrinterInput, 'branch_id'>>;

export async function fetchPrinters(api: StaffApi, branchId?: number): Promise<Printer[]> {
  const body = unwrap(await api.client.GET('/v1/owner/printers', { params: { query: branchId === undefined ? {} : { branch_id: branchId } } }));
  return body.data ?? [];
}

export async function createPrinter(api: StaffApi, input: PrinterInput): Promise<Printer> {
  return unwrap(await api.client.POST('/v1/owner/printers', { body: input as never })).data as Printer;
}

export async function updatePrinter(api: StaffApi, id: number, patch: PrinterPatch): Promise<Printer> {
  return unwrap(await api.client.PATCH('/v1/owner/printers/{id}', { params: { path: { id } }, body: patch as never })).data as Printer;
}

export async function deletePrinter(api: StaffApi, id: number): Promise<void> {
  unwrap(await api.client.DELETE('/v1/owner/printers/{id}', { params: { path: { id } } }));
}

// --- Branch POS settings -------------------------------------------------------------------------------------------------------------

export type BranchPosSettings = OkData<'branchPosSettings.show'>;
export type BranchPosSettingsPatch = NonNullable<JsonRequest<'branchPosSettings.update'>>;

export const POS_MODES = ['counter', 'table', 'hybrid'] as const;
export type PosMode = (typeof POS_MODES)[number];

// The alerts the owner is told about; a key set to null switches that alert off
export const ALERT_KEYS = ['open_room_hours', 'void_amount', 'discount_percent', 'unaccepted_online_minutes'] as const;
export type AlertKey = (typeof ALERT_KEYS)[number];

export async function fetchBranchPosSettings(api: StaffApi, branchId: number): Promise<BranchPosSettings> {
  return unwrap(await api.client.GET('/v1/owner/branches/{id}/pos-settings', { params: { path: { id: branchId } } })).data;
}

// PUT: only what is sent changes. `holidays` REPLACES the whole list (the room tariff's "holiday" days come from it);
// an unknown key is a 422 and nothing is saved.
export async function saveBranchPosSettings(api: StaffApi, branchId: number, patch: BranchPosSettingsPatch): Promise<BranchPosSettings> {
  return unwrap(await api.client.PUT('/v1/owner/branches/{id}/pos-settings', { params: { path: { id: branchId } }, body: patch })).data;
}
