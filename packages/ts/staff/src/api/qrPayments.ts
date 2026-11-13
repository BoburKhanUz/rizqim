import type { StaffApi } from './client';
import { unwrap } from './errors';

// A branch's QR payments at the till (P6-41/P6-42; docs/API.md "QR to'lov kassada"): which providers the cashier may offer
// (Click, Payme, UzQR), the one offered first, how long a QR stays valid, and whether the store's own merchant account for
// each is set up (`merchants`; the keys are edited in the store's payment settings, UzQR's gateway in the fiscal settings).
// settings.manage (the owner), never from a terminal's PIN session; changing needs the `qr_payments` module (403
// module_disabled). The tills read the same block from GET /pos/bootstrap `qr_payments`.
// The route is not in the generated spec yet: its path is borrowed from a route that is (the cast keeps the call typed) and the
// answer is read as the contract says. Replace by the generated types once the spec has it.

export const QR_PROVIDERS = ['click', 'payme', 'uzqr'] as const;
export type QrProviderKey = (typeof QR_PROVIDERS)[number];

export interface QrPaymentSettings {
  branch_id: number;
  // the branch has the qr_payments module
  enabled: boolean;
  providers: QrProviderKey[];
  default_provider: QrProviderKey | null;
  expiry_minutes: number;
  updated_at: string | null;
}

export interface QrMerchantStatus {
  provider: QrProviderKey;
  label: string;
  // the store's own merchant account (Click, Payme) or the UzQR gateway is set up
  configured: boolean;
  // click | payme | sandbox | uzqr:<driver> | uzqr:none
  driver: string;
}

export interface QrPaymentSettingsView {
  settings: QrPaymentSettings;
  merchants: QrMerchantStatus[];
  options: { providers: Array<{ key: QrProviderKey; label: string }>; expiry_min: number; expiry_max: number };
}

// PUT body: only what changes
export type QrPaymentSettingsPatch = Partial<Pick<QrPaymentSettings, 'providers' | 'default_provider' | 'expiry_minutes'>>;

const QR_SETTINGS_PATH = '/v1/owner/pos/qr-payments' as '/v1/owner/pos/tips/settings';

export async function fetchQrPaymentSettings(api: StaffApi, branchId: number): Promise<QrPaymentSettingsView> {
  return unwrap(await api.client.GET(QR_SETTINGS_PATH, { params: { query: { branch_id: branchId } } })).data as unknown as QrPaymentSettingsView;
}

export async function saveQrPaymentSettings(api: StaffApi, branchId: number, patch: QrPaymentSettingsPatch): Promise<QrPaymentSettingsView> {
  return unwrap(await api.client.PUT(QR_SETTINGS_PATH, { body: { branch_id: branchId, ...patch } as never })).data as unknown as QrPaymentSettingsView;
}
