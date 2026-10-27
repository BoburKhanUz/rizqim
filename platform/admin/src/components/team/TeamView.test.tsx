import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';
import { TeamView } from './TeamView';

const member = (overrides: Record<string, unknown>) => ({
  id: 2,
  name: 'Sardor Operator',
  email: 'sardor@rizqim.uz',
  platform_role: 'operator',
  disabled: false,
  invite_pending: false,
  two_factor_enabled: true,
  last_login_at: '2026-10-26T09:00:00+05:00',
  active_sessions_count: 2,
  ...overrides,
});

const TEAM = [
  member({ id: 1, name: 'Aziza Karimova', email: 'aziza@rizqim.uz', platform_role: 'superadmin', active_sessions_count: 1 }),
  member({}),
  member({ id: 3, name: 'Dilnoza Moliya', email: 'dilnoza@rizqim.uz', platform_role: 'finance', two_factor_enabled: false, last_login_at: null, active_sessions_count: 0, disabled: true }),
  member({ id: 4, name: 'Bekzod Yangi', email: 'bekzod@rizqim.uz', platform_role: 'sales', invite_pending: true, two_factor_enabled: false, last_login_at: null, active_sessions_count: 0 }),
];

const card = (name: string) => screen.getByRole('heading', { name: new RegExp(`^${name}`) }).closest('li') as HTMLElement;

function setup(extra: Parameters<typeof mockFetch>[0] = {}, permissions?: string[]) {
  const mocks = mockFetch({ 'GET /auth/me': () => json(meBody(permissions ? { permissions } : {})), 'GET /team': () => ok(TEAM), ...extra });
  renderPage(<TeamView />);
  return { ...mocks, user: userEvent.setup() };
}

describe('Jamoa', () => {
  it('lists the team with role, 2FA, last sign-in and sessions', async () => {
    setup();
    expect(await screen.findByRole('heading', { name: 'Sardor Operator' })).toBeInTheDocument();
    const sardor = within(card('Sardor Operator'));
    expect(sardor.getByText('Operator')).toBeInTheDocument();
    expect(sardor.getByText('2FA yoqilgan')).toBeInTheDocument();
    expect(sardor.getByText('Faol')).toBeInTheDocument();
    expect(sardor.getByText('Faol seanslar').nextSibling).toHaveTextContent('2');

    const dilnoza = within(card('Dilnoza Moliya'));
    expect(dilnoza.getByText('2FA yoqilmagan')).toBeInTheDocument();
    expect(dilnoza.getByText("O'chirilgan")).toBeInTheDocument();
    expect(dilnoza.getByText('hali kirmagan')).toBeInTheDocument();
    expect(within(card('Bekzod Yangi')).getByText('Taklif kutilmoqda')).toBeInTheDocument();
  });

  it('gives the member no buttons on their own row', async () => {
    setup();
    await screen.findByRole('heading', { name: /^Aziza Karimova/ });
    const own = within(card('Aziza Karimova'));
    expect(own.getByText('Siz')).toBeInTheDocument();
    expect(own.queryByRole('button')).toBeNull();
    expect(own.getByText("O'zingizni o'zgartira olmaysiz.")).toBeInTheDocument();
  });

  it('is closed to a member without team.manage', async () => {
    const { callsTo } = setup({}, ['admin.today.view']);
    expect(await screen.findByText("Bu amal uchun ruxsatingiz yo'q.")).toBeInTheDocument();
    expect(callsTo('GET', '/team')).toHaveLength(0);
  });

  it('invites a member and shows the one-time link with a copy button', async () => {
    const { callsTo, user } = setup({
      'POST /team': () => ok({ member: member({ id: 9, name: 'Yangi Xodim', email: 'yangi@rizqim.uz', invite_pending: true }), invite: { url: 'https://admin.rizqim.uz/invite/tok-123', expires_at: '2026-10-30T12:00:00Z' } }),
    });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(screen.getByRole('button', { name: 'Xodim taklif qilish' }));

    const dialog = await screen.findByRole('dialog', { name: 'Yangi xodim taklif qilish' });
    await user.click(within(dialog).getByRole('button', { name: 'Taklif havolasini yaratish' }));
    expect(await within(dialog).findByText('Ism va to\'g\'ri email kiriting.')).toBeInTheDocument();

    await user.type(within(dialog).getByLabelText('Ism'), 'Yangi Xodim');
    await user.type(within(dialog).getByLabelText('Email'), 'yangi@rizqim.uz');
    await user.selectOptions(within(dialog).getByLabelText('Roli'), 'support');
    await user.click(within(dialog).getByRole('button', { name: 'Taklif havolasini yaratish' }));

    const link = await screen.findByRole('dialog', { name: 'Taklif havolasi' });
    expect(link).toHaveTextContent('Havolani Yangi Xodim ga yuboring');
    expect(within(link).getByRole('textbox', { name: 'Taklif havolasi' })).toHaveValue('https://admin.rizqim.uz/invite/tok-123');
    expect(callsTo('POST', '/team')[0]?.body).toEqual({ name: 'Yangi Xodim', email: 'yangi@rizqim.uz', platform_role: 'support' });

    await user.click(within(link).getByRole('button', { name: 'Havoladan nusxa olish' }));
    expect(await navigator.clipboard.readText()).toBe('https://admin.rizqim.uz/invite/tok-123');

    // Shown once: closing it forgets it
    await user.click(within(link).getByRole('button', { name: 'Tayyor' }));
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.queryByDisplayValue(/invite\/tok-123/)).toBeNull();
  });

  it('builds the link from a bare token', async () => {
    const { user } = setup({ 'POST /team': () => ok({ member: null, invite_token: 'bare-token', invite_expires_at: null }) });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(screen.getByRole('button', { name: 'Xodim taklif qilish' }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText('Ism'), 'A B');
    await user.type(within(dialog).getByLabelText('Email'), 'a@b.uz');
    await user.click(within(dialog).getByRole('button', { name: 'Taklif havolasini yaratish' }));
    const link = await screen.findByRole('textbox', { name: 'Taklif havolasi' });
    expect(link).toHaveValue(`${window.location.origin}/invite/bare-token`);
  });

  it('shows the backend\'s reason when the email is taken', async () => {
    const { user } = setup({ 'POST /team': () => refusal(422, 'Tekshiring.', { reason: 'validation' }, { errors: { email: ['Bu email allaqachon jamoada.'] } }) });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(screen.getByRole('button', { name: 'Xodim taklif qilish' }));
    const dialog = await screen.findByRole('dialog');
    await user.type(within(dialog).getByLabelText('Ism'), 'A B');
    await user.type(within(dialog).getByLabelText('Email'), 'aziza@rizqim.uz');
    await user.click(within(dialog).getByRole('button', { name: 'Taklif havolasini yaratish' }));
    expect(await within(dialog).findByText('Bu email allaqachon jamoada.')).toBeInTheDocument();
  });

  it('changes a role', async () => {
    const { callsTo, user } = setup({ 'PATCH /team/2': () => ok(member({ platform_role: 'finance' })) });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(within(card('Sardor Operator')).getByRole('button', { name: "Rolni o'zgartirish: Sardor Operator" }));
    const dialog = await screen.findByRole('dialog', { name: "Rolni o'zgartirish" });
    expect(within(dialog).getByRole('button', { name: 'Saqlash' })).toBeDisabled();
    await user.selectOptions(within(dialog).getByLabelText('Roli'), 'finance');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await screen.findByText('Sardor Operator endi «Moliya».')).toBeInTheDocument();
    expect(callsTo('PATCH', '/team/2')[0]?.body).toEqual({ platform_role: 'finance' });
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('shows the backend\'s refusal inside the dialog (the last superadmin)', async () => {
    const { user } = setup({ 'PATCH /team/2': () => refusal(422, "Oxirgi superadministratorni o'zgartirib bo'lmaydi.") });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(within(card('Sardor Operator')).getByRole('button', { name: /Rolni o'zgartirish/ }));
    const dialog = await screen.findByRole('dialog');
    await user.selectOptions(within(dialog).getByLabelText('Roli'), 'viewer');
    await user.click(within(dialog).getByRole('button', { name: 'Saqlash' }));
    expect(await within(dialog).findByText("Oxirgi superadministratorni o'zgartirib bo'lmaydi.")).toBeInTheDocument();
  });

  it('disables an account after a plain question, and enables it again', async () => {
    const { callsTo, user } = setup({ 'PATCH /team/2': () => ok(member({ disabled: true })), 'PATCH /team/3': () => ok(member({ id: 3 })) });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(within(card('Sardor Operator')).getByRole('button', { name: /Hisobni o'chirish/ }));
    const ask = await screen.findByRole('alertdialog', { name: "Sardor Operator hisobini o'chirasizmi?" });
    expect(ask).toHaveTextContent('barcha seanslari tugaydi');
    await user.click(within(ask).getByRole('button', { name: 'Bekor qilish' }));
    expect(callsTo('PATCH', '/team/2')).toHaveLength(0);

    await user.click(within(card('Sardor Operator')).getByRole('button', { name: /Hisobni o'chirish/ }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "Ha, o'chirish" }));
    expect(await screen.findByText("Sardor Operator hisobi o'chirildi.")).toBeInTheDocument();
    expect(callsTo('PATCH', '/team/2')[0]?.body).toEqual({ disabled: true });

    await user.click(within(card('Dilnoza Moliya')).getByRole('button', { name: /Hisobni yoqish/ }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Ha, yoqish' }));
    expect(await screen.findByText('Dilnoza Moliya hisobi yoqildi.')).toBeInTheDocument();
    expect(callsTo('PATCH', '/team/3')[0]?.body).toEqual({ disabled: false });
  });

  it('resets 2FA after a plain question', async () => {
    const { callsTo, user } = setup({ 'POST /team/2/reset-2fa': () => ok([]) });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(within(card('Sardor Operator')).getByRole('button', { name: /2FA ni tiklash/ }));
    const ask = await screen.findByRole('alertdialog', { name: 'Sardor Operator uchun 2FA ni tiklaysizmi?' });
    expect(ask).toHaveTextContent('Eski telefon va tiklash kodlari ishlamay qoladi');
    await user.click(within(ask).getByRole('button', { name: 'Ha, tiklash' }));
    expect(await screen.findByText(/Sardor Operator uchun 2FA tiklandi/)).toBeInTheDocument();
    expect(callsTo('POST', '/team/2/reset-2fa')).toHaveLength(1);
  });

  it('re-issues an invite for a member who has not accepted yet', async () => {
    const { callsTo, user } = setup({ 'POST /team/4/invite': () => ok({ url: 'https://admin.rizqim.uz/invite/new-tok', expires_at: '2026-10-30T12:00:00Z' }) });
    await screen.findByRole('heading', { name: 'Bekzod Yangi' });
    const bekzod = within(card('Bekzod Yangi'));
    expect(bekzod.queryByRole('button', { name: /2FA ni tiklash/ })).toBeNull();
    await user.click(bekzod.getByRole('button', { name: /Taklifni qayta yuborish/ }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Ha, yangi havola' }));
    expect(await screen.findByRole('textbox', { name: 'Taklif havolasi' })).toHaveValue('https://admin.rizqim.uz/invite/new-tok');
    expect(callsTo('POST', '/team/4/invite')).toHaveLength(1);
  });

  it('asks for the 2FA code when the backend wants a fresh confirmation, then does the change', async () => {
    let attempts = 0;
    const { callsTo, user } = setup({
      'PATCH /team/2': () => (++attempts === 1 ? json({ success: false, message: 'Qayta tasdiqlang', code: 'reauth_required' }, 403) : ok(member({ disabled: true }))),
      'POST /auth/confirm': () => ok({}),
    });
    await screen.findByRole('heading', { name: 'Sardor Operator' });
    await user.click(within(card('Sardor Operator')).getByRole('button', { name: /Hisobni o'chirish/ }));
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: "Ha, o'chirish" }));

    const stepUp = await screen.findByRole('alertdialog', { name: 'Amalni tasdiqlang' });
    await user.type(within(stepUp).getByLabelText('6 xonali kod'), '654321');
    await user.click(within(stepUp).getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText("Sardor Operator hisobi o'chirildi.")).toBeInTheDocument();
    expect(callsTo('PATCH', '/team/2')).toHaveLength(2);
    expect(callsTo('POST', '/auth/confirm')[0]?.body).toEqual({ code: '654321' });
  });

  it('says it when the list cannot be loaded', async () => {
    mockFetch({ 'GET /auth/me': () => json(meBody()), 'GET /team': () => refusal(500, 'Server xatosi') });
    renderPage(<TeamView />);
    expect(await screen.findByText("Jamoa ro'yxati yuklanmadi")).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('Server xatosi')).toBeInTheDocument());
  });
});
