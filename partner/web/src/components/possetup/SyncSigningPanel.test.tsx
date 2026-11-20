import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setSession } from '@/lib/session/client';
import { json, mockFetch, renderPage } from '@/test/helpers';
import { makeBranch } from '../management/testSupport';
import { ok, ownerAnswer, signIn } from '../pos/testSupport';
import { PosSetupView } from './PosSetupView';

vi.mock('next/navigation', () => ({ useRouter: () => ({ replace: vi.fn(), refresh: vi.fn() }) }));

beforeEach(signIn);
afterEach(() => setSession(null));

// Wave M5 S3-d: the owner's switch between "kuzatish" and "qat'iy" for the signatures of the tills' offline queue
// (GET / PUT /owner/pos/sync-signing). Store-wide; turning it on while a till does not sign yet asks a second time.

const view = (over: Record<string, unknown> = {}) => ({ enforce: false, signing_tills: 2, unsigned_tills: [], ...over });
const OLD_TILL = { uuid: 'till-9', name: 'Eski kassa', branch_id: 1, has_key: true, last_sig_status: 'missing' };

const open = async (over: Parameters<typeof mockFetch>[0] = {}) => {
  const mocked = mockFetch({
    'GET /api/v1/me/permissions': () => ownerAnswer(),
    'GET /api/v1/owner/branches': () => ok([makeBranch()]),
    'GET /api/v1/owner/pos/format': () =>
      ok({ branch_id: 1, profile: 'restoran', profile_label: 'Restoran', mode: 'table', first_screen: 'floor', modules: [], locks: { service_charge_zero: false }, module_rows: [], profiles: [], template: { profile: 'restoran', stations: [], dishes: [] } }),
    'GET /api/v1/owner/pos/sync-signing': () => ok(view()),
    ...over,
  });
  const typing = userEvent.setup();
  renderPage(<PosSetupView />);
  await typing.click(await screen.findByRole('tab', { name: 'Navbat imzosi' }));
  return { ...mocked, typing };
};

const puts = (calls: Request[]) => calls.filter((call) => call.method === 'PUT');

describe('Navbat imzosi (M5 S3)', () => {
  it('shows the observe mode and turns enforce on after a confirmation', async () => {
    const { calls, typing } = await open({ 'PUT /api/v1/owner/pos/sync-signing': () => ok(view({ enforce: true })) });
    const toggle = await screen.findByRole('switch', { name: "Qat'iy rejim" });
    expect(toggle).toHaveAttribute('aria-checked', 'false');
    expect(screen.getByTestId('signing-mode')).toHaveTextContent('Kuzatish rejimi');
    expect(screen.getByTestId('signing-tills')).toHaveTextContent('Imzolayotgan kassalar: 2');

    await typing.click(toggle);
    // Nothing is sent before the owner confirms
    expect(puts(calls)).toHaveLength(0);
    const dialog = await screen.findByRole('alertdialog', { name: "Qat'iy rejimni yoqasizmi?" });
    await typing.click(within(dialog).getByRole('button', { name: 'Ha, yoqish' }));

    await waitFor(() => expect(puts(calls)).toHaveLength(1));
    expect(await puts(calls)[0]!.json()).toEqual({ enforce: true });
    expect(await screen.findByText("Qat'iy rejim yoqildi.")).toBeInTheDocument();
    expect(screen.getByRole('switch', { name: "Qat'iy rejim" })).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByTestId('signing-mode')).toHaveTextContent("Qat'iy rejim");
  });

  it('names the tills that do not sign yet and sends the second yes only when the owner gives it', async () => {
    const { calls, typing } = await open({
      'GET /api/v1/owner/pos/sync-signing': () => ok(view({ signing_tills: 1, unsigned_tills: [OLD_TILL] })),
      'PUT /api/v1/owner/pos/sync-signing': async (request) => {
        const body = (await request.clone().json()) as { confirm_unsigned?: boolean };
        return body.confirm_unsigned
          ? ok(view({ enforce: true, signing_tills: 1, unsigned_tills: [OLD_TILL] }))
          : json({ success: false, message: 'Hali imzolamaydigan kassa bor (Eski kassa).', data: { reason: 'tills_not_signing', unsigned_tills: [OLD_TILL] } }, 409);
      },
    });
    const warning = await screen.findByRole('note');
    expect(warning).toHaveTextContent('Eski kassa');
    expect(warning).toHaveTextContent('imzosiz');

    await typing.click(screen.getByRole('switch', { name: "Qat'iy rejim" }));
    const dialog = await screen.findByRole('alertdialog', { name: "Qat'iy rejimni yoqasizmi?" });
    expect(dialog).toHaveTextContent('Eski kassa');
    await typing.click(within(dialog).getByRole('button', { name: 'Baribir yoqish' }));

    await waitFor(() => expect(puts(calls)).toHaveLength(1));
    expect(await puts(calls)[0]!.json()).toEqual({ enforce: true, confirm_unsigned: true });
    expect(await screen.findByText("Qat'iy rejim yoqildi.")).toBeInTheDocument();
  });

  it('turns enforce off at once and shows a refusal in the server words', async () => {
    const { calls, typing } = await open({
      'GET /api/v1/owner/pos/sync-signing': () => ok(view({ enforce: true })),
      'PUT /api/v1/owner/pos/sync-signing': () => json({ success: false, message: "Imzo rejimi butun do'kon uchun: uni faqat do'kon egasi o'zgartiradi.", data: { reason: 'store_wide_only' } }, 403),
    });
    await typing.click(await screen.findByRole('switch', { name: "Qat'iy rejim" }));

    await waitFor(() => expect(puts(calls)).toHaveLength(1));
    expect(await puts(calls)[0]!.json()).toEqual({ enforce: false });
    expect(await screen.findByText("Imzo rejimi butun do'kon uchun: uni faqat do'kon egasi o'zgartiradi.")).toBeInTheDocument();
    expect(screen.getByRole('switch', { name: "Qat'iy rejim" })).toHaveAttribute('aria-checked', 'true');
  });
});
