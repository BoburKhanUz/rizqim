import { NextResponse, type NextRequest } from 'next/server';
import { backendFetch, clearRefreshCookie, DEVICE_COOKIE, forbidden, isRecord, isSameOrigin, REFRESH_COOKIE } from '@/lib/session/server';

// POST /api/session/logout: ends the session on the backend (a device-bound token also ends the device's refresh
// tokens) and clears the cookie. The cookie goes even when the backend cannot be reached: the browser is signed out
// either way.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  let token = request.headers.get('authorization')?.match(/^Bearer (.+)$/i)?.[1];

  // The page's access token may have expired: trade the refresh token for a fresh one so the backend can end the session
  const refreshToken = request.cookies.get(REFRESH_COOKIE)?.value;
  const deviceUuid = request.cookies.get(DEVICE_COOKIE)?.value;
  if (!token && refreshToken && deviceUuid) {
    const renewed = await backendFetch('/v1/auth/refresh', { body: { refresh_token: refreshToken, device_uuid: deviceUuid }, request });
    const data = isRecord(renewed.body?.data) ? renewed.body.data : null;
    if (data && typeof data.token === 'string') token = data.token;
  }

  if (token) await backendFetch('/v1/auth/logout', { token, request });

  const response = NextResponse.json({ ok: true });
  clearRefreshCookie(response);
  return response;
}
