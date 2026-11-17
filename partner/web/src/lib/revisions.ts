import { RevisionTracker } from '@rizqim/staff';

// Wave M2: the change tokens the board holds (docs/API.md §7.1, §8): the staff API's fetch records the X-Revision of
// every single-branch read (lib/session/client.ts getStaffApi), the board asks what an event or a heartbeat means
// (components/shell/LiveOrders.tsx). The browser reads the header of another origin only when the backend's CORS
// settings expose it; without it every channel counts as untracked and refetches on any event, as before.
export const revisions = new RevisionTracker();
