import { randomUUID } from 'node:crypto';
import { NextResponse, type NextRequest } from 'next/server';
import {
  backendFetch,
  DEVICE_COOKIE,
  failure,
  forbidden,
  isRecord,
  isSameOrigin,
  sessionPayload,
  setSessionCookies,
} from '@/lib/session/server';

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// POST /api/session/login {phone, code}: verifies the SMS code with the backend, registering this browser as a `web`
// device. The refresh token goes into an httpOnly cookie; the page gets only the short-lived access token.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const input: unknown = await request.json().catch(() => null);
  const phone = isRecord(input) && typeof input.phone === 'string' ? input.phone : '';
  const code = isRecord(input) && typeof input.code === 'string' ? input.code : '';
  if (!/^\+998\d{9}$/.test(phone) || !/^\d{6}$/.test(code)) {
    return NextResponse.json({ message: 'Telefon yoki kod noto\'g\'ri.', reason: 'invalid_input' }, { status: 422 });
  }

  // The same browser keeps the same device across sign-ins (the backend updates it instead of creating a new one)
  const known = request.cookies.get(DEVICE_COOKIE)?.value;
  const result = await backendFetch('/v1/auth/phone/verify', {
    body: {
      phone,
      code,
      device: {
        type: 'web',
        uuid: known && UUID.test(known) ? known : randomUUID(),
        name: 'Hamkor veb-panel',
        platform: 'web',
      },
    },
  });
  if (result.status < 200 || result.status >= 300) return failure(result, 'Kirib bo\'lmadi. Birozdan so\'ng qayta urinib ko\'ring.');

  const data = isRecord(result.body?.data) ? result.body.data : null;
  const device = data && isRecord(data.device) ? data.device : null;
  const payload = data ? sessionPayload(data, data.user) : null;
  if (!data || !payload || typeof data.refresh_token !== 'string' || !device || typeof device.uuid !== 'string') {
    return NextResponse.json({ message: 'Server kutilmagan javob qaytardi.', reason: 'bad_gateway' }, { status: 502 });
  }

  const response = NextResponse.json(payload);
  setSessionCookies(response, data.refresh_token, data.refresh_token_expires_at, device.uuid);
  return response;
}
