import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { OwnerSettings, OwnerSettingsInput } from './specGaps';
import type { OkData } from './types';

// GET /owner/settings: the owner's own store (the spec types two of its fields wrongly: api/specGaps.ts).
export async function fetchOwnerSettings(api: StaffApi): Promise<OwnerSettings | null> {
  const body = unwrap(await api.client.GET('/v1/owner/settings'));
  return (body.data as unknown as OwnerSettings | null | undefined) ?? null;
}

// POST /owner/settings: 422 on `payment_settings` when a provider that cannot be enabled yet was switched on.
export async function saveOwnerSettings(api: StaffApi, input: OwnerSettingsInput): Promise<void> {
  unwrap(await api.client.POST('/v1/owner/settings', { body: input as never }));
}

export type UploadedFile = OkData<'ownerSettings.upload'>;

// POST /owner/settings/upload: stores an image (logo, banner) and answers its path (what the settings save) and url.
export async function uploadSettingsFile(api: StaffApi, file: File | Blob): Promise<UploadedFile> {
  const form = new FormData();
  form.append('file', file);
  const body = unwrap(await api.client.POST('/v1/owner/settings/upload', { body: form as never, bodySerializer: (value: unknown) => value as FormData }));
  return body.data;
}
