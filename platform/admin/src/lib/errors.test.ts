import { describe, expect, it } from 'vitest';
import { createTranslator } from '@/i18n';
import { ApiError, errorFrom } from './api/client';
import { forgetAcceptedCode, rememberAcceptedCode, wasJustUsed } from './api/usedCodes';
import { authErrorText, errorText, KNOWN_ERROR_CODES } from './errors';

const t = createTranslator('uz');
const refusal = (code: string, status = 422, message = 'Server matni') => errorFrom(status, { success: false, message, code, data: { reason: code, retry_after: 600 } }, null);

describe('error codes of the admin API', () => {
  it('knows every code of docs/API.md "Xato formati"', () => {
    expect([...KNOWN_ERROR_CODES].sort()).toEqual(
      [
        'invalid_credentials', 'member_disabled', 'locked', 'challenge_invalid', 'invalid_code', 'invalid_recovery_code', 'setup_required', 'setup_not_started', 'setup_not_allowed',
        'two_factor_already_enabled', 'invite_invalid', 'invite_already_accepted', 'email_taken', 'reauth_required', 'forbidden', 'superadmin_only',
        'cannot_change_self', 'last_superadmin', 'role_not_editable', 'not_found',
      ].sort(),
    );
  });

  it.each(KNOWN_ERROR_CODES)('says "%s" in a plain Uzbek sentence of the app, not the server\'s text', (code) => {
    const text = errorText(refusal(code), t);
    expect(text).not.toBe('Server matni');
    expect(text).not.toBe(t('unknownError'));
    expect(text.length).toBeGreaterThan(15);
    expect(text).not.toContain('{{');
  });

  it('tells the lockout in minutes from data.retry_after, and a wrong code to wait for the next one', () => {
    expect(errorText(refusal('locked', 429), t)).toBe("Juda ko'p xato urinish. 10 daqiqadan so'ng qayta urinib ko'ring.");
    expect(errorText(refusal('invalid_code'), t)).toContain('30 soniya');
  });

  it('falls back to the backend\'s sentence for a code it does not know, then to the kind of failure', () => {
    expect(errorText(errorFrom(422, { message: 'Boshqa gap', code: 'something_new' }, null), t)).toBe('Boshqa gap');
    expect(errorText(errorFrom(403, {}, null), t)).toBe("Bu amal uchun ruxsatingiz yo'q.");
    expect(errorText(new ApiError('', 0, 'unreachable'), t)).toBe("Serverga ulanib bo'lmadi. Internetni tekshiring.");
    expect(errorText(new Error('x'), t)).toBe(t('unknownError'));
  });

  it('uses the screen\'s own words for a sign-in refusal without a code', () => {
    expect(authErrorText(errorFrom(401, {}, null), t, 'Mening gapim')).toBe('Mening gapim');
    expect(authErrorText(refusal('invalid_credentials'), t, 'Mening gapim')).toBe("Email yoki parol noto'g'ri.");
  });
});

describe('the code that was just used', () => {
  it('is remembered for 90 seconds, in memory, and only the same code counts', () => {
    forgetAcceptedCode();
    expect(wasJustUsed('123456')).toBe(false);
    rememberAcceptedCode('123456', 1_000);
    expect(wasJustUsed('123456', 60_000)).toBe(true);
    expect(wasJustUsed('654321', 60_000)).toBe(false);
    expect(wasJustUsed('123456', 100_000)).toBe(false);
    forgetAcceptedCode();
  });
});
