import { describe, expect, it } from 'vitest';
import { classifyPhoneChange, getUzPhoneDigits, UZ_PHONE_PREFIX } from './phone';

describe('getUzPhoneDigits', () => {
  it.each([
    ['901234567', '901234567'],
    ['90 123 45 67', '901234567'],
    ['(90) 123-45-67', '901234567'],
    ['+998901234567', '901234567'],
    ['+998 (90) 123-45-67', '901234567'],
    ['998901234567', '901234567'],
  ])('reads %j as %s', (input, digits) => {
    expect(getUzPhoneDigits(input)).toBe(digits);
  });

  it.each([
    ['', 'empty'],
    ['12345678', 'too short (8 digits)'],
    ['9012345678', '10 digits'],
    ['99890123456', '11 digits'],
    ['971234567890', '12 digits that are not +998'],
    ['+9989012345678', 'too long (13 digits)'],
    ['abcdefghi', 'no digits at all'],
  ])('rejects %j (%s)', (input) => {
    expect(getUzPhoneDigits(input)).toBeNull();
  });

  it('rejects a missing value', () => {
    expect(getUzPhoneDigits(null)).toBeNull();
    expect(getUzPhoneDigits(undefined)).toBeNull();
  });
});

describe('classifyPhoneChange', () => {
  it('sees an untouched field as unchanged, whitespace aside', () => {
    expect(classifyPhoneChange('+998901234567', '+998901234567')).toEqual({ kind: 'unchanged' });
    expect(classifyPhoneChange('  +998901234567 ', '+998901234567')).toEqual({ kind: 'unchanged' });
    expect(classifyPhoneChange('', '')).toEqual({ kind: 'unchanged' });
    expect(classifyPhoneChange('', null)).toEqual({ kind: 'unchanged' });
  });

  it('sees the same number typed in another format as unchanged (no SMS needed)', () => {
    expect(classifyPhoneChange('+998 90 123 45 67', '+998901234567')).toEqual({ kind: 'unchanged' });
    expect(classifyPhoneChange('90 123 45 67', '+998901234567')).toEqual({ kind: 'unchanged' });
  });

  it('flags a phone that the user emptied', () => {
    expect(classifyPhoneChange('', '+998901234567')).toEqual({ kind: 'cleared' });
    expect(classifyPhoneChange('   ', '+998901234567')).toEqual({ kind: 'cleared' });
  });

  it('flags input that is not an Uzbek number', () => {
    expect(classifyPhoneChange('12345', '+998901234567')).toEqual({ kind: 'invalid' });
    expect(classifyPhoneChange('+7 912 345 67 89', '+998901234567')).toEqual({ kind: 'invalid' });
    expect(classifyPhoneChange('not a phone', null)).toEqual({ kind: 'invalid' });
  });

  it('returns the full +998 number for a different valid number', () => {
    expect(classifyPhoneChange('91 765 43 21', '+998901234567')).toEqual({
      kind: 'changed',
      fullPhone: `${UZ_PHONE_PREFIX}917654321`,
    });
  });

  it('treats the first number of an account without a phone as a change', () => {
    expect(classifyPhoneChange('90 123 45 67', null)).toEqual({ kind: 'changed', fullPhone: '+998901234567' });
    expect(classifyPhoneChange('90 123 45 67', undefined)).toEqual({ kind: 'changed', fullPhone: '+998901234567' });
  });

  it('compares with a saved phone that is itself in an odd format', () => {
    expect(classifyPhoneChange('+998901234567', '90-123-45-67')).toEqual({ kind: 'unchanged' });
  });
});
