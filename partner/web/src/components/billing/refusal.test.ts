import { describe, expect, it } from 'vitest';
import { StaffApiError } from '@rizqim/staff';
import { createTranslator } from '@/i18n';
import { errorText } from '../management/errors';
import { billingRefusalText } from './refusal';

const refusal = (reason: string, data: Record<string, unknown> = {}, status = 403) => new StaffApiError(status, { success: false, message: 'Server matni', code: reason, data: { reason, ...data } });
const uz = createTranslator('uz');
const ru = createTranslator('ru');
const en = createTranslator('en');

describe('the plan refusals in plain words', () => {
  const missing = refusal('plan_feature_missing', { feature: 'bookings', required_plan: 'asosiy', required_plan_name: 'Asosiy' });

  it('names the feature and the plan that has it, in every language', () => {
    expect(billingRefusalText(missing, uz)).toBe("Bron Asosiy tarifda bor. Hozirgi tarifingizda yo'q.");
    expect(billingRefusalText(missing, ru)).toBe('«Бронь» есть в тарифе Asosiy. В вашем текущем тарифе нет.');
    expect(billingRefusalText(missing, en)).toBe('"Bookings" is in the Asosiy plan. Your current plan does not have it.');
  });

  it('copes with a feature no plan has, and with one the panel has no text for', () => {
    expect(billingRefusalText(refusal('plan_feature_missing', { feature: 'deposits' }), uz)).toBe("Bo'nak (avans) hozirgi tarifingizda yo'q.");
    expect(billingRefusalText(refusal('plan_feature_missing', { feature: 'something_new', required_plan: 'pro' }), uz)).toBe("Bu imkoniyat hozirgi tarifingizda yo'q.");
  });

  it('explains the lock and the device limit and says the till keeps working', () => {
    expect(billingRefusalText(refusal('subscription_locked', { locked_since: '2026-11-19T00:00:00+05:00' }), uz)).toContain('Kassa');
    expect(billingRefusalText(refusal('device_limit', { device_type: 'pos', included: 1, active: 1 }), uz)).toContain("Eskisini o'chirib");
  });

  it('is what every screen shows through errorText, and leaves other errors alone', () => {
    expect(errorText(missing, uz)).toBe("Bron Asosiy tarifda bor. Hozirgi tarifingizda yo'q.");
    expect(errorText(refusal('approval_required'), uz)).toBe('Server matni');
    expect(billingRefusalText(new Error('x'), uz)).toBeUndefined();
  });
});
