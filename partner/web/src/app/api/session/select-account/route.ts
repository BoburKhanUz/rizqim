import { NextResponse, type NextRequest } from 'next/server';
import {
  backendFetch,
  clearSelectionCookie,
  failure,
  forbidden,
  isRecord,
  isSameOrigin,
  reasonOf,
  SELECTION_COOKIE,
  signedIn,
  webDevice,
} from '@/lib/session/server';

// POST /api/session/select-account {user_id}: finishes a sign-in whose phone holds several staff accounts, with the
// selection ticket /api/session/login left in its httpOnly cookie. The ticket is single use: the cookie goes once the
// sign-in succeeds or the backend refuses the ticket (`selection_invalid`), and stays after a timeout, 5xx or 429.
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return forbidden();

  const input: unknown = await request.json().catch(() => null);
  const userId = isRecord(input) && typeof input.user_id === 'number' && Number.isInteger(input.user_id) && input.user_id > 0 ? input.user_id : null;
  const ticket = request.cookies.get(SELECTION_COOKIE)?.value;
  if (userId === null || !ticket) {
    const response = NextResponse.json({ message: "Tanlov muddati tugagan. Iltimos, qaytadan kiring.", reason: 'selection_invalid' }, { status: 422 });
    clearSelectionCookie(response);
    return response;
  }

  const result = await backendFetch('/v1/auth/phone/select-account', {
    body: { selection_token: ticket, user_id: userId, device: webDevice(request) },
    request,
  });
  if (result.status < 200 || result.status >= 300) {
    const response = failure(result, 'Kirib bo\'lmadi. Birozdan so\'ng qayta urinib ko\'ring.');
    // Only a ticket the backend refused is dropped; after a timeout, 5xx or 429 it may still be good for another try
    if (reasonOf(result) === 'selection_invalid') clearSelectionCookie(response);
    return response;
  }

  return signedIn(result);
}
