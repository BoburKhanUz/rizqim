import { NextResponse, type NextRequest } from 'next/server';
import {
  accountChoices,
  backendFetch,
  failure,
  forbidden,
  isRecord,
  isSameOrigin,
  messageOf,
  setSelectionCookie,
  signedIn,
  webDevice,
} from '@/lib/session/server';

// POST /api/session/login {phone, code}: verifies the SMS code with the backend, signing in to the store staff account
// that holds the phone (`account: staff`, docs/AUTH.md) and registering this browser as a `web` device. The refresh
// token goes into an httpOnly cookie; the page gets only the short-lived access token.
//
// A phone holding several staff accounts (the same person in two stores) answers 409 `account_selection_required` with
// the accounts to choose from; the backend's selection ticket stays in an httpOnly cookie for /api/session/select-account.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const input: unknown = await request.json().catch(() => null);
  const phone = isRecord(input) && typeof input.phone === 'string' ? input.phone : '';
  const code = isRecord(input) && typeof input.code === 'string' ? input.code : '';
  if (!/^\+998\d{9}$/.test(phone) || !/^\d{6}$/.test(code)) {
    return NextResponse.json({ message: 'Telefon yoki kod noto\'g\'ri.', reason: 'invalid_input' }, { status: 422 });
  }

  const result = await backendFetch('/v1/auth/phone/verify', {
    body: { phone, code, account: 'staff', device: webDevice(request) },
    request,
  });

  const choices = accountChoices(result);
  if (choices) {
    const response = NextResponse.json(
      { message: messageOf(result) ?? 'Hisobni tanlang.', reason: 'account_selection_required', accounts: choices.accounts },
      { status: 409 },
    );
    setSelectionCookie(response, choices.ticket);
    return response;
  }
  if (result.status < 200 || result.status >= 300) return failure(result, 'Kirib bo\'lmadi. Birozdan so\'ng qayta urinib ko\'ring.');

  return signedIn(result);
}
