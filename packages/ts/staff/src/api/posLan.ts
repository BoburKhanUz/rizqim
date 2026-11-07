import type { StaffApi } from './client';
import { unwrap } from './errors';
import type { OkData } from './types';

// P6 local bridge (docs/API.md "Lokal ko'prik (P6)", docs/POS_SPEC.md §5.5): which till of a branch runs the bridge that
// serves the waiters' phones, the kitchen and customer screens over Wi-Fi when the internet is down, its port and the
// branch's key version. The owner (`settings.manage`, never from a terminal's PIN session) picks the hub and rotates the
// key; the key itself never reaches the panel.

// The POS app a terminal runs, as it said when it paired (P6 stage B); null: paired before the app was recorded
export const POS_APPS = ['cashier', 'waiter', 'kds', 'customer_display'] as const;
export type PosApp = (typeof POS_APPS)[number];

type GeneratedBranchLan = OkData<'branchLan.show'>;
// `app` (P6 stage B) is read here until the spec is regenerated with it
export type BranchLanCandidate = GeneratedBranchLan['candidates'][number] & { app?: PosApp | null };
export type BranchLan = Omit<GeneratedBranchLan, 'candidates'> & { candidates: BranchLanCandidate[] };

// The default port of the bridge (the server's when the owner never changed it)
export const LAN_DEFAULT_PORT = 18480;

export interface BranchLanPatch {
  // An active till (uuid) of the branch; null: back to the default (the printing till, else the first cashier till)
  hub_device_id?: string | null;
  // 1024–65535
  port?: number;
}

// GET /owner/pos/lan?branch_id=
export async function fetchBranchLan(api: StaffApi, branchId: number): Promise<BranchLan> {
  return unwrap(await api.client.GET('/v1/owner/pos/lan', { params: { query: { branch_id: branchId } } })).data as BranchLan;
}

// PUT /owner/pos/lan: only what changes; the answer is the branch's whole view. Audited (settings.pos_lan_changed)
export async function saveBranchLan(api: StaffApi, branchId: number, patch: BranchLanPatch): Promise<BranchLan> {
  // The spec does not list `branch_id` in the body yet (the server reads it with the rest)
  return unwrap(await api.client.PUT('/v1/owner/pos/lan', { body: { branch_id: branchId, ...patch } as never })).data as BranchLan;
}

// POST /owner/pos/lan/rotate: a new branch key (`key_id` + 1). Every terminal takes its new keys with its next sign-in or
// restart and cannot join the bridge until then. Audited (pos.lan_key_rotated)
export async function rotateBranchLanKey(api: StaffApi, branchId: number): Promise<BranchLan> {
  return unwrap(await api.client.POST('/v1/owner/pos/lan/rotate', { body: { branch_id: branchId } as never })).data as BranchLan;
}
