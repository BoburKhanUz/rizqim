import { RevisionTracker } from '@rizqim/staff';

// Wave M2: the change tokens this app holds (docs/API.md §7.1, §8). Both HTTP layers feed it: the axios client of the
// customer screens (services/api.ts) and the fetch of the staff screens (services/staffApi.ts) record the
// X-Revision / X-Revision-Scope of every single-scope read; the real-time hooks ask it what an event or a heartbeat
// means (hooks/useRealtimeOrders.ts). One per page load; cleared when the account changes.
export const revisions = new RevisionTracker();
