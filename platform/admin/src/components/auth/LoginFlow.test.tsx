import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { forgetAcceptedCode } from '@/lib/api/usedCodes';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { LoginFlow } from './LoginFlow';

const replace = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ replace, push: vi.fn() }), usePathname: () => '/login' }));

afterEach(() => {
  replace.mockReset();
  forgetAcceptedCode();
});

const anonymous = () => refusal(401, 'Unauthenticated.');
const challenge = (status: 'otp_required' | 'setup_required') => ok({ status, challenge_token: 'chal-1', expires_at: '2026-10-27T12:05:00Z' });
const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

async function fillCredentials(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Email'), 'aziza@rizqim.uz');
  await user.type(screen.getByLabelText('Parol'), 'correct horse battery');
  await user.click(screen.getByRole('button', { name: 'Kirish' }));
}

describe('LoginFlow: email, password and the 6-digit code', () => {
  it('signs in with the code from the phone app and enters the control center', async () => {
    const { callsTo } = mockFetch({
      'GET /auth/me': (() => {
        let signedIn = false;
        return () => (signedIn ? json(meBody()) : ((signedIn = true), anonymous()));
      })(),
      'POST /auth/login': () => challenge('otp_required'),
      'POST /auth/2fa/verify': () => ok({ expires_at: '2026-10-27T20:00:00Z', member: { id: 1, name: 'Aziza', email: 'aziza@rizqim.uz', platform_role: 'superadmin' } }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);

    expect(await screen.findByRole('heading', { name: 'Tasdiqlash kodi' })).toBeInTheDocument();
    await user.type(screen.getByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/'));
    expect(callsTo('POST', '/auth/login')[0]?.body).toEqual({ email: 'aziza@rizqim.uz', password: 'correct horse battery' });
    expect(callsTo('POST', '/auth/2fa/verify')[0]?.body).toEqual({ challenge_token: 'chal-1', code: '123456' });
  });

  it('asks for both fields and says a wrong password in one sentence for every cause', async () => {
    mockFetch({ 'GET /auth/me': anonymous, 'POST /auth/login': () => refusal(422, 'x', { reason: 'invalid_credentials' }, { code: 'invalid_credentials' }) });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await user.click(screen.getByRole('button', { name: 'Kirish' }));
    expect(await screen.findByText('Email va parolni kiriting.')).toBeInTheDocument();

    await fillCredentials(user);
    expect(await screen.findByText("Email yoki parol noto'g'ri.")).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('shows the lockout in the backend\'s words and a disabled account as it is', async () => {
    let answer = refusal(429, 'x', { reason: 'locked', retry_after: 900 }, { code: 'locked' });
    mockFetch({ 'GET /auth/me': anonymous, 'POST /auth/login': () => answer.clone() });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    expect(await screen.findByText("Juda ko'p xato urinish. 15 daqiqadan so'ng qayta urinib ko'ring.")).toBeInTheDocument();

    answer = refusal(403, 'x', { reason: 'member_disabled' }, { code: 'member_disabled' });
    await user.click(screen.getByRole('button', { name: 'Kirish' }));
    expect(await screen.findByText("Hisobingiz o'chirilgan. Superadministratorga murojaat qiling.")).toBeInTheDocument();
  });

  it('refuses a wrong code and lets the member type it again', async () => {
    let attempts = 0;
    mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('otp_required'),
      'POST /auth/2fa/verify': () => (++attempts === 1 ? refusal(422, 'Kod xato.', { reason: 'invalid_code' }, { code: 'invalid_code' }) : ok({ member: null })),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    await user.type(await screen.findByLabelText('6 xonali kod'), '000000');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText(/Ilovada yangi kod chiqquncha \(30 soniya\) kuting/)).toBeInTheDocument();

    await user.clear(screen.getByLabelText('6 xonali kod'));
    await user.type(screen.getByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    await waitFor(() => expect(replace).toHaveBeenCalledWith('/'));
  });

  it('takes a recovery code in place of the app code', async () => {
    const { callsTo } = mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('otp_required'),
      'POST /auth/2fa/verify': () => ok({ member: null }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    await user.click(await screen.findByRole('button', { name: 'Tiklash kodidan foydalanish' }));
    await user.type(screen.getByLabelText('Tiklash kodi'), 'abcd-1234');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    await waitFor(() => expect(callsTo('POST', '/auth/2fa/verify')).toHaveLength(1));
    expect(callsTo('POST', '/auth/2fa/verify')[0]?.body).toEqual({ challenge_token: 'chal-1', recovery_code: 'abcd-1234' });
  });

  it('starts over when the challenge ran out (5 minutes) or was used', async () => {
    mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('otp_required'),
      'POST /auth/2fa/verify': () => refusal(422, 'Muddat tugadi', { reason: 'challenge_invalid' }, { code: 'challenge_invalid' }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    await user.type(await screen.findByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText('Kirish muddati tugadi (5 daqiqa). Qaytadan boshlang.')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('sets up 2FA the first time: QR code, key to copy, first code, recovery codes with a required "saved" box', async () => {
    const { callsTo } = mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('setup_required'),
      'POST /auth/2fa/setup': () => ok({ secret: 'JBSWY3DPEHPK3PXP', otpauth_uri: 'otpauth://totp/RIZQIM:aziza@rizqim.uz?secret=JBSWY3DPEHPK3PXP&issuer=RIZQIM', qr_svg: SVG }),
      'POST /auth/2fa/confirm': () => ok({ expires_at: '2026-10-27T20:00:00Z', member: null, recovery_codes: ['aaaa-1111', 'bbbb-2222', 'cccc-3333'] }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);

    expect(await screen.findByRole('heading', { name: '2 bosqichli tasdiqlashni yoqing' })).toBeInTheDocument();
    const qr = await screen.findByRole('img', { name: 'Ilovaga ulash uchun QR kod' });
    expect(qr.getAttribute('src')).toMatch(/^data:image\/svg\+xml/);
    expect(screen.getByLabelText("QR ishlamasa, kalitni qo'lda kiriting")).toHaveValue('JBSWY3DPEHPK3PXP');

    await user.click(screen.getByRole('button', { name: 'Kalitdan nusxa olish' }));
    expect(await navigator.clipboard.readText()).toBe('JBSWY3DPEHPK3PXP');

    await user.type(screen.getByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Yoqish' }));
    expect(await screen.findByRole('heading', { name: 'Tiklash kodlaringizni saqlang' })).toBeInTheDocument();
    expect(callsTo('POST', '/auth/2fa/setup')).toHaveLength(1);
    expect(callsTo('POST', '/auth/2fa/confirm')[0]?.body).toEqual({ challenge_token: 'chal-1', code: '123456' });

    const list = screen.getByRole('list', { name: 'Tiklash kodlari' });
    expect(within(list).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['aaaa-1111', 'bbbb-2222', 'cccc-3333']);

    await user.click(screen.getByRole('button', { name: 'Nusxa olish' }));
    expect(await navigator.clipboard.readText()).toBe('aaaa-1111\nbbbb-2222\ncccc-3333');
    expect(screen.getByRole('button', { name: 'Yuklab olish (.txt)' })).toBeInTheDocument();

    // Not into the control center until the codes are saved
    const enter = screen.getByRole('button', { name: 'Boshqaruv markaziga o\'tish' });
    expect(enter).toBeDisabled();
    expect(replace).not.toHaveBeenCalled();
    await user.click(screen.getByRole('checkbox', { name: "Saqlab qo'ydim" }));
    expect(enter).toBeEnabled();
    await user.click(enter);
    expect(replace).toHaveBeenCalledWith('/');
  });

  it('downloads the recovery codes as a .txt file', async () => {
    const created: Blob[] = [];
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: (blob: Blob) => (created.push(blob), 'blob:codes'), revokeObjectURL: vi.fn() }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
    mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('setup_required'),
      'POST /auth/2fa/setup': () => ok({ secret: 'S', otpauth_uri: 'otpauth://x', qr_svg: SVG }),
      'POST /auth/2fa/confirm': () => ok({ recovery_codes: ['aaaa-1111'] }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    await user.type(await screen.findByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Yoqish' }));
    await user.click(await screen.findByRole('button', { name: 'Yuklab olish (.txt)' }));
    expect(click).toHaveBeenCalledTimes(1);
    expect(await created[0]?.text()).toContain('aaaa-1111');
  });

  it('never draws a QR code that is not an SVG', async () => {
    mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/login': () => challenge('setup_required'),
      'POST /auth/2fa/setup': () => ok({ secret: 'S', otpauth_uri: 'otpauth://x', qr_svg: '<img src=x onerror=alert(1)>' }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow />);
    await fillCredentials(user);
    await screen.findByLabelText("QR ishlamasa, kalitni qo'lda kiriting");
    expect(screen.queryByRole('img')).toBeNull();
  });
});

describe('LoginFlow: accepting an invite', () => {
  it('chooses a password (12+ characters, twice), then sets up 2FA', async () => {
    const { callsTo } = mockFetch({
      'GET /auth/me': anonymous,
      'POST /auth/invite/accept': () => challenge('setup_required'),
      'POST /auth/2fa/setup': () => ok({ secret: 'KEY', otpauth_uri: 'otpauth://x', qr_svg: SVG }),
    });
    const user = userEvent.setup();
    renderPage(<LoginFlow inviteToken="invite-token-1" />);
    expect(screen.getByRole('heading', { name: 'Taklifni qabul qilish' })).toBeInTheDocument();

    await user.type(screen.getByLabelText('Yangi parol'), 'short');
    await user.type(screen.getByLabelText('Parolni takrorlang'), 'short');
    await user.click(screen.getByRole('button', { name: 'Parolni saqlash' }));
    expect(await screen.findByText('Parol kamida 12 ta belgidan iborat bo\'lsin.')).toBeInTheDocument();

    await user.clear(screen.getByLabelText('Yangi parol'));
    await user.type(screen.getByLabelText('Yangi parol'), 'a long enough password');
    await user.click(screen.getByRole('button', { name: 'Parolni saqlash' }));
    expect(await screen.findByText('Ikkala parol bir xil emas.')).toBeInTheDocument();

    await user.clear(screen.getByLabelText('Parolni takrorlang'));
    await user.type(screen.getByLabelText('Parolni takrorlang'), 'a long enough password');
    await user.click(screen.getByRole('button', { name: 'Parolni saqlash' }));

    expect(await screen.findByRole('heading', { name: '2 bosqichli tasdiqlashni yoqing' })).toBeInTheDocument();
    expect(callsTo('POST', '/auth/invite/accept')).toHaveLength(1);
    expect(callsTo('POST', '/auth/invite/accept')[0]?.body).toEqual({ token: 'invite-token-1', password: 'a long enough password' });
  });

  it('says an old or used link in the backend\'s words', async () => {
    mockFetch({ 'GET /auth/me': anonymous, 'POST /auth/invite/accept': () => refusal(422, 'Taklif muddati tugagan.', { reason: 'invite_invalid' }, { code: 'invite_invalid' }) });
    const user = userEvent.setup();
    renderPage(<LoginFlow inviteToken="old" />);
    await user.type(screen.getByLabelText('Yangi parol'), 'a long enough password');
    await user.type(screen.getByLabelText('Parolni takrorlang'), 'a long enough password');
    await user.click(screen.getByRole('button', { name: 'Parolni saqlash' }));
    expect(await screen.findByText("Taklif havolasi eskirgan yoki ishlatilgan. Superadministratordan yangi havola so'rang.")).toBeInTheDocument();
  });
});
