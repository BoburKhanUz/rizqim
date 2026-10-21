import { NextResponse, type NextRequest } from 'next/server';
import { backendFetch, clearRefreshCookie, DEVICE_COOKIE, forbidden, forgetRefresh, isRecord, isSameOrigin, REFRESH_COOKIE, refreshOnce } from '@/lib/session/server';

// POST /api/session/logout: ends the session on the backend (a device-bound token also ends the device's refresh
// tokens) and clears the cookie. The cookie goes even when the backend cannot be reached: the browser is signed out
// either way.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const pageToken = request.headers.get('authorization')?.match(/^Bearer (.+)$/i)?.[1];
  const refreshToken = request.cookies.get(REFRESH_COOKIE)?.value;
  const deviceUuid = request.cookies.get(DEVICE_COOKIE)?.value;

  // The page's access token may be missing or expired (a tab left idle, another tab renewed the session): trade the
  // refresh token for a fresh one so the backend can still end the session
  const renewedToken = async () => {
    if (!refreshToken || !deviceUuid) return undefined;
    const renewed = await refreshOnce(refreshToken, deviceUuid, request);
    const data = isRecord(renewed.body?.data) ? renewed.body.data : null;
    return data && typeof data.token === 'string' ? data.token : undefined;
  };

  let token = pageToken ?? (await renewedToken());
  if (token) {
    const result = await backendFetch('/v1/auth/logout', { token, request });
    if (result.status === 401 && pageToken) {
      token = await renewedToken();
      if (token) await backendFetch('/v1/auth/logout', { token, request });
    }
  }
  if (refreshToken && deviceUuid) forgetRefresh(refreshToken, deviceUuid);

  const response = NextResponse.json({ ok: true });
  clearRefreshCookie(response);
  return response;
}
