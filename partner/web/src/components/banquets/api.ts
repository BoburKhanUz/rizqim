import { unwrap, type StaffApi } from '@rizqim/staff';

// The banquet calendar and events of P6-51/P6-52 (docs/API.md "Tadbirlar"): halls × days × slots, an event with its
// lines and totals, the hold, changes and moves, freezing the prices on the contract day; the owner's packages, extras,
// hall rent and settings. Called through the generated client's untyped door and read into the types below, so these
// screens do not wait for the spec to be regenerated; the server does every sum and every conflict check.

type Query = Record<string, string | number | boolean | undefined>;
interface Init {
  params?: { query?: Query; path?: Record<string, string | number> };
  body?: unknown;
  headers?: Record<string, string>;
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
  PATCH(path: string, init?: Init): Promise<Result>;
  DELETE(path: string, init?: Init): Promise<Result>;
}

const door = (api: StaffApi): Door => api.client as unknown as Door;

async function data<T>(promise: Promise<Result>): Promise<T> {
  const body = unwrap(await promise) as { data?: T } | undefined;
  return body?.data as T;
}

export const newKey = (): string => (typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);

export type EventStatus = 'hold' | 'confirmed' | 'in_progress' | 'settled' | 'cancelled';
export type Slot = 'morning' | 'day' | 'evening' | 'custom';
export const NAMED_SLOTS: readonly Exclude<Slot, 'custom'>[] = ['morning', 'day', 'evening'];
export const SLOTS: readonly Slot[] = ['morning', 'day', 'evening', 'custom'];
export type EventType = 'toy' | 'osh' | 'birthday' | 'corporate' | 'other';
export const EVENT_TYPES: readonly EventType[] = ['toy', 'osh', 'birthday', 'corporate', 'other'];
export type LineKind = 'package' | 'hall_rent' | 'extra' | 'order';
export type Pricing = 'per_event' | 'per_person';

export interface SlotTime {
  start: string;
  end: string;
}
export type SlotTimes = Record<Exclude<Slot, 'custom'>, SlotTime>;

export interface EventSummary {
  uuid: string;
  branch_id: number;
  event_date: string;
  slot: Slot;
  slot_label: string;
  start_time: string;
  end_time: string;
  starts_at: string;
  ends_at: string;
  hall_ids: number[];
  halls: Array<{ id: number; name: string }>;
  event_type: EventType;
  event_type_label: string;
  status: EventStatus;
  status_label: string;
  title: string | null;
  customer_name: string;
  customer_phone: string | null;
  guests_guaranteed: number;
  guests_minimum: number;
  guests_actual: number | null;
  // max(guaranteed, minimum): what the package is billed for before the day
  guests_billed: number;
  package_id: number | null;
  package_name: string | null;
  total: string;
  paid_total: string;
  outstanding: string;
  hold_expires_at: string | null;
  frozen: boolean;
  contract_no: string | null;
  version: number;
  /** An unpaid schedule row is past its due date (the calendar badge; older servers omit it). */
  overdue?: boolean;
}

export interface EventLine {
  uuid: string;
  kind: LineKind;
  kind_label: string;
  name: string;
  extra_id: number | null;
  hall_id: number | null;
  quantity: string;
  persons: number | null;
  per_person: boolean;
  unit_price: string;
  amount: string;
  frozen: boolean;
  note: string | null;
}

export interface EventWarning {
  code: string;
  message: string;
}

export interface BanquetEvent extends EventSummary {
  package_snapshot: {
    name: string;
    per_person_price: string;
    minimum_guests: number;
    included: string[];
  } | null;
  package_total: string;
  hall_total: string;
  extras_total: string;
  orders_total: string;
  // Display only: every amount is so'm
  quote: { currency: string; rate: string; total: string } | null;
  frozen_at: string | null;
  note: string | null;
  order_uuid: string | null;
  cancel_reason: string | null;
  confirmed_at: string | null;
  cancelled_at: string | null;
  released_at: string | null;
  lines: EventLine[];
  payments: Array<{
    uuid: string;
    kind: string;
    kind_label: string;
    due_date: string | null;
    amount: string;
    status: string;
    status_label: string;
    tender: string | null;
    paid_at: string | null;
  }>;
  warnings: EventWarning[];
}

export interface CalendarHall {
  id: number;
  name: string;
  is_active: boolean;
  events: EventSummary[];
}

export interface Calendar {
  branch_id: number;
  from: string;
  to: string;
  timezone: string;
  slots: SlotTimes;
  halls: CalendarHall[];
}

export interface PackageItem {
  id?: number;
  product_id: number | null;
  product_variant_id: number | null;
  name: string;
  quantity_per_person: string;
  unit: 'portion' | 'piece' | 'kg' | 'l';
}

export interface Package {
  id: number;
  uuid: string;
  name: string;
  per_person_price: string;
  minimum_guests: number;
  included: string[];
  description: string | null;
  is_active: boolean;
  sort_order: number;
  items: PackageItem[];
}

export interface Extra {
  id: number;
  uuid: string;
  name: string;
  price: string;
  pricing: Pricing;
  pricing_label: string;
  is_active: boolean;
  sort_order: number;
}

export interface HallRates {
  hall_id: number;
  hall_name: string;
  branch_id: number;
  is_active: boolean;
  rates: Record<Slot, string | null>;
}

export interface Settings {
  slots: SlotTimes;
  hold_hours: number;
  guest_warning_thresholds: number[];
  window_start: string;
  window_end: string;
  advance_mxik: string | null;
  advance_package_code: string | null;
  cancellation_mxik: string | null;
  cancellation_package_code: string | null;
  quote_currency: string | null;
  version: number;
}

export interface Catalog {
  packages: Package[];
  extras: Extra[];
  halls: HallRates[];
  settings: Settings;
  event_types: Array<{ value: EventType; label: string }>;
}

export interface ExtraInput {
  line_uuid?: string;
  extra_id?: number;
  name?: string;
  unit_price?: string;
  per_person?: boolean;
  quantity?: string;
}

// The fields of a hold or a change; only what is sent changes
export interface EventInput {
  branch_id?: number;
  event_date?: string;
  slot?: Slot;
  start_time?: string | null;
  end_time?: string | null;
  hall_ids?: number[];
  event_type?: EventType;
  title?: string | null;
  customer_name?: string;
  customer_phone?: string | null;
  guests_guaranteed?: number;
  guests_minimum?: number;
  banquet_package_id?: number | null;
  extras?: ExtraInput[];
  quote_currency?: string | null;
  quote_rate?: string | null;
  note?: string | null;
  version?: number;
}

export const fetchCalendar = (
  api: StaffApi,
  query: {
    branch_id: number;
    from: string;
    to: string;
    include_cancelled?: boolean;
  },
) =>
  data<Calendar>(
    door(api).GET('/v1/pos/banquets/calendar', {
      params: {
        query: {
          ...query,
          include_cancelled: query.include_cancelled ? 1 : undefined,
        },
      },
    }),
  );

export const fetchCatalog = (api: StaffApi, branchId: number) =>
  data<Catalog>(
    door(api).GET('/v1/pos/banquets/catalog', {
      params: { query: { branch_id: branchId } },
    }),
  );

export const fetchEvent = (api: StaffApi, uuid: string) => data<BanquetEvent>(door(api).GET(`/v1/pos/banquets/${uuid}`));

export const createEvent = (api: StaffApi, input: EventInput, key: string) =>
  data<BanquetEvent>(
    door(api).POST('/v1/pos/banquets', {
      body: input,
      headers: { 'Idempotency-Key': key },
    }),
  );

export const updateEvent = (api: StaffApi, uuid: string, input: EventInput, key: string) =>
  data<BanquetEvent>(
    door(api).PATCH(`/v1/pos/banquets/${uuid}`, {
      body: input,
      headers: { 'Idempotency-Key': key },
    }),
  );

export const freezeEvent = (api: StaffApi, uuid: string, version: number, key: string) =>
  data<BanquetEvent>(
    door(api).POST(`/v1/pos/banquets/${uuid}/freeze`, {
      body: { version },
      headers: { 'Idempotency-Key': key },
    }),
  );

export const unfreezeEvent = (api: StaffApi, uuid: string, version: number, reason: string, key: string) =>
  data<BanquetEvent>(
    door(api).POST(`/v1/pos/banquets/${uuid}/unfreeze`, {
      body: { version, reason },
      headers: { 'Idempotency-Key': key },
    }),
  );

// The owner's setup (a web sign-in)
const OWNER = '/v1/owner/pos/banquets';

export const fetchSettings = (api: StaffApi) => data<Settings>(door(api).GET(`${OWNER}/settings`));
export const saveSettings = (api: StaffApi, input: Partial<Omit<Settings, 'version'>>) => data<Settings>(door(api).PUT(`${OWNER}/settings`, { body: input }));

export const fetchPackages = (api: StaffApi) => data<{ packages: Package[] }>(door(api).GET(`${OWNER}/packages`)).then((d) => d.packages);
export type PackageInput = Partial<Omit<Package, 'id' | 'uuid' | 'items'>> & {
  items?: Array<Omit<PackageItem, 'id'>>;
};
export const savePackage = (api: StaffApi, id: number | null, input: PackageInput) =>
  data<Package>(id === null ? door(api).POST(`${OWNER}/packages`, { body: input }) : door(api).PATCH(`${OWNER}/packages/${id}`, { body: input }));
export const deletePackage = (api: StaffApi, id: number) => data<{ deleted: boolean }>(door(api).DELETE(`${OWNER}/packages/${id}`));

export const fetchExtras = (api: StaffApi) => data<{ extras: Extra[] }>(door(api).GET(`${OWNER}/extras`)).then((d) => d.extras);
export type ExtraCatalogInput = Partial<Pick<Extra, 'name' | 'price' | 'pricing' | 'is_active' | 'sort_order'>>;
export const saveExtra = (api: StaffApi, id: number | null, input: ExtraCatalogInput) =>
  data<Extra>(id === null ? door(api).POST(`${OWNER}/extras`, { body: input }) : door(api).PATCH(`${OWNER}/extras/${id}`, { body: input }));
export const deleteExtra = (api: StaffApi, id: number) => data<{ deleted: boolean }>(door(api).DELETE(`${OWNER}/extras/${id}`));

export const fetchHalls = (api: StaffApi, branchId: number | null) =>
  data<{ halls: HallRates[] }>(
    door(api).GET(`${OWNER}/halls`, {
      params: { query: { branch_id: branchId ?? undefined } },
    }),
  ).then((d) => d.halls);
export const saveHallRates = (api: StaffApi, hallId: number, rates: Partial<Record<Slot, string | null>>) => data<HallRates>(door(api).PUT(`${OWNER}/halls/${hallId}/rates`, { body: { rates } }));
