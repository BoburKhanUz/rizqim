import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { JsonRequest, OkData } from './types';

// GET /owner/settings: the owner's own store.
export type OwnerSettings = OkData<'ownerSettings.index'>;
// Its payment_settings: one entry per provider (a bare boolean in legacy data; never a secret, only has_secret_key...),
// the delivery fee and switches, the working hours.
export type OwnerPaymentSettings = OwnerSettings['payment_settings'];
export type PaymentProviderSettings = Exclude<NonNullable<OwnerPaymentSettings['cash']>, boolean>;
// POST /owner/settings
export type OwnerSettingsInput = JsonRequest<'ownerSettings.update'>;

export async function fetchOwnerSettings(api: StaffApi): Promise<OwnerSettings | null> {
  const body = unwrap(await api.client.GET('/v1/owner/settings'));
  return body.data ?? null;
}

// POST /owner/settings: 422 on `payment_settings` when a provider that cannot be enabled yet was switched on.
export async function saveOwnerSettings(api: StaffApi, input: OwnerSettingsInput): Promise<void> {
  unwrap(await api.client.POST('/v1/owner/settings', { body: input }));
}

export type UploadedFile = OkData<'ownerSettings.upload'>;

// POST /owner/settings/upload: stores an image (logo, banner) and answers its path (what the settings save) and url.
export async function uploadSettingsFile(api: StaffApi, file: File | Blob): Promise<UploadedFile> {
  const form = new FormData();
  form.append('file', file);
  const body = unwrap(await api.client.POST('/v1/owner/settings/upload', { body: form as never, bodySerializer: (value: unknown) => value as FormData }));
  return body.data;
}
