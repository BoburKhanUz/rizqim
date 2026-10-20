import { NextResponse, type NextRequest } from 'next/server';
import {
  backendFetch,
  clearRefreshCookie,
  DEVICE_COOKIE,
  failure,
  forbidden,
  isRecord,
  isSameOrigin,
  REFRESH_COOKIE,
  sessionPayload,
  setSessionCookies,
} from '@/lib/session/server';

// POST /api/session/refresh: spends the refresh token from the cookie and answers with a new access token; the cookie
// is replaced by the rotated token. A refused token (expired, used twice, revoked) signs this browser out: 401 and the
// cookie is cleared. An unreachable backend is 502 and the cookie stays, so a hiccup does not log anyone out.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const refreshToken = request.cookies.get(REFRESH_COOKIE)?.value;
  const deviceUuid = request.cookies.get(DEVICE_COOKIE)?.value;
  if (!refreshToken || !deviceUuid) {
    return NextResponse.json({ message: 'Kirish talab qilinadi.', reason: 'no_session' }, { status: 401 });
  }

  const result = await backendFetch('/v1/auth/refresh', { body: { refresh_token: refreshToken, device_uuid: deviceUuid }, request });

  if (result.status === 401 || result.status === 403 || result.status === 422) {
    const response = failure({ ...result, status: 401 }, 'Seans tugagan. Qaytadan kiring.');
    clearRefreshCookie(response);
    return response;
  }
  if (result.status < 200 || result.status >= 300) return failure(result, 'Serverga ulanib bo\'lmadi.');

  const data = isRecord(result.body?.data) ? result.body.data : null;
  const device = data && isRecord(data.device) ? data.device : null;
  const payload = data ? sessionPayload(data, device?.user) : null;
  if (!data || !payload || typeof data.refresh_token !== 'string') {
    return NextResponse.json({ message: 'Server kutilmagan javob qaytardi.', reason: 'bad_gateway' }, { status: 502 });
  }

  const response = NextResponse.json(payload);
  setSessionCookies(response, data.refresh_token, data.refresh_token_expires_at, deviceUuid);
  return response;
}
