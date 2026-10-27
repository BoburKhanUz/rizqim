import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { post } from '@/lib/api/client';
import { json, meBody, mockFetch, ok, refusal, renderPage } from '@/test/helpers';

const reauth = () => json({ success: false, message: 'Amalni qayta tasdiqlang.', code: 'reauth_required' }, 403);

function Doer({ onResult }: { onResult: (result: string) => void }) {
  return (
    <button
      onClick={() =>
        void post('/team/9/reset-2fa').then(
          () => onResult('done'),
          (failure: { code?: string }) => onResult(`refused:${failure.code}`),
        )
      }
    >
      do it
    </button>
  );
}

describe('step-up dialog', () => {
  it('opens on 403 reauth_required, confirms with a code and repeats the call once', async () => {
    let attempts = 0;
    const { callsTo } = mockFetch({
      'GET /auth/me': () => json(meBody()),
      'POST /team/9/reset-2fa': () => (++attempts === 1 ? reauth() : ok([])),
      'POST /auth/confirm': () => ok({ confirmed_until: '2026-10-27T12:10:00Z' }),
    });
    const results: string[] = [];
    const user = userEvent.setup();
    renderPage(<Doer onResult={(result) => results.push(result)} />);
    await user.click(screen.getByRole('button', { name: 'do it' }));

    const dialog = await screen.findByRole('alertdialog', { name: 'Amalni tasdiqlang' });
    expect(dialog).toHaveTextContent('6 xonali kod');
    await user.type(screen.getByLabelText('6 xonali kod'), '123456');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));

    await waitFor(() => expect(results).toEqual(['done']));
    expect(callsTo('POST', '/auth/confirm')[0]?.body).toEqual({ code: '123456' });
    expect(callsTo('POST', '/team/9/reset-2fa')).toHaveLength(2);
    expect(screen.queryByRole('alertdialog')).toBeNull();
  });

  it('says a wrong code in the dialog and stays open; cancelling leaves the call refused', async () => {
    mockFetch({
      'GET /auth/me': () => json(meBody()),
      'POST /team/9/reset-2fa': reauth,
      'POST /auth/confirm': () => refusal(422, 'Kod xato.'),
    });
    const results: string[] = [];
    const user = userEvent.setup();
    renderPage(<Doer onResult={(result) => results.push(result)} />);
    await user.click(screen.getByRole('button', { name: 'do it' }));
    await user.type(await screen.findByLabelText('6 xonali kod'), '000000');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText("Kod noto'g'ri yoki eskirgan. Ilovadagi yangi kodni kiriting.")).toBeInTheDocument();
    expect(results).toEqual([]);

    await user.click(screen.getByRole('button', { name: 'Bekor qilish' }));
    await waitFor(() => expect(results).toEqual(['refused:reauth_required']));
    expect(screen.queryByRole('alertdialog')).toBeNull();
  });

  it('wants six digits before it asks the backend', async () => {
    const { callsTo } = mockFetch({ 'GET /auth/me': () => json(meBody()), 'POST /team/9/reset-2fa': reauth });
    const user = userEvent.setup();
    renderPage(<Doer onResult={() => undefined} />);
    await user.click(screen.getByRole('button', { name: 'do it' }));
    await user.type(await screen.findByLabelText('6 xonali kod'), '12');
    await user.click(screen.getByRole('button', { name: 'Tasdiqlash' }));
    expect(await screen.findByText('6 ta raqam kiriting.')).toBeInTheDocument();
    expect(callsTo('POST', '/auth/confirm')).toHaveLength(0);
  });
});
