'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { call } from './client';

// M5 S5-d: the registry of POS app versions (docs/API.md "Boshqaruv markazi", /api/v1/admin/pos/releases). Reads need
// admin.devices.view; writes admin.stores.manage, a step-up (the caller handles it) and a `reason`. Typed by hand like pos.ts.

export const RELEASE_APPS = ['cashier', 'waiter', 'kds', 'customer_display'] as const;
export type ReleaseApp = (typeof RELEASE_APPS)[number];
export const RELEASE_PLATFORMS = ['android', 'windows'] as const;
export type ReleasePlatform = (typeof RELEASE_PLATFORMS)[number];

export interface ReleaseRow {
  uuid: string;
  app: ReleaseApp;
  platform: ReleasePlatform;
  version: string;
  build: number;
  label: string;
  file_url: string;
  sha256: string;
  size_bytes: number;
  notes: string | null;
  released_at: string;
  is_minimum: boolean;
  minimum_from: string | null;
  withdrawn_at: string | null;
  is_latest: boolean;
  created_at: string | null;
}

export interface ReleaseFilters {
  app: ReleaseApp | '';
  platform: ReleasePlatform | '';
  withdrawn: '' | 'yes' | 'no';
}

export const DEFAULT_RELEASE_FILTERS: ReleaseFilters = { app: '', platform: '', withdrawn: '' };

export interface ReleaseCreate {
  app: ReleaseApp;
  platform: ReleasePlatform;
  version: string;
  build: number;
  file_url: string;
  sha256: string;
  size_bytes: number;
  notes?: string | null;
  is_minimum?: boolean;
  minimum_from?: string | null;
}

// What an edit may change (app, platform, version and build stay)
export type ReleaseChange = Partial<Pick<ReleaseCreate, 'file_url' | 'sha256' | 'size_bytes' | 'notes' | 'is_minimum' | 'minimum_from'>>;

const BASE = '/pos/releases';
const one = (uuid: string) => `${BASE}/${encodeURIComponent(uuid)}`;

export async function listReleases(filters: ReleaseFilters, signal?: AbortSignal): Promise<ReleaseRow[]> {
  const query: Record<string, string | number> = {};
  if (filters.app) query.app = filters.app;
  if (filters.platform) query.platform = filters.platform;
  if (filters.withdrawn) query.withdrawn = filters.withdrawn === 'yes' ? 1 : 0;
  const answer = (await call('GET', BASE, { query, signal })).data as { data: ReleaseRow[] };
  return answer.data;
}

const row = async (promise: ReturnType<typeof call>) => (await promise).data as ReleaseRow;

export const createRelease = (input: ReleaseCreate & { reason: string }) => row(call('POST', BASE, { body: input }));
export const updateRelease = (uuid: string, change: ReleaseChange & { reason: string }) => row(call('PATCH', one(uuid), { body: change }));
export const withdrawRelease = (uuid: string, reason: string) => row(call('POST', `${one(uuid)}/withdraw`, { body: { reason } }));
export const restoreRelease = (uuid: string, reason: string) => row(call('POST', `${one(uuid)}/restore`, { body: { reason } }));
export const deleteRelease = async (uuid: string, reason: string) => {
  await call('DELETE', one(uuid), { body: { reason } });
};

export const RELEASE_KEYS = {
  // Under the POS section's key, so every POS write also refreshes this list (and the "outdated" counts follow it)
  list: (filters: ReleaseFilters) => ['admin-pos', 'releases', filters] as const,
};

export const useReleases = (filters: ReleaseFilters, enabled = true) =>
  useQuery({ queryKey: RELEASE_KEYS.list(filters), queryFn: ({ signal }) => listReleases(filters, signal), enabled });

function useReleaseWrite<I, R>(fn: (input: I) => Promise<R>) {
  const client = useQueryClient();
  return useMutation({ mutationFn: fn, onSuccess: () => client.invalidateQueries({ queryKey: ['admin-pos'] }) });
}

export const useCreateRelease = () => useReleaseWrite(createRelease);
export const useUpdateRelease = () => useReleaseWrite(({ uuid, ...change }: ReleaseChange & { uuid: string; reason: string }) => updateRelease(uuid, change));
export const useWithdrawRelease = () => useReleaseWrite(({ uuid, reason }: { uuid: string; reason: string }) => withdrawRelease(uuid, reason));
export const useRestoreRelease = () => useReleaseWrite(({ uuid, reason }: { uuid: string; reason: string }) => restoreRelease(uuid, reason));
export const useDeleteRelease = () => useReleaseWrite(({ uuid, reason }: { uuid: string; reason: string }) => deleteRelease(uuid, reason));
