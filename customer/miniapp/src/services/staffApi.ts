import { createAuthFetch, createStaffApi, setDefaultStaffApi, toApiRoot, withRevisionCapture } from '@rizqim/staff';
import { revisions } from '../lib/revisions';
import { sessionToken, signOut } from '../lib/session';
import { recoverToken } from './api';

// The API of the staff screens (@rizqim/staff, shared with the partner web), signed the way this app signs everything:
// the Sanctum token of the auth store and, on a 401, the Telegram re-login of services/api.ts (one shared single-flight
// and cool-down). The customer screens keep using the axios client of ./api.
export const staffApi = createStaffApi({
  baseUrl: toApiRoot(import.meta.env.VITE_API_URL || '/api/v1'),
  headers: { 'Bypass-Tunnel-Reminder': 'true' },
  // Wave M2: the X-Revision of the board's single-branch reads (lib/revisions.ts); the 304s are the browser cache's
  fetch: withRevisionCapture(
    createAuthFetch({
      getToken: sessionToken, // never a session stored for another Telegram user (lib/session.ts)
      renewToken: recoverToken,
      // Can't renew the session: fall back to the sign-in screen, as the axios client does
      onSignedOut: () => signOut(),
    }),
    revisions,
  ),
});

// The hooks of the package read their API from here (the app has one, for its whole life)
setDefaultStaffApi(staffApi);
