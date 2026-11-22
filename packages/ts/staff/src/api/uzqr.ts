import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// The UzQR credentials (Wave P; docs/API.md "UzQR kassada"): the store's UzQR driver and STIR (the fiscal settings' `tin`),
// and each POS till's own UzQR API key — the restaurant creates one dynamic QR and one API key per till in uzqr.uz. The key
// is write-only: the answers say only whether it is set and its last four characters. A masked or empty `api_key` keeps the
// stored one; `remove_api_key` clears it. settings.manage, never from a terminal's PIN session.

export type UzQrSettingsView = OkData<'uzQrSettings.index'>;
export type UzQrTill = UzQrSettingsView['tills'][number];
export type UzQrTillPatch = JsonRequest<'uzQrSettings.updateTill'>;

export async function fetchUzQrSettings(api: StaffApi, branchId?: number): Promise<UzQrSettingsView> {
  const query = branchId === undefined ? {} : { branch_id: branchId };
  return unwrap(await api.client.GET('/v1/owner/fiscal/uzqr', { params: { query } })).data;
}

export async function saveUzQrTillKey(api: StaffApi, uuid: string, patch: UzQrTillPatch): Promise<UzQrTill> {
  return unwrap(await api.client.PUT('/v1/owner/fiscal/uzqr/tills/{uuid}', { params: { path: { uuid } }, body: patch })).data;
}
