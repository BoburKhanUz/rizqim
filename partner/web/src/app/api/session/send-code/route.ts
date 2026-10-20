import { NextResponse, type NextRequest } from 'next/server';
import { backendFetch, failure, forbidden, isRecord, isSameOrigin } from '@/lib/session/server';

// POST /api/session/send-code {phone}: asks the backend to text a sign-in code. The code the backend may return in
// local development is not passed on.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const input: unknown = await request.json().catch(() => null);
  const phone = isRecord(input) && typeof input.phone === 'string' ? input.phone : '';
  if (!/^\+998\d{9}$/.test(phone)) {
    return NextResponse.json({ message: 'Telefon raqami noto\'g\'ri.', reason: 'invalid_phone' }, { status: 422 });
  }

  const result = await backendFetch('/v1/auth/phone/send-code', { body: { phone } });
  if (result.status < 200 || result.status >= 300) return failure(result, 'SMS yuborib bo\'lmadi. Birozdan so\'ng qayta urinib ko\'ring.');
  return NextResponse.json({ ok: true });
}
