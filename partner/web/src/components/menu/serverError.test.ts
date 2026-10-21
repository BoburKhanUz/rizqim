import { StaffApiError } from '@rizqim/staff';
import { describe, expect, it } from 'vitest';
import { createTranslator } from '@/i18n';
import { describeError, isEnglish, localizeFieldMessage, unplacedMessages } from './serverError';

const t = createTranslator('uz');

describe('describeError', () => {
  it("shows the backend's Uzbek validation message as it is, next to its field", () => {
    const message = "Eng kam miqdor (0,3) qadamga (0,5) karrali bo'lishi kerak, aks holda bu taomni buyurtma qilib bo'lmaydi. Masalan: 0,5.";
    const view = describeError(new StaffApiError(422, { message, errors: { 'variants.0.min_quantity': [message] } }), t);
    expect(view.message).toBe(message);
    expect(view.fields['variants.0.min_quantity']).toBe(message);
  });

  it("puts an Uzbek sentence in place of Laravel's English rules", () => {
    const view = describeError(
      new StaffApiError(422, { message: 'The name field is required. (and 1 more error)', errors: { name: ['The name field is required.'], category_id: ['The selected category id is invalid.'] } }),
      t,
    );
    expect(view.fields.name).toBe(t('menu_err_required'));
    expect(view.fields.category_id).toBe(t('menu_err_invalid'));
    expect(view.message).toBe(t('menu_check_fields'));
    expect(Object.values(view.fields).some(isEnglish)).toBe(false);
  });

  it('maps the usual statuses to Uzbek', () => {
    expect(describeError(new StaffApiError(403, { error: 'Unauthorized' }), t).message).toBe(t('menu_forbidden'));
    expect(describeError(new StaffApiError(404, { message: 'Mahsulot topilmadi.' }), t).message).toBe('Mahsulot topilmadi.');
    expect(describeError(new StaffApiError(500, { message: 'Failed to create product' }), t).message).toBe(t('unknownError'));
    expect(describeError(new StaffApiError(429, {}), t).message).toBe(t('menu_too_many'));
  });

  it('treats anything that is not an API error as a lost connection', () => {
    expect(describeError(new TypeError('Failed to fetch'), t).message).toBe(t('networkError'));
  });
});

describe('localizeFieldMessage', () => {
  it('keeps Uzbek and translates the common English rules', () => {
    expect(localizeFieldMessage("Narx variant uchun beriladi (product_variant_id).", t)).toContain('Narx variant');
    expect(localizeFieldMessage('The image field must be an image.', t)).toBe(t('menu_err_image'));
    expect(localizeFieldMessage('The price field must be a number.', t)).toBe(t('menu_err_number'));
    expect(localizeFieldMessage('The name field must not be greater than 255 characters.', t)).toBe(t('menu_err_too_big'));
    expect(localizeFieldMessage('The quantity field must be at least 1.', t)).toBe(t('menu_err_too_small'));
  });
});

describe('unplacedMessages', () => {
  it('lists the messages of fields without an input, once each', () => {
    expect(unplacedMessages({ name: 'a', modifiers: 'b', groups: 'b', other: 'c' }, /^name$/)).toEqual(['b', 'c']);
  });
});
