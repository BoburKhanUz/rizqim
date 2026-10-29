import { StaffApiError, type StoreSettings } from '@rizqim/staff';
import { describe, expect, it } from 'vitest';
import { createTranslator } from '@/i18n';
import { errorText, firstFieldError, reasonOf } from './errors';
import { parseDecimal, phoneToE164, phoneToInput } from './fields';
import { hoursText, clock } from './hours';
import { cashOn, draftFromSettings, inputFromDraft, onlineOn, providerAvailable, unavailableReason } from './paymentSettings';

const t = createTranslator('uz');

describe('errorText', () => {
  it("shows the server's own message first, and the first field message of a 422 before it", () => {
    expect(errorText(new StaffApiError(422, { message: 'Umumiy xato', errors: { name: ['Ismi kerak.'], phone: ['Raqam xato.'] } }), t)).toBe('Ismi kerak.');
    expect(errorText(new StaffApiError(403, { message: 'Ruxsat etilmagan' }), t)).toBe('Ruxsat etilmagan');
  });

  it('has a line per status when the server said nothing, and the network for a request that got no answer', () => {
    expect(errorText(new StaffApiError(403, null), t)).toBe("Bu amal uchun ruxsatingiz yo'q.");
    expect(errorText(new StaffApiError(404, null), t)).toBe("Topilmadi. Sahifani yangilab ko'ring.");
    expect(errorText(new StaffApiError(429, null), t)).toBe("Juda ko'p urinish. Birozdan so'ng qayta urinib ko'ring.");
    expect(errorText(new StaffApiError(500, null), t)).toBe("Xatolik yuz berdi. Qayta urinib ko'ring.");
    expect(errorText(new TypeError('Failed to fetch'), t)).toBe("Serverga ulanib bo'lmadi. Internetni tekshiring.");
  });

  it('reads the reason of a refusal and the field messages', () => {
    expect(reasonOf(new StaffApiError(422, { data: { reason: 'branch_in_use' } }))).toBe('branch_in_use');
    expect(reasonOf(new Error('x'))).toBeUndefined();
    expect(firstFieldError(new StaffApiError(500, { errors: { a: ['x'] } }))).toBeUndefined();
    expect(firstFieldError(new StaffApiError(422, { errors: { a: '  ' } }))).toBeUndefined();
  });
});

describe('typed values', () => {
  it('reads numbers with spaces and a decimal comma', () => {
    expect(parseDecimal('12 000')).toBe(12000);
    expect(parseDecimal('12,5')).toBe(12.5);
    expect(parseDecimal('  ')).toBeNull();
    expect(parseDecimal('abc')).toBeUndefined();
    expect(parseDecimal('1e999')).toBeUndefined();
  });

  it('turns a typed phone into +998 and back', () => {
    expect(phoneToE164('90 123 45 67')).toBe('+998901234567');
    expect(phoneToE164('')).toBeNull();
    expect(phoneToE164('90 12')).toBeUndefined();
    expect(phoneToInput('+998901234567')).toBe('90 123 45 67');
    expect(phoneToInput(null)).toBe('');
  });
});

describe('hoursText', () => {
  it('words a window, an overnight window and all day', () => {
    expect(hoursText({ open: '09:00:00', close: '22:00:00', all_day: false, overnight: false, timezone: 'Asia/Tashkent' }, t)).toBe('09:00–22:00');
    expect(hoursText({ open: '18:00', close: '02:00', all_day: false, overnight: true, timezone: 'Asia/Tashkent' }, t)).toBe('18:00–02:00 (tungi)');
    expect(hoursText({ open: null, close: null, all_day: true, overnight: false, timezone: 'Asia/Tashkent' }, t)).toBe('24 soat');
    expect(clock(null)).toBe('');
  });
});

describe('payment settings', () => {
  it('reads a provider as an object or as a legacy boolean', () => {
    expect(cashOn(undefined)).toBe(true);
    expect(cashOn({ enabled: false })).toBe(false);
    expect(cashOn(false)).toBe(false);
    expect(onlineOn(undefined)).toBe(false);
    expect(onlineOn(true)).toBe(true);
    expect(onlineOn({ enabled: true })).toBe(true);
    expect(providerAvailable({ enabled: true, available: false })).toBe(false);
    expect(providerAvailable(true)).toBe(true);
    // P4-32: why it is off
    expect(unavailableReason({ enabled: false, available: false })).toBe('not_integrated');
    expect(unavailableReason({ enabled: false, available: false, unavailable_reason: 'switched_off' })).toBe('switched_off');
    expect(unavailableReason({ enabled: true })).toBeNull();
  });

  it('sends merchant keys only for a store on its own account, and a secret only when one was typed', () => {
    const platform = draftFromSettings(settings);
    expect(inputFromDraft(settings, platform, { minOrder: 0, deliveryFee: 0 }).payment_settings).toMatchObject({ click: { enabled: false }, payme: { enabled: false } });
    expect(inputFromDraft(settings, platform, { minOrder: 0, deliveryFee: 0 }).payment_settings.click).toEqual({ enabled: false });
    const own = { ...settings, payment_account_type: 'own_account' as const };
    const draft = draftFromSettings(own);
    draft.merchant.payme = { ...draft.merchant.payme, merchant_id: ' m-1 ', secret_key: '' };
    const input = inputFromDraft(own, draft, { minOrder: 0, deliveryFee: 0 }).payment_settings;
    expect(input.payme).toEqual({ enabled: false, merchant_id: 'm-1' });
    expect(input.click).toEqual({ enabled: false, service_id: null, merchant_id: null });
  });

  const settings = {
    id: 1, name: 'Osh', timezone: 'Asia/Tashkent', logo: 'l.png', logo_url: null, banner: 'b.png', banner_url: null, description: null, phone: null, address: null, location_url: null,
    is_open: true, min_order_amount: 0,
    payment_settings: { uzum: { enabled: true, available: false }, working_hours: {} },
    payment_account_type: 'platform_account' as const, status: 'active' as const, is_order_locked: false, allowed_methods: [],
    // Fiscal fields (P3-11): the payment form never reads them
    tin: null, tax_regime: null, fiscal_settings: {} as StoreSettings['fiscal_settings'],
    telegram: { group_link: null, group_linked: false },
  };

  it('starts a form from the stored values with the defaults of a store that saved nothing', () => {
    const draft = draftFromSettings(settings);
    expect(draft).toMatchObject({ deliveryEnabled: true, pickupEnabled: true, openTime: '09:00', closeTime: '22:00', deliveryFee: '0' });
    expect(draft.providers).toEqual({ cash: true, click: false, payme: false, uzum: false, paynet: false });
  });

  it('writes the request with the stored logo and banner and never switches an unavailable provider on', () => {
    const draft = { ...draftFromSettings(settings), providers: { cash: true, click: true, payme: false, uzum: true, paynet: true } };
    const input = inputFromDraft(settings, draft, { minOrder: 5000, deliveryFee: 1000 });
    expect(input).toMatchObject({ logo: 'l.png', banner: 'b.png', phone: null, min_order_amount: 5000, name: 'Osh', timezone: 'Asia/Tashkent' });
    expect(input.payment_settings).toMatchObject({ delivery_fee: 1000, click: { enabled: true }, uzum: { enabled: false }, paynet: { enabled: true } });
  });
});
