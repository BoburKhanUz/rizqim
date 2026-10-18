import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { failedRequest, secretsIn } from '../test/httpErrors';
import PhoneVerificationModal from './PhoneVerificationModal';

vi.mock('../lib/telegram', () => ({ hapticFeedback: vi.fn(), tg: { initDataUnsafe: {} } }));
vi.mock('../services/api', () => ({ default: { post: vi.fn() } }));

const post = vi.mocked(api.post);
const reply = (data: unknown) => Promise.resolve({ data });

const phoneInput = () => screen.getByPlaceholderText('90 123 45 67');
const codeInput = () => screen.getByPlaceholderText('123456');
const sendButton = () => screen.getByRole('button', { name: 'Tasdiqlash' });
const verifyButton = () => screen.getByRole('button', { name: 'Kodni tasdiqlash' });

function typePhone(value: string) {
  fireEvent.change(phoneInput(), { target: { value } });
}

// Fills in the phone and asks for the SMS code; the modal ends up on the code step
async function reachCodeStep(phone = '90 123 45 67') {
  post.mockResolvedValueOnce({ data: { success: true, message: 'Verification code sent.', code: null } });
  typePhone(phone);
  fireEvent.click(sendButton());
  await screen.findByPlaceholderText('123456');
}

// The countdown re-arms its one-second timer after every render, so it advances one second per act()
async function tick(seconds: number) {
  for (let i = 0; i < seconds; i++) {
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });
  }
}

let alertSpy: ReturnType<typeof vi.spyOn>;

beforeEach(() => {
  alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  vi.useRealTimers();
});

describe('PhoneVerificationModal: phone step', () => {
  it('only accepts digits and enables the button once the 9 digits of the number are in', () => {
    render(<PhoneVerificationModal onVerified={vi.fn()} />);
    expect(sendButton()).toBeDisabled();

    typePhone('90-12x3');
    expect(phoneInput()).toHaveValue('90123');
    expect(sendButton()).toBeDisabled();

    typePhone('901234567');
    expect(sendButton()).toBeEnabled();
  });

  it('starts from a number it was given, in any format', () => {
    render(<PhoneVerificationModal onVerified={vi.fn()} initialPhone="+998 (90) 123-45-67" />);

    expect(phoneInput()).toHaveValue('901234567');
  });

  it('asks for the code with the full +998 number, then moves to the code step', async () => {
    render(<PhoneVerificationModal onVerified={vi.fn()} />);

    await reachCodeStep();

    expect(post).toHaveBeenCalledTimes(1);
    expect(post).toHaveBeenCalledWith('/phone/send-code', { phone: '+998901234567' });
    expect(screen.getByText(/SMS kod yuborildi/)).toHaveTextContent('+998 90 123 45 67');
  });

  it('stays on the phone step and shows the field error when the number is refused', async () => {
    post.mockRejectedValueOnce(failedRequest(422, { message: 'Invalid.', errors: { phone: ['Telefon raqami noto\'g\'ri.'] } }, '/phone/send-code'));
    render(<PhoneVerificationModal onVerified={vi.fn()} />);
    typePhone('901234567');

    fireEvent.click(sendButton());

    await waitFor(() => expect(alertSpy).toHaveBeenCalledWith('Telefon raqami noto\'g\'ri.'));
    expect(screen.queryByPlaceholderText('123456')).not.toBeInTheDocument();
    expect(phoneInput()).toBeEnabled();
  });

  it('saves the number without any SMS when the server does not require verification', async () => {
    const saveWithoutVerification = vi.fn().mockResolvedValue(undefined);
    render(<PhoneVerificationModal onVerified={vi.fn()} saveWithoutVerification={saveWithoutVerification} />);
    typePhone('90 123 45 67');

    fireEvent.click(sendButton());

    await waitFor(() => expect(saveWithoutVerification).toHaveBeenCalledWith('+998901234567'));
    expect(post).not.toHaveBeenCalled();
  });

  it('has a close button only when it can be dismissed', () => {
    const onClose = vi.fn();
    const { rerender } = render(<PhoneVerificationModal onVerified={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Yopish' })).not.toBeInTheDocument();

    rerender(<PhoneVerificationModal onVerified={vi.fn()} onClose={onClose} />);
    fireEvent.click(screen.getByRole('button', { name: 'Yopish' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

describe('PhoneVerificationModal: code step', () => {
  it('verifies the code and reports the number once the server accepts it', async () => {
    const onVerified = vi.fn();
    render(<PhoneVerificationModal onVerified={onVerified} />);
    await reachCodeStep();
    expect(verifyButton()).toBeDisabled(); // 6 digits are needed

    post.mockResolvedValueOnce({ data: { success: true, message: 'Telefon raqamingiz muvaffaqiyatli tasdiqlandi.' } });
    fireEvent.change(codeInput(), { target: { value: '12 34 56' } }); // spaces are dropped
    expect(codeInput()).toHaveValue('123456');
    fireEvent.click(verifyButton());

    await waitFor(() => expect(onVerified).toHaveBeenCalledWith('+998901234567'));
    expect(post).toHaveBeenLastCalledWith('/phone/verify-code', { phone: '+998901234567', code: '123456' });
    expect(onVerified).toHaveBeenCalledTimes(1);
  });

  it('keeps the user on the code step with the server message when the code is wrong', async () => {
    const onVerified = vi.fn();
    render(<PhoneVerificationModal onVerified={onVerified} />);
    await reachCodeStep();

    post.mockRejectedValueOnce(failedRequest(422, { message: 'Tasdiqlash kodi noto\'g\'ri.' }));
    fireEvent.change(codeInput(), { target: { value: '000000' } });
    fireEvent.click(verifyButton());

    await waitFor(() => expect(alertSpy).toHaveBeenCalledWith('Tasdiqlash kodi noto\'g\'ri.'));
    expect(onVerified).not.toHaveBeenCalled();
    expect(codeInput()).toBeEnabled();
  });

  it('treats a 200 answer without success as a failure', async () => {
    const onVerified = vi.fn();
    render(<PhoneVerificationModal onVerified={onVerified} />);
    await reachCodeStep();

    post.mockResolvedValueOnce({ data: { success: false, message: 'Urinishlar soni tugadi.' } });
    fireEvent.change(codeInput(), { target: { value: '111111' } });
    fireEvent.click(verifyButton());

    await waitFor(() => expect(alertSpy).toHaveBeenCalledWith('Urinishlar soni tugadi.'));
    expect(onVerified).not.toHaveBeenCalled();
  });

  it('never logs the token, the code or the phone of a failed request', async () => {
    const logged = vi.spyOn(console, 'error');
    render(<PhoneVerificationModal onVerified={vi.fn()} />);
    await reachCodeStep();

    post.mockRejectedValueOnce(failedRequest(422, { message: 'Tasdiqlash kodi noto\'g\'ri.' }));
    fireEvent.change(codeInput(), { target: { value: '482913' } });
    fireEvent.click(verifyButton());

    await waitFor(() => expect(logged).toHaveBeenCalled());
    expect(logged.mock.calls.flat()).toContain('HTTP 422: Tasdiqlash kodi noto\'g\'ri.');
    expect(secretsIn(logged.mock.calls)).toEqual([]);
    expect(secretsIn(alertSpy.mock.calls)).toEqual([]);
  });

  it('only offers a new code once the 60 second countdown is over', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<PhoneVerificationModal onVerified={vi.fn()} />);
    await reachCodeStep();
    expect(screen.getByText(/\(60s\)/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Kodni qayta yuborish' })).not.toBeInTheDocument();

    await tick(30);
    expect(screen.getByText(/\(30s\)/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Kodni qayta yuborish' })).not.toBeInTheDocument();

    await tick(30);
    post.mockResolvedValueOnce({ data: { success: true } });
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Kodni qayta yuborish' }));
    });

    expect(post).toHaveBeenLastCalledWith('/phone/send-code', { phone: '+998901234567' });
    expect(post).toHaveBeenCalledTimes(2);
    expect(screen.getByText(/\(60s\)/)).toBeInTheDocument(); // a new countdown has started
  });

  it('goes back to the phone step to change the number', async () => {
    render(<PhoneVerificationModal onVerified={vi.fn()} />);
    await reachCodeStep();

    fireEvent.click(screen.getByRole('button', { name: "Raqamni o'zgartirish" }));

    expect(phoneInput()).toHaveValue('901234567');
  });
});
